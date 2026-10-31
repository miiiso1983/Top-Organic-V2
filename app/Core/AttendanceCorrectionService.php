<?php
namespace App\Core;

/** Approved punch corrections: immutable before/after history and optimistic concurrency. */
class AttendanceCorrectionService
{
    public static function timestamp(string $value): string
    {
        $value=str_replace('T',' ',trim($value));
        if(strlen($value)===16)$value.=':00';
        $d=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value,new \DateTimeZone('Asia/Baghdad'));
        if(!$d||$d->format('Y-m-d H:i:s')!==$value)throw new \RuntimeException('تاريخ ووقت البصمة غير صالح');
        return $value;
    }

    public static function transaction(callable $action): mixed
    {
        $db=Database::connect();$owner=!$db->inTransaction();
        if($owner)$db->beginTransaction();
        try{$result=$action();if($owner)$db->commit();return $result;}
        catch(\Throwable $e){if($owner&&$db->inTransaction())$db->rollBack();throw $e;}
    }

    public static function prepare(int $employeeId,array $data): array
    {
        $mode=(string)($data['correction_mode']??'auto');
        if($mode==='')$mode='auto'; // Compatibility with already installed clients.
        if(!in_array($mode,['auto','add','correct'],true))throw new \RuntimeException('نوع المعالجة غير صالح');
        $type=(string)($data['request_type']??'in');$plan=[];
        foreach(['in','out'] as $kind){
            if(!in_array($type,[$kind,'both'],true))continue;
            $at=self::timestamp((string)($data['clock_'.$kind.'_at']??''));
            $original=trim((string)($data['original_'.$kind.'_at']??''));
            $target=(int)($data['target_'.$kind.'_id']??0);
            $rows=[];
            if($target){
                $rows=Database::query('SELECT id,checked_at,source FROM attendance WHERE id=? AND employee_id=? AND check_type=?',[$target,$employeeId,$kind])->fetchAll();
                if(!$rows)throw new \RuntimeException('البصمة المحددة لا تخص الموظف أو نوع الطلب');
            }elseif($original!==''){
                $original=self::timestamp($original);
                // Forms select minutes. Reject multiple matches rather than guessing a punch.
                $rows=Database::query('SELECT id,checked_at,source FROM attendance WHERE employee_id=? AND check_type=? AND checked_at>=? AND checked_at<?',[$employeeId,$kind,substr($original,0,16).':00',date('Y-m-d H:i:s',strtotime(substr($original,0,16).':00 +1 minute'))])->fetchAll();
                if(!$rows)throw new \RuntimeException('لم توجد البصمة الأصلية المحددة؛ راجع سجل الموظف');
            }elseif($mode==='correct'){
                throw new \RuntimeException('لتصحيح بصمة خاطئة حدد تاريخ ووقت البصمة الأصلية');
            }elseif($mode==='auto'){
                $rows=Database::query('SELECT id,checked_at,source FROM attendance WHERE employee_id=? AND check_type=? AND checked_at>=? AND checked_at<?',[$employeeId,$kind,substr($at,0,10).' 00:00:00',date('Y-m-d 00:00:00',strtotime(substr($at,0,10).' +1 day'))])->fetchAll();
            }
            if($mode==='add'&&($target||$original!==''))throw new \RuntimeException('اختر تصحيح بصمة عند تحديد وقت أصلي');
            if(count($rows)>1)throw new \RuntimeException('توجد عدة بصمات؛ اختر تصحيح بصمة وحدد الوقت الأصلي بدقة');
            $old=$rows[0]??null;
            if(!$old&&Database::query('SELECT id FROM attendance WHERE employee_id=? AND check_type=? AND checked_at=?',[$employeeId,$kind,$at])->fetch())throw new \RuntimeException('هذه البصمة مسجلة بالفعل');
            $revision=$old?(int)Database::query('SELECT COALESCE(MAX(id),0) FROM attendance_corrections WHERE attendance_id=?',[$old['id']])->fetchColumn():0;
            $plan[$kind]=['mode'=>$mode,'revision'=>$revision,'target_id'=>$old?(int)$old['id']:null,'original_at'=>$old['checked_at']??null,'original_source'=>$old['source']??null,'corrected_at'=>$at];
        }
        if(!$plan)throw new \RuntimeException('نوع البصمة غير صالح');
        if(isset($plan['in'],$plan['out'])&&$plan['out']['corrected_at']<=$plan['in']['corrected_at'])throw new \RuntimeException('وقت الخروج يجب أن يكون بعد الدخول؛ حدد تاريخ اليوم التالي للمناوبة الليلية');
        return self::bindShiftWindows($employeeId,$plan,$data);
    }

    private static function bindShiftWindows(int $employeeId,array $plan,array $data): array
    {
        $times=array_column($plan,'corrected_at');sort($times);
        $from=date('Y-m-d',strtotime($times[0].' -2 days'));$to=date('Y-m-d',strtotime(end($times).' +2 days'));
        $shift=HrService::loadShiftMap([$employeeId],$from,$to)[$employeeId]??null;
        if(AttendanceShiftPolicy::isFlexible($shift))return $plan;
        $date=trim((string)($data['shift_date']??''));
        if($date!=='' && self::timestamp($date.' 00:00:00')!==$date.' 00:00:00')throw new \RuntimeException('تاريخ بداية الوردية غير صالح');
        foreach($plan as $kind=>&$change){
            $record=['check_type'=>$kind,'checked_at'=>$change['corrected_at'],'source'=>'request'];
            $w=AttendanceShiftPolicy::locate($record,$shift,$date?:null);
            if(!$w)throw new \RuntimeException('يجب تحديد وردية صحيحة للموظف قبل اعتماد تصحيح البصمة');
            if(!AttendanceShiftPolicy::approvedTimeFits($record,$w,$shift))throw new \RuntimeException('وقت البصمة خارج الوردية المحددة أو يتداخل مع الوردية التالية؛ راجع تاريخ بداية الوردية');
            unset($w['rank'],$w['distance'],$w['eligible']);
            $change['shift_window']=$w;$change['shift_date']=$date;
        }unset($change);
        if(isset($plan['in'],$plan['out']) && ($plan['in']['shift_window']['key']!==$plan['out']['shift_window']['key'] || strtotime($plan['out']['corrected_at'])-strtotime($plan['in']['corrected_at'])>=86400))throw new \RuntimeException('يجب أن يخص الدخول والخروج الوردية نفسها');
        return $plan;
    }

    public static function remember(int $requestId,int $employeeId,array $plan): void
    {
        Database::query('INSERT INTO attendance_correction_plans(request_id,employee_id,plan_json) VALUES (?,?,?) ON DUPLICATE KEY UPDATE plan_json=VALUES(plan_json),last_error=NULL',[$requestId,$employeeId,json_encode($plan,JSON_UNESCAPED_UNICODE)]);
    }

    /** Keep web, app, edits and returned requests attached to the same employee ledger. */
    public static function syncWorkflow(array $request,array $payload): void
    {
        $wid=(int)$request['id'];
        $req=Database::query('SELECT * FROM missing_attendance_requests WHERE workflow_request_id=?',[$wid])->fetch();
        // API/team paths may bind the entity immediately before the workflow link is written.
        if(!$req && !empty($request['data']['entity_id']) && ($request['data']['entity_type']??'')==='missing_attendance_request')
            $req=Database::query('SELECT * FROM missing_attendance_requests WHERE id=?',[(int)$request['data']['entity_id']])->fetch();
        $eid=(int)($payload['employee_id']??$request['requester_employee_id']??0);
        if($req && $eid && $eid!==(int)$req['employee_id'])throw new \RuntimeException('لا يمكن تغيير موظف طلب البصمة');
        if(!$eid)throw new \RuntimeException('ملف الموظف مطلوب');
        $owner=(int)Database::query('SELECT user_id FROM employees WHERE id=?',[$eid])->fetchColumn();
        if($owner!==(int)$request['requester_id']){
            $svc=new HrTeamService((int)$request['requester_id']);
            if(!isset($svc->actions($svc->employee($eid))['hr_missing_attendance']))throw new \RuntimeException('لا توجد صلاحية لتقديم طلب لهذا الموظف');
        }
        if(!$req){
            $id=HrService::createMissingAttendanceRequest($eid,$payload);
            Database::query('UPDATE missing_attendance_requests SET workflow_request_id=? WHERE id=?',[$wid,$id]);
            return;
        }
        $id=(int)$req['id'];
        if($req['status']==='fully_approved')throw new \RuntimeException('لا يمكن تعديل تصحيح معتمد');
        $values=[];foreach(['clock_in_at','clock_out_at'] as $key)$values[$key]=!empty($payload[$key])?self::timestamp($payload[$key]):null;
        $saved=Database::query('SELECT plan_json FROM attendance_correction_plans WHERE request_id=?',[$id])->fetch();
        $refresh=$request['status']==='returned_for_edit' || !$saved || $req['request_type']!==($payload['request_type']??'in') || $req['clock_in_at']!==$values['clock_in_at'] || $req['clock_out_at']!==$values['clock_out_at'];
        $oldPlan=$saved?(json_decode($saved['plan_json'],true)?:[]):[];
        foreach($oldPlan as $kind=>$change){
            if(trim((string)($payload['shift_date']??''))!==($change['shift_date']??''))$refresh=true;
            if(!empty($payload['correction_mode']) && $payload['correction_mode']!==($change['mode']??'auto'))$refresh=true;
            if(!empty($payload['original_'.$kind.'_at']) && substr(self::timestamp($payload['original_'.$kind.'_at']),0,16)!==substr($change['original_at']??'',0,16))$refresh=true;
        }
        if($refresh)self::remember($id,$eid,self::prepare($eid,$payload));
        Database::query('UPDATE missing_attendance_requests SET request_type=?,clock_in_at=?,clock_out_at=?,reason=?,workflow_request_id=? WHERE id=?',[$payload['request_type']??'in',$values['clock_in_at'],$values['clock_out_at'],$payload['reason']??'',$wid,$id]);
    }

    public static function apply(int $requestId,int $actorId): array
    {
        return self::transaction(function()use($requestId,$actorId){
            $req=Database::query('SELECT * FROM missing_attendance_requests WHERE id=? FOR UPDATE',[$requestId])->fetch();
            if(!$req)throw new \RuntimeException('طلب نسيان البصمة غير موجود');
            if(!$actorId||!Database::query("SELECT id FROM requests WHERE id=? AND status='approved'",[$req['workflow_request_id']])->fetch())throw new \RuntimeException('لا يمكن تطبيق التصحيح قبل الاعتماد النهائي');
            $saved=Database::query('SELECT * FROM attendance_correction_plans WHERE request_id=? FOR UPDATE',[$requestId])->fetch();
            if(!empty($saved['applied_at']))return []; // A repeated outcome must not overwrite a later correction.
            $eid=(int)$req['employee_id'];
            Database::query('SELECT id FROM employees WHERE id=? FOR UPDATE',[$eid])->fetch();
            if(!$saved){self::remember($requestId,$eid,self::prepare($eid,$req));$saved=Database::query('SELECT * FROM attendance_correction_plans WHERE request_id=?',[$requestId])->fetch();}
            $plan=json_decode($saved['plan_json'],true);$result=[];$dates=[];
            if(!isset(reset($plan)['shift_window'])){
                $plan=self::bindShiftWindows($eid,$plan,$req);
                self::remember($requestId,$eid,$plan);
            }
            foreach($plan as $change){
                if(empty($change['shift_window']))continue;
                $w=$change['shift_window'];
                $current=HrService::loadShiftMap([$eid],$w['work_date'],$w['work_date'])[$eid]??null;
                $matches=array_filter(AttendanceShiftPolicy::windows($current,$w['work_date']),fn($now)=>$now['key']===$w['key'] && $now['start']===$w['start'] && $now['end']===$w['end']);
                if(!$matches)throw new \RuntimeException('تغيرت وردية الموظف منذ تقديم الطلب؛ أعد الطلب للتعديل والمراجعة');
            }
            self::validateOrder($eid,$plan);
            foreach($plan as $kind=>$change){
                $at=$change['corrected_at'];$id=(int)($change['target_id']??0);
                if($id){
                    $old=Database::query('SELECT * FROM attendance WHERE id=? AND employee_id=? AND check_type=? FOR UPDATE',[$id,$eid,$kind])->fetch();
                    if(!$old||$old['checked_at']!==$change['original_at']||$old['source']!==$change['original_source']||(int)Database::query('SELECT COALESCE(MAX(id),0) FROM attendance_corrections WHERE attendance_id=?',[$id])->fetchColumn()!==(int)($change['revision']??0))throw new \RuntimeException('تغيّرت البصمة منذ تقديم الطلب؛ أعد الطلب للتعديل والمراجعة');
                    $dates[]=substr($old['checked_at'],0,10);
                }
                if(Database::query('SELECT id FROM attendance WHERE employee_id=? AND check_type=? AND checked_at=? AND id<>? FOR UPDATE',[$eid,$kind,$at,$id])->fetch())throw new \RuntimeException('توجد بصمة مطابقة؛ لم يُطبّق التصحيح لتجنب التكرار');
                if($id){
                    // Preserve device/location/photo evidence and notes; approved correction is not geofence proof.
                    Database::query("UPDATE attendance SET checked_at=?,source='request' WHERE id=?",[$at,$id]);
                }else{
                    Database::query("INSERT INTO attendance(employee_id,check_type,checked_at,source,within_geofence,notes) VALUES (?,?,?,'request',0,?)",[$eid,$kind,$at,'بصمة مضافة بطلب معتمد #'.$requestId]);
                    $id=(int)Database::lastInsertId();
                }
                Database::query('INSERT INTO attendance_corrections(request_id,attendance_id,employee_id,check_type,original_at,corrected_at,original_source,approved_by) VALUES (?,?,?,?,?,?,?,?)',[$requestId,$id,$eid,$kind,$change['original_at'],$at,$change['original_source'],$actorId]);
                $dates[]=substr($at,0,10);$result[]=['id'=>$id,'type'=>$kind,'checked_at'=>$at,'action'=>$change['target_id']?'updated':'inserted'];
            }
            foreach(array_unique($dates) as $date){
                HrService::recalculateAttendanceForDate($eid,$date);
                HrService::recalculateAttendanceForDate($eid,date('Y-m-d',strtotime($date.' -1 day')));
            }
            Database::query('UPDATE attendance_correction_plans SET applied_at=NOW(),applied_by=?,last_error=NULL WHERE request_id=?',[$actorId,$requestId]);
            return $result;
        });
    }

    /** A replacement must stay within its existing adjacent punches; don't merge two shifts. */
    private static function validateOrder(int $employeeId,array $plan): void
    {
        $targets=array_values(array_filter(array_column($plan,'target_id')));
        foreach($plan as $change){
            if(!$change['target_id'])continue;
            $exclude=implode(',',array_fill(0,count($targets),'?'));
            $before=Database::query("SELECT checked_at FROM attendance WHERE employee_id=? AND id NOT IN ($exclude) AND checked_at<? ORDER BY checked_at DESC LIMIT 1",[$employeeId,...$targets,$change['original_at']])->fetchColumn();
            $after=Database::query("SELECT checked_at FROM attendance WHERE employee_id=? AND id NOT IN ($exclude) AND checked_at>? ORDER BY checked_at ASC LIMIT 1",[$employeeId,...$targets,$change['original_at']])->fetchColumn();
            if(($before && $change['corrected_at']<=$before)||($after && $change['corrected_at']>=$after))throw new \RuntimeException('الوقت المصحح يتداخل مع بصمة مجاورة؛ راجع دخول وخروج الوردية معاً');
        }
    }

    /** Add history only to already authorized records; never broaden the employee scope. */
    public static function annotate(array $records): array
    {
        $ids=array_values(array_unique(array_filter(array_map(fn($r)=>(int)($r['id']??0),$records))));$history=[];
        foreach(array_chunk($ids,500) as $batch){
            $marks=implode(',',array_fill(0,count($batch),'?'));
            foreach(Database::query("SELECT c.*,m.reason,u.full_name AS approved_by_name FROM attendance_corrections c JOIN missing_attendance_requests m ON m.id=c.request_id LEFT JOIN users u ON u.id=c.approved_by WHERE c.attendance_id IN ($marks) ORDER BY c.id",$batch)->fetchAll() as $c)$history[(int)$c['attendance_id']][]=$c;
        }
        foreach($records as &$r){
            $items=$history[(int)($r['id']??0)]??[];$r['corrections']=$items;
            $r['original_at']=$items?$items[0]['original_at']:null;
            $r['correction_details']=implode("\n",array_map(fn($c)=>'طلب #'.$c['request_id'].' | '.($c['original_at']??'غير مسجل').' ← '.$c['corrected_at'].' | '.($c['approved_by_name']??'').' | '.$c['applied_at'].' | '.$c['reason'],$items));
            if(!$items&&($r['source']??'')==='request')$r['correction_details']='طلب سابق — الوقت الأصلي غير محفوظ';
        }unset($r);
        return $records;
    }
}
