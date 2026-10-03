<?php
declare(strict_types=1);

namespace App\Core;

class DocumentUpload
{
    private const ALLOWED = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];
    private const MAX_BYTES = 10 * 1024 * 1024; // 10MB

    public static function fromMultipart(string $field, string $subdir): ?string
    {
        if (empty($_FILES[$field]['tmp_name']) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        return self::fromFilesArray([
            'tmp_name' => $_FILES[$field]['tmp_name'],
            'error' => $_FILES[$field]['error'],
            'size' => $_FILES[$field]['size'] ?? 0,
            'name' => $_FILES[$field]['name'] ?? '',
        ], $subdir);
    }

    public static function fromFilesArray(array $file, string $subdir): ?string
    {
        if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        $tmp = $file['tmp_name'];
        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new \RuntimeException('حجم الملف كبير جداً (الحد الأقصى 10MB)');
        }
        $mime = mime_content_type($tmp) ?: '';
        if (!isset(self::ALLOWED[$mime])) {
            throw new \RuntimeException('نوع الملف غير مدعوم: ' . $mime);
        }
        $dir = BASE_PATH . '/public/uploads/' . $subdir;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('لا يمكن إنشاء مجلد المستندات: ' . $dir);
        }
        if (!is_writable($dir)) {
            throw new \RuntimeException('مجلد المستندات غير قابل للكتابة: ' . $dir);
        }
        $name = uniqid('doc_', true) . '.' . self::ALLOWED[$mime];
        if (!move_uploaded_file($tmp, $dir . '/' . $name)) {
            throw new \RuntimeException('فشل نقل الملف المرفوع');
        }
        return '/uploads/' . $subdir . '/' . $name;
    }
}
