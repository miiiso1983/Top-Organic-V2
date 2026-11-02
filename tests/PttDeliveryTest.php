<?php
declare(strict_types=1);
require __DIR__.'/../app/Core/Database.php';
require __DIR__.'/../app/Core/Settings.php';
require __DIR__.'/../app/Core/Ptt/PttService.php';
require __DIR__.'/../app/Core/Ptt/PttDeliveryService.php';
require __DIR__.'/../app/Core/Ptt/PttApnsService.php';
use App\Core\{Database,Settings};
use App\Core\Ptt\{PttService,PttDeliveryService,PttApnsService};
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
(new ReflectionProperty(Database::class,'instance'))->setValue(null,$db);
foreach([
 'CREATE TABLE users(id INTEGER PRIMARY KEY,full_name TEXT)',
 'CREATE TABLE ptt_channels(id INTEGER PRIMARY KEY,name TEXT,is_active INTEGER,is_readonly INTEGER)',
 'CREATE TABLE ptt_channel_members(channel_id INTEGER,user_id INTEGER,muted INTEGER,can_send INTEGER,role TEXT)',
 'CREATE TABLE ptt_messages(id INTEGER PRIMARY KEY,channel_id INTEGER,sender_id INTEGER,status TEXT,priority TEXT,duration_sec REAL)',
 'CREATE TABLE ptt_message_recipients(id INTEGER PRIMARY KEY,message_id INTEGER,user_id INTEGER,status TEXT)',
 'CREATE TABLE settings(skey TEXT PRIMARY KEY,svalue TEXT)'
] as $sql)$db->exec($sql);
function expect(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
$db->exec("INSERT INTO users VALUES(1,'Sender'),(2,'Receiver'),(3,'Other')");
$db->exec("INSERT INTO ptt_channels VALUES(1,'Authorized',1,0),(2,'Muted',1,0),(3,'Removed',1,0),(4,'Inactive',0,0)");
$db->exec("INSERT INTO ptt_channel_members VALUES(1,2,0,1,'member'),(2,2,1,1,'member'),(4,2,0,1,'member')");
for($id=1;$id<=110;$id++){
 $db->prepare("INSERT INTO ptt_messages VALUES(?,1,1,'sent','normal',3)")->execute([$id]);
 $db->prepare("INSERT INTO ptt_message_recipients VALUES(?,?,2,'sent')")->execute([$id,$id]);
}
expect(PttDeliveryService::feed(2,null)['cursor']===110,'Initial receiver must not autoplay old history');
$all=[];$cursor=0;do{$page=PttDeliveryService::feed(2,$cursor,25);$all=array_merge($all,array_column($page['messages'],'id'));$cursor=$page['cursor'];}while($page['has_more']);
expect($all===range(1,110),'Pagination must deliver every message once, oldest first');
expect(PttDeliveryService::feed(3,0)['messages']===[],'Another user must never receive this feed');
expect(PttDeliveryService::authorizedMessage(1,3)===null,'Audio access requires actual recipient');
foreach([2,3,4] as $channel){$id=110+$channel;$db->exec("INSERT INTO ptt_messages VALUES($id,$channel,1,'sent','normal',1)");$db->exec("INSERT INTO ptt_message_recipients VALUES($id,$id,2,'sent')");expect(PttDeliveryService::authorizedMessage($id,2)===null,'Mute, removed membership and inactive channel must revoke queued audio');}
expect(PttDeliveryService::feed(2,110)['messages']===[],'Disallowed channel deliveries excluded');
$db->exec("UPDATE ptt_message_recipients SET status='played' WHERE id=1");
expect(count(PttDeliveryService::feed(2,0,100)['messages'])===100 && PttDeliveryService::feed(2,0,100)['messages'][0]['id']===2,'Played items must not auto-repeat');
$db->exec("UPDATE ptt_channel_members SET muted=1 WHERE channel_id=1");
expect(PttDeliveryService::authorizedMessage(2,2)===null,'Mute must apply even after fetch');
$db->exec("UPDATE ptt_channel_members SET muted=0,can_send=0 WHERE channel_id=1");
expect(!PttService::canSend(1,2),'Channel send permission must be enforced');
$db->exec("UPDATE ptt_channel_members SET can_send=1 WHERE channel_id=1");
expect(PttService::canSend(1,2),'Authorized send remains allowed');
$db->exec("INSERT INTO ptt_messages VALUES(120,NULL,1,'sent','normal',2); INSERT INTO ptt_message_recipients VALUES(120,120,2,'sent')");
expect(PttDeliveryService::authorizedMessage(120,2)!==null,'Direct designated recipient receives own messages');
// Verify the APNs ES256 JOSE signature against a generated throwaway P-256 key.
$key=openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_EC,'curve_name'=>'prime256v1']);openssl_pkey_export($key,$pem);
foreach(['ptt_apns_key_id'=>'TESTKEY','ptt_apns_team_id'=>'TESTTEAM','ptt_apns_private_key'=>$pem] as $k=>$v)$db->prepare('INSERT INTO settings VALUES(?,?)')->execute([$k,$v]);
$jwt=PttApnsService::jwt();[$header,$payload,$sig]=explode('.',$jwt);$raw=base64_decode(strtr($sig,'-_','+/'));
expect(strlen($raw)===64,'APNs signature must be fixed-width JOSE ES256');
$integer=function($x){$x=ltrim($x,"\0");if($x===''||(ord($x[0])&128))$x="\0".$x;return "\x02".chr(strlen($x)).$x;};
$body=$integer(substr($raw,0,32)).$integer(substr($raw,32));$der="\x30".chr(strlen($body)).$body;
expect(openssl_verify($header.'.'.$payload,$der,openssl_pkey_get_details($key)['key'],OPENSSL_ALGO_SHA256)===1,'APNs signature must validate');
echo "PASS: recipient isolation, current membership, mute/revocation, 110-message pagination, no historical replay, send permissions and APNs signature. No real notifications sent.\n";

// Execute the routes file itself: endpoints placed after its return would be unreachable.
require __DIR__.'/../app/Core/Router.php';
$router=require __DIR__.'/../config/routes.php';
$routes=(new ReflectionProperty($router,'routes'))->getValue($router);
foreach(['/ptt/delivery','/ptt/messages/1/audio','/api/ptt/delivery','/api/ptt/messages/1/audio','/api/ptt/receiver/register','/api/ptt/receiver/remove'] as $path){
 $found=array_values(array_filter($routes,fn($r)=>preg_match($r['pattern'],$path)));
 expect(count($found)===1,'Receiver route must be registered exactly once: '.$path);
 expect(in_array(str_starts_with($path,'/api/')?'api':'auth',$found[0]['middleware'],true),'Receiver route must require authentication');
 if(!str_ends_with($path,'/remove'))expect(in_array('perm:ptt.use',$found[0]['middleware'],true),'Receiver route must require PTT permission');
}
echo "PASS: web/mobile receiver routes registered with authentication and PTT permissions.\n";
