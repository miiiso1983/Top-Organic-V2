<?php
namespace App\Core;

use OpenAI;

/**
 * OpenAI LLM integration for AI Reservation Assistant
 * يستخدم GPT-4o-mini أو ما يعادله لتحديد النية والردود.
 */
class OpenAiService
{
    public static function isEnabled(): bool
    {
        return (bool)Settings::get('openai_enabled', 0);
    }

    private static function client(): ?OpenAI\Client
    {
        $key = Settings::get('openai_api_key', '');
        if (!$key) return null;
        return OpenAI::client($key);
    }

    public static function process(array $conversation, string $message): ?array
    {
        if (!self::isEnabled()) return null;
        $client = self::client();
        if (!$client) return null;

        $model = Settings::get('openai_model', 'gpt-4o-mini');
        $maxTokens = (int)Settings::get('openai_max_tokens', 800);
        $temp = (float)Settings::get('openai_temperature', 0.3);

        $messages = self::buildMessages($conversation, $message);
        try {
            $response = $client->chat()->create([
                'model' => $model,
                'messages' => $messages,
                'response_format' => ['type' => 'json_object'],
                'max_tokens' => $maxTokens,
                'temperature' => $temp,
            ]);
            $content = $response->choices[0]->message->content ?? '';
            return self::parseResponse($content);
        } catch (\Throwable $e) {
            error_log('OpenAI error: ' . $e->getMessage());
            return null;
        }
    }

    private static function buildMessages(array $conversation, string $message): array
    {
        $system = self::systemPrompt();
        $history = Database::query(
            "SELECT direction, role, message FROM ai_messages
             WHERE conversation_id = ? ORDER BY created_at ASC LIMIT 20",
            [$conversation['id']]
        )->fetchAll();

        $messages = [['role' => 'system', 'content' => $system]];
        foreach ($history as $h) {
            $role = $h['role'] === 'human' ? 'assistant' : ($h['direction'] === 'in' ? 'user' : 'assistant');
            $messages[] = ['role' => $role, 'content' => $h['message']];
        }
        $messages[] = ['role' => 'user', 'content' => $message];
        return $messages;
    }

    private static function systemPrompt(): string
    {
        $opening = Settings::get('reservation_opening_time', '09:00');
        $closing = Settings::get('reservation_closing_time', '23:00');
        $types = ReservationTypeService::listAll();
        $typeLines = [];
        foreach ($types as $t) {
            $typeLines[] = '- ' . $t['name'] . ' (' . $t['code'] . '): ' . $t['min_guests'] . '-' . $t['max_guests'] . ' أشخاص، ' . $t['duration_minutes'] . ' دقيقة';
        }
        $typesText = implode("\n", $typeLines) ?: '- regular: 1-20 أشخاص';

        return "أنت مساعد حجز ذكي في Top Organic Restaurant. تحدث بالعربية بأسلوب ودي وموجز.\n"
            . "الهدف: مساعدة العملاء في الحجز، معرفة الأوقات المتاحة، التعديل، الإلغاء، الأسئلة، والدفع.\n"
            . "أوقات العمل: من {$opening} إلى {$closing} يومياً.\n"
            . "أنواع الحجز المتاحة:\n{$typesText}\n"
            . "الرد يجب أن يكون JSON فقط بالهيكل التالي:\n"
            . '{"intent": "greeting|availability|book|modify|cancel|payment|handoff|faq|fallback", '
            . '"reply": "الرد بالعربية", '
            . '"entities": {"date": "YYYY-MM-DD", "time": "HH:MM", "guests": number, "phone": "05xxxxxxxx", "name": "", "reservation_no": "", "type": "regular", "branch_id": number, "notes": "", "duration": number}}\n'
            . "إذا كان العميل يريد حجز طاولة ولم يعطِ كل البيانات، اطلب البيانات المفقودة واحتفظ بالنية book.\n"
            . "إذا طلب العميل موظفاً، ضع intent handoff.\n"
            . "لا تُعلّم العميل أنك نموذج لغوي. قدم رداً طبيعياً وعملياً.";
    }

    private static function parseResponse(string $content): ?array
    {
        $content = trim($content);
        if (!$content) return null;
        $data = json_decode($content, true);
        if (!is_array($data) || empty($data['reply'])) return null;

        $intent = $data['intent'] ?? 'fallback';
        if (!in_array($intent, ['greeting','availability','book','modify','cancel','payment','handoff','faq','fallback'], true)) {
            $intent = 'fallback';
        }
        return [
            'intent' => $intent,
            'text' => $data['reply'],
            'entities' => is_array($data['entities'] ?? null) ? $data['entities'] : [],
        ];
    }
}
