<?php
// Same isolated temporary-table fixture as the activity report; never use with writable production tables.
require __DIR__.'/SystemActivityIntegrationTest.php';
use App\Core\{Database,SystemActivityReport as Report,SystemActivityPresentation as Presentation,SystemActivityExcel as Excel};
check(Report::page(array_merge($f,['q'=>'تسجيل حضور']))['total']===2,'Arabic operation search did not match stored technical code');
check(Presentation::action('ApiController.pushSubscribe')==='تفعيل استقبال إشعارات الجهاز','Push translation');
check(Presentation::value('resigned','status')==='مستقيل','Status translation');
check(Presentation::value('active','full_name')==='active','User-entered name changed');
check(str_contains(Presentation::notes('{"shift_id":12}'),'الوردية رقم 12'),'Structured details not translated');
check(!str_contains(Presentation::notes('{"password":"do-not-disclose"}'),'do-not-disclose'),'Secret details leaked');
check(str_contains(Presentation::notes('created:12, updated:3'),'عدد السجلات المضافة: 12'),'Import details translation');
Database::query('UPDATE audit_log SET details=? WHERE id=1',['=HYPERLINK("https://invalid.example","danger")']);
$selected=array_merge($f,['source'=>'operations','entity'=>'departments','entity_id'=>7,'user_id'=>1,'branch_id'=>1,'action'=>'تعديل قسم','per_page'=>25,'page'=>2]);
$path=Excel::build(Report::exportRows($selected),$selected);
$book=\PhpOffice\PhpSpreadsheet\IOFactory::load($path);$sheet=$book->getActiveSheet();
check($sheet->getHighestRow()===34,'Export uses current page instead of all 31 matching rows');
check($sheet->getRightToLeft() && $sheet->getFreezePane()==='A4','Arabic view/frozen header missing');
check((string)$sheet->getCell('F4')->getValue()==='تعديل قسم','Export operation mismatch');
check(str_contains((string)$sheet->getCell('A2')->getValue(),'مستخدم اختبار'),'Export does not describe active filters');
$found=false;foreach(range(4,34) as $r)if((string)$sheet->getCell('A'.$r)->getValue()==='1'){$found=true;check($sheet->getCell('K'.$r)->getDataType()!=='f' && str_starts_with((string)$sheet->getCell('K'.$r)->getValue(),'=HYPERLINK'),'Formula injection / text mismatch');}
check($found,'Missing matching row');$book->disconnectWorksheets();unset($book);unlink($path);
check(!$db->inTransaction() && $db->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY),'Export did not restore database state');
$none=array_merge($f,['user_id'=>998]);$empty=Excel::build(Report::exportRows($none),$none);$book=\PhpOffice\PhpSpreadsheet\IOFactory::load($empty);check($book->getActiveSheet()->getHighestRow()===3,'Empty export missing headers or includes records');$book->disconnectWorksheets();unset($book);unlink($empty);
// More than the former common 5,000 cap; selected dates/actions still exclude unrelated rows.
$stmt=$db->prepare("INSERT INTO audit_log (user_id,action,entity,entity_id,details,created_at) VALUES (1,'تعديل قسم','departments',7,?,'2026-11-11 10:00:00')");
foreach(range(1,5100) as $n)$stmt->execute(['بيان تجريبي '.$n]);
$total=Report::page($selected)['total'];$path=Excel::build(Report::exportRows($selected),$selected);$zip=new ZipArchive();$zip->open($path);$xml=simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));check(count($xml->sheetData->row)===$total+3 && $total===5131,'Large export truncated');check($xml->xpath('//*[local-name()="f"]')===[],'Export contains formula cells');$zip->close();unlink($path);
// Very long details are continued instead of lost to Excel cell limits.
$example=Report::page($f)['rows'][0];$example['source']='operations';$example['method']='';$example['ip']='';$example['device']='';$example['details']=str_repeat('أ',40000);$long=Excel::build([$example],$f);$zip=new ZipArchive();$zip->open($long);$xml=simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));$joined='';foreach($xml->sheetData->row as $rr)if((int)$rr['r']>=4)foreach($rr->c as $c)if(str_starts_with((string)$c['r'],'K'))$joined.=(string)$c->is->t;check($joined===str_repeat('أ',40000),'Long detail lost or altered');$zip->close();unlink($long);
echo "PASS: Arabic labels/search/details, identical filtered export, all 5,131 rows beyond pagination, Excel reader round-trip, formula safety, empty export, long text, connection restored.\n";
