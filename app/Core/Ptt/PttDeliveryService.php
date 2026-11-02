<?php
namespace App\Core\Ptt;

use App\Core\{Database, Response};

/** Recipient-scoped delivery shared by web and native background receivers. */
final class PttDeliveryService
{
    private const ACCESS = "m.status='sent' AND m.sender_id<>r.user_id AND
        (m.channel_id IS NULL OR (c.is_active=1 AND cm.user_id IS NOT NULL AND cm.muted=0))";

    public static function feed(int $userId, ?int $after, int $limit = 50): array
    {
        $limit=max(1,min(100,$limit));
        if($after===null) return ['cursor'=>(int)Database::query('SELECT COALESCE(MAX(id),0) FROM ptt_message_recipients WHERE user_id=?',[$userId])->fetchColumn(),'messages'=>[],'has_more'=>false];
        // Recipient IDs preserve insertion order across message fan-out.
        $rows=Database::query("SELECT r.id AS delivery_id,m.id,m.channel_id,m.priority,m.duration_sec,
                u.full_name AS sender_name,c.name AS channel_name
            FROM ptt_message_recipients r JOIN ptt_messages m ON m.id=r.message_id
            JOIN users u ON u.id=m.sender_id LEFT JOIN ptt_channels c ON c.id=m.channel_id
            LEFT JOIN ptt_channel_members cm ON cm.channel_id=m.channel_id AND cm.user_id=r.user_id
            WHERE r.user_id=? AND r.id>? AND r.status IN ('sent','delivered') AND ".self::ACCESS."
            ORDER BY r.id LIMIT ?",[$userId,max(0,$after),$limit+1])->fetchAll();
        $more=count($rows)>$limit;$rows=array_slice($rows,0,$limit);
        return ['cursor'=>$rows?(int)end($rows)['delivery_id']:$after,'messages'=>$rows,'has_more'=>$more];
    }

    public static function authorizedMessage(int $messageId,int $userId): ?array
    {
        return Database::query("SELECT m.* FROM ptt_message_recipients r JOIN ptt_messages m ON m.id=r.message_id
            LEFT JOIN ptt_channels c ON c.id=m.channel_id
            LEFT JOIN ptt_channel_members cm ON cm.channel_id=m.channel_id AND cm.user_id=r.user_id
            WHERE r.user_id=? AND m.id=? AND ".self::ACCESS,[$userId,$messageId])->fetch()?:null;
    }

    public static function audio(int $messageId,int $userId): void
    {
        $message=self::authorizedMessage($messageId,$userId);
        if(!$message){Response::json(['ok'=>false,'error'=>'الرسالة غير متاحة لهذا المستخدم'],403);return;}
        $root=realpath(BASE_PATH.'/public/uploads/ptt');
        $path=realpath(BASE_PATH.'/public/'.ltrim($message['file_path'],'/'));
        if(!$root||!$path||!str_starts_with($path,$root.DIRECTORY_SEPARATOR)||!is_file($path)){
            Response::json(['ok'=>false,'error'=>'الملف غير متاح'],404);return;
        }
        $ext=strtolower((string)$message['file_ext']);
        if(in_array($ext,['webm','ogg'],true)){
            try{$path=self::compatibleAudio($path);$ext='m4a';}
            catch(\Throwable $e){error_log('PTT audio conversion unavailable');Response::json(['ok'=>false,'error'=>'تعذر تجهيز الصوت، أعد المحاولة'],503);return;}
        }
        $types=['m4a'=>'audio/mp4','aac'=>'audio/aac','mp3'=>'audio/mpeg','ogg'=>'audio/ogg','webm'=>'audio/webm','wav'=>'audio/wav'];
        header('Content-Type: '.($types[$ext]??'application/octet-stream'));
        header('Content-Length: '.filesize($path));header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
        readfile($path);
    }
    /** Private AAC cache: browser Opus recordings must also play on native iOS. */
    public static function compatibleAudio(string $source): string
    {
        $directory=BASE_PATH.'/storage/ptt_delivery';
        if(!is_dir($directory)&&!mkdir($directory,0700,true)&&!is_dir($directory))throw new \RuntimeException('Cannot create audio cache');
        $target=$directory.'/'.hash_file('sha256',$source).'.m4a';
        $lock=fopen($target.'.lock','c');
        if(!$lock)throw new \RuntimeException('Cannot lock audio cache');
        $tmp=null;$process=null;
        try{
            $deadline=microtime(true)+12;
            while(!flock($lock,LOCK_EX|LOCK_NB)){if(microtime(true)>$deadline)throw new \RuntimeException('Audio cache busy');usleep(50000);}
            if(is_file($target)&&filesize($target)>0)return $target;
            $binary=null;foreach(['/usr/bin/ffmpeg','/opt/homebrew/bin/ffmpeg','/usr/local/bin/ffmpeg'] as $candidate)if(is_executable($candidate)){$binary=$candidate;break;}
            if(!$binary)throw new \RuntimeException('Audio converter unavailable');
            $tmp=tempnam($directory,'converting-');
            $process=proc_open([$binary,'-nostdin','-hide_banner','-loglevel','error','-y','-protocol_whitelist','file,pipe','-i',$source,'-map','0:a:0','-vn','-c:a','aac','-ac','1','-ar','24000','-b:a','48000','-f','ipod',$tmp],[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes);
            if(!is_resource($process))throw new \RuntimeException('Cannot start audio converter');
            do{$state=proc_get_status($process);if(!$state['running'])break;if(microtime(true)>$deadline){proc_terminate($process,9);throw new \RuntimeException('Audio conversion timeout');}usleep(50000);}while(true);
            proc_close($process);$process=null;
            if($state['exitcode']!==0||!filesize($tmp))throw new \RuntimeException('Invalid audio');
            chmod($tmp,0600);if(!rename($tmp,$target))throw new \RuntimeException('Cannot cache audio');$tmp=null;
            return $target;
        }finally{
            if(is_resource($process)){proc_terminate($process,9);proc_close($process);}
            if($tmp&&is_file($tmp))unlink($tmp);flock($lock,LOCK_UN);fclose($lock);
        }
    }

}
