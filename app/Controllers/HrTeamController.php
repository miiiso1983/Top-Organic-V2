<?php
namespace App\Controllers;
use App\Core\{Controller,Auth,ApiAuth,Response,HrTeamService};
final class HrTeamController extends Controller
{
    private function service(): HrTeamService { return new HrTeamService((int)(str_starts_with($_SERVER['REQUEST_URI']??'','/api/')?ApiAuth::id():Auth::id())); }
    private function body(): array {
        if(!str_starts_with($_SERVER['REQUEST_URI']??'','/api/') && (empty($_SESSION['hr_team_csrf']) || !hash_equals((string)$_SESSION['hr_team_csrf'],(string)($_SERVER['HTTP_X_CSRF_TOKEN']??''))))throw new \RuntimeException('انتهت صلاحية الصفحة؛ أعد فتحها',403);
        return $this->jsonInput();
    }
    private function run(callable $fn): void {
        try{Response::json(['ok'=>true,'data'=>$fn()]);}
        catch(\RuntimeException $e){Response::json(['ok'=>false,'error'=>$e instanceof \PDOException?'تعذر إكمال العملية؛ يرجى إعادة المحاولة':$e->getMessage()],in_array($e->getCode(),[403,404,422],true)?$e->getCode():422);if($e instanceof \PDOException)error_log((string)$e);}
        catch(\Throwable $e){error_log((string)$e);Response::json(['ok'=>false,'error'=>'تعذر إكمال العملية'],500);}
    }
    public function page(): void {
        $_SESSION['hr_team_csrf']??=bin2hex(random_bytes(32));
        $this->view('hr/team',['title'=>'الموظفون وإجراءات الفريق','csrf'=>$_SESSION['hr_team_csrf']]);
    }
    public function employee(string $id):void{$this->run(fn()=>$this->service()->employeeView((int)$id));}
    public function payroll(string $id,string $pid):void{$this->run(fn()=>$this->service()->payroll((int)$id,(int)$pid));}
    public function employees():void{$this->run(fn()=>$this->service()->employees($_GET));}
    public function cases():void{$this->run(fn()=>$this->service()->cases($_GET));}
    public function detail(string $id):void{$this->run(fn()=>$this->service()->detail((int)$id));}
    public function read(string $id):void{$this->run(function()use($id){$this->body();return $this->service()->detail((int)$id,true);});}
    public function decide(string $id):void{$this->run(function()use($id){$b=$this->body();$this->service()->decide((int)$id,(string)($b['action']??''),(string)($b['notes']??''));return ['message'=>'تم تسجيل القرار'];});}
    public function form(string $id,string $action):void{$this->run(fn()=>$this->service()->form((int)$id,$action));}
    public function perform(string $id,string $action):void{$this->run(fn()=>$this->service()->perform((int)$id,$action,$this->body()));}
    public function archive(string $id):void{$this->run(fn()=>$this->service()->archive((int)$id,$_GET));}
    public function file(string $id):void{
        try{$f=$this->service()->file((int)$id);header('Content-Type: '.$f['mime']);header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');echo $f['image_data'];}catch(\RuntimeException $e){Response::json(['ok'=>false,'error'=>$e->getMessage()],403);}
    }
    public function fileData(string $id):void { $this->run(function()use($id){$f=$this->service()->file((int)$id);return ['mime'=>$f['mime'],'base64'=>base64_encode($f['image_data'])];}); }
    public function export():void{
        $rows=$this->service()->cases($_GET,true)['rows'];
        $headers=['الرقم','الموظف','الكود','النوع','العنوان','التفاصيل','الحالة','تاريخ الإنشاء','تاريخ القراءة','سريان النقل'];
        $book=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$sheet=$book->getActiveSheet();$sheet->setRightToLeft(true);
        $all=[$headers];foreach($rows as $r)$all[]=[$r['id'],$r['full_name'],$r['emp_code'],$r['kind_label'],$r['subject'],$r['body'],$r['status_label'],$r['created_at'],$r['employee_read_at'],$r['effective_at']];
        foreach($all as $i=>$row)foreach($row as $j=>$v)$sheet->setCellValueExplicit(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($j+1).($i+1),(string)$v,\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->getStyle('A1:J1')->getFont()->setBold(true);$sheet->setAutoFilter('A1:J'.count($all));
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="employee-cases.xlsx"');header('Cache-Control: no-store');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save('php://output');
    }
}
