<?php
namespace App\Core\Workflow;

use App\Core\{Database, WorkflowEngine};

/**
 * صندوق وارد المعتمد — بيانات موحدة للويب و API:
 * عدادات (معلق/عاجل/اليوم) + قائمة مُثراة مرتبة (العاجل أولاً).
 */
final class InboxService
{
    public static function forUser(int $userId): array
    {
        $items = WorkflowEngine::getPendingForUser($userId);
        $dueMap = self::dueMap($userId);
        $now = time();
        $today = date('Y-m-d');

        foreach ($items as &$it) {
            $id = (int)$it['id'];
            $due = $dueMap[$id] ?? null;
            $created = strtotime((string)$it['created_at']);
            $it['due_at'] = $due;
            $it['is_overdue'] = $due ? strtotime($due) < $now : false;
            $it['is_today'] = str_starts_with((string)$it['created_at'], $today);
            $it['elapsed'] = self::elapsed($created, $now);
            $it['current_step_title'] = $it['step']['title'] ?? self::nodeTitle($id);
        }
        unset($it);

        // العاجل أولاً ثم الأقدم
        usort($items, function ($a, $b) {
            if ($a['is_overdue'] !== $b['is_overdue']) return $a['is_overdue'] ? -1 : 1;
            return strtotime((string)$a['created_at']) <=> strtotime((string)$b['created_at']);
        });

        return [
            'counts' => [
                'pending' => count($items),
                'urgent'  => count(array_filter($items, fn($i) => $i['is_overdue'])),
                'today'   => count(array_filter($items, fn($i) => $i['is_today'])),
            ],
            'items' => $items,
        ];
    }

    /** آخر استحقاق لمهمة المستخدم على كل طلب (المحرك الديناميكي) */
    private static function dueMap(int $userId): array
    {
        try {
            $rows = Database::query(
                "SELECT request_id, MIN(due_at) AS due_at FROM workflow_tasks
                 WHERE assigned_to = ? AND status = 'pending' AND due_at IS NOT NULL
                 GROUP BY request_id",
                [$userId]
            )->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
        $map = [];
        foreach ($rows as $r) { $map[(int)$r['request_id']] = $r['due_at']; }
        return $map;
    }

    private static function nodeTitle(int $requestId): string
    {
        try {
            return (string)Database::query(
                "SELECT n.title FROM requests r JOIN workflow_nodes n ON n.id = r.current_node_id WHERE r.id = ?",
                [$requestId]
            )->fetchColumn() ?: '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function elapsed(int $from, int $now): string
    {
        $mins = max(0, (int)round(($now - $from) / 60));
        if ($mins < 60) return $mins . ' دقيقة';
        $hours = intdiv($mins, 60);
        if ($hours < 24) return $hours . ' ساعة';
        return intdiv($hours, 24) . ' يوم';
    }
}
