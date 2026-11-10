<?php
declare(strict_types=1);
namespace App\Core;

final class SystemActivityReport
{
    public const SOURCES = ['operations' => 'العمليات', 'employees' => 'تعديلات الموظفين', 'approvals' => 'الموافقات', 'web' => 'طلبات الويب', 'mobile_api' => 'طلبات الموبايل'];
    public const RESULTS = ['recorded' => 'حدث مسجّل', 'response' => 'استجابة طبيعية', 'accepted' => 'دخول ناجح', 'rejected' => 'طلب مرفوض', 'error' => 'خطأ في الخادم'];
    public static function filters(array $input): array
    {
        $read = static fn($key, $default = '') => is_scalar($input[$key] ?? null) ? trim((string)$input[$key]) : $default;
        $f = [];
        foreach (['from', 'to'] as $key) {
            $v = $read($key, array_key_exists($key, $input) ? '' : ($key === 'from' ? date('Y-m-d', strtotime('-6 days')) : date('Y-m-d')));
            if ($v !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $v) || date('Y-m-d', strtotime($v)) !== $v)) throw new \InvalidArgumentException('أدخل تاريخاً صحيحاً.');
            $f[$key] = $v;
        }
        if ($f['from'] && $f['to'] && $f['from'] > $f['to']) throw new \InvalidArgumentException('تاريخ البداية يجب أن يسبق تاريخ النهاية.');
        foreach (['source', 'result', 'action', 'q', 'entity'] as $key) $f[$key] = mb_substr($read($key), 0, 190);
        if ($f['source'] && !isset(self::SOURCES[$f['source']])) throw new \InvalidArgumentException('نوع السجل غير صحيح.');
        if ($f['result'] && !isset(self::RESULTS[$f['result']])) throw new \InvalidArgumentException('نتيجة الحدث غير صحيحة.');
        foreach (['user_id', 'branch_id', 'entity_id'] as $key) $f[$key] = max(0, (int)$read($key));
        $f['per_page'] = in_array((int)$read('per_page'), [25, 50, 100], true) ? (int)$read('per_page') : 25;
        $f['page'] = min(10000000, max(1, (int)$read('page', '1')));
        return $f;
    }

    /** All values are bound; source IDs and SQL expressions are fixed application constants. */
    public static function query(array $f): array
    {
        $parts = []; $params = [];
        $sources = [
            'operations' => ['audit_log', 'l.user_id', "l.action", "COALESCE(l.entity,'')", 'l.entity_id', "COALESCE(l.details,'')", "''", "''", "''", "''", "''", "''", "'recorded'", 'NULL', 'NULL', 'NULL', 'NULL', "''"],
            'employees' => ['employee_audit_logs', 'l.changed_by', 'l.action', "'employees'", 'l.employee_id', "''", 'l.field_name', 'l.old_value', 'l.new_value', "''", "''", "''", "'recorded'", 'NULL', 'NULL', 'NULL', 'NULL', "''"],
            'approvals' => ['request_history', 'l.actor_id', 'l.action', "'requests'", 'l.request_id', 'l.notes', "''", "''", "''", "''", 'l.ip', 'l.device', "'recorded'", 'NULL', 'NULL', 'NULL', 'NULL', 'l.actor_name'],
            'web' => ['system_activity_events', 'l.user_id', 'l.action', 'l.route', 'l.entity_id', "''", "''", "''", "''", 'l.method', 'l.ip', 'l.device', 'l.result', 'l.http_status', 'l.duration_ms', 'l.branch_id', 'NULL', "''"],
            'mobile_api' => ['system_activity_events', 'l.user_id', 'l.action', 'l.route', 'l.entity_id', "''", "''", "''", "''", 'l.method', 'l.ip', 'l.device', 'l.result', 'l.http_status', 'l.duration_ms', 'l.branch_id', 'NULL', "''"],
        ];
        foreach ($sources as $source => $s) {
            if ($f['source'] && $source !== $f['source']) continue;
            if (!$f['source'] && $source === 'mobile_api') continue;
            [$table,$user,$action,$entity,$id,$details,$field,$before,$after,$method,$ip,$device,$result,$status,$duration,$branch,$unused,$actorName] = $s;
            $where = ['1=1']; $values = [];
            $sourceExpr = "'$source'";
            if (in_array($source, ['web','mobile_api'], true)) {
                $sourceExpr = 'l.channel';
                if ($f['source']) { $where[] = 'l.channel = ?'; $values[] = $source; }
            }
            foreach (['from' => '>=', 'to' => '<'] as $key => $op) {
                if ($f[$key]) { $where[] = "l.created_at $op ?"; $values[] = $key === 'to' ? date('Y-m-d', strtotime($f[$key] . ' +1 day')) : $f[$key]; }
            }
            foreach (['user_id' => $user, 'action' => $action, 'entity_id' => $id, 'entity' => $entity, 'result' => $result] as $key => $expr) {
                if ($f[$key] !== '' && $f[$key] !== 0) { $where[] = "$expr = ?"; $values[] = $f[$key]; }
            }
            $text = static fn($expr) => "CONVERT(COALESCE($expr,'') USING utf8mb4) COLLATE utf8mb4_unicode_ci";
            $parts[] = "SELECT l.id, l.created_at, $sourceExpr AS source, $user AS user_id, " . $text($actorName) . " AS actor_name, " . $text($action) . " AS action, " . $text($entity) . " AS entity, $id AS entity_id, " . $text($details) . " AS details, " . $text($field) . " AS field_name, " . $text($before) . " AS old_value, " . $text($after) . " AS new_value, " . $text($method) . " AS method, " . $text($ip) . " AS ip, " . $text($device) . " AS device, $result AS result, $status AS http_status, $duration AS duration_ms, $branch AS branch_id FROM $table l WHERE " . implode(' AND ', $where);
            array_push($params, ...$values);
        }
        $sql = ' FROM (' . implode(' UNION ALL ', $parts) . ") events LEFT JOIN users u ON u.id=events.user_id LEFT JOIN employees target ON events.entity='employees' AND target.id=events.entity_id LEFT JOIN branches b ON b.id=COALESCE(events.branch_id,u.branch_id) WHERE 1=1";
        if ($f['branch_id']) { $sql .= ' AND COALESCE(events.branch_id,u.branch_id)=?'; $params[] = $f['branch_id']; }
        if ($f['q'] !== '') {
            // Literal substring search: escape SQL LIKE metacharacters, not arbitrary wildcards.
            $q = '%' . strtr($f['q'], ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $conditions = ["u.full_name LIKE ? ESCAPE '!'", "events.actor_name LIKE ? ESCAPE '!'", "target.full_name LIKE ? ESCAPE '!'", "events.action LIKE ? ESCAPE '!'", "events.entity LIKE ? ESCAPE '!'", "events.details LIKE ? ESCAPE '!'"];
            array_push($params, $q,$q,$q,$q,$q,$q);
            foreach (self::choices() as $kind => $options) {
                $codes = array_keys(array_filter($options, fn($label) => mb_stripos($label, $f['q']) !== false));
                if ($codes) {
                    $column = $kind === 'actions' ? 'events.action' : 'events.entity';
                    $conditions[] = $column . ' IN (' . implode(',', array_fill(0, count($codes), '?')) . ')';
                    array_push($params, ...$codes);
                }
            }
            $sql .= ' AND (' . implode(' OR ', $conditions) . ')';
        }
        return [$sql,$params];
    }
    public static function page(array $f): array
    {
        [$sql,$params] = self::query($f);
        $total = (int)Database::query('SELECT COUNT(*)' . $sql, $params)->fetchColumn();
        $pages = max(1, (int)ceil($total / $f['per_page'])); $page = min($f['page'], $pages);
        $rows = Database::query("SELECT events.*, COALESCE(NULLIF(events.actor_name,''),u.full_name,'غير محدد / النظام') AS user_name, target.full_name AS target_name, b.name AS branch_name" . $sql . ' ORDER BY events.created_at DESC, events.source, events.id DESC LIMIT ' . $f['per_page'] . ' OFFSET ' . (($page-1)*$f['per_page']), $params)->fetchAll();
        return compact('total','pages','page','rows');
    }
    public static function actionLabel(string $action): string {return SystemActivityPresentation::action($action);}

    public static function choices(): array
    {
        $actions = []; $entities = [];
        $queries = [
            'SELECT DISTINCT action, entity FROM audit_log',
            "SELECT DISTINCT action, 'employees' AS entity FROM employee_audit_logs",
            "SELECT DISTINCT action, 'requests' AS entity FROM request_history",
            'SELECT DISTINCT action, route AS entity FROM system_activity_events',
        ];
        foreach ($queries as $sql) foreach (Database::query($sql)->fetchAll() as $row) {
            $actions[$row['action']] = SystemActivityPresentation::action($row['action']);
            if ($row['entity']) $entities[$row['entity']] = SystemActivityPresentation::entity($row['entity']);
        }
        asort($actions); asort($entities);
        return compact('actions', 'entities');
    }

    /** One snapshot, no page limit and no buffered result array. Always restore the connection. */
    public static function exportRows(array $filters): \Generator
    {
        [$sql, $params] = self::query($filters);
        $db = Database::connect();
        $buffered = $db->getAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
        $ownsTransaction = !$db->inTransaction();
        $stmt = null;
        try {
            if ($ownsTransaction) $db->beginTransaction();
            $db->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            $stmt = Database::query("SELECT events.*, COALESCE(NULLIF(events.actor_name,''),u.full_name,'غير محدد / النظام') AS user_name, target.full_name AS target_name, b.name AS branch_name" . $sql . ' ORDER BY events.created_at DESC, events.source, events.id DESC', $params);
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) yield $row;
        } finally {
            if ($stmt) $stmt->closeCursor();
            $db->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $buffered);
            if ($ownsTransaction && $db->inTransaction()) $db->rollBack();
        }
    }
}
