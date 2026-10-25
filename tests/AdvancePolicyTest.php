<?php
declare(strict_types=1);
require __DIR__.'/../app/Core/AdvancePolicyService.php';
use App\Core\AdvancePolicyService as Policy;
function verify(bool $ok,string $message):void { if(!$ok) throw new RuntimeException($message); }
function rejects(callable $fn):void { try{$fn();}catch(RuntimeException $e){return;}throw new RuntimeException('Expected rejection'); }
// Payroll contains a 50,000 installment, but the full remaining debt is 300,000.
$s=Policy::amounts(1000000,['total_earnings'=>500000,'total_deductions'=>100000,'advances'=>50000],300000);
verify($s['remaining']===150000.0,'Full debt replaces this month installment without double deduction');
verify($s['salary_cap']===250000.0,'25 percent salary');
$s2=Policy::amounts(1000000,['total_earnings'=>800000,'total_deductions'=>50000],0);
verify($s2['remaining']===250000.0,'Salary percentage is the upper bound');
$s3=Policy::amounts(1000000,['total_earnings'=>100000,'total_deductions'=>100000,'deficit_amount'=>50000,'rounding_adjustment'=>250],0);
verify($s3['remaining']===0.0 && $s3['net_entitlement']===-50250.0,'Restore deficit and rounding before calculating available funds');
verify(Policy::amounts(1000000,['total_earnings'=>800000],0,100000)['remaining']===150000.0,'Reserve open requests');
$args=['hr_advance',150000,$s,'2026-10-13',false,'',1];Policy::assertRules(...$args);
$args[3]='2026-10-12';rejects(fn()=>Policy::assertRules(...$args));
$args[0]='hr_exceptional_advance';$args[5]='سبب استثنائي';Policy::assertRules(...$args);
$args[4]=true;rejects(fn()=>Policy::assertRules(...$args));
$args[4]=false;$args[1]=150000.01;rejects(fn()=>Policy::assertRules(...$args));
$args[1]=0;rejects(fn()=>Policy::assertRules(...$args));
$args[0]='hr_long_advance';$args[1]=1200000;$args[6]=12;Policy::assertRules(...$args);
$args[5]='';rejects(fn()=>Policy::assertRules(...$args));
$args[5]='سبب';$args[6]=1;rejects(fn()=>Policy::assertRules(...$args));
echo "Advance policy: calculation, debt, rounding, date, duplicate and long-term rules passed.\n";
