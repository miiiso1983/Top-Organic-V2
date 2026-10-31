<?php
/** Additive and repeatable; bootstrap the application before running from CLI. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
use App\Core\Database;
$db = Database::connect();
$db->exec("CREATE TABLE IF NOT EXISTS attendance_correction_plans (
 request_id INT PRIMARY KEY, employee_id INT NOT NULL, plan_json JSON NOT NULL,
 applied_at DATETIME NULL, applied_by INT NULL, last_error VARCHAR(1000) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->exec("CREATE TABLE IF NOT EXISTS attendance_corrections (
 id INT AUTO_INCREMENT PRIMARY KEY, request_id INT NOT NULL, attendance_id INT NOT NULL,
 employee_id INT NOT NULL, check_type VARCHAR(3) NOT NULL, original_at DATETIME NULL,
 corrected_at DATETIME NOT NULL, original_source VARCHAR(30) NULL,
 approved_by INT NOT NULL, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY request_punch(request_id,check_type), INDEX attendance_history(attendance_id,id),
 INDEX employee_history(employee_id,corrected_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$t=Database::query("SELECT id,fields_json FROM approval_templates WHERE code='hr_missing_attendance'")->fetch();
if($t){
 $fields=json_decode($t['fields_json'],true)?:[];
 $names=array_column($fields,'name');
 $extra=[
  ['name'=>'shift_date','type'=>'date','label'=>'تاريخ بداية الوردية (اختياري؛ يحدد الوردية الليلية)','required'=>false],
  ['name'=>'correction_mode','type'=>'select','label'=>'نوع المعالجة','required'=>false,'options'=>[['value'=>'add','label'=>'إضافة بصمة مفقودة'],['value'=>'correct','label'=>'تصحيح بصمة خاطئة']]],
  ['name'=>'original_in_at','type'=>'datetime','label'=>'وقت الدخول الأصلي عند التصحيح','required'=>false],
  ['name'=>'original_out_at','type'=>'datetime','label'=>'وقت الخروج الأصلي عند التصحيح','required'=>false],
 ];
 foreach($extra as $f)if(!in_array($f['name'],$names,true))$fields[]=$f;
 Database::query('UPDATE approval_templates SET fields_json=? WHERE id=?',[json_encode($fields,JSON_UNESCAPED_UNICODE),$t['id']]);
}
echo "Attendance correction audit and forms ready.\n";
