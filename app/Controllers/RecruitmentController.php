<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Response, Auth, Helpers, DocumentUpload};

/**
 * التقديم الوظيفي: استمارة تقديم عامة (بدون تسجيل دخول) + إدارة المتقدمين لكل وظيفة.
 * مبنية على حقول "فورمة التقديم الوظيفي.xlsx" الأصلية.
 */
class RecruitmentController extends Controller
{
    private const MARITAL_OPTIONS = ['أعزب', 'متزوج', 'مطلق', 'أرمل'];

    // ===== الاستمارة العامة (بدون تسجيل دخول) =====

    public function applyForm(): void
    {
        $jobs = Database::query(
            "SELECT j.id, j.title, d.name AS department_name
             FROM jobs j LEFT JOIN departments d ON d.id = j.department_id
             WHERE j.is_active = 1 ORDER BY j.title"
        )->fetchAll();
        $branches = Database::query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name")->fetchAll();
        $this->viewBare('recruitment/apply', [
            'jobs' => $jobs,
            'branches' => $branches,
            'maritalOptions' => self::MARITAL_OPTIONS,
            'error' => $_SESSION['apply_error'] ?? null,
            'old' => $_SESSION['apply_old'] ?? [],
        ]);
        unset($_SESSION['apply_error'], $_SESSION['apply_old']);
    }

    public function store(): void
    {
        $jobId = (int) $this->input('job_id');
        $fullName = trim((string) $this->input('full_name'));
        $phone = trim((string) $this->input('phone'));

        if ($jobId <= 0 || $fullName === '' || $phone === '') {
            $_SESSION['apply_error'] = 'يرجى تعبئة الحقول المطلوبة: الوظيفة المستهدفة، الاسم الثلاثي، رقم الهاتف';
            $_SESSION['apply_old'] = $_POST;
            Response::redirect('/careers/apply');
            return;
        }

        $jobExists = (int) Database::query("SELECT COUNT(*) FROM jobs WHERE id = ? AND is_active = 1", [$jobId])->fetchColumn();
        if (!$jobExists) {
            $_SESSION['apply_error'] = 'الوظيفة المختارة غير متاحة حالياً';
            $_SESSION['apply_old'] = $_POST;
            Response::redirect('/careers/apply');
            return;
        }

        $cvPath = null;
        if (!empty($_FILES['cv_file']['tmp_name']) && $_FILES['cv_file']['error'] === UPLOAD_ERR_OK) {
            try {
                $cvPath = DocumentUpload::fromMultipart('cv_file', 'recruitment_cvs');
            } catch (\RuntimeException $e) {
                $_SESSION['apply_error'] = $e->getMessage();
                $_SESSION['apply_old'] = $_POST;
                Response::redirect('/careers/apply');
                return;
            }
        }

        $experiences = [];
        $companies = (array) ($_POST['exp_company'] ?? []);
        foreach ($companies as $i => $company) {
            $company = trim((string) $company);
            $title = trim((string) ($_POST['exp_title'][$i] ?? ''));
            $duration = trim((string) ($_POST['exp_duration'][$i] ?? ''));
            $lastSalary = trim((string) ($_POST['exp_salary'][$i] ?? ''));
            $leaveReason = trim((string) ($_POST['exp_reason'][$i] ?? ''));
            if ($company === '' && $title === '' && $duration === '' && $lastSalary === '' && $leaveReason === '') {
                continue;
            }
            $experiences[] = [
                'company' => $company, 'title' => $title, 'duration' => $duration,
                'last_salary' => $lastSalary, 'leave_reason' => $leaveReason,
            ];
        }

        $applicationNo = Helpers::docNumber('APP', 'job_applications', 'application_no');

        Database::query(
            "INSERT INTO job_applications
                (application_no, job_id, branch_id, full_name, phone, age, nationality, marital_status, residence,
                 education_major, graduation_year, total_experience_years, expected_salary,
                 has_restaurant_experience, has_relatives_in_company, is_available_now,
                 experiences_json, cv_path, submitted_ip)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $applicationNo, $jobId, $this->input('branch_id') ?: null, $fullName, $phone,
                $this->input('age') ?: null, (string) $this->input('nationality'), (string) $this->input('marital_status') ?: null,
                (string) $this->input('residence'), (string) $this->input('education_major'), (string) $this->input('graduation_year'),
                (string) $this->input('total_experience_years'), $this->input('expected_salary') ?: null,
                $this->input('has_restaurant_experience') ? 1 : 0,
                $this->input('has_relatives_in_company') ? 1 : 0,
                $this->input('is_available_now') ? 1 : 0,
                json_encode($experiences, JSON_UNESCAPED_UNICODE),
                $cvPath,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]
        );

        Response::redirect('/careers/apply/thanks?no=' . urlencode($applicationNo));
    }

    public function thanks(): void
    {
        $this->viewBare('recruitment/thanks', ['applicationNo' => (string) $this->input('no', '')]);
    }

    // ===== إدارة طلبات التوظيف (داخلية) =====

    public function index(): void
    {
        $jobs = Database::query(
            "SELECT j.id, j.title, d.name AS department_name,
                    COUNT(a.id) AS total_count,
                    SUM(a.status = 'new') AS new_count,
                    SUM(a.status = 'shortlisted') AS shortlisted_count,
                    SUM(a.status = 'hired') AS hired_count
             FROM jobs j
             LEFT JOIN departments d ON d.id = j.department_id
             LEFT JOIN job_applications a ON a.job_id = j.id
             GROUP BY j.id
             HAVING total_count > 0
             ORDER BY total_count DESC, j.title"
        )->fetchAll();
        $this->view('recruitment/index', [
            'title' => 'إدارة طلبات التوظيف',
            'jobs' => $jobs,
        ]);
    }

    public function jobApplicants(string $jobId): void
    {
        $job = Database::query("SELECT * FROM jobs WHERE id = ?", [(int) $jobId])->fetch();
        if (!$job) Response::abort(404, 'الوظيفة غير موجودة');
        $applicants = Database::query(
            "SELECT a.*, b.name AS branch_name FROM job_applications a
             LEFT JOIN branches b ON b.id = a.branch_id
             WHERE a.job_id = ? ORDER BY a.created_at DESC",
            [(int) $jobId]
        )->fetchAll();
        $this->view('recruitment/job_applicants', [
            'title' => 'المتقدمون - ' . $job['title'],
            'job' => $job,
            'applicants' => $applicants,
        ]);
    }

    public function show(string $id): void
    {
        $applicant = Database::query(
            "SELECT a.*, j.title AS job_title, b.name AS branch_name FROM job_applications a
             JOIN jobs j ON j.id = a.job_id
             LEFT JOIN branches b ON b.id = a.branch_id
             WHERE a.id = ?",
            [(int) $id]
        )->fetch();
        if (!$applicant) Response::abort(404, 'الطلب غير موجود');
        $experiences = json_decode($applicant['experiences_json'] ?? '[]', true) ?: [];
        $this->view('recruitment/show', [
            'title' => 'طلب توظيف: ' . $applicant['full_name'],
            'applicant' => $applicant,
            'experiences' => $experiences,
        ]);
    }

    public function updateStatus(string $id): void
    {
        $status = (string) $this->input('status', 'new');
        if (!in_array($status, ['new', 'reviewed', 'shortlisted', 'rejected', 'hired'], true)) {
            $status = 'new';
        }
        Database::query(
            "UPDATE job_applications SET status = ?, hr_notes = ? WHERE id = ?",
            [$status, (string) $this->input('hr_notes'), (int) $id]
        );
        $this->flash('success', 'تم تحديث حالة الطلب');
        Response::redirect('/hr/recruitment/applications/' . $id);
    }
}
