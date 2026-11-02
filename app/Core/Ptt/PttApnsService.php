<?php
namespace App\Core\Ptt;
use App\Core\{Database, Settings};

/** Apple's ephemeral Push-to-Talk tokens are distinct from ordinary FCM tokens. */
final class PttApnsService
{
    public static function configured(): bool
    {
        return Settings::get('ptt_apns_key_id','')!=='' && Settings::get('ptt_apns_team_id','')!=='' && Settings::get('ptt_apns_private_key','')!=='';
    }
    public static function register(int $uid,array $data): void
    {
        $device=(string)($data['device_id']??'');$token=strtolower((string)($data['token']??''));
        if(!preg_match('/^[a-f0-9-]{36}$/i',$device)||!preg_match('/^[a-f0-9]{32,256}$/',$token))throw new \RuntimeException('بيانات جهاز PTT غير صالحة');
        $environment=($data['environment']??'')==='development'?'development':'production';
        Database::query('INSERT INTO ptt_receiver_devices (device_id,user_id,token,environment,is_active) VALUES (?,?,?,?,1) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),token=VALUES(token),environment=VALUES(environment),is_active=1,updated_at=CURRENT_TIMESTAMP',[$device,$uid,$token,$environment]);
    }
    public static function unregister(int $uid,string $device): void
    {
        Database::query('UPDATE ptt_receiver_devices SET is_active=0 WHERE device_id=? AND user_id=?',[$device,$uid]);
    }
    public static function notify(int $uid,int $messageId,string $sender): void
    {
        if(!self::configured()||!PttDeliveryService::authorizedMessage($messageId,$uid))return;
        $devices=Database::query('SELECT device_id,token,environment FROM ptt_receiver_devices WHERE user_id=? AND is_active=1',[$uid])->fetchAll();
        if(!$devices)return;
        $jwt=self::jwt();$bundle=(string)Settings::get('ptt_apns_bundle_id','com.toporganic.topOrganicApp');
        foreach($devices as $d){
            $host=$d['environment']==='development'?'api.sandbox.push.apple.com':'api.push.apple.com';
            $ch=curl_init('https://'.$host.'/3/device/'.$d['token']);
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTP_VERSION=>CURL_HTTP_VERSION_2_0,
                CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>5,CURLOPT_HTTPHEADER=>['authorization: bearer '.$jwt,'apns-topic: '.$bundle.'.voip-ptt','apns-push-type: pushtotalk','apns-priority: 10','apns-expiration: 0','content-type: application/json'],
                CURLOPT_POSTFIELDS=>json_encode(['message_id'=>$messageId,'recipient_user_id'=>$uid,'sender_name'=>$sender],JSON_UNESCAPED_UNICODE)]);
            curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
            if($status===410)self::unregister($uid,$d['device_id']);
            elseif($status!==200)error_log('PTT APNs delivery failed: HTTP '.$status.' message '.$messageId);
        }
    }
    public static function jwt(): string
    {
        $b64=fn($v)=>rtrim(strtr(base64_encode($v),'+/','-_'),'=');
        $data=$b64(json_encode(['alg'=>'ES256','kid'=>Settings::get('ptt_apns_key_id')])).'.'.$b64(json_encode(['iss'=>Settings::get('ptt_apns_team_id'),'iat'=>time()]));
        if(!openssl_sign($data,$signature,(string)Settings::get('ptt_apns_private_key'),OPENSSL_ALGO_SHA256))throw new \RuntimeException('Invalid APNs signing key');
        if(ord($signature[0])!==48||ord($signature[2])!==2)throw new \RuntimeException('Invalid ECDSA signature');
        $rl=ord($signature[3]);$r=substr($signature,4,$rl);$sl=ord($signature[5+$rl]);$s=substr($signature,6+$rl,$sl);
        $r=ltrim($r,"\0");$s=ltrim($s,"\0");if(strlen($r)>32||strlen($s)>32)throw new \RuntimeException('APNs requires a P-256 key');
        return $data.'.'.$b64(str_pad($r,32,"\0",STR_PAD_LEFT).str_pad($s,32,"\0",STR_PAD_LEFT));
    }
}
