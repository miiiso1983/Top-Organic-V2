<?php
namespace App\Core;

/** Read store publication, independently of a successfully committed upload edit. */
final class GooglePlayReleaseService
{
    private const ROOT='https://androidpublisher.googleapis.com/androidpublisher/v3/applications/com.toporganic.top_organic_app';
    private static function http(string $url, string $method='GET', ?string $body=null, array $headers=[]): array
    {
        $c=curl_init($url);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,
            CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_HTTPHEADER=>$headers]);
        if($body!==null)curl_setopt($c,CURLOPT_POSTFIELDS,$body);
        $raw=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
        if($raw===false || $status<200 || $status>=300)throw new \RuntimeException('Store verification HTTP '.$status);
        return json_decode($raw,true) ?: [];
    }
    private static function token(): string
    {
        $sa=json_decode((string)Settings::get('fcm_service_account_json',''),true);
        if(empty($sa['client_email']) || empty($sa['private_key']))throw new \RuntimeException('Store service account unavailable');
        $b=static fn($data)=>rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'),'=');$now=time();
        $unsigned=$b(['alg'=>'RS256','typ'=>'JWT']).'.'.$b(['iss'=>$sa['client_email'],'scope'=>'https://www.googleapis.com/auth/androidpublisher','aud'=>'https://oauth2.googleapis.com/token','iat'=>$now,'exp'=>$now+3600]);
        if(!openssl_sign($unsigned,$sig,$sa['private_key'],OPENSSL_ALGO_SHA256))throw new \RuntimeException('Store authentication failed');
        $jwt=$unsigned.'.'.rtrim(strtr(base64_encode($sig),'+/','-_'),'=');
        $auth=self::http('https://oauth2.googleapis.com/token','POST',http_build_query(['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$jwt]),['Content-Type: application/x-www-form-urlencoded']);
        if(empty($auth['access_token']))throw new \RuntimeException('Store authentication failed');
        return $auth['access_token'];
    }
    public static function isPublished(array $summary,int $build): bool
    {
        foreach($summary['releases']??[] as $release) {
            if(($release['track']??'')!=='production' || ($release['releaseLifecycleState']??'')!=='RELEASE_LIFECYCLE_STATE_PUBLISHED')continue;
            foreach($release['activeArtifacts']??[] as $artifact)if((int)($artifact['versionCode']??0)===$build)return true;
        }
        return false;
    }
    public static function fullyRolledOut(array $track,int $build): bool
    {
        foreach($track['releases']??[] as $r) {
            if(($r['status']??'')!=='completed' || isset($r['countryTargeting']) || (isset($r['userFraction']) && (float)$r['userFraction']<1))continue;
            if(in_array((string)$build,array_map('strval',$r['versionCodes']??[]),true))return true;
        }
        return false;
    }
    public static function available(int $build): bool
    {
        $headers=['Authorization: Bearer '.self::token(),'Content-Type: application/json'];
        $summary=self::http(self::ROOT.'/tracks/production/releases','GET',null,$headers);
        if(!self::isPublished($summary,$build))return false;
        // Disposable read edit confirms full rollout; always remove it, never commit it.
        $edit=self::http(self::ROOT.'/edits','POST','{}',$headers);$id=$edit['id'];
        try { return self::fullyRolledOut(self::http(self::ROOT.'/edits/'.$id.'/tracks/production','GET',null,$headers),$build); }
        finally { self::http(self::ROOT.'/edits/'.$id,'DELETE',null,$headers); }
    }
    public static function activatePendingAndroid(): string
    {
        $key='mobile_update_android';$before=(string)Settings::get($key,'{}');$p=json_decode($before,true)?:[];
        if(($p['mode']??'')!=='pending' || (int)($p['min_build']??0)<1)return 'not_pending';
        if(!self::available((int)$p['min_build']))return 'awaiting_publication';
        $p['mode']='required';$p['verified_build']=(int)$p['min_build'];$p['verified_at']=gmdate('c');
        $updated=Database::query('UPDATE settings SET svalue=? WHERE skey=? AND svalue=?',[json_encode($p,JSON_UNESCAPED_UNICODE),$key,$before])->rowCount();
        if(!$updated)return 'policy_changed';
        Helpers::audit('mobile_update_policy','settings',null,'تفعيل إلزام أندرويد بعد التحقق من نشر البناء '.$p['min_build'].' بالكامل في Google Play');
        return 'activated';
    }
}
