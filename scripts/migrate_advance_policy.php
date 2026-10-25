<?php
/** Run through the normal application bootstrap (CLI only). Additive, repeatable migration. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
use App\Core\Database;
$db = Database::connect();
foreach (['salary_advances'=>['accountant_approved_at'=>'DATETIME NULL','accountant_approved_by'=>'INT NULL'], 'requests'=>['advance_policy_period'=>'VARCHAR(7) NULL']] as $table=>$columns) {
    foreach ($columns as $column=>$type) {
        if (!$db->query("SHOW COLUMNS FROM `$table` LIKE '$column'")->fetch()) $db->exec("ALTER TABLE `$table` ADD COLUMN `$column` $type");
    }
}
$db->beginTransaction();
try {
    $role = fn(string $name) => (int) Database::query('SELECT id FROM roles WHERE name=?', [$name])->fetchColumn();
    $hr = $role('مدير قسم الموارد البشرية'); $finance = $role('مدير مالي');
    if (!$hr || !$finance) throw new RuntimeException('يلزم تحديد دور الموارد البشرية والمدير المالي قبل تفعيل المسارات');
    $types = [
        'hr_advance'=>['طلب السلفة الشهرية', [['direct_manager',null,'المدير المباشر'],['role',$hr,'الموارد البشرية']]],
        'hr_exceptional_advance'=>['طلب السلفة الشهرية الاستثنائية', [['direct_manager',null,'المدير المباشر'],['manager_of_manager',null,'مدير المدير المباشر'],['role',$finance,'المدير المالي']]],
        'hr_long_advance'=>['طلب سلفة طويلة الأجل', [['direct_manager',null,'المدير المباشر'],['manager_of_manager',null,'مدير المدير المباشر'],['role',$hr,'الموارد البشرية']]],
    ];
    foreach ($types as $code=>[$name,$steps]) {
        $fields = [
            ['name'=>'employee_id','type'=>'employee','label'=>'الموظف','required'=>true],
            ['name'=>'amount','type'=>'number','label'=>'المبلغ','required'=>true],
            ['name'=>'reason','type'=>'textarea','label'=>'السبب','required'=>$code!=='hr_advance'],
        ];
        if ($code==='hr_long_advance') $fields[]=['name'=>'installments','type'=>'number','label'=>'عدد الأقساط الشهرية','required'=>true];
        Database::query("INSERT INTO approval_templates (code,name,description,icon,fields_json,amount_field,is_active)
            VALUES (?,?,?,'💰',?,'amount',1) ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description),fields_json=VALUES(fields_json),is_active=1",
            [$code,$name,'سياسة السلف: موافقات ثم اعتماد المحاسب ثم التسليم',json_encode($fields,JSON_UNESCAPED_UNICODE)]);
        $tid=(int)Database::query('SELECT id FROM approval_templates WHERE code=?',[$code])->fetchColumn();
        if ($code!=='hr_advance') {
            $hrRoles=Database::query("SELECT r.id FROM roles r WHERE r.name IN ('مدير النظام','مدير قسم الموارد البشرية') OR EXISTS
                (SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=r.id AND p.code='hr.manage')")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($hrRoles as $rid) Database::query('INSERT INTO template_allowed_roles (template_id,role_id,can_create,can_view_all) VALUES (?,?,1,1) ON DUPLICATE KEY UPDATE can_create=1',[$tid,$rid]);
        }
        $definitionCode='advance_policy_v1_'.$code;
        $did=(int)Database::query('SELECT id FROM workflow_definitions WHERE code=?',[$definitionCode])->fetchColumn();
        if ($did) continue;
        Database::query('INSERT INTO workflow_definitions (code,name,description,request_type,template_id,priority,is_active) VALUES (?,?,?,?,?,1,1)',[$definitionCode,$name,'سياسة السلف المعتمدة 2026-10-26',$code,$tid]);
        $did=(int)$db->lastInsertId();
        Database::query("INSERT INTO workflow_versions (definition_id,version_no,status,published_at,notes) VALUES (?,1,'published',NOW(),'مسارات سياسة السلف المعتمدة')",[$did]);
        $vid=(int)$db->lastInsertId();$previous=null;
        $nodes=[['start','start','بدء الطلب',[]]];
        foreach ($steps as $i=>[$kind,$rid,$label]) {
            $approver=['type'=>$kind];if($rid)$approver['role_id']=$rid;
            $nodes[]=['approve_'.($i+1),'approval',$label,['approver'=>$approver,'approval_mode'=>'any','required_approver'=>true,'on_reject'=>'final']];
        }
        $nodes[]=['end','end','اكتملت الموافقات — بانتظار المحاسب',['status'=>'approved']];
        foreach ($nodes as $i=>[$key,$type,$title,$config]) {
            Database::query('INSERT INTO workflow_nodes (version_id,node_key,node_type,title,config_json,pos_x,pos_y) VALUES (?,?,?,?,?,240,?)',[$vid,$key,$type,$title,json_encode($config,JSON_UNESCAPED_UNICODE),$i*160]);
            $nid=(int)$db->lastInsertId();
            if($previous)Database::query("INSERT INTO workflow_edges (version_id,from_node_id,to_node_id,handle) VALUES (?,?,?,'default')",[$vid,$previous,$nid]);
            $previous=$nid;
        }
    }
    $db->commit();echo "Advance policy schema and three approval routes ready.\n";
} catch (Throwable $e) { $db->rollBack(); throw $e; }
