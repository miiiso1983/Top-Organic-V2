<?php
namespace App\Core\Ptt;

use App\Core\{Database, Settings};
use OpenAI;

/**
 * مساعد AI للرسائل الصوتية: تفريغ (Whisper) + تلخيص + استخراج مهام + ترجمة.
 * يستخدم إعدادات OpenAI الموجودة في النظام (openai_enabled / openai_api_key).
 */
final class PttAiService
{
    public static function isEnabled(): bool
    {
        return (bool)Settings::get('openai_enabled', 0) && (string)Settings::get('openai_api_key', '') !== '';
    }

    private static function client(): OpenAI\Client
    {
        return OpenAI::client((string)Settings::get('openai_api_key'));
    }

    /** تفريغ الرسالة إلى نص وحفظه */
    public static function transcribe(int $messageId): ?string
    {
        if (!self::isEnabled()) throw new \RuntimeException('خدمة الذكاء الاصطناعي غير مفعّلة');
        $m = PttService::message($messageId);
        if (!$m) throw new \RuntimeException('الرسالة غير موجودة');
        if (!empty($m['transcript'])) return $m['transcript'];

        $path = BASE_PATH . '/public' . $m['file_path'];
        if (!is_file($path)) throw new \RuntimeException('الملف الصوتي غير موجود');

        $res = self::client()->audio()->transcribe([
            'model' => 'whisper-1',
            'file' => fopen($path, 'r'),
            'response_format' => 'verbose_json',
        ]);
        $text = trim((string)($res->text ?? ''));
        $lang = (string)($res->language ?? '');
        Database::query(
            "UPDATE ptt_messages SET transcript = ?, transcript_lang = ? WHERE id = ?",
            [$text, $lang ?: null, $messageId]
        );
        return $text;
    }

    /** ملخص قصير للرسالة */
    public static function summarize(int $messageId): ?string
    {
        $m = PttService::message($messageId);
        if (!$m) throw new \RuntimeException('الرسالة غير موجودة');
        if (!empty($m['ai_summary'])) return $m['ai_summary'];
        $text = $m['transcript'] ?: self::transcribe($messageId);
        if (!$text) throw new \RuntimeException('لا يوجد نص للتلخيص');
        $summary = self::chat(
            'أنت مساعد يلخّص رسائل صوتية لمطعم. لخّص الرسالة التالية في سطر أو سطرين بالعربية، مع الحفاظ على الأرقام والأسماء.',
            $text
        );
        if ($summary) {
            Database::query("UPDATE ptt_messages SET ai_summary = ? WHERE id = ?", [$summary, $messageId]);
        }
        return $summary;
    }

    /** استخراج المهام (Action Items) من الرسالة */
    public static function extractTasks(int $messageId): array
    {
        $m = PttService::message($messageId);
        if (!$m) throw new \RuntimeException('الرسالة غير موجودة');
        if (!empty($m['ai_tasks_json'])) return json_decode($m['ai_tasks_json'], true) ?: [];
        $text = $m['transcript'] ?: self::transcribe($messageId);
        if (!$text) throw new \RuntimeException('لا يوجد نص لاستخراج المهام');
        $json = self::chat(
            'استخرج المهام التنفيذية من الرسالة الصوتية التالية. أعد JSON فقط بالشكل: {"tasks":[{"title":"وصف المهمة","assignee":"الشخص المطلوب إن ذُكر وإلا فارغ","due":"الموعد إن ذُكر وإلا فارغ"}]}. إن لم توجد مهام أعد {"tasks":[]}.',
            $text,
            true
        );
        $tasks = [];
        if ($json) {
            $decoded = json_decode($json, true);
            $tasks = array_values(array_filter((array)($decoded['tasks'] ?? []), fn($t) => !empty($t['title'])));
        }
        Database::query("UPDATE ptt_messages SET ai_tasks_json = ? WHERE id = ?", [json_encode($tasks, JSON_UNESCAPED_UNICODE), $messageId]);
        return $tasks;
    }

    /** ترجمة النص المفرغ */
    public static function translate(int $messageId, string $targetLang): ?string
    {
        $m = PttService::message($messageId);
        if (!$m) throw new \RuntimeException('الرسالة غير موجودة');
        $text = $m['transcript'] ?: self::transcribe($messageId);
        if (!$text) throw new \RuntimeException('لا يوجد نص للترجمة');
        $langName = $targetLang === 'en' ? 'الإنجليزية' : 'العربية';
        $translation = self::chat('ترجم النص التالي إلى ' . $langName . ' ترجمة دقيقة ومختصرة دون أي إضافات.', $text);
        if ($translation) {
            Database::query(
                "UPDATE ptt_messages SET ai_translation = ?, ai_translation_lang = ? WHERE id = ?",
                [$translation, $targetLang, $messageId]
            );
        }
        return $translation;
    }

    /** كل التحليلات دفعة واحدة (زر واحد في الواجهة) */
    public static function runAll(int $messageId): array
    {
        $out = ['transcript' => null, 'summary' => null, 'tasks' => []];
        $out['transcript'] = self::transcribe($messageId);
        try { $out['summary'] = self::summarize($messageId); } catch (\Throwable $e) { error_log('PTT AI summarize: ' . $e->getMessage()); }
        try { $out['tasks'] = self::extractTasks($messageId); } catch (\Throwable $e) { error_log('PTT AI tasks: ' . $e->getMessage()); }
        return $out;
    }

    /** تفريغ تلقائي دوري: العاجل/الطارئ أولاً (أو الكل عند تفعيل الإعداد) */
    public static function cronAutoTranscribe(int $limit = 5): int
    {
        if (!self::isEnabled()) return 0;
        $all = (bool)Settings::get('ptt_auto_transcribe', 0);
        $rows = Database::query(
            "SELECT id FROM ptt_messages
             WHERE transcript IS NULL " . ($all ? '' : "AND priority IN ('urgent','emergency')") . "
             ORDER BY id DESC LIMIT ?",
            [$limit]
        )->fetchAll();
        $count = 0;
        foreach ($rows as $r) {
            try { self::transcribe((int)$r['id']); $count++; }
            catch (\Throwable $e) { error_log('PTT auto-transcribe: ' . $e->getMessage()); }
        }
        return $count;
    }

    private static function chat(string $system, string $user, bool $jsonMode = false): ?string
    {
        $params = [
            'model' => Settings::get('openai_model', 'gpt-4o-mini'),
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => mb_substr($user, 0, 4000)],
            ],
            'max_tokens' => 500,
            'temperature' => 0.2,
        ];
        if ($jsonMode) $params['response_format'] = ['type' => 'json_object'];
        $res = self::client()->chat()->create($params);
        $content = trim((string)($res->choices[0]->message->content ?? ''));
        return $content !== '' ? $content : null;
    }
}
