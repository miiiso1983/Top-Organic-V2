<?php
namespace App\Core;

final class MobileUpdateService
{
    public const STORES = [
        'android'=>'https://play.google.com/store/apps/details?id=com.toporganic.top_organic_app',
        'ios'=>'https://apps.apple.com/app/id6798642181',
    ];
    public static function policy(string $platform): array
    {
        $raw = isset(self::STORES[$platform]) ? json_decode((string)Settings::get('mobile_update_'.$platform, '{}'), true) : [];
        $raw = is_array($raw) ? $raw : [];
        return array_merge(['mode'=>'off','min_build'=>0,'version'=>'','verified_build'=>0,'verified_at'=>null], $raw);
    }
    public static function required(array $policy, int $build): bool
    {
        // A desired minimum is never enough: store availability must be verified first.
        return ($policy['mode'] ?? '') === 'required' && (int)$policy['min_build'] > 0
            && (int)($policy['verified_build'] ?? 0) >= (int)$policy['min_build'] && $build < (int)$policy['min_build'];
    }
    public static function payload(string $platform, int $build): array
    {
        $p = self::policy($platform);
        return ['platform'=>$platform,'minimum_build'=>(int)$p['min_build'],
            'version'=>$p['version'],'required'=>self::required($p,$build),
            'enabled'=>$p['mode']==='required' && (int)$p['verified_build'] >= (int)$p['min_build'],
            'store_url'=>self::STORES[$platform] ?? '',
            'message'=>'يرجى تحديث تطبيق Top Organic من المتجر للاستمرار. التحديث مطلوب للحفاظ على أمان تسجيل الحضور والانصراف.'];
    }
    public static function headerClient(): ?array
    {
        $p = strtolower((string)($_SERVER['HTTP_X_APP_PLATFORM'] ?? ''));
        $b = (string)($_SERVER['HTTP_X_APP_BUILD'] ?? '');
        if (!isset(self::STORES[$p]) || !preg_match('/^[1-9][0-9]{0,8}$/D',$b)) return null;
        return ['platform'=>$p,'build_number'=>(int)$b,'version_name'=>substr((string)($_SERVER['HTTP_X_APP_VERSION'] ?? ''),0,32)];
    }
    private static function tokenHash(): ?string
    {
        return preg_match('/^Bearer\s+(.+)$/i',(string)($_SERVER['HTTP_AUTHORIZATION']??''),$m) ? hash('sha256',$m[1]) : null;
    }
    public static function remember(int $user, ?string $legacyPlatform = null): void
    {
        $hash=self::tokenHash(); $client=self::headerClient();
        if (!$client && isset(self::STORES[$legacyPlatform ?? ''])) $client=['platform'=>$legacyPlatform,'build_number'=>0,'version_name'=>''];
        if (!$hash || !$client) return;
        $old=Database::query('SELECT * FROM mobile_app_sessions WHERE token_hash=? AND user_id=?',[$hash,$user])->fetch();
        if ($old) {
            // Old tokens may have been shared between devices. Never infer a platform from the user's other sessions.
            $ambiguous=(int)$old['ambiguous'] || $old['platform']!==$client['platform'];
            // Push registration from an old native client must not erase known build metadata.
            $build=max((int)$old['build_number'], $client['build_number']);
            Database::query('UPDATE mobile_app_sessions SET ambiguous=?,build_number=?,version_name=?,last_seen=CURRENT_TIMESTAMP WHERE token_hash=?',
                [(int)$ambiguous,$build,$client['version_name'] ?: $old['version_name'],$hash]);
        } else {
            Database::query('INSERT IGNORE INTO mobile_app_sessions (token_hash,user_id,platform,build_number,version_name,last_seen) VALUES (?,?,?,?,?,CURRENT_TIMESTAMP)',
                [$hash,$user,$client['platform'],$client['build_number'],$client['version_name']]);
        }
    }
    public static function requestClient(?int $user): ?array
    {
        if ($c=self::headerClient()) return $c;
        $hash=self::tokenHash();
        if (!$user || !$hash) return null;
        $c=Database::query('SELECT * FROM mobile_app_sessions WHERE token_hash=? AND user_id=?',[$hash,$user])->fetch();
        return $c && !(int)$c['ambiguous'] ? $c : null;
    }
    public static function rejectIfRequired(?int $user = null): bool
    {
        if ($user) self::remember($user);
        $c=self::requestClient($user);
        if (!$c) return false; // Unknown legacy sessions remain compatible until the device identifies itself.
        $data=self::payload($c['platform'],(int)$c['build_number']);
        if (!$data['required']) return false;
        SystemActivityService::outcome('rejected');
        Response::json(['ok'=>false,'error'=>$data['message'],'error_code'=>'app_update_required','update'=>$data],426);
        return true;
    }
}
