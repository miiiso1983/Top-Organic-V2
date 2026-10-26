<?php
namespace App\Core;

/** One authorization and state-transition boundary shared by browser and both mobile platforms. */
final class HrTeamService
{
    public const KINDS = ['notice'=>'تنبيه وتوجيه','notice_request'=>'طلب تنبيه','warning'=>'طلب إنذار','photo_loading'=>'تحميلات مصورة','transfer'=>'نقل إلى مدير آخر'];
    private int $uid;
    private array $perms;
    private array $user;
    private int $own;
    public function __construct(int $userId) {
        $this->user = Database::query('SELECT u.id,u.role_id,r.name role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? AND u.is_active=1',[$userId])->fetch() ?: [];
        if (!$this->user) throw new \RuntimeException('غير مصرح',403);
        $this->uid=$userId;
        $this->perms=Auth::effectivePermissions((int)$this->user['role_id'],$this->user['role_name']);
        $this->own=(int)Database::query('SELECT id FROM employees WHERE user_id=?',[$userId])->fetchColumn();
    }
    public function can(string $p): bool { return $this->user['role_name']==='مدير النظام' || in_array($p,$this->perms,true); }
    private function all(): bool { return $this->can('hr.team.all'); }
    public function employee(int $id): array {
        $e=Database::query('SELECT e.id,e.user_id,e.manager_id,e.department_id,e.full_name,e.emp_code,e.status,e.file_status,d.name department,j.title job_title FROM employees e LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN jobs j ON j.id=e.job_id WHERE e.id=?',[$id])->fetch();
        if (!$e) throw new \RuntimeException('الموظف غير موجود',404);
        return $e;
    }
    private function direct(array $e): bool { return $this->own>0 && (int)$e['manager_id']===$this->own && (int)$e['id']!==$this->own; }
    private function manage(array $e): bool { return $this->all() || $this->direct($e); }
    private function financial(array $e): bool { return (int)$e['user_id']===$this->uid || $this->can('hr.payslip.view'); }
    private function assertView(array $e): void { if (!$this->manage($e) && (int)$e['user_id']!==$this->uid && !$this->can('hr.payslip.view')) throw new \RuntimeException('ليس لديك صلاحية عرض هذا الموظف',403); }
    private function scope(string $alias='e'): array {
        if ($this->all() || $this->can('hr.payslip.view')) return ['1=1',[]];
        return ["($alias.user_id=? OR $alias.manager_id=?)",[$this->uid,$this->own?:-1]];
    }
    public function employees(array $q): array {
        [$where,$p]=$this->scope();
        if (trim($q['search']??'')!=='') { $where.=' AND (e.full_name LIKE ? OR e.emp_code LIKE ?)'; $p[]='%'.trim($q['search']).'%'; $p[]=end($p); }
        $page=max(1,(int)($q['page']??1)); $size=self::pageSize($q);
        $total=(int)Database::query("SELECT COUNT(*) FROM employees e WHERE $where",$p)->fetchColumn();
        $rows=Database::query("SELECT e.id,e.full_name,e.emp_code,e.manager_id,e.user_id,e.status,e.file_status,e.department_id,d.name department,j.title job_title FROM employees e LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN jobs j ON j.id=e.job_id WHERE $where ORDER BY e.full_name,e.id LIMIT ? OFFSET ?",[...$p,$size,($page-1)*$size])->fetchAll();
        foreach($rows as &$e) $e['actions']=$this->actions($e);
        return ['rows'=>$rows,'total'=>$total,'page'=>$page,'page_size'=>$size];
    }
    public static function pageSize(array $q): int { $s=(int)($q['page_size']??25);return in_array($s,[10,25,50,100],true)?$s:25; }
    public function actions(array $e): array {
        $a=['archive'=>'أرشيف الموظف'];
        if ($this->financial($e)) $a['payslip']='كشف الراتب';
        if (!$this->manage($e)) return $a;
        if ($e['status']!=='active' || $e['file_status']!=='open') return $a;
        foreach(self::KINDS as $key=>$label)if($this->can('hr.team.'.$key))$a[$key]=$label;
        if ($this->can('hr.evaluation.manage') || $this->can('mobile.evaluations')) $a['evaluation']='تقييم الأداء';
        if ($this->can('hr.manage') || $this->can('mobile.attendance') || $this->can('hr.view')) $a['shift']='تعيين وردية';
        foreach(['hr_leave'=>'إجازة','hr_missing_attendance'=>'نسيان بصمة','deduction_request'=>'طلب خصم','hr_overtime'=>'طلب عمل إضافي','suspension_request'=>'طلب إيقاف دوام','hr_exceptional_advance'=>'سلفة استثنائية','hr_long_advance'=>'سلفة طويلة الأجل'] as $code=>$label){
            $t=WorkflowTemplateService::getTemplateByCode($code);
            if(!$t || empty($t['is_active']))continue;
            if(in_array($code,['hr_exceptional_advance','hr_long_advance'],true) && !AdvancePolicyService::canManageHr($this->uid))continue;
            if(WorkflowEngine::canCreate((int)$t['id'],(int)$this->user['role_id']) || ($code==='hr_missing_attendance' && ($this->can('mobile.evaluations')||$this->can('hr.evaluation.manage'))))$a[$code]=$label;
        }
        return $a;
    }
    private function assertAction(int $id,string $action): array {
        $e=$this->employee($id);
        $this->assertView($e);
        if(!isset($this->actions($e)[$action]))throw new \RuntimeException('هذا الإجراء غير مسموح لهذا الموظف',403);
        return $e;
    }
    private function hrUsers(): array {
        return array_map('intval',Database::query("SELECT DISTINCT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE u.is_active=1 AND (r.name='مدير النظام' OR EXISTS (SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=u.role_id AND p.code='hr.team.transfer.approve'))")->fetchAll(\PDO::FETCH_COLUMN));
    }
    private function managerOfManager(array $e): int {
        return (int)Database::query('SELECT u.id FROM employees m JOIN employees mm ON mm.id=m.manager_id JOIN users u ON u.id=mm.user_id AND u.is_active=1 WHERE m.id=?',[(int)$e['manager_id']])->fetchColumn();
    }
    private function event(int $id,string $action,string $notes=''): void { Database::query('INSERT INTO hr_case_events(case_id,actor_id,action,notes) VALUES (?,?,?,?)',[$id,$this->uid,$action,$notes]); }
    private function notify(array $uids,int $id,string $message): void {
        foreach(array_unique(array_filter($uids)) as $uid) Helpers::notify((int)$uid,'إجراءات الموظفين',$message,'/hr/team?case='.$id,$this->uid,null,[],null,'hr_employee_case',$id,'status_update',[],true);
    }
    private static function transaction(callable $fn): mixed {
        $db=Database::connect();$db->beginTransaction();
        try{$r=$fn();$db->commit();return $r;}catch(\Throwable $e){$db->rollBack();throw $e;}
    }
    public function createCase(int $eid,array $b): int {
        $kind=(string)($b['kind']??'');$e=$this->assertAction($eid,$kind);
        if(!isset(self::KINDS[$kind]))throw new \RuntimeException('نوع غير صالح',422);
        $subject=trim((string)($b['subject']??''));$body=trim((string)($b['body']??''));$key=(string)($b['request_key']??'');
        if($subject===''||mb_strlen($subject)>200||$body===''||mb_strlen($body)>10000||!preg_match('/^[a-zA-Z0-9_-]{16,80}$/',$key))throw new \RuntimeException('العنوان والتفاصيل ومعرف الطلب مطلوبة',422);
        $images=(array)($b['images']??[]);$files=[];
        if(count($images)>5)throw new \RuntimeException('الحد الأقصى خمس صور للبلاغ',422);
        foreach($images as $image){
            if(!is_string($image)||strlen($image)>7*1024*1024)throw new \RuntimeException('حجم الصورة يتجاوز 5MB',422);
            $raw=base64_decode(preg_replace('#^data:image/[^;]+;base64,#','',$image),true);
            $info=$raw!==false?@getimagesizefromstring($raw):false;
            if(!$info||strlen($raw)>5*1024*1024||!in_array($info['mime'],['image/jpeg','image/png','image/webp'],true))throw new \RuntimeException('الصورة غير صالحة',422);
            $files[]=[$info['mime'],$raw];
        }
        if($kind==='photo_loading'&&!$files)throw new \RuntimeException('أرفق صورة واحدة على الأقل',422);
        return self::transaction(function()use($eid,$b,$e,$kind,$subject,$body,$key,$files){
            Database::query('SELECT id FROM hr_team_mutex WHERE id=1 FOR UPDATE')->fetch();
            $existing=Database::query('SELECT id,employee_id,kind FROM hr_employee_cases WHERE created_by=? AND request_key=?',[$this->uid,$key])->fetch();if($existing){if((int)$existing['employee_id']!==$eid||$existing['kind']!==$kind)throw new \RuntimeException('معرف الطلب مستخدم لإجراء آخر',422);return (int)$existing['id'];}
            $e=$this->assertAction($eid,$kind);$approver=null;$new=null;$state='issued';$warning=null;
            if($kind==='warning'){
                $approver=$this->managerOfManager($e);
                if(!$approver||$approver===$this->uid)throw new \RuntimeException('يجب ربط مدير المدير بحساب نشط مستقل قبل طلب الإنذار',422);
                $warning=(string)($b['warning_type']??'written');if(!in_array($warning,['verbal','written','final'],true))throw new \RuntimeException('نوع الإنذار غير صالح',422);
                $state='pending_manager';
            }
            if($kind==='transfer'){
                $new=(int)($b['new_manager_id']??0);$target=$this->validateTransfer($eid,$new);
                if(Database::query("SELECT id FROM hr_employee_cases WHERE employee_id=? AND kind='transfer' AND status IN ('pending_manager','pending_hr')",[$eid])->fetchColumn())throw new \RuntimeException('يوجد طلب نقل معلق لهذا الموظف',422);
                if(!$this->hrUsers())throw new \RuntimeException('لا يوجد معتمد موارد بشرية مخول',422);
                $approver=(int)$target['user_id'];$state='pending_manager';
            }
            Database::query('INSERT INTO hr_employee_cases(employee_id,kind,subject,body,status,created_by,approver_id,new_manager_id,old_manager_id,department_id,warning_type,request_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',[$eid,$kind,$subject,$body,$state,$this->uid,$approver,$new,$e['manager_id'],$e['department_id'],$warning,$key]);
            $id=(int)Database::lastInsertId();
            foreach($files as [$mime,$raw])Database::query('INSERT INTO hr_case_files(case_id,mime,image_data) VALUES (?,?,?)',[$id,$mime,$raw]);
            $this->event($id,'created');
            if($state==='issued')$this->notify([(int)$e['user_id']],$id,self::KINDS[$kind].' جديد');
            else $this->notify([$approver],$id,'طلب بانتظار قرارك: '.self::KINDS[$kind]);
            if(in_array($kind,['notice_request','transfer'],true))$this->notify([...$this->hrUsers(),$kind==='notice_request'?$this->managerOfManager($e):0],$id,self::KINDS[$kind].' جديد للاطلاع');
            return $id;
        });
    }
    private function validateTransfer(int $eid,int $new): array {
        $e=$this->employee($eid);$target=$this->employee($new);
        if(!$e['department_id']||(int)$e['department_id']!==(int)$target['department_id']||$eid===$new||(int)$e['manager_id']===$new||$target['status']!=='active'||$target['file_status']!=='open')throw new \RuntimeException('اختر مديراً آخر نشطاً داخل القسم نفسه',422);
        $u=Database::query('SELECT u.id,r.level FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? AND u.is_active=1',[$target['user_id']])->fetch();
        if(!$u || ((int)$u['level']<60&&!Database::query('SELECT id FROM employees WHERE manager_id=? LIMIT 1',[$new])->fetchColumn()))throw new \RuntimeException('الحساب المستلم ليس مديراً نشطاً',422);
        $seen=[];$cursor=$new;
        while($cursor){if($cursor===$eid||isset($seen[$cursor]))throw new \RuntimeException('النقل ينشئ حلقة في التسلسل الإداري',422);$seen[$cursor]=true;$cursor=(int)Database::query('SELECT manager_id FROM employees WHERE id=?',[$cursor])->fetchColumn();}
        $positions=Database::query('SELECT id,employee_id,parent_id FROM org_positions WHERE is_active=1 AND employee_id IN (?,?)',[$eid,$new])->fetchAll();
        $ep=array_values(array_filter($positions,fn($p)=>(int)$p['employee_id']===$eid));$np=array_values(array_filter($positions,fn($p)=>(int)$p['employee_id']===$new));
        if(count($ep)>1||count($np)>1)throw new \RuntimeException('يوجد أكثر من منصب نشط؛ صحح ربط الهيكل قبل النقل',422);
        if($ep && !$np)throw new \RuntimeException('يلزم ربط المدير الجديد بمنصب في الهيكل التنظيمي أولاً',422);
        if($ep&&$np){$cursor=(int)$np[0]['id'];$seen=[];while($cursor){if($cursor===(int)$ep[0]['id']||isset($seen[$cursor]))throw new \RuntimeException('النقل ينشئ حلقة في الهيكل التنظيمي',422);$seen[$cursor]=true;$cursor=(int)Database::query('SELECT parent_id FROM org_positions WHERE id=?',[$cursor])->fetchColumn();}}
        return $target;
    }
    public function managers(int $eid): array {
        $e=$this->assertAction($eid,'transfer');$rows=Database::query('SELECT e.id,e.full_name FROM employees e JOIN users u ON u.id=e.user_id AND u.is_active=1 JOIN roles r ON r.id=u.role_id WHERE e.department_id=? AND e.status=\'active\' AND e.file_status=\'open\' AND (r.level>=60 OR EXISTS(SELECT 1 FROM employees c WHERE c.manager_id=e.id)) ORDER BY e.full_name',[$e['department_id']])->fetchAll();
        return array_values(array_filter($rows,function($r)use($eid){try{$this->validateTransfer($eid,(int)$r['id']);return true;}catch(\RuntimeException){return false;}}));
    }
    private function caseRow(int $id): array {
        $r=Database::query('SELECT c.*,e.full_name,e.emp_code,e.user_id employee_user_id,u.full_name creator_name,n.full_name new_manager_name FROM hr_employee_cases c JOIN employees e ON e.id=c.employee_id JOIN users u ON u.id=c.created_by LEFT JOIN employees n ON n.id=c.new_manager_id WHERE c.id=?',[$id])->fetch();
        if(!$r)throw new \RuntimeException('السجل غير موجود',404);
        $e=$this->employee((int)$r['employee_id']);
        $own=(int)$e['user_id']===$this->uid;
        $visible=$this->all()||$this->direct($e)||(int)$r['created_by']===$this->uid||(int)$r['approver_id']===$this->uid||($r['kind']==='transfer'&&$this->can('hr.team.transfer.approve'))||($r['kind']==='notice_request'&&$this->managerOfManager($e)===$this->uid)||($own&&in_array($r['status'],['issued','approved'],true));
        // Pending/rejected warnings are never exposed to the employee who is their subject.
        if($own && $r['kind']==='warning' && !in_array($r['status'],['issued','approved'],true))$visible=false;
        if(!$visible)throw new \RuntimeException('ليس لديك صلاحية عرض هذا السجل',403);
        return $r;
    }
    private function decisionActions(array $r): array {
        $a=[];
        if($r['status']==='pending_manager'&&(int)$r['approver_id']===$this->uid)$a=['approve','reject'];
        if($r['kind']==='transfer'&&$r['status']==='pending_hr'&&$this->can('hr.team.transfer.approve'))$a=['approve','reject'];
        if(in_array($r['status'],['pending_manager','pending_hr'],true)&&(int)$r['created_by']===$this->uid)$a[]='cancel';
        return $a;
    }
    public function detail(int $id,bool $read=false): array {
        $r=$this->caseRow($id);
        if($read&&(int)$r['employee_user_id']===$this->uid&&in_array($r['status'],['issued','approved'],true)&&!$r['employee_read_at']){
            Database::query('UPDATE hr_employee_cases SET employee_read_at=COALESCE(employee_read_at,NOW()) WHERE id=?',[$id]);$r['employee_read_at']=date('Y-m-d H:i:s');
        }
        $r['actions']=$this->decisionActions($r);
        $r['events']=Database::query('SELECT v.action,v.notes,v.created_at,u.full_name actor_name FROM hr_case_events v JOIN users u ON u.id=v.actor_id WHERE v.case_id=? ORDER BY v.id',[$id])->fetchAll();
        $r['files']=Database::query('SELECT id,mime FROM hr_case_files WHERE case_id=? ORDER BY id',[$id])->fetchAll();
        return $r;
    }
    public function file(int $id): array {
        $f=Database::query('SELECT case_id FROM hr_case_files WHERE id=?',[$id])->fetch();if(!$f)throw new \RuntimeException('الصورة غير موجودة',404);
        $this->caseRow((int)$f['case_id']);return Database::query('SELECT mime,image_data FROM hr_case_files WHERE id=?',[$id])->fetch();
    }
    public function decide(int $id,string $action,string $notes): void {
        self::transaction(function()use($id,$action,$notes){
            Database::query('SELECT id FROM hr_team_mutex WHERE id=1 FOR UPDATE')->fetch();
            Database::query('SELECT id FROM hr_employee_cases WHERE id=? FOR UPDATE',[$id])->fetch();
            $r=$this->caseRow($id);
            if(!in_array($action,$this->decisionActions($r),true))throw new \RuntimeException('القرار غير متاح أو سبق اتخاذه',403);
            if(mb_strlen($notes)>4000)throw new \RuntimeException('الملاحظات طويلة جداً',422);
            if(in_array($action,['reject','cancel'],true)&&trim($notes)==='')throw new \RuntimeException('سبب الرفض أو الإلغاء مطلوب',422);
            $state=$action==='reject'?'rejected':($action==='cancel'?'cancelled':'approved');
            if($action==='approve'&&$r['kind']==='warning'){
                $wid=HrService::createWarning((int)$r['employee_id'],$r['warning_type'],$r['body'],date('Y-m-d'),$this->uid);
                Database::query('UPDATE hr_employee_cases SET warning_id=? WHERE id=?',[$wid,$id]);
            }
            if($action==='approve'&&$r['kind']==='transfer'){
                // Serialize all transfers and lock the hierarchy while checking it again.
                Database::query('SELECT id FROM employees ORDER BY id FOR UPDATE')->fetchAll();
                Database::query('SELECT id FROM org_positions ORDER BY id FOR UPDATE')->fetchAll();
                $e=$this->employee((int)$r['employee_id']);
                if((int)$e['manager_id']!==(int)$r['old_manager_id']||(int)$e['department_id']!==(int)$r['department_id'])throw new \RuntimeException('تغير مدير الموظف أو قسمه؛ ألغِ الطلب وأعد تقديمه',422);
                $target=$this->validateTransfer((int)$r['employee_id'],(int)$r['new_manager_id']);
                if((int)$target['user_id']!==(int)$r['approver_id'])throw new \RuntimeException('تغير حساب المدير المستلم؛ أعد الطلب',422);
                if($r['status']==='pending_manager'){
                    $state='pending_hr';Database::query('UPDATE hr_employee_cases SET accepted_by=?,accepted_at=NOW() WHERE id=?',[$this->uid,$id]);
                    $this->notify($this->hrUsers(),$id,'نقل قبله المدير الجديد وبانتظار اعتماد الموارد البشرية');
                }else{
                    HrTransferSnapshot::capture((int)$r['employee_id']);
                    Database::query('UPDATE employees SET manager_id=? WHERE id=?',[$r['new_manager_id'],$r['employee_id']]);
                    $parent=Database::query('SELECT id FROM org_positions WHERE employee_id=? AND is_active=1',[$r['new_manager_id']])->fetchColumn();
                    if($parent)Database::query('UPDATE org_positions SET parent_id=? WHERE employee_id=? AND is_active=1',[$parent,$r['employee_id']]);
                    Database::query('UPDATE hr_employee_cases SET effective_at=NOW() WHERE id=?',[$id]);
                    $this->notify([(int)$target['user_id'],...$this->hrUsers()],$id,'تم النقل؛ يرجى تحديث وردية الموظف بالتنسيق مع الموارد البشرية');
                }
            }
            Database::query('UPDATE hr_employee_cases SET status=?,decided_by=?,decided_at=NOW() WHERE id=?',[$state,$this->uid,$id]);
            $this->event($id,$state,$notes);
            $recipients=[(int)$r['created_by']];
            if($state==='approved')$recipients[]=(int)$r['employee_user_id'];
            $this->notify($recipients,$id,'تحديث '.self::KINDS[$r['kind']].': '.self::statusLabel($state));
        });
    }
    public static function statusLabel(string $s): string { return ['issued'=>'صادر','pending_manager'=>'بانتظار المدير','pending_hr'=>'بانتظار الموارد البشرية','approved'=>'معتمد','rejected'=>'مرفوض','cancelled'=>'ملغى'][$s]??$s; }
    public function cases(array $q,bool $export=false): array {
        $p=[];$where='1=1';
        if(!$this->all()){
            $where="(c.created_by=? OR c.approver_id=? OR e.manager_id=? OR (e.user_id=? AND c.status IN ('issued','approved')) OR (c.kind='notice_request' AND EXISTS (SELECT 1 FROM employees m WHERE m.id=e.manager_id AND m.manager_id=?))";
            $p=[$this->uid,$this->uid,$this->own?:-1,$this->uid,$this->own?:-1];
            if($this->can('hr.team.transfer.approve'))$where.=" OR c.kind='transfer'";
            $where.=')';
        }
        $where.=" AND NOT (c.kind='warning' AND e.user_id=? AND c.status NOT IN ('issued','approved'))";$p[]=$this->uid;
        foreach(['kind','status','employee_id'] as $key)if(!empty($q[$key])){$where.=" AND c.$key=?";$p[]=$q[$key];}
        foreach(['from'=>'>=','to'=>'<='] as $key=>$op)if(!empty($q[$key])){self::date($q[$key]);$where.=" AND c.created_at $op ?";$p[]=$q[$key].($key==='to'?' 23:59:59':' 00:00:00');}
        if(trim($q['search']??'')!==''){ $where.=' AND (e.full_name LIKE ? OR e.emp_code LIKE ? OR c.subject LIKE ?)';$term='%'.trim($q['search']).'%';$p=[...$p,$term,$term,$term]; }
        $sql="FROM hr_employee_cases c JOIN employees e ON e.id=c.employee_id WHERE $where";
        $total=(int)Database::query('SELECT COUNT(*) '.$sql,$p)->fetchColumn();
        $page=max(1,(int)($q['page']??1));$size=self::pageSize($q);$limit=$export?'':' LIMIT ? OFFSET ?';
        $rows=Database::query("SELECT c.id,c.employee_id,c.kind,c.subject,c.body,c.status,c.employee_read_at,c.created_at,c.effective_at,e.full_name,e.emp_code ".$sql.' ORDER BY c.id DESC'.$limit,$export?$p:[...$p,$size,($page-1)*$size])->fetchAll();
        foreach($rows as &$row){$row['kind_label']=self::KINDS[$row['kind']];$row['status_label']=self::statusLabel($row['status']);}
        return ['rows'=>$rows,'total'=>$total,'page'=>$page,'page_size'=>$size];
    }
    private static function date(string $value): string {
        $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        if(!$d||$d->format('Y-m-d')!==$value)throw new \RuntimeException('تاريخ غير صالح',422);return $value;
    }
    public function form(int $eid,string $action): array {
        $e=$this->assertAction($eid,$action);
        $f=fn($name,$label,$type='text',$options=[])=>['name'=>$name,'label'=>$label,'type'=>$type,'options'=>$options,'required'=>true];
        $reason=$f('reason','السبب والتفاصيل','textarea');
        $fields=[];
        switch($action){
            case 'notice':case 'notice_request':case 'warning':case 'photo_loading':case 'transfer':
                $fields=[$f('subject','العنوان'),$f('body','التفاصيل والتوجيه','textarea')];
                if($action==='warning')$fields[]=$f('warning_type','نوع الإنذار','select',['verbal'=>'شفهي','written'=>'كتابي','final'=>'نهائي']);
                if($action==='transfer')$fields[]=$f('new_manager_id','المدير الجديد من القسم نفسه','select',array_column($this->managers($eid),'full_name','id'));
                if($action==='photo_loading')$fields[]=$f('images','الصور (حتى 5 صور، 5MB للصورة)','images');
                break;
            case 'hr_leave':$fields=[$f('leave_type','نوع الإجازة','select',['annual'=>'سنوية','unpaid'=>'بدون راتب','sick'=>'مرضية','emergency'=>'طارئة']),$f('date_from','من','date'),$f('date_to','إلى','date'),$reason];break;
            case 'hr_missing_attendance':$fields=[$f('request_type','البصمة','select',['in'=>'دخول','out'=>'خروج','both'=>'دخول وخروج']),$f('clock_in_at','وقت الدخول','datetime-local'),$f('clock_out_at','وقت الخروج','datetime-local'),$reason];$fields[1]['required']=false;$fields[2]['required']=false;break;
            case 'deduction_request':$fields=[$f('deduction_amount','مبلغ الخصم','number'),$reason];break;
            case 'hr_overtime':$fields=[$f('request_date','تاريخ العمل','date'),$f('hours','عدد الساعات','number'),$reason];break;
            case 'suspension_request':
                $t=WorkflowTemplateService::getTemplateByCode($action);
                $fields=array_values(array_filter($t['fields'],fn($v)=>!in_array($v['name'],['employee_id','employee_name'],true)));break;
            case 'hr_exceptional_advance':case 'hr_long_advance':$fields=[$f('amount','المبلغ','number'),$reason];if($action==='hr_long_advance')$fields[]=$f('installments','عدد الأقساط','number');break;
            case 'shift':$fields=[$f('shift_id','الوردية الافتراضية','select',array_column(Database::query('SELECT id,name FROM shifts ORDER BY name')->fetchAll(),'name','id'))];break;
            case 'evaluation':
                $fields=[$f('period','الشهر','month'),['name'=>'notes','label'=>'ملاحظات','type'=>'textarea','required'=>false]];
                foreach(HrService::getApplicableKpiIds($eid) as $kid){$k=Database::query('SELECT id,name,weight FROM performance_kpis WHERE id=?',[$kid])->fetch();$fields[]=$f('kpi_'.$kid,$k['name'].' (الوزن '.$k['weight'].')','number');}
                break;
            default:throw new \RuntimeException('لا يوجد نموذج لهذا الإجراء',422);
        }
        return ['employee'=>$e,'action'=>$action,'title'=>$this->actions($e)[$action],'fields'=>$fields,'advance_snapshot'=>in_array($action,['hr_exceptional_advance','hr_long_advance'],true)?AdvancePolicyService::snapshot($eid):null];
    }
    public function perform(int $eid,string $action,array $b): array {
        $e=$this->assertAction($eid,$action);
        if(isset(self::KINDS[$action])){ $b['kind']=$action; return ['case_id'=>$this->createCase($eid,$b)]; }
        $schema=$this->form($eid,$action);
        foreach($schema['fields'] as $f){
            $value=$b[$f['name']]??'';
            if(!is_scalar($value)&&$value!==null)throw new \RuntimeException('قيمة حقل غير صالحة',422);
            if(!empty($f['required'])&&trim((string)$value)==='')throw new \RuntimeException('الحقل مطلوب: '.$f['label'],422);
            if(($f['type']??'')==='date'&&$value!=='')self::date((string)$value);
            if(($f['type']??'')==='select'&&$value!==''){
                $opts=(array)($f['options']??[]);$allowed=[];
                foreach($opts as $k=>$v)$allowed[]=is_array($v)?(string)($v['value']??''):(array_is_list($opts)?(string)$v:(string)$k);
                if(!in_array((string)$value,$allowed,true))throw new \RuntimeException('اختيار غير صالح: '.$f['label'],422);
            }
        }
        $reason=trim((string)($b['reason']??''));
        if(mb_strlen($reason)>10000)throw new \RuntimeException('النص طويل جداً',422);
        $key=(string)($b['request_key']??'');
        if(!preg_match('/^[a-zA-Z0-9_-]{16,80}$/',$key))throw new \RuntimeException('معرف الطلب مطلوب',422);
        return self::transaction(function()use($eid,$action,$b,$e,$reason,$key){
            Database::query('SELECT id FROM hr_team_mutex WHERE id=1 FOR UPDATE')->fetch();
            $this->assertAction($eid,$action);
            $old=Database::query('SELECT employee_id,action,result_json FROM hr_team_submissions WHERE created_by=? AND request_key=?',[$this->uid,$key])->fetch();
            if($old){if((int)$old['employee_id']!==$eid||$old['action']!==$action)throw new \RuntimeException('معرف طلب مستخدم لإجراء آخر',422);return json_decode($old['result_json'],true);}
            $result=(function()use($eid,$action,$b,$e,$reason){
            if($action==='shift'){
                $sid=(int)($b['shift_id']??0);
                if(!Database::query('SELECT id FROM shifts WHERE id=?',[$sid])->fetchColumn())throw new \RuntimeException('وردية غير صالحة',422);
                HrService::setEmployeeDefaultShift($eid,$sid);
                HrService::notifyEmployeeShiftChanged($eid,'تم تحديث ورديتك الافتراضية','يرجى مراجعة الوردية الجديدة','/hr/team');
                Helpers::audit('hr.team.shift','employees',$eid,json_encode(['shift_id'=>$sid]),$this->uid);return ['message'=>'تم تعيين الوردية'];
            }
            if($action==='evaluation'){
                $period=(string)($b['period']??'');self::date($period.'-01');
                if(Database::query("SELECT id FROM evaluations WHERE employee_id=? AND period=? AND evaluation_type='monthly'",[$eid,$period])->fetchColumn())throw new \RuntimeException('تم التقييم لهذا الشهر مسبقاً',422);
                $scores=[];foreach(HrService::getApplicableKpiIds($eid) as $kid){$v=$b['kpi_'.$kid]??null;if(!is_numeric($v)||(float)$v<0||(float)$v>100)throw new \RuntimeException('درجات التقييم يجب أن تكون بين صفر و100',422);$w=Database::query('SELECT weight FROM performance_kpis WHERE id=?',[$kid])->fetchColumn();$scores[]=['kpi_id'=>$kid,'score'=>(float)$v,'weight'=>(float)$w,'notes'=>''];}
                if(!$scores)throw new \RuntimeException('لا توجد مؤشرات تقييم مرتبطة بالوظيفة',422);
                return ['evaluation_id'=>HrService::createEvaluation($eid,$period,'monthly',$scores,(string)($b['notes']??''),$this->uid)];
            }
            $data=$b;unset($data['entity_id'],$data['entity_type']);$data['employee_id']=$eid;$data['employee_name']=$e['full_name'];$data['reason']=$reason;
            $entity=null;$entityId=null;
            if($action==='hr_leave'){
                $from=self::date((string)$b['date_from']);$to=self::date((string)$b['date_to']);$lt=(string)$b['leave_type'];
                if(!in_array($lt,['annual','unpaid','sick','emergency'],true)||$to<$from||HrService::hasLeaveOverlap($eid,$from,$to))throw new \RuntimeException('فترة أو نوع إجازة غير صالح أو متداخل',422);
                Database::query("INSERT INTO leave_requests(employee_id,requested_by,request_type,leave_type,date_from,date_to,reason,status) VALUES (?,?,'leave',?,?,?,?,'pending')",[$eid,$this->uid,$lt,$from,$to,$reason]);
                $entityId=(int)Database::lastInsertId();$entity='leave_request';$data['request_type']='leave';
            }elseif($action==='hr_missing_attendance'){
                foreach(['clock_in_at','clock_out_at'] as $key)if(!empty($data[$key])){$dt=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',(string)$data[$key]);if(!$dt||$dt->format('Y-m-d\TH:i')!==$data[$key])throw new \RuntimeException('وقت البصمة غير صالح',422);$data[$key]=$dt->format('Y-m-d H:i:s');}
                $entityId=HrService::createMissingAttendanceRequest($eid,$data);$entity='missing_attendance_request';
            }elseif($action==='hr_overtime'){
                self::date((string)$b['request_date']);$h=(float)$b['hours'];if(!is_finite($h)||$h<=0||$h>24)throw new \RuntimeException('ساعات العمل بين صفر و24',422);
                $entityId=HrService::createOvertimeRequest($eid,$b['request_date'],$h,$reason);$entity='overtime_request';
                $data['amount']=HrService::calculateOvertimeAmount($eid,$h,substr($b['request_date'],0,7));
            }elseif($action==='deduction_request'){
                $n=(float)$b['deduction_amount'];if(!is_finite($n)||$n<=0)throw new \RuntimeException('المبلغ يجب أن يكون موجباً',422);
                $entityId=HrService::createAdjustmentFromWorkflowData($data,$this->uid,'deduction');$entity='hr_adjustment';
            }
            $t=WorkflowTemplateService::getTemplateByCode($action);
            if(!$t)throw new \RuntimeException('المسار غير متاح',422);
            // Missing punch is delegated only after this service's employee and permission check.
            $rid=WorkflowEngine::createRequest((int)$t['id'],$this->uid,$data,$action==='hr_missing_attendance');
            $pos=Database::query('SELECT id FROM org_positions WHERE employee_id=? AND is_active=1 ORDER BY id LIMIT 1',[$eid])->fetchColumn();
            Database::query('UPDATE requests SET requester_employee_id=?,requester_position_id=? WHERE id=?',[$eid,$pos?:null,$rid]);
            if($entityId){
                $payload=json_decode(Database::query('SELECT data_json FROM requests WHERE id=?',[$rid])->fetchColumn(),true)?:[];
                $payload['entity_id']=$entityId;$payload['entity_type']=$entity;
                Database::query('UPDATE requests SET data_json=? WHERE id=?',[json_encode($payload,JSON_UNESCAPED_UNICODE),$rid]);
                \App\Core\Workflow\EntityRegistry::bind($entity,$entityId,$rid);
                Database::query('INSERT INTO hr_workflow_links(entity_type,entity_id,request_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE request_id=VALUES(request_id)',[$entity,$entityId,$rid]);
            }
            WorkflowEngine::submitRequest($rid,$this->uid);
            if($entity==='missing_attendance_request')Database::query('UPDATE missing_attendance_requests SET workflow_request_id=? WHERE id=?',[$rid,$entityId]);
            return ['request_id'=>$rid,'message'=>'تم إرسال الطلب إلى مسار الموافقات'];
            })();
            Database::query('INSERT INTO hr_team_submissions(created_by,request_key,employee_id,action,result_json) VALUES (?,?,?,?,?)',[$this->uid,$key,$eid,$action,json_encode($result,JSON_UNESCAPED_UNICODE)]);
            return $result;
        });
    }
    public function archive(int $eid,array $q): array {
        $e=$this->employee($eid);$this->assertView($e);
        $type=(string)($q['type']??'warnings');
        $types=[
            'warnings'=>['employee_warnings','id,warning_type,reason,warning_date,status','الإنذارات'],
            'deductions'=>['hr_adjustments','id,adjustment_type,amount,period,reason,status','الخصومات والتسويات'],
            'payroll'=>['payrolls','id,period,base_salary,total_earnings,total_deductions,net_salary,status','كشوف الرواتب'],
            'leaves'=>['leave_requests','id,leave_type,date_from,date_to,reason,status','الإجازات'],
            'attendance'=>['attendance','id,check_type,checked_at','الحضور والانصراف'],
            'advances'=>['salary_advances','id,advance_type,amount,remaining_amount,status','السلف'],
            'evaluations'=>['evaluations','id,period,total_score,evaluation_type','التقييمات'],
            'tips'=>['employee_monthly_tips','id,period,full_amount,prorated_amount,notes','التيبس'],
            'loadings'=>['employee_monthly_loadings','id,period,amount,notes','التحميلات المالية'],
        ];
        if(!isset($types[$type]))throw new \RuntimeException('أرشيف غير صالح',422);
        if(in_array($type,['deductions','payroll','advances','tips','loadings'],true)&&!$this->financial($e))throw new \RuntimeException('المعلومات المالية للموظف نفسه وللمخولين فقط',403);
        if(!$this->manage($e)&&(int)$e['user_id']!==$this->uid&&!in_array($type,['deductions','payroll','advances','tips','loadings'],true))throw new \RuntimeException('ليس لديك صلاحية هذا الأرشيف',403);
        [$table,$columns,$label]=$types[$type];$page=max(1,(int)($q['page']??1));$size=self::pageSize($q);
        $rows=Database::query("SELECT $columns FROM $table WHERE employee_id=? ORDER BY id DESC LIMIT ? OFFSET ?",[$eid,$size,($page-1)*$size])->fetchAll();
        $total=(int)Database::query("SELECT COUNT(*) FROM $table WHERE employee_id=?",[$eid])->fetchColumn();
        return ['rows'=>$rows,'total'=>$total,'page'=>$page,'page_size'=>$size,'title'=>$label];
    }
    public function employeeView(int $eid): array { $e=$this->employee($eid);$this->assertView($e);$e['actions']=$this->actions($e);return $e; }
    public function payroll(int $eid,int $pid): array {
        $e=$this->employee($eid);
        if(!$this->financial($e))throw new \RuntimeException('لا تملك صلاحية كشف الراتب',403);
        $p=Database::query('SELECT * FROM payrolls WHERE id=? AND employee_id=?',[$pid,$eid])->fetch();
        if(!$p)throw new \RuntimeException('كشف الراتب غير موجود',404);
        $overtime=Database::query('SELECT hours,multiplier,amount FROM payroll_overtime WHERE payroll_id=? ORDER BY id',[$pid])->fetchAll();
        $allowances=Database::query('SELECT allowance_type,amount FROM payroll_allowances WHERE payroll_id=? ORDER BY id',[$pid])->fetchAll();
        $p=PayrollPresentation::detail($p);
        $columns=PayrollPresentation::columns($p);
        $items=array_map(static function($c){return ['label'=>$c['label'],'value'=>(isset($c['days'])?$c['days'].' يوم / ':'').$c['value']];},$columns);
        return ['employee'=>$e['full_name'],'payroll'=>$p,'overtime'=>$overtime,'allowances'=>$allowances,'columns'=>$columns,'items'=>$items];
    }
}
