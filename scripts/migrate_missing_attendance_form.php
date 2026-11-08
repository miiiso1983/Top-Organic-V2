<?php
/** Presentation-only migration; retain all workflow steps, permissions and request history. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
use App\Core\Database;
$template=Database::query("SELECT id,fields_json FROM approval_templates WHERE code='hr_missing_attendance'")->fetch();
if(!$template)throw new RuntimeException('Missing attendance template not found');
$fields=json_decode($template['fields_json'],true,512,JSON_THROW_ON_ERROR);
$fields=array_values(array_filter($fields,fn($field)=>!in_array($field['name']??'', ['correction_mode','original_in_at','original_out_at'],true)));
$updated=json_encode($fields,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
if($updated!==$template['fields_json']){
    $statement=Database::query('UPDATE approval_templates SET fields_json=? WHERE id=? AND fields_json=?',[$updated,$template['id'],$template['fields_json']]);
    if($statement->rowCount()!==1)throw new RuntimeException('Template changed during migration; retry after review');
}
echo "Missing attendance form unified; approval steps and history unchanged.\n";
