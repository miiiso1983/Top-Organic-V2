<?php
namespace App\Core;

use Aws\Rekognition\RekognitionClient;
use Aws\Exception\AwsException;

/**
 * خدمة التعرف على الوجوه باستخدام AWS Rekognition
 */
class FaceRecognitionService
{
    private static ?RekognitionClient $client = null;

    public static function isConfigured(): bool
    {
        $key = Settings::get('aws_access_key', '');
        $secret = Settings::get('aws_secret_key', '');
        $region = Settings::get('aws_region', '');
        return !empty($key) && !empty($secret) && !empty($region);
    }

    private static function client(): ?RekognitionClient
    {
        if (self::$client !== null) return self::$client;
        if (!self::isConfigured()) return null;

        self::$client = new RekognitionClient([
            'version' => 'latest',
            'region'  => Settings::get('aws_region'),
            'credentials' => [
                'key'    => Settings::get('aws_access_key'),
                'secret' => Settings::get('aws_secret_key'),
            ],
        ]);
        return self::$client;
    }

    /**
     * مقارنة وجهين من ملفين على السيرفر.
     * @return array{ok: bool, similarity: float|null, error: string|null}
     */
    public static function compareFiles(string $sourcePath, string $targetPath): array
    {
        if (!self::isConfigured()) {
            return ['ok' => false, 'similarity' => null, 'error' => 'AWS Rekognition غير مضبوط'];
        }
        if (!is_file($sourcePath) || !is_file($targetPath)) {
            return ['ok' => false, 'similarity' => null, 'error' => 'صورة المرجع أو السيلفي غير موجودة'];
        }

        $client = self::client();
        if (!$client) {
            return ['ok' => false, 'similarity' => null, 'error' => 'تعذر إنشاء عميل AWS'];
        }

        try {
            $result = $client->compareFaces([
                'SourceImage' => ['Bytes' => file_get_contents($sourcePath)],
                'TargetImage' => ['Bytes' => file_get_contents($targetPath)],
                'SimilarityThreshold' => 0,
            ]);

            $matches = $result['FaceMatches'] ?? [];
            if (empty($matches)) {
                return ['ok' => false, 'similarity' => 0.0, 'error' => null];
            }

            $similarity = (float)($matches[0]['Similarity'] ?? 0);
            return ['ok' => true, 'similarity' => $similarity, 'error' => null];
        } catch (AwsException $e) {
            error_log('[FaceRecognition] AWS error: ' . $e->getMessage());
            return ['ok' => false, 'similarity' => null, 'error' => 'خطأ AWS: ' . $e->getAwsErrorMessage()];
        } catch (\Throwable $e) {
            error_log('[FaceRecognition] error: ' . $e->getMessage());
            return ['ok' => false, 'similarity' => null, 'error' => 'خطأ داخلي: ' . $e->getMessage()];
        }
    }

    /**
     * مقارنة وجهين من بيانات base64 (المرجع ملف، السيلفي base64).
     * @return array{ok: bool, similarity: float|null, error: string|null}
     */
    public static function compareBase64(string $sourcePath, string $base64Target): array
    {
        if (!self::isConfigured()) {
            return ['ok' => false, 'similarity' => null, 'error' => 'AWS Rekognition غير مضبوط'];
        }
        if (!is_file($sourcePath)) {
            return ['ok' => false, 'similarity' => null, 'error' => 'صورة المرجع غير موجودة'];
        }
        $targetBytes = self::decodeBase64($base64Target);
        if ($targetBytes === null) {
            return ['ok' => false, 'similarity' => null, 'error' => 'صورة السيلفي غير صالحة'];
        }

        $client = self::client();
        if (!$client) {
            return ['ok' => false, 'similarity' => null, 'error' => 'تعذر إنشاء عميل AWS'];
        }

        try {
            $result = $client->compareFaces([
                'SourceImage' => ['Bytes' => file_get_contents($sourcePath)],
                'TargetImage' => ['Bytes' => $targetBytes],
                'SimilarityThreshold' => 0,
            ]);

            $matches = $result['FaceMatches'] ?? [];
            if (empty($matches)) {
                return ['ok' => false, 'similarity' => 0.0, 'error' => null];
            }

            $similarity = (float)($matches[0]['Similarity'] ?? 0);
            return ['ok' => true, 'similarity' => $similarity, 'error' => null];
        } catch (AwsException $e) {
            error_log('[FaceRecognition] AWS error: ' . $e->getMessage());
            return ['ok' => false, 'similarity' => null, 'error' => 'خطأ AWS: ' . $e->getAwsErrorMessage()];
        } catch (\Throwable $e) {
            error_log('[FaceRecognition] error: ' . $e->getMessage());
            return ['ok' => false, 'similarity' => null, 'error' => 'خطأ داخلي: ' . $e->getMessage()];
        }
    }

    private static function decodeBase64(string $data): ?string
    {
        if (str_starts_with($data, 'data:image')) {
            if (!preg_match('/^data:image\/(\w+);base64,(.+)$/', $data, $m)) {
                return null;
            }
            $data = $m[2];
        }
        $bytes = base64_decode($data, true);
        return $bytes === false ? null : $bytes;
    }
}
