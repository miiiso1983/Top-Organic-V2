<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Auth, Response, AiAssistantService, Settings};

class AiAssistantController extends Controller
{
    /** صفحة المحادثة المستقلة */
    public function index(): void
    {
        $this->viewBare('sales/ai_assistant', ['channel' => 'web']);
    }

    /** Web Chat endpoint */
    public function chat(): void
    {
        $b = $this->jsonInput();
        $message = trim((string)($b['message'] ?? ''));
        $channelId = (string)($b['channel_id'] ?? 'guest_' . ($_SESSION['ai_session'] ?? uniqid()));
        $_SESSION['ai_session'] = $channelId;
        if (!$message) {
            Response::json(['ok' => false, 'error' => 'الرسالة فارغة']);
            return;
        }
        try {
            $result = AiAssistantService::receive('web', $channelId, $message, [
                'session_id' => $channelId,
                'language' => 'ar',
            ]);
            Response::json($result);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** Webhook للقنوات الخارجية (WhatsApp, Messenger, Telegram, Phone) */
    public function webhook(string $channel): void
    {
        if (!in_array($channel, AiAssistantService::CHANNELS, true)) {
            Response::json(['ok' => false, 'error' => 'قناة غير مدعومة']);
            return;
        }
        $b = $this->jsonInput();
        $channelId = (string)($b['from'] ?? $b['phone'] ?? $b['sender'] ?? '');
        $message = trim((string)($b['message'] ?? $b['text'] ?? ''));
        if (!$channelId || !$message) {
            Response::json(['ok' => false, 'error' => 'بيانات غير كافية']);
            return;
        }
        try {
            $result = AiAssistantService::receive($channel, $channelId, $message, [
                'session_id' => $channel . ':' . $channelId,
                'phone' => $channelId,
                'language' => 'ar',
            ]);
            Response::json($result);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** لوحة إدارة المحادثات */
    public function admin(): void
    {
        if (!Auth::can('ai_assistant.view') && !Auth::can('ai_assistant.manage')) {
            Response::redirect('/dashboard');
        }
        $conversations = Database::query(
            "SELECT c.*, u.full_name AS agent_name
             FROM ai_conversations c
             LEFT JOIN users u ON u.id = c.handoff_to
             ORDER BY c.last_message_at DESC
             LIMIT 100"
        )->fetchAll();
        $this->view('admin/ai_conversations', ['title' => 'محادثات AI', 'conversations' => $conversations]);
    }

    /** عرض تفاصيل محادثة للموظف */
    public function conversation(int $id): void
    {
        if (!Auth::can('ai_assistant.view') && !Auth::can('ai_assistant.manage')) {
            Response::redirect('/dashboard');
        }
        $conv = Database::query("SELECT * FROM ai_conversations WHERE id = ?", [$id])->fetch();
        if (!$conv) { Response::redirect('/admin/ai-conversations'); }
        $messages = Database::query("SELECT * FROM ai_messages WHERE conversation_id = ? ORDER BY created_at", [$id])->fetchAll();
        Database::query("UPDATE ai_conversations SET handoff_to = ?, status = 'active' WHERE id = ? AND status = 'handoff'", [(int)Auth::id(), $id]);
        $this->view('admin/ai_conversation', ['title' => 'محادثة #' . $id, 'conversation' => $conv, 'messages' => $messages]);
    }

    /** موظف يرد على محادثة محولة */
    public function reply(int $id): void
    {
        if (!Auth::can('ai_assistant.handoff') && !Auth::can('ai_assistant.manage')) {
            Response::json(['ok' => false, 'error' => 'لا صلاحية']);
            return;
        }
        $b = $this->jsonInput();
        $message = trim((string)($b['message'] ?? ''));
        if (!$message) {
            Response::json(['ok' => false, 'error' => 'الرسالة فارغة']);
            return;
        }
        Database::query(
            "INSERT INTO ai_messages (conversation_id, direction, role, message) VALUES (?,?,?,?)",
            [$id, 'out', 'human', $message]
        );
        Database::query("UPDATE ai_conversations SET last_message_at = NOW() WHERE id = ?", [$id]);
        Response::json(['ok' => true]);
    }
}
