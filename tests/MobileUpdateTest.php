<?php
namespace App\Core {
 class Settings {public static array $values=[];public static function get($k,$d=null){return self::$values[$k]??$d;}}
 class Database {public static \PDO $db;public static function query($sql,$args=[]){$sql=str_replace('INSERT IGNORE','INSERT OR IGNORE',$sql);$s=self::$db->prepare($sql);$s->execute($args);return $s;}}
 class Response {public static $data;public static $status;public static function json($d,$s=200){self::$data=$d;self::$status=$s;}}
 class SystemActivityService {public static function outcome($s){} public static function begin(){} public static function route(...$args){}}
 class Auth {public static function check(){return false;}public static function canAny($p){return true;} public static function effectivePermissions(...$a){return [];}}
 class ApiAuth {public static function check(){return true;}public static function id(){return 7;}public static function user(){return ['id'=>7,'full_name'=>'Test','role_id'=>1,'role_name'=>'Employee','branch_id'=>1];}}
}
namespace {
 require __DIR__.'/../app/Core/MobileUpdateService.php';require __DIR__.'/../app/Core/GooglePlayReleaseService.php';
 use App\Core\{MobileUpdateService as U,GooglePlayReleaseService as G,Settings,Database,Response};
 function check($v,$label){if(!$v)throw new RuntimeException($label);echo "PASS $label\n";}
 Database::$db=new PDO('sqlite::memory:');Database::$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);Database::$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
 Database::$db->exec('CREATE TABLE mobile_app_sessions(token_hash TEXT PRIMARY KEY,user_id INTEGER,platform TEXT,build_number INTEGER,version_name TEXT,ambiguous INTEGER DEFAULT 0,last_seen TEXT)');
 $p=['mode'=>'pending','min_build'=>68,'verified_build'=>0,'version'=>'1.3.8'];
 check(!U::required($p,67),'pending cannot block');$p['mode']='required';check(!U::required($p,67),'unverified cannot block');$p['verified_build']=68;
 Settings::$values['mobile_update_android']=json_encode($p);Settings::$values['mobile_update_ios']=json_encode(['mode'=>'off','min_build'=>68]);
 check(U::payload('android',67)['required'],'old Android blocked');check(!U::payload('android',68)['required'],'current Android allowed');check(!U::payload('android',69)['required'],'future Android allowed');check(!U::payload('ios',66)['required'],'iOS independently off');
 $_SERVER=['HTTP_AUTHORIZATION'=>'Bearer test-token'];check(!U::rejectIfRequired(7),'unknown platform not guessed');
 U::remember(7,'android');check(U::rejectIfRequired(7) && Response::$status===426 && Response::$data['error_code']==='app_update_required','legacy Android identified by same session');
 check(!U::rejectIfRequired(8),'other user cannot inherit session');
 $_SERVER['HTTP_X_APP_PLATFORM']='android';$_SERVER['HTTP_X_APP_BUILD']='68';$_SERVER['HTTP_X_APP_VERSION']='1.3.8';check(!U::rejectIfRequired(7),'updated session allowed');
 unset($_SERVER['HTTP_X_APP_PLATFORM'],$_SERVER['HTTP_X_APP_BUILD'],$_SERVER['HTTP_X_APP_VERSION']);check(!U::rejectIfRequired(7),'native background client inherits same session build');
 $_SERVER=['HTTP_AUTHORIZATION'=>'Bearer shared-token'];U::remember(7,'android');U::remember(7,'ios');check(U::requestClient(7)===null,'cross-platform shared legacy token not guessed');
 $_SERVER=['HTTP_X_APP_PLATFORM'=>'android','HTTP_X_APP_BUILD'=>'67x'];check(U::headerClient()===null,'malformed build rejected');
 $summary=['releases'=>[['track'=>'production','releaseLifecycleState'=>'RELEASE_LIFECYCLE_STATE_IN_REVIEW','activeArtifacts'=>[['versionCode'=>68]]]]];
 check(!G::isPublished($summary,68),'review is not availability');$summary['releases'][0]['releaseLifecycleState']='RELEASE_LIFECYCLE_STATE_APPROVED_NOT_PUBLISHED';check(!G::isPublished($summary,68),'approved not published cannot activate');$summary['releases'][0]['releaseLifecycleState']='RELEASE_LIFECYCLE_STATE_PUBLISHED';check(G::isPublished($summary,68),'published exact build');check(!G::isPublished($summary,67),'wrong build cannot activate');
 $track=['releases'=>[['status'=>'inProgress','versionCodes'=>['68'],'userFraction'=>0.5]]];check(!G::fullyRolledOut($track,68),'partial rollout cannot activate');$track['releases'][0]=['status'=>'completed','versionCodes'=>['68']];check(G::fullyRolledOut($track,68),'full rollout accepted');$track['releases'][0]['countryTargeting']=['countries'=>['US']];check(!G::fullyRolledOut($track,68),'country restricted rollout cannot activate');
 require __DIR__.'/../app/Core/Router.php';
 $calls=0;$router=new \App\Core\Router();$router->post('/api/attendance/check',function()use(&$calls){$calls++;},['api']);
 $_SESSION=[];$_SERVER=['REQUEST_URI'=>'/api/attendance/check','REQUEST_METHOD'=>'POST','HTTP_AUTHORIZATION'=>'Bearer test-router','HTTP_X_APP_PLATFORM'=>'android','HTTP_X_APP_BUILD'=>'67'];$router->dispatch();check($calls===0 && Response::$status===426,'real router prevents attendance side effects');
 $_SERVER['HTTP_X_APP_BUILD']='68';$router->dispatch();check($calls===1,'real router permits current build');
 $_SERVER['HTTP_X_APP_PLATFORM']='ios';$_SERVER['HTTP_X_APP_BUILD']='66';$router->dispatch();check($calls===2,'real router keeps iOS operational');
 echo "All mobile update tests passed\n";
}
