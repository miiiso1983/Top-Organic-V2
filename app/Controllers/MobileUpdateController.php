<?php
namespace App\Controllers;
use App\Core\{Controller,MobileUpdateService,Settings,Database,Helpers,Response};
final class MobileUpdateController extends Controller
{
    public function policy(): void
    {
        header('Cache-Control: no-store');
        $c=MobileUpdateService::headerClient();
        $platform=$c['platform'] ?? (string)($_GET['platform']??'');
        if (!isset(MobileUpdateService::STORES[$platform])) { Response::json(['ok'=>false,'error'=>'منصة غير صالحة'],422);return; }
        Response::json(['ok'=>true,'data'=>MobileUpdateService::payload($platform,(int)($c['build_number']??0))]);
    }
    public function index(): void
    {
        $_SESSION['mobile_update_csrf'] ??= bin2hex(random_bytes(32));
        $platforms=[];foreach(MobileUpdateService::STORES as $key=>$url)$platforms[$key]=MobileUpdateService::policy($key);
        $page=max(1,(int)($_GET['page']??1));$offset=($page-1)*50;
        $sessions=Database::query("SELECT u.full_name,s.platform,s.build_number,s.version_name,s.ambiguous,MAX(s.last_seen) AS last_seen
            FROM mobile_app_sessions s JOIN users u ON u.id=s.user_id
            WHERE s.last_seen>=DATE_SUB(NOW(),INTERVAL 30 DAY)
            GROUP BY u.id,u.full_name,s.platform,s.build_number,s.version_name,s.ambiguous ORDER BY last_seen DESC LIMIT 51 OFFSET $offset")->fetchAll();
        $more=count($sessions)>50;$sessions=array_slice($sessions,0,50);
        $this->view('admin/mobile_updates',['title'=>'تحديثات تطبيق الموظفين','platforms'=>$platforms,'sessions'=>$sessions,'page'=>$page,'more'=>$more]);
    }
    public function save(): void
    {
        if (empty($_SESSION['mobile_update_csrf']) || !hash_equals($_SESSION['mobile_update_csrf'],(string)$this->input('csrf',''))) { Response::abort(403,'أعد فتح الصفحة');return; }
        $platform=(string)$this->input('platform');$mode=(string)$this->input('mode');$build=(int)$this->input('min_build');$version=trim((string)$this->input('version'));
        if (!isset(MobileUpdateService::STORES[$platform]) || !in_array($mode,['off','pending','required'],true) || $build<1 || $build>999999999 || !preg_match('/^[0-9]+(?:\.[0-9]+){1,3}$/D',$version)) { Response::abort(422,'تحقق من المنصة ورقم الإصدار والبناء');return; }
        $old=MobileUpdateService::policy($platform);$p=$old;$p['min_build']=$build;$p['version']=$version;$p['mode']=$mode;
        if ($old['min_build']!==$build || $old['version']!==$version) { $p['verified_build']=0;$p['verified_at']=null; }
        if ($mode==='required' && (int)$p['verified_build']<$build) { $this->flash('danger','لم يتم التحقق من توفر هذه النسخة بالمتجر. اختر انتظار التوفر.');Response::redirect('/admin/mobile-updates');return; }
        if ($platform==='ios' && $mode==='pending') { $this->flash('danger','آيفون مستقل: يبقى متوقفاً حتى التحقق من توفر نسخته ثم تفعيله.');Response::redirect('/admin/mobile-updates');return; }
        Settings::set('mobile_update_'.$platform,json_encode($p,JSON_UNESCAPED_UNICODE));
        Helpers::audit('mobile_update_policy','settings',null,'تعديل سياسة تحديث '.($platform==='android'?'أندرويد':'آيفون').' — البناء '.$build.' — '.['off'=>'متوقف','pending'=>'انتظار توفر المتجر','required'=>'إلزامي'][$mode]);
        $this->flash('success','تم حفظ إعداد هذه المنصة فقط');Response::redirect('/admin/mobile-updates');
    }
}
