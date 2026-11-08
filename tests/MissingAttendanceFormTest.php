<?php
/** Render the actual web view using synthetic data; no database or session login. */
namespace App\Core {
    class Helpers { public static function e($value): string { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); } }
    class Database { public static function query(...$args): object { return new class { public function fetch(): array { return ['id'=>1,'full_name'=>'موظف تجريبي']; } }; } }
    class AdvancePolicyService { public static function isAdvance($code): bool { return false; } }
}
namespace {
    $_SESSION=['user_id'=>1];
    $template=['id'=>1,'code'=>'hr_missing_attendance','name'=>'نسيان بصمة','fields'=>[
        ['name'=>'request_type','type'=>'select','label'=>'نوع البصمة','required'=>true,'options'=>[['value'=>'in','label'=>'دخول'],['value'=>'out','label'=>'خروج'],['value'=>'both','label'=>'دخول وخروج']]],
        ['name'=>'clock_in_at','type'=>'datetime','label'=>'وقت الدخول'],
        ['name'=>'clock_out_at','type'=>'datetime','label'=>'وقت الخروج'],
        ['name'=>'reason','type'=>'textarea','label'=>'السبب','required'=>true],
        ['name'=>'correction_mode','type'=>'select','label'=>'نوع المعالجة','options'=>['add','correct']],
        ['name'=>'original_in_at','type'=>'datetime','label'=>'الدخول الأصلي'],
        ['name'=>'original_out_at','type'=>'datetime','label'=>'الخروج الأصلي'],
    ]];
    $request=['id'=>1,'status'=>'returned_for_edit','data'=>['request_type'=>'out','clock_out_at'=>'2026-10-16 04:00:00']];
    ob_start();require __DIR__.'/../app/Views/workflows/request_form.php';$html=ob_get_clean();
    foreach(['name="correction_mode"','name="original_in_at"','name="original_out_at"','id="request_category"'] as $removed)
        if(str_contains($html,$removed))throw new \RuntimeException('Obsolete control remains: '.$removed);
    if(substr_count($html,'type="datetime-local"')!==2 || !str_contains($html,'value="out" selected') || !str_contains($html,'value="2026-10-16T04:00:00"'))throw new \RuntimeException('Punch type or date/time rendering failed');
    if(($argv[1]??'')==='--html')echo $html;
    else echo "PASS: one missing-attendance web form, legacy controls hidden, normal punch options and date/time values preserved.\n";
}
