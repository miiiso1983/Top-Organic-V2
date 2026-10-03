<?php
declare(strict_types=1);

namespace App\Core;

/**
 * خدمة تحليل سجلات أجهزة البصمة (ZKTeco والأجهزة المشابهة)
 */
class BiometricService
{
    /**
     * قراءة ملف CSV وإرجاع السجلات.
     * يدعم التنسيقات الشائعة للرؤوس ZKTeco:
     * emp_code, date, time, check_type (in/out), device_id
     */
    public static function parseCsv(string $filePath): array
    {
        if (!is_readable($filePath)) {
            return ['error' => 'لا يمكن قراءة الملف'];
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return ['error' => 'فشل في فتح الملف'];
        }

        $headers = fgetcsv($handle, 0, ',', '"', '\\');
        if (!$headers) {
            fclose($handle);
            return ['error' => 'ملف CSV فارز'];
        }

        // البحث عن أسماء الأعمدة الشائعة
        $headerMap = [];
        foreach ($headers as $idx => $h) {
            $headerMap[self::normalize($h)] = $idx;
        }

        $rows = [];
        while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $row = self::extractRow($data, $headerMap);
            if ($row) {
                $rows[] = $row;
            }
        }
        fclose($handle);
        return $rows;
    }

    private static function extractRow(array $data, array $headerMap): ?array
    {
        $empCode = self::value($data, $headerMap, ['emp_code', 'pin', 'id', 'code', 'employee_code', 'badgenumber']);
        $date = self::value($data, $headerMap, ['date', 'check_date', 'day']);
        $time = self::value($data, $headerMap, ['time', 'check_time', 'hour']);
        $type = self::value($data, $headerMap, ['check_type', 'status', 'check_in_out', 'checkinout', 'state']);
        $deviceId = self::value($data, $headerMap, ['device_id', 'device', 'machine']);

        if (!$empCode || !$date || !$time) {
            return null;
        }

        $datetime = self::buildDateTime($date, $time);
        if (!$datetime) {
            return null;
        }

        $checkType = 'in';
        if ($type !== null) {
            $t = strtolower(trim($type));
            if (in_array($t, ['out', 'checkout', 'check-out', '1', 'leave', 'exit'], true)) {
                $checkType = 'out';
            } elseif (in_array($t, ['in', 'checkin', 'check-in', '0', 'enter', 'entry'], true)) {
                $checkType = 'in';
            } elseif (strpos($t, 'out') !== false) {
                $checkType = 'out';
            }
        }

        return [
            'emp_code' => trim($empCode),
            'checked_at' => $datetime,
            'check_type' => $checkType,
            'device_id' => $deviceId ? trim($deviceId) : null,
        ];
    }

    private static function value(array $data, array $headerMap, array $keys): ?string
    {
        foreach ($keys as $k) {
            $idx = $headerMap[$k] ?? null;
            if ($idx !== null && isset($data[$idx]) && $data[$idx] !== '') {
                return $data[$idx];
            }
        }
        return null;
    }

    private static function normalize(string $text): string
    {
        return strtolower(str_replace([' ', '_', '-'], ['', '_', '_'], trim($text)));
    }

    private static function buildDateTime(string $date, string $time): ?string
    {
        $date = trim($date);
        $time = trim($time);
        $formats = [
            'Y-m-d H:i:s', 'Y/m/d H:i:s', 'd-m-Y H:i:s', 'd/m/Y H:i:s',
            'Y-m-d H:i', 'Y/m/d H:i', 'd-m-Y H:i', 'd/m/Y H:i',
        ];
        foreach ($formats as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, "$date $time");
            if ($dt !== false) {
                return $dt->format('Y-m-d H:i:s');
            }
        }
        // إذا كان التاريخ والوقت في عمود مفصولة
        $dt = \DateTime::createFromFormat('Y-m-d', $date);
        if ($dt) {
            $t = \DateTime::createFromFormat('H:i:s', $time) ?: \DateTime::createFromFormat('H:i', $time);
            if ($t) {
                $dt->setTime((int)$t->format('H'), (int)$t->format('i'), (int)$t->format('s'));
                return $dt->format('Y-m-d H:i:s');
            }
        }
        return null;
    }
}
