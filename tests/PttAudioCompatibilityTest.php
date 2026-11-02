<?php
declare(strict_types=1);
require __DIR__.'/../app/Core/Ptt/PttDeliveryService.php';
use App\Core\Ptt\PttDeliveryService;
$ffmpeg=null;foreach(['/usr/bin/ffmpeg','/opt/homebrew/bin/ffmpeg','/usr/local/bin/ffmpeg'] as $path)if(is_executable($path)){$ffmpeg=$path;break;}
if(!$ffmpeg){echo "SKIP: ffmpeg unavailable on this machine.\n";exit;}
define('BASE_PATH',sys_get_temp_dir().'/ptt-codec-test-'.bin2hex(random_bytes(6)));
mkdir(BASE_PATH,0700);
function runAudioCommand(array $args):void{
 $p=proc_open($args,[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes);
 if(!is_resource($p)||proc_close($p)!==0)throw new RuntimeException('Codec fixture failed');
}
try{
 foreach(['webm','ogg'] as $ext){
  $source=BASE_PATH.'/synthetic.'.$ext;
  runAudioCommand([$ffmpeg,'-nostdin','-y','-f','lavfi','-i','anullsrc=r=24000:cl=mono','-t','0.2','-c:a','libopus',$source]);
  $before=hash_file('sha256',$source);$converted=PttDeliveryService::compatibleAudio($source);
  if(!is_file($converted)||!filesize($converted)||$before!==hash_file('sha256',$source))throw new RuntimeException('Conversion must preserve original');
  if($converted!==PttDeliveryService::compatibleAudio($source))throw new RuntimeException('Conversion cache not reused');
  runAudioCommand([$ffmpeg,'-nostdin','-i',$converted,'-f','null','-']);
 }
 file_put_contents(BASE_PATH.'/invalid.webm','not audio');$rejected=false;
 try{PttDeliveryService::compatibleAudio(BASE_PATH.'/invalid.webm');}catch(RuntimeException){$rejected=true;}
 if(!$rejected)throw new RuntimeException('Invalid audio accepted');
 echo "PASS: synthetic WebM/Ogg converted to decodable AAC, original preserved, cache reused, corrupt audio rejected.\n";
}finally{
 $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
 foreach($files as $f){if($f->isDir())rmdir($f->getPathname());else unlink($f->getPathname());}rmdir(BASE_PATH);
}
