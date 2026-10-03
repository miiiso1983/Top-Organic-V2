<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Auth, Response, SyncService, Settings};
use App\Models\Printer;

class AdminController extends Controller
{
    /** صفحة حالة المزامنة الهجينة مع الخادم المركزي */
    public function sync(): void
    {
        $this->view('admin/sync', [
            'title'  => 'المزامنة السحابية',
            'status' => SyncService::status(),
            'recentIncoming' => Database::query(
                "SELECT * FROM sync_incoming ORDER BY id DESC LIMIT 50"
            )->fetchAll(),
        ]);
    }

    /** تشغيل مزامنة يدوية فورية مع الخادم المركزي */
    public function runSync(): void
    {
        $result = SyncService::push();
        if ($result['ok']) {
            $this->flash('success', "تمت المزامنة: أُرسل {$result['pushed']} عملية، ووردت {$result['remote_pending']} عملية من المركز");
        } else {
            $this->flash('danger', 'فشلت المزامنة: ' . $result['error']);
        }
        Response::redirect('/admin/sync');
    }

    public function users(): void
    {
        $users = Database::query(
            "SELECT u.*, r.name AS role_name, b.name AS branch_name FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN branches b ON b.id = u.branch_id
             ORDER BY u.full_name"
        )->fetchAll();
        $roles = Database::query("SELECT * FROM roles ORDER BY id")->fetchAll();
        $branches = Database::query("SELECT * FROM branches WHERE is_active=1")->fetchAll();
        $permissions = Database::query("SELECT * FROM permissions ORDER BY module, id")->fetchAll();
        $rolePerms = [];
        foreach (Database::query("SELECT role_id, permission_id FROM role_permissions")->fetchAll() as $rp) {
            $rolePerms[$rp['role_id']][] = (int)$rp['permission_id'];
        }

        // قوالب سير العمل وصلاحياتها لكل دور
        $workflowTemplates = Database::query("SELECT * FROM approval_templates WHERE is_active = 1 ORDER BY name")->fetchAll();
        $templateRolePerms = [];
        foreach (Database::query("SELECT * FROM template_allowed_roles")->fetchAll() as $tr) {
            $templateRolePerms[$tr['role_id']][$tr['template_id']] = $tr;
        }

        $this->view('admin/users', [
            'title' => 'المستخدمون والصلاحيات', 'users' => $users, 'roles' => $roles,
            'branches' => $branches, 'permissions' => $permissions, 'rolePerms' => $rolePerms,
            'workflowTemplates' => $workflowTemplates, 'templateRolePerms' => $templateRolePerms,
        ]);
    }

    public function storeUser(): void
    {
        $username = trim((string)$this->input('username'));
        $exists = Database::query("SELECT id FROM users WHERE username = ?", [$username])->fetch();
        if ($exists) {
            $this->flash('danger', 'اسم المستخدم موجود مسبقاً');
            Response::redirect('/admin/users');
        }
        Database::query(
            "INSERT INTO users (username, password_hash, full_name, role_id, branch_id) VALUES (?,?,?,?,?)",
            [$username, password_hash((string)$this->input('password'), PASSWORD_DEFAULT),
             (string)$this->input('full_name'), (int)$this->input('role_id'),
             $this->input('branch_id') ?: null]
        );
        $this->flash('success', 'تم إنشاء المستخدم');
        Response::redirect('/admin/users');
    }

    public function editUser(string $id): void
    {
        $user = Database::query(
            "SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?",
            [(int)$id]
        )->fetch();
        if (!$user) { Response::abort(404, 'المستخدم غير موجود'); }

        $roles = Database::query("SELECT * FROM roles ORDER BY id")->fetchAll();
        $branches = Database::query("SELECT * FROM branches WHERE is_active=1")->fetchAll();

        $roleId = (int)$user['role_id'];
        $permissions = Database::query("SELECT * FROM permissions ORDER BY module, id")->fetchAll();
        $rolePermIds = [];
        foreach (Database::query("SELECT permission_id FROM role_permissions WHERE role_id = ?", [$roleId])->fetchAll() as $rp) {
            $rolePermIds[] = (int)$rp['permission_id'];
        }

        $workflowTemplates = Database::query("SELECT * FROM approval_templates WHERE is_active = 1 ORDER BY name")->fetchAll();
        $templateRolePerms = [];
        foreach (Database::query("SELECT * FROM template_allowed_roles WHERE role_id = ?", [$roleId])->fetchAll() as $tr) {
            $templateRolePerms[$tr['template_id']] = $tr;
        }

        $this->view('admin/users_edit', [
            'title' => 'تعديل المستخدم: ' . $user['full_name'],
            'user' => $user, 'roles' => $roles, 'branches' => $branches,
            'permissions' => $permissions, 'rolePermIds' => $rolePermIds,
            'workflowTemplates' => $workflowTemplates, 'templateRolePerms' => $templateRolePerms,
        ]);
    }

    public function updateUser(string $id): void
    {
        $userId = (int)$id;
        $user = Database::query("SELECT id FROM users WHERE id = ?", [$userId])->fetch();
        if (!$user) {
            $this->flash('danger', 'المستخدم غير موجود');
            Response::redirect('/admin/users');
        }

        $username = trim((string)$this->input('username'));
        $fullName = trim((string)$this->input('full_name'));
        $roleId = (int)$this->input('role_id');
        $branchId = $this->input('branch_id') ?: null;
        $isActive = !empty($this->input('is_active')) ? 1 : 0;
        $password = (string)$this->input('password');

        $duplicate = Database::query(
            "SELECT id FROM users WHERE username = ? AND id != ?",
            [$username, $userId]
        )->fetch();
        if ($duplicate) {
            $this->flash('danger', 'اسم المستخدم موجود مسبقاً');
            Response::redirect('/admin/users/' . $userId . '/edit');
        }

        $params = [$username, $fullName, $roleId, $branchId, $isActive, $userId];
        $passwordSql = '';
        if ($password !== '') {
            $passwordSql = ", password_hash = ?";
            array_splice($params, 5, 0, [password_hash($password, PASSWORD_DEFAULT)]);
        }

        Database::query(
            "UPDATE users SET username = ?, full_name = ?, role_id = ?, branch_id = ?, is_active = ? $passwordSql WHERE id = ?",
            $params
        );
        $this->flash('success', 'تم تحديث بيانات المستخدم');
        Response::redirect('/admin/users');
    }

    public function deleteUser(string $id): void
    {
        $userId = (int)$id;
        if ($userId === Auth::id()) {
            $this->flash('danger', 'لا يمكن حذف المستخدم الحالي');
            Response::redirect('/admin/users');
        }

        $user = Database::query("SELECT full_name FROM users WHERE id = ?", [$userId])->fetch();
        if (!$user) {
            $this->flash('danger', 'المستخدم غير موجود');
            Response::redirect('/admin/users');
        }

        try {
            Database::query("DELETE FROM users WHERE id = ?", [$userId]);
            $this->flash('success', 'تم حذف المستخدم: ' . $user['full_name']);
        } catch (\Throwable $e) {
            // إذا كان المستخدم مرتبطاً بسجلات أخرى، يتم تعطيله بدلاً من الحذف
            Database::query("UPDATE users SET is_active = 0, username = CONCAT(username, '.inactive.', id) WHERE id = ?", [$userId]);
            $this->flash('warning', 'المستخدم مرتبط بسجلات، تم تعطيله بدلاً من الحذف');
        }
        Response::redirect('/admin/users');
    }

    /** إنشاء دور جديد */
    public function storeRole(): void
    {
        $name = trim((string)$this->input('name'));
        if ($name === '') {
            $this->flash('danger', 'اسم الدور مطلوب');
            Response::redirect('/admin/users');
        }
        $exists = Database::query("SELECT id FROM roles WHERE name = ?", [$name])->fetch();
        if ($exists) {
            $this->flash('danger', 'الدور موجود مسبقاً');
            Response::redirect('/admin/users');
        }
        Database::query(
            "INSERT INTO roles (name, level, description) VALUES (?,?,?)",
            [$name, (int)$this->input('level', 0), trim((string)$this->input('description', '')) ?: null]
        );
        $newRoleId = (int)Database::lastInsertId();
        $this->flash('success', 'تم إنشاء الدور: ' . $name . '، الرجاء تحديد صلاحياته الآن');
        Response::redirect('/admin/users?open_role=' . $newRoleId);
    }

    /** تعديل بيانات دور (الاسم/المستوى/الوصف) */
    public function updateRole(string $id): void
    {
        $roleId = (int)$id;
        $role = Database::query("SELECT id FROM roles WHERE id = ?", [$roleId])->fetch();
        if (!$role) {
            $this->flash('danger', 'الدور غير موجود');
            Response::redirect('/admin/users');
        }
        $name = trim((string)$this->input('name'));
        if ($name === '') {
            $this->flash('danger', 'اسم الدور مطلوب');
            Response::redirect('/admin/users');
        }
        $duplicate = Database::query("SELECT id FROM roles WHERE name = ? AND id != ?", [$name, $roleId])->fetch();
        if ($duplicate) {
            $this->flash('danger', 'اسم الدور موجود مسبقاً');
            Response::redirect('/admin/users');
        }
        Database::query(
            "UPDATE roles SET name = ?, level = ?, description = ? WHERE id = ?",
            [$name, (int)$this->input('level', 0), trim((string)$this->input('description', '')) ?: null, $roleId]
        );
        $this->flash('success', 'تم تحديث الدور');
        Response::redirect('/admin/users');
    }

    /** حذف دور (يُمنع إذا كان مرتبطاً بمستخدمين حالياً) */
    public function deleteRole(string $id): void
    {
        $roleId = (int)$id;
        $role = Database::query("SELECT name FROM roles WHERE id = ?", [$roleId])->fetch();
        if (!$role) {
            $this->flash('danger', 'الدور غير موجود');
            Response::redirect('/admin/users');
        }
        $usersCount = (int)Database::query(
            "SELECT COUNT(*) AS c FROM users WHERE role_id = ?", [$roleId]
        )->fetch()['c'];
        if ($usersCount > 0) {
            $this->flash('danger', "لا يمكن حذف الدور لارتباطه بـ {$usersCount} مستخدم، أعد تعيين أدوارهم أولاً");
            Response::redirect('/admin/users');
        }
        Database::query("DELETE FROM roles WHERE id = ?", [$roleId]);
        $this->flash('success', 'تم حذف الدور: ' . $role['name']);
        Response::redirect('/admin/users');
    }

    public function savePermissions(string $id): void
    {
        $roleId = (int)$id;
        $permIds = array_map('intval', (array)($_POST['perm_ids'] ?? []));
        $templatePerms = $_POST['template_perms'] ?? [];
        if (!is_array($templatePerms)) {
            $templatePerms = [];
        }

        Database::beginTransaction();
        try {
            Database::query("DELETE FROM role_permissions WHERE role_id = ?", [$roleId]);
            foreach ($permIds as $pid) {
                Database::query("INSERT INTO role_permissions (role_id, permission_id) VALUES (?,?)", [$roleId, $pid]);
            }

            Database::query("DELETE FROM template_allowed_roles WHERE role_id = ?", [$roleId]);
            foreach ($templatePerms as $templateId => $values) {
                $templateId = (int)$templateId;
                if ($templateId <= 0) continue;
                $canCreate = !empty($values['can_create']) ? 1 : 0;
                $canViewAll = !empty($values['can_view_all']) ? 1 : 0;
                if ($canCreate || $canViewAll) {
                    Database::query(
                        "INSERT INTO template_allowed_roles (template_id, role_id, can_create, can_view_all) VALUES (?,?,?,?)",
                        [$templateId, $roleId, $canCreate, $canViewAll]
                    );
                }
            }

            Database::commit();
            $this->flash('success', 'تم حفظ صلاحيات الدور');
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'خطأ: ' . $e->getMessage());
        }
        $returnUrl = (string)$this->input('return_url', '');
        if ($returnUrl !== '' && $returnUrl[0] === '/' && (!isset($returnUrl[1]) || $returnUrl[1] !== '/')) {
            Response::redirect($returnUrl);
        }
        Response::redirect('/admin/users');
    }

    /** إعدادات الطابعات الحرارية */
    // ===== مشغّل الترحيلات =====
    public function migrate(): void
    {
        $applied = \App\Core\MigrationRunner::applied();
        $files = [];
        foreach (\App\Core\MigrationRunner::files() as $f) {
            $name = basename($f);
            $files[] = [
                'name' => $name,
                'applied' => $applied[$name] ?? null,
                'pending' => !isset($applied[$name]) || ($applied[$name]['status'] ?? '') === 'failed',
            ];
        }
        $this->view('admin/migrate', [
            'title' => 'ترحيلات قاعدة البيانات',
            'files' => $files,
            'report' => $_SESSION['migration_report'] ?? null,
        ]);
        unset($_SESSION['migration_report']);
    }

    public function runMigrations(): void
    {
        $report = \App\Core\MigrationRunner::runPending();
        $_SESSION['migration_report'] = $report;
        $failed = array_filter($report, fn($r) => $r['status'] === 'failed');
        $this->flash($failed ? 'danger' : 'success',
            $failed ? ('فشل في: ' . implode('، ', array_keys($failed))) : 'تم تنفيذ كل الترحيلات المعلقة بنجاح');
        Response::redirect('/admin/migrate');
    }

    /** تشغيل ملف ترحيل واحد محدد (محمي من path traversal) */
    public function runMigrationFile(): void
    {
        $name = basename((string)$this->input('file', ''));
        $path = BASE_PATH . '/database/' . $name;
        if ($name === '' || !in_array($path, \App\Core\MigrationRunner::files(), true)) {
            $this->flash('danger', 'ملف غير معروف');
            Response::redirect('/admin/migrate');
        }
        $r = \App\Core\MigrationRunner::runFile($path);
        $_SESSION['migration_report'] = [$name => $r];
        $this->flash($r['status'] === 'failed' ? 'danger' : 'success',
            $r['status'] === 'failed' ? ('فشل: ' . $r['error']) : ("تم: $name (ناجحة: {$r['ok']}، تحذيرات: {$r['warned']})"));
        Response::redirect('/admin/migrate');
    }

    // ===== إعدادات الإشعارات الفورية (FCM / LiveKit / Web Push) =====
    public function pushSettings(): void
    {
        $token = (string)\App\Core\Settings::get('workflow_cron_token', '');
        if ($token === '') {
            $token = bin2hex(random_bytes(20));
            \App\Core\Settings::set('workflow_cron_token', $token);
        }
        $this->view('admin/push_settings', [
            'title' => 'إعدادات الإشعارات والبث الفوري',
            's' => [
                'fcm_service_account_json' => \App\Core\Settings::get('fcm_service_account_json', ''),
                'fcm_server_key' => \App\Core\Settings::get('fcm_server_key', ''),
                'livekit_url' => \App\Core\Settings::get('livekit_url', ''),
                'livekit_api_key' => \App\Core\Settings::get('livekit_api_key', ''),
                'livekit_api_secret' => \App\Core\Settings::get('livekit_api_secret', ''),
                'vapid_public' => \App\Core\Settings::get('push_vapid_public', ''),
            ],
            'fcmConfigured' => \App\Core\Push\FcmService::isConfigured(),
            'cronUrl' => '/cron/workflow-escalation?token=' . $token,
        ]);
    }

    public function savePushSettings(): void
    {
        foreach (['fcm_service_account_json', 'fcm_server_key', 'livekit_url', 'livekit_api_key', 'livekit_api_secret'] as $k) {
            \App\Core\Settings::set($k, trim((string)$this->input($k, '')));
        }
        // إبطال التوكن المخزن عند تغيير بيانات الاعتماد
        \App\Core\Settings::set('fcm_access_token', '');
        \App\Core\Settings::set('fcm_access_token_exp', '0');
        // توليد مفاتيح VAPID لـ Web Push عند أول حفظ
        try { \App\Core\Push\WebPushService::ensureVapidKeys(); } catch (\Throwable $e) {}
        $this->flash('success', 'تم حفظ الإعدادات');
        Response::redirect('/admin/push-settings');
    }

    public function testPush(): void
    {
        $uid = (int)Auth::id();
        $tokens = Database::query(
            "SELECT endpoint FROM push_subscriptions WHERE user_id = ? AND is_active = 1 AND platform IN ('android','ios')",
            [$uid]
        )->fetchAll(\PDO::FETCH_COLUMN);
        if (!$tokens) {
            $this->flash('danger', 'لا توجد توكنات موبايل مسجلة لحسابك — سجّل من التطبيق أولاً (push_subscriptions)');
            Response::redirect('/admin/push-settings');
        }
        $res = \App\Core\Push\FcmService::send($tokens, 'اختبار FCM', 'إشعار تجريبي من النظام ✔', '/notifications');
        $this->flash($res['sent'] ? 'success' : 'danger',
            "أُرسل: {$res['sent']} — فشل: {$res['failed']}" . ($res['errors'] ? ' — ' . implode(' | ', $res['errors']) : ''));
        Response::redirect('/admin/push-settings');
    }

    public function printerSettings(): void
    {
        $this->view('admin/printer_settings', [
            'title' => 'إعدادات الطابعات',
            'settings' => Settings::printer(),
            'printers' => Printer::all(),
            'kitchens' => Database::query("SELECT id, name FROM preparation_kitchens WHERE is_active = 1 ORDER BY sort_order, id")->fetchAll(),
            'printerTypes' => Printer::types(),
        ]);
    }

    public function savePrinterSettings(): void
    {
        $width = (int)$this->input('receipt_width', 80);
        if (!in_array($width, [58, 80], true)) { $width = 80; }

        Settings::set('printer_receipt_width', $width);
        Settings::set('printer_receipt_header', (string)$this->input('receipt_header', ''));
        Settings::set('printer_receipt_footer', (string)$this->input('receipt_footer', ''));
        Settings::set('printer_show_logo', !empty($this->input('show_logo')) ? '1' : '0');
        Settings::set('printer_show_tax', !empty($this->input('show_tax')) ? '1' : '0');
        Settings::set('printer_tax_number', (string)$this->input('tax_number', ''));
        Settings::set('printer_auto_print', !empty($this->input('auto_print')) ? '1' : '0');
        Settings::set('printer_name', (string)$this->input('printer_name', ''));
        Settings::set('printer_show_cashier', !empty($this->input('show_cashier')) ? '1' : '0');
        Settings::set('printer_kitchen_name', (string)$this->input('kitchen_printer', ''));
        Settings::set('printer_copies', max(1, (int)$this->input('copies', 1)));

        $this->flash('success', 'تم حفظ إعدادات الطابعات');
        Response::redirect('/admin/printer');
    }

    public function storePrinter(): void
    {
        $name = trim((string)$this->input('name'));
        if ($name === '') {
            $this->flash('danger', 'اسم الطابعة مطلوب');
            Response::redirect('/admin/printer');
            return;
        }
        $config = [];
        if ($this->input('ip')) $config['ip'] = (string)$this->input('ip');
        if ($this->input('port')) $config['port'] = (string)$this->input('port');
        Printer::create([
            'name' => $name,
            'type' => (string)$this->input('type', 'thermal'),
            'kitchen_id' => $this->input('kitchen_id') ? (int)$this->input('kitchen_id') : null,
            'config' => $config,
            'is_active' => !empty($this->input('is_active')),
        ]);
        $this->flash('success', 'تم إضافة الطابعة');
        Response::redirect('/admin/printer');
    }

    public function updatePrinter(string $id): void
    {
        $name = trim((string)$this->input('name'));
        if ($name === '') {
            $this->flash('danger', 'اسم الطابعة مطلوب');
            Response::redirect('/admin/printer');
            return;
        }
        $config = [];
        if ($this->input('ip')) $config['ip'] = (string)$this->input('ip');
        if ($this->input('port')) $config['port'] = (string)$this->input('port');
        Printer::update((int)$id, [
            'name' => $name,
            'type' => (string)$this->input('type', 'thermal'),
            'kitchen_id' => $this->input('kitchen_id') ? (int)$this->input('kitchen_id') : null,
            'config' => $config,
            'is_active' => !empty($this->input('is_active')),
        ]);
        $this->flash('success', 'تم تحديث الطابعة');
        Response::redirect('/admin/printer');
    }

    public function deletePrinter(string $id): void
    {
        Printer::delete((int)$id);
        $this->flash('success', 'تم حذف الطابعة');
        Response::redirect('/admin/printer');
    }

    /** إعدادات OpenAI */
    public function openAiSettings(): void
    {
        if (!Auth::can('ai_assistant.manage')) {
            Response::redirect('/dashboard');
        }
        $this->view('admin/openai_settings', [
            'title' => 'إعدادات OpenAI',
            'enabled' => (bool)Settings::get('openai_enabled', 0),
            'apiKey' => Settings::get('openai_api_key', ''),
            'model' => Settings::get('openai_model', 'gpt-4o-mini'),
            'maxTokens' => (int)Settings::get('openai_max_tokens', 800),
            'temperature' => (float)Settings::get('openai_temperature', 0.3),
        ]);
    }

    public function saveOpenAiSettings(): void
    {
        if (!Auth::can('ai_assistant.manage')) {
            Response::redirect('/dashboard');
        }
        Settings::set('openai_enabled', !empty($this->input('enabled')) ? '1' : '0');
        Settings::set('openai_api_key', trim((string)$this->input('api_key', '')));
        Settings::set('openai_model', trim((string)$this->input('model', 'gpt-4o-mini')));
        Settings::set('openai_max_tokens', (string)max(50, (int)$this->input('max_tokens', 800)));
        Settings::set('openai_temperature', (string)min(1.0, max(0.0, (float)$this->input('temperature', 0.3))));

        $this->flash('success', 'تم حفظ إعدادات OpenAI');
        Response::redirect('/admin/openai-settings');
    }

    /** إعدادات التعرف على الوجه (AWS Rekognition) */
    public function faceRecognitionSettings(): void
    {
        if (!Auth::can('admin.settings')) {
            Response::redirect('/dashboard');
        }
        $this->view('admin/face_recognition_settings', [
            'title' => 'إعدادات التعرف على الوجه',
            'configured' => \App\Core\FaceRecognitionService::isConfigured(),
            's' => [
                'aws_access_key' => Settings::get('aws_access_key', ''),
                'aws_secret_key' => Settings::get('aws_secret_key', ''),
                'aws_region' => Settings::get('aws_region', ''),
                'aws_similarity_threshold' => (int)Settings::get('aws_similarity_threshold', 80),
                'face_recognition_enabled' => (bool)Settings::get('face_recognition_enabled', 0),
            ],
            'regions' => [
                'us-east-1' => 'US East (N. Virginia)',
                'us-west-2' => 'US West (Oregon)',
                'eu-central-1' => 'Europe (Frankfurt)',
                'eu-west-1' => 'Europe (Ireland)',
                'me-south-1' => 'Middle East (Bahrain)',
                'ap-south-1' => 'Asia Pacific (Mumbai)',
            ],
        ]);
    }

    public function saveFaceRecognitionSettings(): void
    {
        if (!Auth::can('admin.settings')) {
            Response::redirect('/dashboard');
        }
        Settings::set('aws_access_key', trim((string)$this->input('aws_access_key', '')));
        Settings::set('aws_secret_key', trim((string)$this->input('aws_secret_key', '')));
        Settings::set('aws_region', trim((string)$this->input('aws_region', '')));
        Settings::set('aws_similarity_threshold', (string)max(0, min(100, (int)$this->input('aws_similarity_threshold', 80))));
        Settings::set('face_recognition_enabled', !empty($this->input('face_recognition_enabled')) ? '1' : '0');
        $this->flash('success', 'تم حفظ إعدادات التعرف على الوجه');
        Response::redirect('/admin/face-recognition-settings');
    }

    public function testFaceRecognitionConnection(): void
    {
        if (!Auth::can('admin.settings')) {
            Response::json(['ok' => false, 'error' => 'Unauthorized'], 403);
            return;
        }
        if (!\App\Core\FaceRecognitionService::isConfigured()) {
            Response::json(['ok' => false, 'error' => 'إعدادات AWS غير مكتملة'], 400);
            return;
        }
        try {
            $sts = new \Aws\Sts\StsClient([
                'version' => 'latest',
                'region'  => \App\Core\Settings::get('aws_region'),
                'credentials' => [
                    'key'    => \App\Core\Settings::get('aws_access_key'),
                    'secret' => \App\Core\Settings::get('aws_secret_key'),
                ],
            ]);
            $identity = $sts->getCallerIdentity();
            Response::json([
                'ok' => true,
                'message' => 'تم الاتصال بـ AWS بنجاح',
                'account' => $identity['Account'] ?? 'unknown',
                'arn' => $identity['Arn'] ?? 'unknown',
            ]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => 'فشل الاتصال: ' . $e->getMessage()], 500);
        }
    }

    /** إدارة فروع وإحداثيات الجيوفينس */
    public function branches(): void
    {
        $branches = Database::query("SELECT * FROM branches ORDER BY id")->fetchAll();
        $this->view('admin/branches', [
            'title' => 'إدارة الفروع',
            'branches' => $branches,
        ]);
    }

    public function updateBranch(string $id): void
    {
        Database::query(
            "UPDATE branches SET name = ?, address = ?, phone = ?, latitude = ?, longitude = ?, allowed_radius_meters = ?, is_active = ? WHERE id = ?",
            [(string)$this->input('name'), (string)$this->input('address'), (string)$this->input('phone'),
             $this->input('latitude') ?: null, $this->input('longitude') ?: null,
             (int)$this->input('allowed_radius_meters', 200), !empty($this->input('is_active')) ? 1 : 0, (int)$id]
        );
        $this->flash('success', 'تم تحديث بيانات الفرع');
        Response::redirect('/admin/branches');
    }

    public function storeBranch(): void
    {
        $name = trim((string)$this->input('name'));
        if (!$name) {
            $this->flash('danger', 'اسم الفرع مطلوب');
            Response::redirect('/admin/branches');
            return;
        }
        Database::query(
            "INSERT INTO branches (name, address, phone, latitude, longitude, allowed_radius_meters, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $name,
                (string)$this->input('address'),
                (string)$this->input('phone'),
                $this->input('latitude') ?: null,
                $this->input('longitude') ?: null,
                (int)$this->input('allowed_radius_meters', 200),
                !empty($this->input('is_active')) ? 1 : 0,
            ]
        );
        $this->flash('success', 'تم إضافة الفرع');
        Response::redirect('/admin/branches');
    }

    public function deleteBranch(string $id): void
    {
        $id = (int)$id;
        $linkedTables = [
            'cash_registers', 'dining_tables', 'employees', 'fixed_assets', 'floors',
            'kitchen_sections', 'kitchen_stations', 'material_requests', 'notifications',
            'ptt_channels', 'ptt_messages', 'requests', 'users', 'warehouses',
        ];
        $linked = [];
        foreach ($linkedTables as $table) {
            try {
                $row = Database::query("SELECT COUNT(*) AS c FROM {$table} WHERE branch_id = ?", [$id])->fetch();
                if ((int)($row['c'] ?? 0) > 0) {
                    $linked[] = $table;
                }
            } catch (\Throwable $e) {
                // table may not exist
            }
        }
        if ($linked) {
            $this->flash('danger', 'لا يمكن حذف الفرع: يوجد بيانات مرتبطة بـ ' . implode('، ', $linked));
            Response::redirect('/admin/branches');
            return;
        }
        Database::query("DELETE FROM branches WHERE id = ?", [$id]);
        $this->flash('success', 'تم حذف الفرع');
        Response::redirect('/admin/branches');
    }

    /** إدارة أنواع التطبيقات (HungerStation, Jahez, Mrsool...) */
    public function appTypes(): void
    {
        $this->view('admin/app_types', [
            'title' => 'أنواع التطبيقات',
            'appTypes' => Database::query("SELECT * FROM app_types ORDER BY name")->fetchAll(),
        ]);
    }

    public function storeAppType(): void
    {
        $name = trim((string)$this->input('name'));
        $code = trim((string)$this->input('code'));
        if (!$name) {
            $this->flash('danger', 'اسم التطبيق مطلوب');
            Response::redirect('/admin/app-types');
        }
        Database::query(
            "INSERT INTO app_types (name, code, is_active) VALUES (?,?,1)",
            [$name, $code ?: null]
        );
        $this->flash('success', 'تم إضافة التطبيق');
        Response::redirect('/admin/app-types');
    }

    public function updateAppType(string $id): void
    {
        $name = trim((string)$this->input('name'));
        $code = trim((string)$this->input('code'));
        $isActive = !empty($this->input('is_active')) ? 1 : 0;
        if (!$name) {
            $this->flash('danger', 'اسم التطبيق مطلوب');
            Response::redirect('/admin/app-types');
        }
        Database::query(
            "UPDATE app_types SET name = ?, code = ?, is_active = ? WHERE id = ?",
            [$name, $code ?: null, $isActive, (int)$id]
        );
        $this->flash('success', 'تم تحديث التطبيق');
        Response::redirect('/admin/app-types');
    }

    public function deleteAppType(string $id): void
    {
        $linked = (int)Database::query(
            "SELECT COUNT(*) AS c FROM orders WHERE app_type_id = ?", [(int)$id]
        )->fetch()['c'];
        if ($linked > 0) {
            Database::query("UPDATE app_types SET is_active = 0 WHERE id = ?", [(int)$id]);
            $this->flash('warning', 'التطبيق مرتبط بطلبات، تم تعطيله بدلاً من الحذف');
        } else {
            Database::query("DELETE FROM app_types WHERE id = ?", [(int)$id]);
            $this->flash('success', 'تم حذف التطبيق');
        }
        Response::redirect('/admin/app-types');
    }
}

