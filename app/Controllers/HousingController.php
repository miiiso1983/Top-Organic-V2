<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Response, Auth, Helpers, ImportHelper, HrService};

/**
 * وحدة إدارة السكن — مواقع السكن (سكن خارجي/اليرموك/سبتي/القادسية/الداخلي)
 * كل موقع يحتوي شققاً، وكل شقة تحتوي غرفاً بعدد أسرّة محدد، ويُسكَّن الموظفون في غرفة محددة.
 */
class HousingController extends Controller
{
    // ===== الصفحة الرئيسية: قائمة التسكين + نموذج تسكين جديد + إعدادات الهيكلية =====
    public function index(): void
    {
        $sites = Database::query("SELECT * FROM housing_sites ORDER BY sort_order")->fetchAll();

        // الشقق والغرف مجمّعة لكل موقع (لبناء شجرة الإعدادات وقوائم الاختيار)
        $apartments = Database::query(
            "SELECT a.*, s.name AS site_name FROM housing_apartments a
             JOIN housing_sites s ON s.id = a.site_id
             ORDER BY s.sort_order, a.apartment_number"
        )->fetchAll();

        $rooms = Database::query(
            "SELECT r.*, a.apartment_number, a.site_id,
                    (SELECT COUNT(*) FROM employee_housing_assignments ea WHERE ea.room_id = r.id AND ea.status = 'active') AS occupied_beds
             FROM housing_rooms r
             JOIN housing_apartments a ON a.id = r.apartment_id
             ORDER BY a.apartment_number, r.room_number"
        )->fetchAll();

        // تجميع الشقق تحت كل موقع، والغرف تحت كل شقة (لعرض الهيكلية والاستخدام في JS)
        $apartmentsBySite = [];
        foreach ($apartments as $a) {
            $apartmentsBySite[(int)$a['site_id']][] = $a;
        }
        $roomsByApartment = [];
        foreach ($rooms as $r) {
            $roomsByApartment[(int)$r['apartment_id']][] = $r;
        }

        // قائمة الموظفين المسكَّنين حالياً (اسم الموظف، القسم، الوظيفة، الموقع، الشقة، الغرفة)
        $assignments = Database::query(
            "SELECT ea.*, e.full_name, e.emp_code,
                    COALESCE(d.name, e.department) AS department_name,
                    COALESCE(j.title, e.position) AS job_title,
                    s.name AS site_name, ap.apartment_number, r.room_number, r.bed_capacity
             FROM employee_housing_assignments ea
             JOIN employees e ON e.id = ea.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN jobs j ON j.id = e.job_id
             JOIN housing_sites s ON s.id = ea.site_id
             LEFT JOIN housing_apartments ap ON ap.id = ea.apartment_id
             LEFT JOIN housing_rooms r ON r.id = ea.room_id
             WHERE ea.status = 'active'
             ORDER BY s.sort_order, ap.apartment_number, r.room_number, e.full_name"
        )->fetchAll();

        // كل الموظفين (لقائمة الاختيار عند التسكين) مع بيان ما إذا كان لديه تسكين فعلي حالياً
        $employees = Database::query(
            "SELECT e.id, e.full_name, e.emp_code,
                    COALESCE(d.name, e.department) AS department_name,
                    COALESCE(j.title, e.position) AS job_title
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN jobs j ON j.id = e.job_id
             WHERE e.file_status = 'open'
             ORDER BY e.full_name"
        )->fetchAll();

        $this->view('hr/housing', [
            'title' => 'إدارة السكن',
            'sites' => $sites,
            'apartments' => $apartments,
            'rooms' => $rooms,
            'apartmentsBySite' => $apartmentsBySite,
            'roomsByApartment' => $roomsByApartment,
            'assignments' => $assignments,
            'employees' => $employees,
        ]);
    }

    // ===== إعدادات الهيكلية: إضافة/حذف شقة =====
    public function storeApartment(): void
    {
        $siteId = (int)$this->input('site_id', 0);
        $apartmentNumber = trim((string)$this->input('apartment_number', ''));
        if (!$siteId || $apartmentNumber === '') {
            $this->flash('danger', 'الموقع ورقم الشقة مطلوبان');
            Response::redirect('/hr/housing');
            return;
        }
        try {
            Database::query(
                "INSERT INTO housing_apartments (site_id, apartment_number, notes) VALUES (?,?,?)",
                [$siteId, $apartmentNumber, (string)$this->input('notes', '') ?: null]
            );
            $this->flash('success', 'تمت إضافة الشقة بنجاح');
        } catch (\Throwable $e) {
            $this->flash('danger', 'تعذّرت الإضافة: رقم الشقة مستخدم مسبقاً في هذا الموقع');
        }
        Response::redirect('/hr/housing');
    }

    public function deleteApartment(string $id): void
    {
        Database::query("DELETE FROM housing_apartments WHERE id = ?", [(int)$id]);
        $this->flash('success', 'تم حذف الشقة وجميع غرفها');
        Response::redirect('/hr/housing');
    }

    // ===== إعدادات الهيكلية: إضافة/حذف غرفة =====
    public function storeRoom(): void
    {
        $apartmentId = (int)$this->input('apartment_id', 0);
        $roomNumber = trim((string)$this->input('room_number', ''));
        $bedCapacity = max(1, (int)$this->input('bed_capacity', 1));
        if (!$apartmentId || $roomNumber === '') {
            $this->flash('danger', 'الشقة ورقم الغرفة مطلوبان');
            Response::redirect('/hr/housing');
            return;
        }
        try {
            Database::query(
                "INSERT INTO housing_rooms (apartment_id, room_number, bed_capacity, notes) VALUES (?,?,?,?)",
                [$apartmentId, $roomNumber, $bedCapacity, (string)$this->input('notes', '') ?: null]
            );
            $this->flash('success', 'تمت إضافة الغرفة بنجاح');
        } catch (\Throwable $e) {
            $this->flash('danger', 'تعذّرت الإضافة: رقم الغرفة مستخدم مسبقاً في هذه الشقة');
        }
        Response::redirect('/hr/housing');
    }

    // ===== تحديث مبلغ الاستقطاع الافتراضي لموقع سكن =====
    public function updateSiteDeduction(string $id): void
    {
        $amount = (float)$this->input('monthly_deduction', 0);
        Database::query("UPDATE housing_sites SET monthly_deduction = ? WHERE id = ?", [$amount, (int)$id]);
        $this->flash('success', 'تم تحديث مبلغ الاستقطاع الافتراضي للموقع');
        Response::redirect('/hr/housing');
    }

    public function deleteRoom(string $id): void
    {
        $roomId = (int)$id;
        $occupied = (int)Database::query(
            "SELECT COUNT(*) FROM employee_housing_assignments WHERE room_id = ? AND status = 'active'",
            [$roomId]
        )->fetchColumn();
        if ($occupied > 0) {
            $this->flash('danger', 'لا يمكن حذف الغرفة لوجود موظفين مسكَّنين فيها حالياً');
            Response::redirect('/hr/housing');
            return;
        }
        Database::query("DELETE FROM housing_rooms WHERE id = ?", [$roomId]);
        $this->flash('success', 'تم حذف الغرفة');
        Response::redirect('/hr/housing');
    }

    // ===== تعديل عدد أسرّة غرفة قائمة =====
    public function updateRoom(string $id): void
    {
        $roomId = (int)$id;
        $bedCapacity = (int)$this->input('bed_capacity', 0);
        if ($bedCapacity < 1) {
            $this->flash('danger', 'عدد الأسرّة يجب أن يكون رقماً أكبر من صفر');
            Response::redirect('/hr/housing');
            return;
        }
        $occupied = (int)Database::query(
            "SELECT COUNT(*) FROM employee_housing_assignments WHERE room_id = ? AND status = 'active'",
            [$roomId]
        )->fetchColumn();
        if ($bedCapacity < $occupied) {
            $this->flash('danger', "لا يمكن تقليل عدد الأسرّة إلى أقل من عدد الموظفين المسكَّنين حالياً في هذه الغرفة ($occupied)");
            Response::redirect('/hr/housing');
            return;
        }
        Database::query("UPDATE housing_rooms SET bed_capacity = ? WHERE id = ?", [$bedCapacity, $roomId]);
        $this->flash('success', 'تم تحديث عدد أسرّة الغرفة بنجاح');
        Response::redirect('/hr/housing');
    }

    // ===== AJAX: الشقق التابعة لموقع معيّن =====
    public function apartmentsForSite(string $siteId): void
    {
        $rows = Database::query(
            "SELECT id, apartment_number FROM housing_apartments WHERE site_id = ? ORDER BY apartment_number",
            [(int)$siteId]
        )->fetchAll();
        Response::json(['data' => $rows]);
    }

    // ===== AJAX: الغرف التابعة لشقة معيّنة مع عدد الأسرّة المتاحة =====
    public function roomsForApartment(string $apartmentId): void
    {
        // عند التعديل، يُستثنى التسكين الحالي نفسه من حساب الإشغال حتى لا يظهر الغرفة مكتملة لمجرد وجود هذا التسكين فيها
        $excludeAssignmentId = (int)($_GET['exclude_id'] ?? 0);
        $rows = Database::query(
            "SELECT r.id, r.room_number, r.bed_capacity,
                    (SELECT COUNT(*) FROM employee_housing_assignments ea WHERE ea.room_id = r.id AND ea.status = 'active' AND ea.id != ?) AS occupied_beds
             FROM housing_rooms r WHERE r.apartment_id = ? ORDER BY r.room_number",
            [$excludeAssignmentId, (int)$apartmentId]
        )->fetchAll();
        foreach ($rows as &$r) {
            $r['available_beds'] = max(0, (int)$r['bed_capacity'] - (int)$r['occupied_beds']);
        }
        Response::json(['data' => $rows]);
    }

    // ===== تسكين موظف في موقع/شقة/غرفة =====
    public function assignEmployee(): void
    {
        $employeeId = (int)$this->input('employee_id', 0);
        $siteId = (int)$this->input('site_id', 0);
        $apartmentId = (int)$this->input('apartment_id', 0) ?: null;
        $roomId = (int)$this->input('room_id', 0) ?: null;
        $assignedDate = (string)$this->input('assigned_date', date('Y-m-d'));
        $deductionInput = $this->input('deduction_amount', '');

        if (!$employeeId || !$siteId) {
            $this->flash('danger', 'الموظف والموقع مطلوبان');
            Response::redirect('/hr/housing');
            return;
        }

        if ($roomId) {
            $room = Database::query("SELECT bed_capacity FROM housing_rooms WHERE id = ?", [$roomId])->fetch();
            $occupied = (int)Database::query(
                "SELECT COUNT(*) FROM employee_housing_assignments WHERE room_id = ? AND status = 'active'",
                [$roomId]
            )->fetchColumn();
            if (!$room || $occupied >= (int)$room['bed_capacity']) {
                $this->flash('danger', 'لا يوجد سرير متاح في هذه الغرفة');
                Response::redirect('/hr/housing');
                return;
            }
        }

        // مبلغ الاستقطاع: القيمة المدخلة يدوياً إن وجدت، وإلاّ مبلغ الموقع الافتراضي
        $siteRow = Database::query("SELECT site_key, monthly_deduction FROM housing_sites WHERE id = ?", [$siteId])->fetch();
        $deductionAmount = ($deductionInput !== '' && $deductionInput !== null)
            ? (float)$deductionInput
            : (float)($siteRow['monthly_deduction'] ?? 0);

        Database::beginTransaction();
        try {
            // إنهاء أي تسكين نشط سابق لنفس الموظف قبل التسكين الجديد
            Database::query(
                "UPDATE employee_housing_assignments SET status = 'ended', ended_date = ? WHERE employee_id = ? AND status = 'active'",
                [$assignedDate, $employeeId]
            );
            Database::query(
                "INSERT INTO employee_housing_assignments (employee_id, site_id, apartment_id, room_id, deduction_amount, assigned_date, status, notes, created_by)
                 VALUES (?,?,?,?,?,?,'active',?,?)",
                [$employeeId, $siteId, $apartmentId, $roomId, $deductionAmount, $assignedDate, (string)$this->input('notes', '') ?: null, (int)(Auth::id() ?? 0)]
            );
            // مزامنة نوع السكن ومبلغ الاستقطاع الشهري في ملف الموظف مع التسكين الجديد
            if ($siteRow && $siteRow['site_key']) {
                Database::query(
                    "UPDATE employees SET housing_type = ?, housing_monthly_deduction = ? WHERE id = ?",
                    [$siteRow['site_key'], $deductionAmount, $employeeId]
                );
            }
            Database::commit();
            $this->flash('success', 'تم تسكين الموظف بنجاح وتحديث استقطاع السكن في ملفه');
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'تعذّر التسكين: ' . $e->getMessage());
        }
        Response::redirect('/hr/housing');
    }

    // ===== تنزيل نموذج Excel لرفع هيكلية السكن (شقق/غرف بدون تسكين موظفين) =====
    public function structureImportTemplate(): void
    {
        ImportHelper::downloadTemplate(
            ['اسم السكن', 'رقم الشقة', 'رقم الغرفة', 'عدد الأسرة'],
            'نموذج_استيراد_هيكلية_السكن.xlsx',
            ['سكن اليرموك', '1', '101', '4']
        );
    }

    // ===== استيراد جماعي لهيكلية السكن: إنشاء/تحديث الشقق والغرف حسب اسم السكن دون تسكين أي موظف =====
    public function importStructure(): void
    {
        if (empty($_FILES['structure_file']['tmp_name']) || $_FILES['structure_file']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger', 'يرجى اختيار ملف Excel صالح');
            Response::redirect('/hr/housing');
            return;
        }

        $read = ImportHelper::readFile($_FILES['structure_file']['tmp_name']);
        if ($read['errors']) {
            ImportHelper::flashErrors($read['errors']);
            Response::redirect('/hr/housing');
            return;
        }

        $sites = Database::query("SELECT id, name FROM housing_sites")->fetchAll();
        $siteByName = [];
        foreach ($sites as $s) { $siteByName[trim($s['name'])] = $s; }

        $errors = [];
        $apartmentsCreated = 0;
        $roomsCreated = 0;
        $roomsUpdated = 0;

        foreach ($read['rows'] as $idx => $row) {
            $line = $idx + 2;
            $siteName = trim((string)($row['اسم السكن'] ?? ''));
            $apartmentNumber = trim((string)($row['رقم الشقة'] ?? ''));
            $roomNumber = trim((string)($row['رقم الغرفة'] ?? ''));
            $bedCapacity = $row['عدد الأسرة'] ?? '';

            if ($siteName === '' || $apartmentNumber === '' || $roomNumber === '') {
                $errors[] = "الصف $line: اسم السكن ورقم الشقة ورقم الغرفة مطلوبة";
                continue;
            }
            if ($bedCapacity === '' || $bedCapacity === null || !is_numeric($bedCapacity) || (int)$bedCapacity < 1) {
                $errors[] = "الصف $line: عدد الأسرة يجب أن يكون رقماً أكبر من صفر";
                continue;
            }

            $site = $siteByName[$siteName] ?? null;
            if (!$site) {
                $errors[] = "الصف $line: اسم السكن '$siteName' غير معروف (تأكد من مطابقة الاسم تماماً لأحد الأنواع المعتمدة)";
                continue;
            }

            $apt = Database::query(
                "SELECT id FROM housing_apartments WHERE site_id = ? AND apartment_number = ?",
                [$site['id'], $apartmentNumber]
            )->fetch();
            if (!$apt) {
                Database::query(
                    "INSERT INTO housing_apartments (site_id, apartment_number) VALUES (?, ?)",
                    [$site['id'], $apartmentNumber]
                );
                $apartmentId = (int)Database::lastInsertId();
                $apartmentsCreated++;
            } else {
                $apartmentId = (int)$apt['id'];
            }

            $room = Database::query(
                "SELECT id, bed_capacity FROM housing_rooms WHERE apartment_id = ? AND room_number = ?",
                [$apartmentId, $roomNumber]
            )->fetch();
            if (!$room) {
                Database::query(
                    "INSERT INTO housing_rooms (apartment_id, room_number, bed_capacity) VALUES (?, ?, ?)",
                    [$apartmentId, $roomNumber, (int)$bedCapacity]
                );
                $roomsCreated++;
            } else {
                Database::query("UPDATE housing_rooms SET bed_capacity = ? WHERE id = ?", [(int)$bedCapacity, $room['id']]);
                $roomsUpdated++;
            }
        }

        if ($errors) {
            ImportHelper::flashErrors($errors);
        }
        $this->flash(
            $errors ? 'danger' : 'success',
            "تم استيراد هيكلية السكن: $apartmentsCreated شقة جديدة، $roomsCreated غرفة جديدة، $roomsUpdated غرفة محدَّثة (من أصل " . count($read['rows']) . ' صف)'
        );
        Response::redirect('/hr/housing');
    }

    // ===== تنزيل نموذج Excel للاستيراد الجماعي للسكن =====
    public function importTemplate(): void
    {
        ImportHelper::downloadTemplate(
            ['كود الموظف', 'اسم الموظف', 'السكن', 'رقم الشقة', 'رقم الغرفة', 'مبلغ الاستقطاع'],
            'نموذج_استيراد_السكن.xlsx',
            ['EMP001', 'اسم الموظف (للمرجعية فقط)', 'سكن اليرموك', '1', '101', '50000']
        );
    }

    // ===== استيراد جماعي للسكن: السكن ورقم الشقة ورقم الغرفة ومبلغ الاستقطاع =====
    public function import(): void
    {
        if (empty($_FILES['import_file']['tmp_name']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger', 'يرجى اختيار ملف Excel صالح');
            Response::redirect('/hr/housing');
            return;
        }

        $read = ImportHelper::readFile($_FILES['import_file']['tmp_name']);
        if ($read['errors']) {
            ImportHelper::flashErrors($read['errors']);
            Response::redirect('/hr/housing');
            return;
        }

        $sites = Database::query("SELECT id, site_key, name, monthly_deduction FROM housing_sites")->fetchAll();
        $siteByName = [];
        foreach ($sites as $s) { $siteByName[trim($s['name'])] = $s; }

        $errors = [];
        $successCount = 0;
        $assignedDate = date('Y-m-d');

        foreach ($read['rows'] as $idx => $row) {
            $line = $idx + 2;
            $empCode = trim((string)($row['كود الموظف'] ?? ''));
            $siteName = trim((string)($row['السكن'] ?? ''));
            $apartmentNumber = trim((string)($row['رقم الشقة'] ?? ''));
            $roomNumber = trim((string)($row['رقم الغرفة'] ?? ''));
            $deductionCell = $row['مبلغ الاستقطاع'] ?? '';

            if ($empCode === '' || $siteName === '') {
                $errors[] = "الصف $line: كود الموظف والسكن مطلوبان";
                continue;
            }

            $employee = Database::query("SELECT id FROM employees WHERE emp_code = ?", [$empCode])->fetch();
            if (!$employee) {
                $errors[] = "الصف $line: كود الموظف '$empCode' غير موجود";
                continue;
            }

            $site = $siteByName[$siteName] ?? null;
            if (!$site) {
                $errors[] = "الصف $line: نوع السكن '$siteName' غير معروف (تأكد من مطابقة الاسم تماماً لأحد الأنواع المعتمدة)";
                continue;
            }

            $apartmentId = null;
            $roomId = null;

            if ($apartmentNumber !== '') {
                $apt = Database::query(
                    "SELECT id FROM housing_apartments WHERE site_id = ? AND apartment_number = ?",
                    [$site['id'], $apartmentNumber]
                )->fetch();
                if (!$apt) {
                    Database::query(
                        "INSERT INTO housing_apartments (site_id, apartment_number) VALUES (?, ?)",
                        [$site['id'], $apartmentNumber]
                    );
                    $apartmentId = (int)Database::lastInsertId();
                } else {
                    $apartmentId = (int)$apt['id'];
                }

                if ($roomNumber !== '') {
                    $room = Database::query(
                        "SELECT id, bed_capacity FROM housing_rooms WHERE apartment_id = ? AND room_number = ?",
                        [$apartmentId, $roomNumber]
                    )->fetch();
                    if (!$room) {
                        Database::query(
                            "INSERT INTO housing_rooms (apartment_id, room_number, bed_capacity) VALUES (?, ?, 1)",
                            [$apartmentId, $roomNumber]
                        );
                        $roomId = (int)Database::lastInsertId();
                    } else {
                        $roomId = (int)$room['id'];
                        // توسيع سعة الغرفة تلقائياً عند الحاجة كي لا يُحجب استيراد بيانات إدارية معتمدة
                        $occupied = (int)Database::query(
                            "SELECT COUNT(*) FROM employee_housing_assignments WHERE room_id = ? AND status = 'active' AND employee_id != ?",
                            [$roomId, $employee['id']]
                        )->fetchColumn();
                        if ($occupied + 1 > (int)$room['bed_capacity']) {
                            Database::query("UPDATE housing_rooms SET bed_capacity = ? WHERE id = ?", [$occupied + 1, $roomId]);
                        }
                    }
                }
            }

            $deductionAmount = ($deductionCell !== '' && $deductionCell !== null && is_numeric($deductionCell))
                ? (float)$deductionCell
                : (float)$site['monthly_deduction'];

            Database::beginTransaction();
            try {
                Database::query(
                    "UPDATE employee_housing_assignments SET status = 'ended', ended_date = ? WHERE employee_id = ? AND status = 'active'",
                    [$assignedDate, $employee['id']]
                );
                Database::query(
                    "INSERT INTO employee_housing_assignments (employee_id, site_id, apartment_id, room_id, deduction_amount, assigned_date, status, created_by)
                     VALUES (?,?,?,?,?,?,'active',?)",
                    [$employee['id'], $site['id'], $apartmentId, $roomId, $deductionAmount, $assignedDate, (int)(Auth::id() ?? 0)]
                );
                Database::query(
                    "UPDATE employees SET housing_type = ?, housing_monthly_deduction = ? WHERE id = ?",
                    [$site['site_key'], $deductionAmount, $employee['id']]
                );
                Database::commit();
                $successCount++;
            } catch (\Throwable $e) {
                Database::rollBack();
                $errors[] = "الصف $line: تعذّر التسكين (" . $e->getMessage() . ")";
            }
        }

        if ($errors) {
            ImportHelper::flashErrors($errors);
        }
        $this->flash($errors ? 'danger' : 'success', "تم تسكين $successCount موظف بنجاح من أصل " . count($read['rows']) . ' صف');
        Response::redirect('/hr/housing');
    }

    // ===== إنهاء تسكين موظف =====
    // ===== منطق مشترك: إنهاء تسكين نشط واحد ومزامنة ملف الموظف (يُستخدم من الإنهاء المفرد والجماعي) =====
    private function endAssignmentInternal(int $id): bool
    {
        $assignment = Database::query("SELECT employee_id FROM employee_housing_assignments WHERE id = ? AND status = 'active'", [$id])->fetch();
        if (!$assignment) {
            return false;
        }
        Database::query(
            "UPDATE employee_housing_assignments SET status = 'ended', ended_date = ? WHERE id = ? AND status = 'active'",
            [date('Y-m-d'), $id]
        );
        // مزامنة ملف الموظف: إنهاء التسكين يُعيده إلى حالة سكن خارجي ويصفر استقطاع السكن
        Database::query("UPDATE employees SET housing_type = NULL, housing_monthly_deduction = 0 WHERE id = ?", [(int)$assignment['employee_id']]);
        return true;
    }

    public function endAssignment(string $id): void
    {
        $ok = $this->endAssignmentInternal((int)$id);
        $this->flash($ok ? 'success' : 'danger', $ok ? 'تم إنهاء تسكين الموظف وتحديث ملفه' : 'التسكين غير موجود أو منتهٍ مسبقاً');
        Response::redirect('/hr/housing');
    }

    // ===== حذف/إنهاء تسكين جماعي لعدة موظفين محددين دفعة واحدة =====
    public function bulkEndAssignments(): void
    {
        $ids = (array)$this->input('ids', []);
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            $this->flash('danger', 'لم يتم تحديد أي موظف لحذف/إنهاء تسكينه');
            Response::redirect('/hr/housing');
            return;
        }
        $count = 0;
        foreach ($ids as $id) {
            if ($this->endAssignmentInternal($id)) {
                $count++;
            }
        }
        $this->flash(
            $count ? 'success' : 'danger',
            $count ? "تم حذف/إنهاء تسكين $count موظف بنجاح وتحديث ملفاتهم" : 'تعذّر حذف أي تسكين (ربما تم إنهاؤه مسبقاً)'
        );
        Response::redirect('/hr/housing');
    }

    // ===== تعديل تسكين قائم (الموقع/الشقة/الغرفة/مبلغ الاستقطاع) =====
    public function updateAssignment(string $id): void
    {
        $assignmentId = (int)$id;
        $assignment = Database::query("SELECT * FROM employee_housing_assignments WHERE id = ?", [$assignmentId])->fetch();
        if (!$assignment) {
            $this->flash('danger', 'التسكين غير موجود');
            Response::redirect('/hr/housing');
            return;
        }

        $siteId = (int)$this->input('site_id', 0);
        $apartmentId = (int)$this->input('apartment_id', 0) ?: null;
        $roomId = (int)$this->input('room_id', 0) ?: null;
        $assignedDate = (string)$this->input('assigned_date', $assignment['assigned_date']);
        $deductionInput = $this->input('deduction_amount', '');

        if (!$siteId) {
            $this->flash('danger', 'الموقع مطلوب');
            Response::redirect('/hr/housing');
            return;
        }

        if ($roomId) {
            $room = Database::query("SELECT bed_capacity FROM housing_rooms WHERE id = ?", [$roomId])->fetch();
            $occupied = (int)Database::query(
                "SELECT COUNT(*) FROM employee_housing_assignments WHERE room_id = ? AND status = 'active' AND id != ?",
                [$roomId, $assignmentId]
            )->fetchColumn();
            if (!$room || $occupied >= (int)$room['bed_capacity']) {
                $this->flash('danger', 'لا يوجد سرير متاح في هذه الغرفة');
                Response::redirect('/hr/housing');
                return;
            }
        }

        $siteRow = Database::query("SELECT site_key, monthly_deduction FROM housing_sites WHERE id = ?", [$siteId])->fetch();
        $deductionAmount = ($deductionInput !== '' && $deductionInput !== null)
            ? (float)$deductionInput
            : (float)($siteRow['monthly_deduction'] ?? 0);

        Database::beginTransaction();
        try {
            Database::query(
                "UPDATE employee_housing_assignments SET site_id = ?, apartment_id = ?, room_id = ?, deduction_amount = ?, assigned_date = ?, notes = ? WHERE id = ?",
                [$siteId, $apartmentId, $roomId, $deductionAmount, $assignedDate, (string)$this->input('notes', '') ?: null, $assignmentId]
            );
            if ($siteRow && $siteRow['site_key'] && $assignment['status'] === 'active') {
                Database::query(
                    "UPDATE employees SET housing_type = ?, housing_monthly_deduction = ? WHERE id = ?",
                    [$siteRow['site_key'], $deductionAmount, (int)$assignment['employee_id']]
                );
            }
            Database::commit();
            $this->flash('success', 'تم تحديث بيانات التسكين بنجاح');
        } catch (\Throwable $e) {
            Database::rollBack();
            $this->flash('danger', 'تعذّر التحديث: ' . $e->getMessage());
        }
        Response::redirect('/hr/housing');
    }

    // ===== تصدير قائمة الموظفين المسكّنين حالياً إلى Excel =====
    public function exportAssignments(): void
    {
        $rows = Database::query(
            "SELECT ea.*, e.full_name, e.emp_code,
                    COALESCE(d.name, e.department) AS department_name,
                    COALESCE(j.title, e.position) AS job_title,
                    s.name AS site_name, ap.apartment_number, r.room_number
             FROM employee_housing_assignments ea
             JOIN employees e ON e.id = ea.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN jobs j ON j.id = e.job_id
             JOIN housing_sites s ON s.id = ea.site_id
             LEFT JOIN housing_apartments ap ON ap.id = ea.apartment_id
             LEFT JOIN housing_rooms r ON r.id = ea.room_id
             WHERE ea.status = 'active'
             ORDER BY s.sort_order, ap.apartment_number, r.room_number, e.full_name"
        )->fetchAll();

        $headers = ['كود الموظف', 'اسم الموظف', 'القسم', 'الوظيفة', 'السكن', 'رقم الشقة', 'رقم الغرفة', 'مبلغ الاستقطاع', 'تاريخ التسكين'];
        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                $r['emp_code'], $r['full_name'], $r['department_name'] ?? '-', $r['job_title'] ?? '-',
                $r['site_name'], $r['apartment_number'] ?? '-', $r['room_number'] ?? '-',
                $r['deduction_amount'], $r['assigned_date'],
            ];
        }
        ImportHelper::downloadTemplateMulti($headers, 'housing_assignments_' . date('Y-m-d') . '.xlsx', $data);
    }
}
