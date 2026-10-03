<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Response, Auth, ImportHelper};
use App\Core\Ptt\PttService;

/**
 * وحدة Push-to-Talk — الويب
 */
class PttController extends Controller
{
    // ===== الرئيسية: القنوات + آخر الرسائل الواردة =====
    public function index(): void
    {
        $uid = (int)Auth::id();
        PttService::markDeliveredOnFetch($uid);
        $this->view('ptt/index', [
            'title' => 'PTT — الاتصال الصوتي الفوري',
            'channels' => PttService::channelsForUser($uid),
            'inbox' => PttService::inbox($uid, 30),
            'counts' => PttService::unplayedCounts($uid),
            'priorities' => PttService::PRIORITIES,
        ]);
    }

    // ===== صفحة قناة (محادثة + PTT) =====
    public function channel(string $id): void
    {
        $uid = (int)Auth::id();
        $channel = PttService::channel((int)$id);
        if (!$channel) Response::abort(404, 'القناة غير موجودة');
        if (!PttService::membership((int)$id, $uid) && !Auth::can('ptt.manage')) {
            Response::abort(403, 'لست عضواً في هذه القناة');
        }
        $member = PttService::membership((int)$id, $uid) ?: [];
        $canLivePublish = !empty($member['can_broadcast_live']) || ($member['role'] ?? '') === 'supervisor'
            || (Auth::can('ptt.broadcast_live') && PttService::canSend((int)$id, $uid));
        $this->view('ptt/channel', [
            'title' => $channel['name'],
            'channel' => $channel,
            'messages' => PttService::channelHistory((int)$id),
            'members' => PttService::members((int)$id),
            'canSend' => PttService::canSend((int)$id, $uid),
            'canEmergency' => Auth::can('ptt.emergency'),
            'maxDuration' => PttService::maxDuration($channel),
            'priorities' => PttService::PRIORITIES,
            'liveConfigured' => \App\Core\Ptt\LiveKitService::isConfigured(),
            'canLivePublish' => $canLivePublish,
            'activeLive' => \App\Core\Ptt\LiveKitService::activeSession((int)$id),
        ]);
    }

    // ===== إرسال رسالة (JSON — رفع ملف صوتي) =====
    public function send(): void
    {
        try {
            $audio = PttService::saveAudio($_FILES['audio'] ?? []);
            $input = $_POST;
            $input['allow_emergency'] = Auth::can('ptt.emergency');
            $id = PttService::sendMessage((int)Auth::id(), $input, $audio);
            Response::json(['ok' => true, 'message_id' => $id]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    // ===== حالات: تشغيل / تأكيد / حذف =====
    public function played(string $id): void
    {
        Response::json(['ok' => PttService::markPlayed((int)$id, (int)Auth::id())]);
    }

    public function ack(string $id): void
    {
        Response::json(['ok' => PttService::acknowledge((int)$id, (int)Auth::id())]);
    }

    public function deleteMessage(string $id): void
    {
        try {
            PttService::deleteMessage((int)$id, (int)Auth::id(), Auth::can('ptt.delete'));
            Response::json(['ok' => true]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 403);
        }
    }

    // ===== البث المباشر (LiveKit) =====
    public function liveToken(string $id): void
    {
        $uid = (int)Auth::id();
        if (!\App\Core\Ptt\LiveKitService::isConfigured()) {
            Response::json(['ok' => false, 'error' => 'لم يُضبط LiveKit — راجع الإدارة ← إعدادات الإشعارات والبث'], 503);
            return;
        }
        $channel = PttService::channel((int)$id);
        if (!$channel) { Response::json(['ok' => false, 'error' => 'القناة غير موجودة'], 404); return; }
        $member = PttService::membership((int)$id, $uid);
        if (!$member) { Response::json(['ok' => false, 'error' => 'لست عضواً في هذه القناة'], 403); return; }

        // البث: يتطلب صلاحية البث المباشر أو إشراف القناة؛ الاستماع متاح لكل عضو
        $wantPublish = !empty($_POST['publish']);
        $canPublish = $wantPublish && (
            !empty($member['can_broadcast_live']) || $member['role'] === 'supervisor'
            || (Auth::can('ptt.broadcast_live') && PttService::canSend((int)$id, $uid))
        );
        if ($wantPublish && !$canPublish) {
            Response::json(['ok' => false, 'error' => 'لا تملك صلاحية البث المباشر في هذه القناة'], 403);
            return;
        }

        if ($canPublish && !PttService::isServiceActive()) {
            Response::json(['ok' => false, 'error' => 'خدمة PTT متوقفة جزئياً حالياً من قبل الإدارة — لا يمكن بدء بث جديد'], 423);
            return;
        }
        $name = (string)Auth::name();
        $token = \App\Core\Ptt\LiveKitService::token((int)$id, $uid, $name, $canPublish);
        $sessionId = null;
        if ($canPublish) {
            $sessionId = \App\Core\Ptt\LiveKitService::startSession((int)$id, $uid);
        }
        Response::json(['ok' => true, 'data' => [
            'token' => $token,
            'url' => \App\Core\Ptt\LiveKitService::wsUrl(),
            'room' => \App\Core\Ptt\LiveKitService::roomName((int)$id),
            'can_publish' => $canPublish,
            'session_id' => $sessionId,
        ]]);
    }

    public function liveStop(string $id): void
    {
        \App\Core\Ptt\LiveKitService::endSession((int)$id);
        Response::json(['ok' => true]);
    }

    // ===== مزامنة ديناميكية للأعضاء حسب القسم =====
    public function syncChannel(string $id): void
    {
        if (!Auth::can('ptt.sync_departments')) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية المزامنة'], 403);
            return;
        }
        try {
            $added = PttService::syncDepartmentMembers((int)$id);
            Response::json(['ok' => true, 'added' => $added]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function syncAllChannels(): void
    {
        if (!Auth::can('ptt.sync_departments')) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية المزامنة'], 403);
            return;
        }
        try {
            $results = PttService::syncAllDepartmentChannels();
            Response::json(['ok' => true, 'results' => $results]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    // ===== تحليل بالذكاء الاصطناعي (تفريغ/تلخيص/مهام) =====
    public function transcribe(string $id): void
    {
        try {
            $result = \App\Core\Ptt\PttAiService::runAll((int)$id);
            Response::json(['ok' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    // ===== Dashboard =====
    public function dashboard(): void
    {
        $this->view('ptt/dashboard', [
            'title' => 'لوحة تحليلات PTT',
            'stats' => PttService::dashboardStats(),
        ]);
    }

    // ===== البحث =====
    public function search(): void
    {
        $uid = (int)Auth::id();
        $this->view('ptt/search', [
            'title' => 'بحث الرسائل الصوتية',
            'results' => PttService::search($uid, $_GET),
            'filters' => $_GET,
            'channels' => PttService::channelsForUser($uid),
            'priorities' => PttService::PRIORITIES,
            'users' => Database::query("SELECT id, full_name FROM users ORDER BY full_name LIMIT 300")->fetchAll(),
        ]);
    }

    // ===== الإدارة (ptt.manage) =====
    public function admin(): void
    {
        $this->view('ptt/admin', [
            'title' => 'إدارة قنوات PTT',
            'channels' => PttService::allChannels(),
            'branches' => Database::query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name")->fetchAll(),
            'users' => Database::query("SELECT id, full_name FROM users ORDER BY full_name")->fetchAll(),
            'departments' => PttService::departmentList(),
            'serviceStatusMeta' => PttService::serviceStatusMeta(),
        ]);
    }

    // ===== التحكم بحالة خدمة PTT — حصرياً للإدارة العليا (ptt.service_control) =====
    public function serviceStop(): void
    {
        if (!Auth::can('ptt.service_control')) {
            $this->flash('danger', 'ليس لديك صلاحية التحكم بحالة خدمة PTT');
            Response::redirect('/ptt/admin');
            return;
        }
        try {
            PttService::setServiceStatus(PttService::SERVICE_STATUS_PARTIAL, (int)Auth::id());
            $this->flash('success', 'تم إيقاف خدمة PTT جزئياً — لن يتمكن المستخدمون من إرسال رسائل أو بدء بث جديد حتى إعادة التشغيل');
        } catch (\Throwable $e) {
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/ptt/admin');
    }

    public function serviceRestart(): void
    {
        if (!Auth::can('ptt.service_control')) {
            $this->flash('danger', 'ليس لديك صلاحية التحكم بحالة خدمة PTT');
            Response::redirect('/ptt/admin');
            return;
        }
        try {
            PttService::setServiceStatus(PttService::SERVICE_STATUS_ACTIVE, (int)Auth::id());
            $this->flash('success', 'تمت إعادة تشغيل خدمة PTT بشكل كامل');
        } catch (\Throwable $e) {
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        Response::redirect('/ptt/admin');
    }

    public function storeChannel(): void
    {
        try {
            $data = $this->channelData();
            $id = PttService::saveChannel($data, null, (int)Auth::id());
            PttService::setMember($id, (int)Auth::id(), ['can_send' => 1, 'can_broadcast_live' => 1, 'role' => 'supervisor']);
            if (!empty($data['auto_sync'])) {
                PttService::syncDepartmentMembers($id);
            }
            $this->flash('success', 'تم إنشاء القناة');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/ptt/admin');
    }

    public function updateChannel(string $id): void
    {
        try {
            $data = $this->channelData();
            PttService::saveChannel($data, (int)$id, (int)Auth::id());
            if (!empty($data['auto_sync'])) {
                PttService::syncDepartmentMembers((int)$id);
            }
            $this->flash('success', 'تم تحديث القناة');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/ptt/admin');
    }

    public function deleteChannel(string $id): void
    {
        PttService::deleteChannel((int)$id);
        $this->flash('success', 'تم حذف القناة');
        Response::redirect('/ptt/admin');
    }

    // ===== استيراد القنوات من Excel =====
    public function channelsTemplate(): void
    {
        ImportHelper::downloadTemplate(
            ['code', 'name', 'description', 'department', 'branch', 'color', 'icon', 'auto_sync', 'is_active', 'is_readonly', 'max_duration_sec'],
            'ptt_channels_template.xlsx',
            ['kitchen_main', 'المطبخ الرئيسي', 'قناة المطبخ', 'المطبخ', 'الفرع الرئيسي', '#2563eb', 'fa-solid fa-tower-broadcast', 1, 1, 0, 60]
        );
    }

    public function importChannels(): void
    {
        try {
            $file = $_FILES['excel_file'] ?? [];
            $tmp = $file['tmp_name'] ?? '';
            if (!$tmp) {
                throw new \Exception('لم يُرفع ملف');
            }
            $read = ImportHelper::readFile($tmp);
            if (!empty($read['errors'])) {
                ImportHelper::flashErrors($read['errors']);
                Response::redirect('/ptt/admin');
                return;
            }
            $rows = $read['rows'];
            $errors = ImportHelper::validateRequired($rows, ['code', 'name']);
            $errors = array_merge($errors, ImportHelper::validateUnique($rows, 'code'));
            if ($errors) {
                ImportHelper::flashErrors($errors);
                Response::redirect('/ptt/admin');
                return;
            }

            $branches = Database::query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name")->fetchAll();
            $branchMap = [];
            $branchById = [];
            foreach ($branches as $b) {
                $branchMap[mb_strtolower(trim($b['name']), 'UTF-8')] = (int)$b['id'];
                $branchById[(int)$b['id']] = true;
            }
            $departments = Database::query("SELECT name FROM departments WHERE is_active = 1 ORDER BY name")->fetchAll();
            $deptMap = [];
            foreach ($departments as $d) {
                $deptMap[mb_strtolower(trim($d['name']), 'UTF-8')] = trim($d['name']);
            }

            $created = 0;
            $updated = 0;
            $actorId = (int)Auth::id();
            foreach ($rows as $row) {
                $code = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)($row['code'] ?? ''));
                $name = trim((string)($row['name'] ?? ''));
                if (!$code || !$name) {
                    continue;
                }
                $deptName = trim((string)($row['department'] ?? ''));
                $department = $deptMap[mb_strtolower($deptName, 'UTF-8')] ?? ($deptName ?: null);
                $branchName = trim((string)($row['branch'] ?? ''));
                $branchId = null;
                if ($branchName !== '') {
                    if (is_numeric($branchName) && isset($branchById[(int)$branchName])) {
                        $branchId = (int)$branchName;
                    } else {
                        $branchId = $branchMap[mb_strtolower($branchName, 'UTF-8')] ?? null;
                    }
                }
                $data = [
                    'code' => $code,
                    'name' => $name,
                    'description' => trim((string)($row['description'] ?? '')),
                    'branch_id' => $branchId,
                    'department' => $department,
                    'color' => trim((string)($row['color'] ?? '')) ?: '#2563eb',
                    'icon' => trim((string)($row['icon'] ?? '')) ?: 'fa-solid fa-tower-broadcast',
                    'is_active' => $this->parseBoolValue($row['is_active'] ?? 1),
                    'is_readonly' => $this->parseBoolValue($row['is_readonly'] ?? 0),
                    'owner_id' => 0,
                    'auto_sync' => $this->parseBoolValue($row['auto_sync'] ?? 0),
                    'max_duration_sec' => (int)($row['max_duration_sec'] ?? 0),
                ];
                $existing = Database::query("SELECT id FROM ptt_channels WHERE code = ?", [$code])->fetch();
                $id = null;
                if ($existing) {
                    $id = (int)$existing['id'];
                    PttService::saveChannel($data, $id, $actorId);
                    $updated++;
                } else {
                    $id = PttService::saveChannel($data, null, $actorId);
                    if ($actorId > 0 && Database::query("SELECT id FROM users WHERE id = ?", [$actorId])->fetch()) {
                        PttService::setMember($id, $actorId, ['can_send' => 1, 'can_broadcast_live' => 1, 'role' => 'supervisor']);
                    }
                    $created++;
                }
                if (!empty($data['auto_sync'])) {
                    PttService::syncDepartmentMembers($id);
                }
            }
            $this->flash('success', "تم استيراد القنوات: $created جديدة، $updated مُحدثة");
        } catch (\Throwable $e) {
            $this->flash('danger', 'فشل الاستيراد: ' . $e->getMessage());
        }
        Response::redirect('/ptt/admin');
    }

    // ===== إدارة الأعضاء =====
    public function members(string $id): void
    {
        $channel = PttService::channel((int)$id);
        if (!$channel) Response::abort(404, 'القناة غير موجودة');
        $this->view('ptt/members', [
            'title' => 'أعضاء: ' . $channel['name'],
            'channel' => $channel,
            'members' => PttService::members((int)$id),
            'users' => Database::query("SELECT id, full_name FROM users ORDER BY full_name")->fetchAll(),
        ]);
    }

    public function storeMember(string $id): void
    {
        PttService::setMember((int)$id, (int)$this->input('user_id'), [
            'can_send' => $this->input('can_send'),
            'can_broadcast_live' => $this->input('can_broadcast_live'),
            'role' => (string)$this->input('role', 'member'),
            'muted' => $this->input('muted'),
        ]);
        $this->flash('success', 'تم حفظ العضو');
        Response::redirect('/ptt/admin/channels/' . (int)$id . '/members');
    }

    public function destroyMember(string $id, string $userId): void
    {
        PttService::removeMember((int)$id, (int)$userId);
        $this->flash('success', 'تمت إزالة العضو');
        Response::redirect('/ptt/admin/channels/' . (int)$id . '/members');
    }

    private function parseBoolValue($value): bool
    {
        $v = strtolower(trim((string)$value));
        return in_array($v, ['1', 'yes', 'true', 'نعم', 'ديناميكية', 'active', 'فعال'], true);
    }

    private function channelData(): array
    {
        return [
            'code' => preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$this->input('code')),
            'name' => (string)$this->input('name'),
            'description' => (string)$this->input('description', ''),
            'branch_id' => (int)$this->input('branch_id', 0),
            'department' => trim((string)$this->input('department', '')),
            'color' => (string)$this->input('color', '#2563eb'),
            'icon' => (string)$this->input('icon', 'fa-solid fa-tower-broadcast'),
            'is_active' => $this->input('is_active'),
            'is_readonly' => $this->input('is_readonly'),
            'owner_id' => (int)$this->input('owner_id', 0),
            'auto_sync' => $this->input('auto_sync'),
            'max_duration_sec' => (int)$this->input('max_duration_sec', 0),
        ];
    }
}
