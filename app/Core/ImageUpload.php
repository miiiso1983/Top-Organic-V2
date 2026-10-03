<?php
namespace App\Core;

/**
 * حفظ صور الأصناف (رفع مباشر من الويب أو Base64 من تطبيق الموبايل)
 */
class ImageUpload
{
    private const ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const MAX_BYTES = 5 * 1024 * 1024; // 5MB

    /** رفع من نموذج ويب (multipart/form-data) عبر $_FILES[$field] */
    public static function fromMultipart(string $field, string $subdir): ?string
    {
        if (empty($_FILES[$field]['tmp_name']) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        $tmp = $_FILES[$field]['tmp_name'];
        if (($_FILES[$field]['size'] ?? 0) > self::MAX_BYTES) {
            throw new \RuntimeException('حجم الصورة كبير جداً (الحد الأقصى 5MB)');
        }
        $mime = mime_content_type($tmp) ?: '';
        if (!isset(self::ALLOWED[$mime])) {
            throw new \RuntimeException('نوع الصورة غير مدعوم: ' . $mime);
        }
        $dir = BASE_PATH . '/public/uploads/' . $subdir;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('لا يمكن إنشاء مجلد الصور: ' . $dir);
        }
        if (!is_writable($dir)) {
            throw new \RuntimeException('مجلد الصور غير قابل للكتابة، تحقق من صلاحيات المجلد: ' . $dir);
        }
        $name = uniqid('img_', true) . '.' . self::ALLOWED[$mime];
        if (!move_uploaded_file($tmp, $dir . '/' . $name)) {
            throw new \RuntimeException('فشل نقل الملف المرفوع إلى مجلد الصور');
        }
        return '/uploads/' . $subdir . '/' . $name;
    }

    /** حفظ صورة مرسلة كنص Base64 (من تطبيق الموبايل عبر JSON) */
    public static function fromBase64(?string $data, string $subdir): ?string
    {
        if (!$data) {
            return null;
        }
        if (preg_match('/^data:(image\/\w+);base64,(.+)$/', $data, $m)) {
            $mime = $m[1];
            $raw = base64_decode($m[2], true);
        } else {
            $mime = 'image/jpeg';
            $raw = base64_decode($data, true);
        }
        if ($raw === false) {
            throw new \RuntimeException('صورة Base64 غير صالحة');
        }
        if (!isset(self::ALLOWED[$mime])) {
            throw new \RuntimeException('نوع الصورة غير مدعوم: ' . $mime);
        }
        if (strlen($raw) > self::MAX_BYTES) {
            throw new \RuntimeException('حجم الصورة كبير جداً (الحد الأقصى 5MB)');
        }
        $dir = BASE_PATH . '/public/uploads/' . $subdir;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('لا يمكن إنشاء مجلد الصور: ' . $dir);
        }
        if (!is_writable($dir)) {
            throw new \RuntimeException('مجلد الصور غير قابل للكتابة، تحقق من صلاحيات المجلد: ' . $dir);
        }
        $name = uniqid('img_', true) . '.' . self::ALLOWED[$mime];
        if (file_put_contents($dir . '/' . $name, $raw) === false) {
            throw new \RuntimeException('فشل حفظ الصورة في المجلد: ' . $dir);
        }
        return '/uploads/' . $subdir . '/' . $name;
    }
}
