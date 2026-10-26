<?php
/** Additive migration. Run from the CLI application bootstrap. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
use App\Core\Database;
$db = Database::connect();
$db->exec("CREATE TABLE IF NOT EXISTS hr_team_mutex (id INT PRIMARY KEY) ENGINE=InnoDB");
$db->exec('INSERT IGNORE INTO hr_team_mutex VALUES (1)');
$db->exec("CREATE TABLE IF NOT EXISTS hr_employee_cases (
 id INT AUTO_INCREMENT PRIMARY KEY, employee_id INT NOT NULL, kind VARCHAR(24) NOT NULL,
 subject VARCHAR(200) NOT NULL, body TEXT NOT NULL, status VARCHAR(24) NOT NULL,
 created_by INT NOT NULL, approver_id INT NULL, new_manager_id INT NULL, old_manager_id INT NULL,
 department_id INT NULL, warning_type VARCHAR(16) NULL, warning_id INT NULL,
 accepted_by INT NULL, accepted_at DATETIME NULL, decided_by INT NULL, decided_at DATETIME NULL,
 employee_read_at DATETIME NULL, effective_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 request_key VARCHAR(80) NOT NULL, UNIQUE KEY request_once(created_by,request_key),
 INDEX employee_history(employee_id,created_at), INDEX case_queue(kind,status,approver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->exec("CREATE TABLE IF NOT EXISTS hr_case_events (
 id INT AUTO_INCREMENT PRIMARY KEY, case_id INT NOT NULL, actor_id INT NOT NULL,
 action VARCHAR(32) NOT NULL, notes TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX case_history(case_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->exec("CREATE TABLE IF NOT EXISTS hr_case_files (
 id INT AUTO_INCREMENT PRIMARY KEY, case_id INT NOT NULL, mime VARCHAR(40) NOT NULL,
 image_data MEDIUMBLOB NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX case_files(case_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->exec("CREATE TABLE IF NOT EXISTS hr_transfer_approvers (
 request_id INT NOT NULL, resolver_key VARCHAR(80) NOT NULL, user_ids JSON NOT NULL,
 PRIMARY KEY(request_id,resolver_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->exec("CREATE TABLE IF NOT EXISTS hr_team_submissions (
 created_by INT NOT NULL, request_key VARCHAR(80) NOT NULL, employee_id INT NOT NULL,
 action VARCHAR(60) NOT NULL, result_json JSON NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(created_by,request_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->beginTransaction();
try {
foreach (['hr.team.all'=>'إدارة سجلات وإجراءات جميع الموظفين','hr.team.transfer.approve'=>'اعتماد نقل الموظفين بعد قبول المدير الجديد'] as $code=>$name) {
 $inserted=Database::query("INSERT IGNORE INTO permissions(code,name,module) VALUES (?,?,'hr')",[$code,$name])->rowCount();
 if(!$inserted)continue;
 Database::query("INSERT IGNORE INTO role_permissions(role_id,permission_id)
 SELECT DISTINCT r.id,p.id FROM roles r JOIN permissions p ON p.code=?
 WHERE r.name IN ('مدير النظام','مدير قسم الموارد البشرية') OR EXISTS
 (SELECT 1 FROM role_permissions rp JOIN permissions op ON op.id=rp.permission_id WHERE rp.role_id=r.id AND op.code='hr.manage')",[$code]);
}
foreach (\App\Core\HrTeamService::KINDS as $kind=>$label) {
    $inserted=Database::query("INSERT IGNORE INTO permissions(code,name,module) VALUES (?,?,'hr')",['hr.team.'.$kind,$label])->rowCount();
    if(!$inserted)continue;
    Database::query("INSERT IGNORE INTO role_permissions(role_id,permission_id)
        SELECT DISTINCT r.id,p.id FROM roles r JOIN permissions p ON p.code=? WHERE
        r.name IN ('مدير النظام','مدير قسم الموارد البشرية') OR r.level>=60 OR EXISTS
        (SELECT 1 FROM role_permissions rp JOIN permissions op ON op.id=rp.permission_id WHERE rp.role_id=r.id AND op.code='hr.team.all') OR EXISTS
        (SELECT 1 FROM users u JOIN employees m ON m.user_id=u.id JOIN employees e ON e.manager_id=m.id WHERE u.role_id=r.id)",['hr.team.'.$kind]);
}
// Import existing warnings into the dedicated report without notifying employees again.
Database::query("INSERT IGNORE INTO hr_employee_cases(employee_id,kind,subject,body,status,created_by,warning_type,warning_id,request_key,created_at)
 SELECT w.employee_id,'warning','إنذار مسجل سابقاً',w.reason,'issued',COALESCE(u.id,(SELECT MIN(id) FROM users)),w.warning_type,w.id,CONCAT('legacy_warning_',w.id),COALESCE(w.created_at,NOW())
 FROM employee_warnings w LEFT JOIN users u ON u.id=w.created_by
 WHERE NOT EXISTS (SELECT 1 FROM hr_employee_cases c WHERE c.warning_id=w.id)");
$db->commit();
echo "HR team schema ready.\n";
} catch (Throwable $e) { $db->rollBack(); throw $e; }
