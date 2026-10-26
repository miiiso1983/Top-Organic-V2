<?php
namespace App\Core;

/** Display-only payroll projection shared by web and mobile; never recalculates stored totals. */
final class PayrollPresentation
{
    public const LABELS = ['base'=>'الراتب الأساسي','overtime'=>'إضافي (أيام/مبلغ)','overtime_request'=>'طلب العمل الإضافي (أيام)','bonuses'=>'مكافآت','tips_addition'=>'إضافي تيبس','work_days_settlement'=>'تسوية أيام دوام','working_days'=>'أيام الدوام','absence'=>'خصم الغياب','deductions'=>'خصومات','advances'=>'سلف','long_term_advances'=>'سلف طويلة الأجل','insurance'=>'التأمينات المستقطعة','loadings'=>'تحميلات','tips'=>'تيبس','social_security'=>'ضمان','housing'=>'سكن','remaining'=>'قيمة المتبقي','net'=>'صافي','status'=>'الحالة'];
    public static function enrich(array $p,array $requests,float $adjustedBase): array {
        $p['display_base_salary']=(float)($p['full_base_salary'] ?? $p['base_salary'])+(float)($p['raises']??0)-(float)($p['salary_reduction']??0);
        $p['remaining_days']=max(0,(int)date('t',strtotime($p['period'].'-01'))-(int)($p['working_days']??0));
        $p['remaining_value']=round($p['remaining_days']*$adjustedBase/30,2);
        $p['overtime_request_hours']=(float)($requests['hours']??0);
        $p['overtime_request_count']=(int)($requests['count']??0);
        $p['overtime_request_days']=round($p['overtime_request_hours']/((float)($p['required_hours']??0) ?: 8.0),2);
        return $p;
    }
    public static function detail(array $p): array {
        $e=Database::query('SELECT base_salary,required_hours FROM employees WHERE id=?',[(int)$p['employee_id']])->fetch() ?: [];
        $p['full_base_salary']=$e['base_salary']??$p['base_salary'];$p['required_hours']=$e['required_hours']??8;
        $ot=Database::query("SELECT COUNT(*) count,COALESCE(SUM(hours),0) hours FROM overtime_requests WHERE employee_id=? AND DATE_FORMAT(request_date,'%Y-%m')=? AND status != 'rejected'",[(int)$p['employee_id'],$p['period']])->fetch();
        return self::enrich($p,$ot?:[],HrService::adjustedMonthlyBase((int)$p['employee_id'],$p['period']));
    }
    public static function columns(array $p): array {
        $fields=['base'=>'display_base_salary','overtime'=>'overtime','overtime_request'=>'overtime_request_days','bonuses'=>'bonuses','tips_addition'=>'tips_addition','work_days_settlement'=>'work_days_settlement','working_days'=>'working_days','absence'=>'absence_deduction','deductions'=>'deductions','advances'=>'advances','long_term_advances'=>'long_term_advances','insurance'=>'security_deposit','loadings'=>'loadings','tips'=>'tips','social_security'=>'social_security','housing'=>'housing_deduction','remaining'=>'remaining_value','net'=>'net_salary'];
        $en=['Base salary','Overtime (days/amount)','Overtime requested (days)','Bonuses','Tips addition','Working days settlement','Working days','Absence deduction','Deductions','Advances','Long-term advances','Withheld deposits','Loadings','Tips','Social security','Housing','Remaining value','Net','Status'];
        $rows=[];$i=0;
        foreach(self::LABELS as $key=>$label){
            $row=['key'=>$key,'label'=>$label,'label_en'=>$en[$i++],'value'=>$key==='status' ? ($p['status']??'') : (float)($p[$fields[$key]]??0),'unit'=>in_array($key,['working_days','overtime_request'],true)?'days':'money'];
            $row['section']=in_array($key,['base','overtime','overtime_request','bonuses','tips_addition','work_days_settlement'],true)?'earnings':(in_array($key,['remaining','net','status'],true)?'summary':'deductions');
            foreach(['overtime'=>'overtime_days','absence'=>'absence_days','remaining'=>'remaining_days'] as $k=>$days)if($key===$k)$row['days']=(int)($p[$days]??0);
            if($key==='status'){$row['unit']='status';$row['value']=['draft'=>'مسودة','approved'=>'معتمد','paid'=>'مدفوع'][$p['status']??'']??($p['status']??'');}
            $rows[]=$row;
        }
        return $rows;
    }
}
