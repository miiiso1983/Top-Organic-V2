<?php
namespace App\Core\Ptt;

use App\Core\{Database, Helpers, Settings};
use App\Core\Workflow\PushService;

/**
 * خدمة Push-to-Talk — قنوات ورسائل صوتية ومستلمون وحالات.
 */
final class PttService
{
    public const PRIORITIES = ['normal' => 'عادية', 'important' => 'مهمة', 'urgent' => 'عاجلة', 'emergency' => 'طارئة'];
    public const RECIPIENT_TYPES = ['channel','user','multi','department','branch','level','all'];
    public const RECIPIENT_STATES = ['sent','delivered','played','acknowledged','failed'];

    // ===== حالة الخدمة العامة (تحكم إداري: إيقاف جزئي / إعادة تشغيل) =====
    public const SERVICE_STATUS_ACTIVE = 'active';
    public const SERVICE_STATUS_PARTIAL = 'partial_stop';
    public const SERVICE_STATUSES = [self::SERVICE_STATUS_ACTIVE, self::SERVICE_STATUS_PARTIAL];

    private const AUDIO_MIMES = [
        'audio/webm' => 'webm', 'video/webm' => 'webm',
        'audio/ogg' => 'ogg', 'application/ogg' => 'ogg',
        'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'video/mp4' => 'm4a',
        'audio/mpeg' => 'mp3', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav',
        'audio/aac' => 'aac',
    ];

    // ===== إعدادات =====
    public static function maxDuration(?array $channel = null): int
    {
        if ($channel && !empty($channel['max_duration_sec'])) return (int)$channel['max_duration_sec'];
        return max(5, (int)Settings::get('ptt_max_duration_sec', 60));
    }
    public static function maxBytes(): int
    {
        return max(1, (int)Settings::get('ptt_max_mb', 8)) * 1024 * 1024;
    }

    // ===== القنوات =====
    public static function channelsForUser(int $userId): array
    {
        return Database::query(
            "SELECT c.*, m.role AS member_role, m.can_send, m.muted,
                    (SELECT COUNT(*) FROM ptt_channel_members cm WHERE cm.channel_id = c.id) AS members_count
             FROM ptt_channels c
             JOIN ptt_channel_members m ON m.channel_id = c.id AND m.user_id = ?
             WHERE c.is_active = 1
             ORDER BY c.name",
            [$userId]
        )->fetchAll();
    }

    public static function allChannels(): array
    {
        return Database::query(
            "SELECT c.*, b.name AS branch_name, u.full_name AS owner_name,
                    (SELECT COUNT(*) FROM ptt_channel_members cm WHERE cm.channel_id = c.id) AS members_count
             FROM ptt_channels c
             LEFT JOIN branches b ON b.id = c.branch_id
             LEFT JOIN users u ON u.id = c.owner_id
             ORDER BY c.name"
        )->fetchAll();
    }

    public static function channel(int $id): ?array
    {
        return Database::query("SELECT * FROM ptt_channels WHERE id = ?", [$id])->fetch() ?: null;
    }

    public static function membership(int $channelId, int $userId): ?array
    {
        return Database::query(
            "SELECT * FROM ptt_channel_members WHERE channel_id = ? AND user_id = ?",
            [$channelId, $userId]
        )->fetch() ?: null;
    }

    public static function canSend(int $channelId, int $userId): bool
    {
        $m = self::membership($channelId, $userId);
        if (!$m) return false;
        $c = self::channel($channelId);
        if ($c && $c['is_readonly'] && $m['role'] !== 'supervisor') return false;
        // أي عضو في القناة يستطيع التحدث (ما عدا القنوات المقروءة فقط للسوبرفايزر)
        return true;
    }

    public static function saveChannel(array $data, ?int $id, int $actorId): int
    {
        $row = [
            $data['name'], $data['description'] ?? '', ($data['branch_id'] ?? 0) ?: null, ($data['department'] ?? '') ?: null,
            $data['color'] ?? '#2563eb', $data['icon'] ?? 'fa-solid fa-tower-broadcast',
            !empty($data['is_active']) ? 1 : 0, !empty($data['is_readonly']) ? 1 : 0,
            ($data['owner_id'] ?? 0) ?: null, !empty($data['auto_sync']) ? 1 : 0, ($data['max_duration_sec'] ?? 0) ?: null,
        ];
        if ($id) {
            Database::query(
                "UPDATE ptt_channels SET name=?, description=?, branch_id=?, department=?, color=?, icon=?,
                        is_active=?, is_readonly=?, owner_id=?, auto_sync=?, max_duration_sec=? WHERE id=?",
                [...$row, $id]
            );
            return $id;
        }
        Database::query(
            "INSERT INTO ptt_channels (code, name, description, branch_id, department, color, icon, is_active, is_readonly, owner_id, auto_sync, max_duration_sec, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [$data['code'], ...$row, $actorId]
        );
        return (int)Database::lastInsertId();
    }

    public static function deleteChannel(int $id): void
    {
        Database::query("DELETE FROM ptt_channels WHERE id = ?", [$id]);
    }

    // ===== الأعضاء =====
    public static function members(int $channelId): array
    {
        return Database::query(
            "SELECT m.*, u.full_name, u.username, r.name AS role_name
             FROM ptt_channel_members m
             JOIN users u ON u.id = m.user_id
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE m.channel_id = ? ORDER BY m.role DESC, u.full_name",
            [$channelId]
        )->fetchAll();
    }

    public static function setMember(int $channelId, int $userId, array $flags): void
    {
        Database::query(
            "INSERT INTO ptt_channel_members (channel_id, user_id, can_send, can_broadcast_live, role, muted, `manual`)
             VALUES (?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE can_send = VALUES(can_send), can_broadcast_live = VALUES(can_broadcast_live),
                                     role = VALUES(role), muted = VALUES(muted), `manual` = VALUES(`manual`)",
            [$channelId, $userId, !empty($flags['can_send']) ? 1 : 0,
             !empty($flags['can_broadcast_live']) ? 1 : 0,
             ($flags['role'] ?? 'member') === 'supervisor' ? 'supervisor' : 'member',
             !empty($flags['muted']) ? 1 : 0,
             !empty($flags['manual']) ? 1 : 0]
        );
    }

    public static function manualChannelsForUser(int $userId): array
    {
        return Database::query(
            "SELECT channel_id FROM ptt_channel_members WHERE user_id = ? AND `manual` = 1",
            [$userId]
        )->fetchAll(\PDO::FETCH_COLUMN);
    }

    public static function setManualChannels(int $userId, array $channelIds): void
    {
        $channelIds = array_values(array_unique(array_filter(array_map('intval', $channelIds))));
        $inTransaction = Database::connect()->inTransaction();
        if (!$inTransaction) {
            Database::beginTransaction();
        }
        try {
            Database::query(
                "DELETE FROM ptt_channel_members WHERE user_id = ? AND `manual` = 1",
                [$userId]
            );
            foreach ($channelIds as $cid) {
                self::setMember($cid, $userId, ['can_send' => 1, 'manual' => 1]);
            }
            if (!$inTransaction) {
                Database::commit();
            }
        } catch (\Throwable $e) {
            if (!$inTransaction) {
                Database::rollBack();
            }
            throw $e;
        }
    }

    public static function removeMember(int $channelId, int $userId): void
    {
        Database::query("DELETE FROM ptt_channel_members WHERE channel_id = ? AND user_id = ?", [$channelId, $userId]);
    }

    // ===== حلّ المستلمين =====
    public static function resolveRecipients(string $type, ?int $channelId, array $recipientData, int $senderId): array
    {
        $users = match ($type) {
            'channel' => Database::query(
                "SELECT user_id FROM ptt_channel_members WHERE channel_id = ? AND user_id <> ?",
                [$channelId, $senderId]
            )->fetchAll(\PDO::FETCH_COLUMN),
            'user' => [(int)($recipientData['user_id'] ?? 0)],
            'multi' => array_map('intval', (array)($recipientData['user_ids'] ?? [])),
            'department' => Database::query(
                "SELECT DISTINCT u.id FROM users u JOIN employees e ON e.user_id = u.id
                 WHERE e.department = ? AND u.id <> ?",
                [(string)($recipientData['department'] ?? ''), $senderId]
            )->fetchAll(\PDO::FETCH_COLUMN),
            'branch' => Database::query(
                "SELECT DISTINCT u.id FROM users u JOIN employees e ON e.user_id = u.id
                 WHERE e.branch_id = ? AND u.id <> ?",
                [(int)($recipientData['branch_id'] ?? 0), $senderId]
            )->fetchAll(\PDO::FETCH_COLUMN),
            'level' => Database::query(
                "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
                 WHERE r.level >= ? AND u.id <> ?",
                [(int)($recipientData['level'] ?? 0), $senderId]
            )->fetchAll(\PDO::FETCH_COLUMN),
            'all' => Database::query("SELECT id FROM users WHERE id <> ?", [$senderId])->fetchAll(\PDO::FETCH_COLUMN),
            default => [],
        };
        return array_values(array_unique(array_filter(array_map('intval', $users), fn($u) => $u > 0 && $u !== $senderId)));
    }

    // ===== حفظ ملف الصوت =====
    public static function saveAudio(array $file): array
    {
        if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('لم يُستلم ملف صوتي');
        }
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0) throw new \RuntimeException('الملف الصوتي فارغ');
        if ($size > self::maxBytes()) {
            throw new \RuntimeException('الملف أكبر من الحد المسموح (' . (int)(self::maxBytes() / 1048576) . 'MB)');
        }
        $mime = (function_exists('mime_content_type') ? mime_content_type($file['tmp_name']) : '') ?: '';
        // احتياطي: إذا لم تتوفر دالة mime_content_type أو أرسل العميل نوعاً معروفاً
        if (!$mime && !empty($file['type'])) {
            $mime = (string)$file['type'];
        }
        $ext = self::AUDIO_MIMES[$mime] ?? null;
        // احتياطي: بعض العملاء يرسل octet-stream أو لا يعرف mime — نتحقق من امتداد الاسم الأصلي
        if (!$ext && in_array($mime, ['application/octet-stream', 'binary/octet-stream', ''], true)) {
            $byName = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
            if (in_array($byName, ['webm','ogg','m4a','mp3','wav','aac','mp4'], true)) {
                $ext = $byName === 'mp4' ? 'm4a' : $byName;
            }
        }
        if (!$ext) throw new \RuntimeException('صيغة الصوت غير مدعومة: ' . ($mime ?: 'غير معروف'));
        $dir = BASE_PATH . '/public/uploads/ptt/' . date('Ym');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('لا يمكن إنشاء مجلد الصوتيات');
        }
        $name = uniqid('ptt_', true) . '.' . $ext;
        $dest = $dir . '/' . $name;
        // move_uploaded_file لسياق HTTP، وrename كاحتياط (CLI/اختبارات/تخزين مؤقت)
        $moved = is_uploaded_file($file['tmp_name'])
            ? move_uploaded_file($file['tmp_name'], $dest)
            : rename($file['tmp_name'], $dest);
        if (!$moved) {
            throw new \RuntimeException('فشل حفظ الملف الصوتي');
        }
        return ['path' => '/uploads/ptt/' . date('Ym') . '/' . $name, 'ext' => $ext, 'size' => $size, 'mime' => $mime];
    }

    // ===== حالة الخدمة =====
    /** تقرأ حالة خدمة PTT الحالية (active أو partial_stop) */
    public static function serviceStatus(): string
    {
        $status = (string)Settings::get('ptt_service_status', self::SERVICE_STATUS_ACTIVE);
        return in_array($status, self::SERVICE_STATUSES, true) ? $status : self::SERVICE_STATUS_ACTIVE;
    }

    public static function isServiceActive(): bool
    {
        return self::serviceStatus() === self::SERVICE_STATUS_ACTIVE;
    }

    /** تفاصيل آخر تغيير لحالة الخدمة (من قام به ومتى) للعرض في لوحة الإدارة */
    public static function serviceStatusMeta(): array
    {
        return [
            'status' => self::serviceStatus(),
            'updated_by' => (int)Settings::get('ptt_service_status_by', 0),
            'updated_at' => Settings::get('ptt_service_status_at', null),
        ];
    }

    /** تغيير حالة خدمة PTT وتسجيل من قام بالإجراء وتوقيته — يجب التحقق من الصلاحية قبل الاستدعاء */
    public static function setServiceStatus(string $status, int $actorId): void
    {
        if (!in_array($status, self::SERVICE_STATUSES, true)) {
            throw new \RuntimeException('حالة خدمة غير صالحة');
        }
        Settings::set('ptt_service_status', $status);
        Settings::set('ptt_service_status_by', $actorId);
        Settings::set('ptt_service_status_at', date('Y-m-d H:i:s'));
    }

    /** يرمي استثناء إذا كانت الخدمة متوقفة جزئياً — يمنع إنشاء محتوى جديد (رسائل/بث مباشر) */
    public static function assertServiceActive(): void
    {
        if (!self::isServiceActive()) {
            throw new \RuntimeException('خدمة PTT متوقفة جزئياً حالياً من قبل الإدارة — لا يمكن إرسال رسائل أو بدء بث جديد حتى إعادة التشغيل');
        }
    }

    // ===== الإرسال =====
    public static function sendMessage(int $senderId, array $input, array $audio): int
    {
        self::assertServiceActive();
        $type = (string)($input['recipient_type'] ?? 'channel');
        if (!in_array($type, self::RECIPIENT_TYPES, true)) throw new \RuntimeException('نوع المستلمين غير صالح');
        $channelId = (int)($input['channel_id'] ?? 0) ?: null;
        $priority = (string)($input['priority'] ?? 'normal');
        if (!isset(self::PRIORITIES[$priority])) $priority = 'normal';
        if ($priority === 'emergency' && empty($input['allow_emergency'])) {
            throw new \RuntimeException('لا تملك صلاحية إرسال رسائل طارئة');
        }

        $channel = $channelId ? self::channel($channelId) : null;
        if ($channel && !self::canSend((int)$channelId, $senderId)) {
            throw new \RuntimeException('لا تملك صلاحية الإرسال في هذه القناة');
        }
        $duration = (float)($input['duration_sec'] ?? 0);
        if ($duration > self::maxDuration($channel) + 1) {
            throw new \RuntimeException('الرسالة أطول من الحد المسموح (' . self::maxDuration($channel) . ' ثانية)');
        }

        $recipientData = json_decode((string)($input['recipient_data'] ?? '{}'), true) ?: [];
        $recipients = self::resolveRecipients($type, $channelId, $recipientData, $senderId);
        // السماح بإرسال رسالة في قناة فارغة (للاختبار) أو عندما لا يوجد مستلمون آخرون
        if (!$recipients && $type !== 'channel') throw new \RuntimeException('لا يوجد مستلمون للرسالة');
        if (!$recipients && $type === 'channel') $recipients = [];
        $replyTo = (int)($input['reply_to'] ?? 0) ?: null;

        Database::query(
            "INSERT INTO ptt_messages (channel_id, reply_to, sender_id, recipient_type, recipient_json, priority,
                file_path, file_ext, codec, duration_sec, size_bytes, require_ack, require_listen, require_reply, branch_id)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [$channelId, $replyTo, $senderId, $type, $recipientData ? json_encode($recipientData, JSON_UNESCAPED_UNICODE) : null,
             $priority, $audio['path'], $audio['ext'], $audio['ext'] === 'webm' ? 'opus' : $audio['ext'],
             $duration, $audio['size'],
             !empty($input['require_ack']) ? 1 : 0, !empty($input['require_listen']) ? 1 : 0,
             !empty($input['require_reply']) ? 1 : 0, $channel['branch_id'] ?? null]
        );
        $messageId = (int)Database::lastInsertId();

        // ربط الرد برسالة الأصل عند المستلم (المرسل الحالي كان مستلماً للأصل)
        if ($replyTo) {
            Database::query(
                "UPDATE ptt_message_recipients SET reply_message_id = ? WHERE message_id = ? AND user_id = ?",
                [$messageId, $replyTo, $senderId]
            );
        }

        $title = $priority === 'emergency' ? '🚨 رسالة طارئة' : ($channel ? 'رسالة: ' . $channel['name'] : 'رسالة صوتية جديدة');
        $muted = $channelId ? array_map('intval', Database::query(
            "SELECT user_id FROM ptt_channel_members WHERE channel_id = ? AND muted = 1", [$channelId]
        )->fetchAll(\PDO::FETCH_COLUMN)) : [];
        $senderName = self::senderName($senderId);
        $link = $channelId ? '/ptt/channels/' . $channelId : '/ptt';
        foreach ($recipients as $uid) {
            // لا نرسل إشعاراً للمرسل نفسه عن رسالته
            if ($uid === $senderId) {
                continue;
            }
            Database::query(
                "INSERT INTO ptt_message_recipients (message_id, user_id) VALUES (?,?)
                 ON DUPLICATE KEY UPDATE status = status",
                [$messageId, $uid]
            );
            if (!in_array($uid, $muted, true)) {
                Helpers::notify($uid, $title, 'من: ' . $senderName, $link);
                PushService::queue($uid, $title, 'رسالة صوتية من ' . $senderName, $link);
            }
        }

        // محاولة إرسال Push فوراً دون انتظار الكرون
        try {
            \App\Core\Push\PushSender::processQueue(50);
        } catch (\Throwable $e) {
            error_log('PTT immediate push failed: ' . $e->getMessage());
        }

        return $messageId;
    }

    private static function senderName(int $id): string
    {
        return (string)Database::query("SELECT full_name FROM users WHERE id = ?", [$id])->fetchColumn() ?: '#' . $id;
    }

    // ===== الوارد والسجل =====
    public static function inbox(int $userId, int $limit = 50): array
    {
        return Database::query(
            "SELECT m.*, r.status AS my_status, r.delivered_at, r.played_at, r.acknowledged_at,
                    u.full_name AS sender_name, c.name AS channel_name, c.color AS channel_color, c.icon AS channel_icon
             FROM ptt_message_recipients r
             JOIN ptt_messages m ON m.id = r.message_id
             JOIN users u ON u.id = m.sender_id
             LEFT JOIN ptt_channels c ON c.id = m.channel_id
             WHERE r.user_id = ?
             ORDER BY m.id DESC LIMIT ?",
            [$userId, $limit]
        )->fetchAll();
    }

    public static function channelHistory(int $channelId, int $limit = 60): array
    {
        return Database::query(
            "SELECT m.*, u.full_name AS sender_name,
                    (SELECT COUNT(*) FROM ptt_message_recipients r WHERE r.message_id = m.id) AS recipients_count,
                    (SELECT COUNT(*) FROM ptt_message_recipients r WHERE r.message_id = m.id AND r.status IN ('played','acknowledged')) AS played_count
             FROM ptt_messages m
             JOIN users u ON u.id = m.sender_id
             WHERE m.channel_id = ?
             ORDER BY m.id DESC LIMIT ?",
            [$channelId, $limit]
        )->fetchAll();
    }

    public static function message(int $id): ?array
    {
        return Database::query("SELECT * FROM ptt_messages WHERE id = ?", [$id])->fetch() ?: null;
    }

    // ===== انتقالات الحالة =====
    /** يُستدعى عند جلب الوارد من التطبيق: sent → delivered */
    public static function markDeliveredOnFetch(int $userId): int
    {
        $stmt = Database::query(
            "UPDATE ptt_message_recipients SET status = 'delivered', delivered_at = NOW()
             WHERE user_id = ? AND status = 'sent'",
            [$userId]
        );
        return $stmt->rowCount();
    }

    public static function markPlayed(int $messageId, int $userId): bool
    {
        $stmt = Database::query(
            "UPDATE ptt_message_recipients
             SET status = CASE WHEN status IN ('sent','delivered') THEN 'played' ELSE status END,
                 delivered_at = COALESCE(delivered_at, NOW()), played_at = COALESCE(played_at, NOW())
             WHERE message_id = ? AND user_id = ?",
            [$messageId, $userId]
        );
        return $stmt->rowCount() > 0;
    }

    public static function acknowledge(int $messageId, int $userId): bool
    {
        $stmt = Database::query(
            "UPDATE ptt_message_recipients
             SET status = 'acknowledged', acknowledged_at = NOW(),
                 delivered_at = COALESCE(delivered_at, NOW()), played_at = COALESCE(played_at, NOW())
             WHERE message_id = ? AND user_id = ?",
            [$messageId, $userId]
        );
        return $stmt->rowCount() > 0;
    }

    // ===== عدادات (شارة + استمرارية الطوارئ) =====
    public static function unplayedCounts(int $userId): array
    {
        $rows = Database::query(
            "SELECT m.priority, COUNT(*) AS c
             FROM ptt_message_recipients r JOIN ptt_messages m ON m.id = r.message_id
             WHERE r.user_id = ? AND r.status IN ('sent','delivered')
             GROUP BY m.priority",
            [$userId]
        )->fetchAll();
        $out = ['total' => 0, 'normal' => 0, 'important' => 0, 'urgent' => 0, 'emergency' => 0];
        foreach ($rows as $r) {
            $out[$r['priority']] = (int)$r['c'];
            $out['total'] += (int)$r['c'];
        }
        return $out;
    }

    // ===== البحث =====
    public static function search(int $userId, array $filters): array
    {
        $where = ["(r.user_id = ? OR m.sender_id = ?)"];
        $params = [$userId, $userId];
        if (!empty($filters['sender_id'])) { $where[] = "m.sender_id = ?"; $params[] = (int)$filters['sender_id']; }
        if (!empty($filters['channel_id'])) { $where[] = "m.channel_id = ?"; $params[] = (int)$filters['channel_id']; }
        if (!empty($filters['priority'])) { $where[] = "m.priority = ?"; $params[] = (string)$filters['priority']; }
        if (!empty($filters['recipient_type'])) { $where[] = "m.recipient_type = ?"; $params[] = (string)$filters['recipient_type']; }
        if (!empty($filters['branch_id'])) { $where[] = "m.branch_id = ?"; $params[] = (int)$filters['branch_id']; }
        if (!empty($filters['department'])) {
            $where[] = "m.sender_id IN (SELECT u.id FROM users u JOIN employees e ON e.user_id = u.id WHERE e.department = ?)";
            $params[] = (string)$filters['department'];
        }
        if (!empty($filters['date_from'])) { $where[] = "m.created_at >= ?"; $params[] = $filters['date_from'] . ' 00:00:00'; }
        if (!empty($filters['date_to'])) { $where[] = "m.created_at <= ?"; $params[] = $filters['date_to'] . ' 23:59:59'; }
        if (!empty($filters['q'])) {
            $where[] = "(m.transcript LIKE ? OR m.ai_summary LIKE ?)";
            $q = '%' . (string)$filters['q'] . '%';
            $params[] = $q; $params[] = $q;
        }

        return Database::query(
            "SELECT DISTINCT m.*, u.full_name AS sender_name, c.name AS channel_name, c.color AS channel_color
             FROM ptt_messages m
             LEFT JOIN ptt_message_recipients r ON r.message_id = m.id
             JOIN users u ON u.id = m.sender_id
             LEFT JOIN ptt_channels c ON c.id = m.channel_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY m.id DESC LIMIT 200",
            $params
        )->fetchAll();
    }

    // ===== Dashboard تحليلات =====
    public static function dashboardStats(): array
    {
        $today = Database::query(
            "SELECT COUNT(*) AS total,
                    SUM(priority = 'urgent') AS urgent,
                    SUM(priority = 'emergency') AS emergency
             FROM ptt_messages WHERE DATE(created_at) = CURDATE()"
        )->fetch();
        return [
            'today' => [
                'total' => (int)($today['total'] ?? 0),
                'urgent' => (int)($today['urgent'] ?? 0),
                'emergency' => (int)($today['emergency'] ?? 0),
            ],
            'top_channels' => Database::query(
                "SELECT c.name, c.color, COUNT(*) AS c FROM ptt_messages m
                 JOIN ptt_channels c ON c.id = m.channel_id
                 WHERE m.created_at >= CURDATE() - INTERVAL 7 DAY
                 GROUP BY c.id ORDER BY c DESC LIMIT 5"
            )->fetchAll(),
            'top_senders' => Database::query(
                "SELECT u.full_name, COUNT(*) AS c FROM ptt_messages m
                 JOIN users u ON u.id = m.sender_id
                 WHERE m.created_at >= CURDATE() - INTERVAL 7 DAY
                 GROUP BY m.sender_id ORDER BY c DESC LIMIT 5"
            )->fetchAll(),
            'unplayed' => Database::query(
                "SELECT COUNT(*) AS c, COALESCE(TIMESTAMPDIFF(HOUR, MIN(m.created_at), NOW()), 0) AS oldest_hours
                 FROM ptt_message_recipients r JOIN ptt_messages m ON m.id = r.message_id
                 WHERE r.status IN ('sent','delivered')"
            )->fetch(),
            'avg_response_minutes' => (float)(Database::query(
                "SELECT AVG(TIMESTAMPDIFF(MINUTE, m.created_at, r.played_at))
                 FROM ptt_message_recipients r JOIN ptt_messages m ON m.id = r.message_id
                 WHERE r.played_at IS NOT NULL AND m.created_at >= CURDATE() - INTERVAL 7 DAY"
            )->fetchColumn() ?: 0),
        ];
    }

    // ===== الاحتفاظ: حذف الرسائل الأقدم من المدة المضبوطة =====
    public static function purgeOld(): int
    {
        $days = (int)Settings::get('ptt_retention_days', 90);
        if ($days <= 0) return 0;
        $days = max(1, $days);
        $rows = Database::query(
            "SELECT id, file_path FROM ptt_messages WHERE created_at < NOW() - INTERVAL $days DAY"
        )->fetchAll();
        foreach ($rows as $r) {
            $path = BASE_PATH . '/public' . $r['file_path'];
            if (is_file($path)) @unlink($path);
        }
        Database::query("DELETE FROM ptt_messages WHERE created_at < NOW() - INTERVAL $days DAY");
        return count($rows);
    }

    // ===== حذف رسالة (حسب الصلاحيات) =====
    public static function deleteMessage(int $messageId, int $userId, bool $force = false): void
    {
        $m = self::message($messageId);
        if (!$m) return;
        if (!$force && (int)$m['sender_id'] !== $userId) {
            throw new \RuntimeException('لا تملك حذف هذه الرسالة');
        }
        $path = BASE_PATH . '/public' . $m['file_path'];
        Database::query("DELETE FROM ptt_messages WHERE id = ?", [$messageId]);
        if (is_file($path)) @unlink($path);
    }

    // ===== مزامنة ديناميكية للأعضاء حسب القسم =====
    public static function departmentList(): array
    {
        return Database::query(
            "SELECT DISTINCT name FROM departments WHERE is_active = 1 ORDER BY name"
        )->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * مزامنة أعضاء قناة واحدة مع موظفي القسم المرتبط.
     * يضيف موظفي القسم ويحذف الأعضاء الديناميكيين (member) الذين لم يعودوا في القسم.
     * يحافظ على المالك والمشرفين.
     */
    public static function syncDepartmentMembers(int $channelId): int
    {
        $channel = self::channel($channelId);
        if (!$channel || empty($channel['auto_sync'])) {
            return 0;
        }
        $department = (string)($channel['department'] ?? '');
        if ($department !== '') {
            $userIds = Database::query(
                "SELECT DISTINCT e.user_id FROM employees e
                 JOIN users u ON u.id = e.user_id
                 WHERE e.department = ? AND e.user_id IS NOT NULL AND e.status = 'active'",
                [$department]
            )->fetchAll(\PDO::FETCH_COLUMN);
        } else {
            // قناة بدون قسم تعني "كل الموظفين"
            $userIds = Database::query(
                "SELECT DISTINCT e.user_id FROM employees e
                 JOIN users u ON u.id = e.user_id
                 WHERE e.user_id IS NOT NULL AND e.status = 'active'"
            )->fetchAll(\PDO::FETCH_COLUMN);
        }
        $userIds = array_values(array_filter(array_map('intval', $userIds)));

        $ownerId = (int)($channel['owner_id'] ?? 0);
        $supervisors = Database::query(
            "SELECT user_id FROM ptt_channel_members WHERE channel_id = ? AND role = 'supervisor'",
            [$channelId]
        )->fetchAll(\PDO::FETCH_COLUMN);
        $protected = array_values(array_unique(array_filter(array_merge([$ownerId], array_map('intval', $supervisors)))));
        $keep = array_values(array_unique(array_merge($userIds, $protected)));

        Database::beginTransaction();
        try {
            foreach ($userIds as $uid) {
                Database::query(
                    "INSERT INTO ptt_channel_members (channel_id, user_id, can_send, can_broadcast_live, role, muted)
                     VALUES (?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE can_send = VALUES(can_send), can_broadcast_live = VALUES(can_broadcast_live)",
                    [$channelId, $uid, 1, 0, 'member', 0]
                );
            }
            if ($keep) {
                $placeholders = implode(',', array_fill(0, count($keep), '?'));
                Database::query(
                    "DELETE FROM ptt_channel_members
                     WHERE channel_id = ? AND user_id NOT IN ($placeholders) AND role = 'member' AND `manual` = 0",
                    array_merge([$channelId], $keep)
                );
            } else {
                Database::query(
                    "DELETE FROM ptt_channel_members WHERE channel_id = ? AND role = 'member' AND `manual` = 0",
                    [$channelId]
                );
            }
            Database::commit();
            return count($userIds);
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /** مزامنة كل القنوات التي تملك خيار المزامنة التلقائية */
    public static function syncAllDepartmentChannels(): array
    {
        $channels = Database::query(
            "SELECT id FROM ptt_channels WHERE auto_sync = 1"
        )->fetchAll(\PDO::FETCH_COLUMN);
        $results = [];
        foreach ($channels as $cid) {
            try {
                $results[$cid] = self::syncDepartmentMembers((int)$cid);
            } catch (\Throwable $e) {
                $results[$cid] = 'error: ' . $e->getMessage();
            }
        }
        return $results;
    }

    /**
     * عند تغيير قسم موظف أو إنشائه أو إلغاء تنشيطه: أضفه/أزله من القنوات الديناميكية.
     * oldDepartment: القسم السابق (للإزالة) — يُترك null عند الإنشاء.
     */
    public static function syncEmployeeChannels(int $employeeId, ?string $oldDepartment = null): void
    {
        $employee = Database::query(
            "SELECT user_id, department, status FROM employees WHERE id = ?",
            [$employeeId]
        )->fetch();
        if (!$employee || !$employee['user_id']) {
            return;
        }
        $userId = (int)$employee['user_id'];
        $isActive = $employee['status'] === 'active';
        $newDepartment = $employee['department'] ?? '';

        if (!$isActive) {
            // إزالة الموظف من كل القنوات الديناميكية (ما عدا المالك/المشرف)
            $allChannels = Database::query(
                "SELECT id, owner_id FROM ptt_channels WHERE auto_sync = 1"
            )->fetchAll();
            foreach ($allChannels as $c) {
                if ($userId === (int)($c['owner_id'] ?? 0)) continue;
                $isSupervisor = (bool)Database::query(
                    "SELECT 1 FROM ptt_channel_members WHERE channel_id = ? AND user_id = ? AND role = 'supervisor'",
                    [(int)$c['id'], $userId]
                )->fetch();
                if ($isSupervisor) continue;
                Database::query(
                    "DELETE FROM ptt_channel_members WHERE channel_id = ? AND user_id = ? AND `manual` = 0",
                    [(int)$c['id'], $userId]
                );
            }
            return;
        }

        if ($oldDepartment && $oldDepartment !== $newDepartment) {
            $oldChannels = Database::query(
                "SELECT id, owner_id FROM ptt_channels WHERE auto_sync = 1 AND department = ?",
                [$oldDepartment]
            )->fetchAll();
            foreach ($oldChannels as $c) {
                if ($userId === (int)($c['owner_id'] ?? 0)) continue;
                $isSupervisor = (bool)Database::query(
                    "SELECT 1 FROM ptt_channel_members WHERE channel_id = ? AND user_id = ? AND role = 'supervisor'",
                    [(int)$c['id'], $userId]
                )->fetch();
                if ($isSupervisor) continue;
                Database::query(
                    "DELETE FROM ptt_channel_members WHERE channel_id = ? AND user_id = ? AND `manual` = 0",
                    [(int)$c['id'], $userId]
                );
            }
        }

        // الإضافة إلى قنوات "كل الموظفين" (بدون قسم)
        $allChannels = Database::query(
            "SELECT id FROM ptt_channels WHERE auto_sync = 1 AND (department IS NULL OR department = '')"
        )->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($allChannels as $cid) {
            Database::query(
                "INSERT INTO ptt_channel_members (channel_id, user_id, can_send, can_broadcast_live, role, muted)
                 VALUES (?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE can_send = VALUES(can_send), can_broadcast_live = VALUES(can_broadcast_live)",
                [(int)$cid, $userId, 1, 0, 'member', 0]
            );
        }

        if ($newDepartment !== '') {
            $newChannels = Database::query(
                "SELECT id FROM ptt_channels WHERE auto_sync = 1 AND department = ?",
                [$newDepartment]
            )->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($newChannels as $cid) {
                Database::query(
                    "INSERT INTO ptt_channel_members (channel_id, user_id, can_send, can_broadcast_live, role, muted)
                     VALUES (?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE can_send = VALUES(can_send), can_broadcast_live = VALUES(can_broadcast_live)",
                    [(int)$cid, $userId, 1, 0, 'member', 0]
                );
            }
        }
    }
}
