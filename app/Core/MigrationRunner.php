<?php
namespace App\Core;

/**
 * مشغّل الترحيلات — ينفّذ database/*.sql بالترتيب مع سجل يمنع التكرار.
 * متسامح مع أخطاء «موجود مسبقاً» (جدول/عمود/مفتاح مكرر) = تحذير وليس فشل.
 */
final class MigrationRunner
{
    /** أخطاء MySQL التي تُعتبر تحذيراً (الكائن موجود/بذور مكررة/قيود مكررة) */
    private const TOLERATED = [1022, 1050, 1060, 1061, 1062, 1068, 1091, 1826];

    public static function ensureLedger(): void
    {
        Database::query(
            "CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                filename VARCHAR(120) NOT NULL UNIQUE,
                status ENUM('ok','warnings','failed') NOT NULL DEFAULT 'ok',
                statements_ok INT DEFAULT 0,
                statements_warned INT DEFAULT 0,
                error TEXT NULL,
                applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    public static function files(): array
    {
        $files = glob(BASE_PATH . '/database/*.sql') ?: [];
        sort($files);
        return $files;
    }

    public static function applied(): array
    {
        self::ensureLedger();
        $rows = Database::query("SELECT * FROM migrations")->fetchAll();
        $out = [];
        foreach ($rows as $r) { $out[$r['filename']] = $r; }
        return $out;
    }

    public static function pending(): array
    {
        $applied = self::applied();
        return array_values(array_filter(self::files(), function ($f) use ($applied) {
            $name = basename($f);
            return !isset($applied[$name]) || $applied[$name]['status'] === 'failed';
        }));
    }

    /** تشغيل كل الملفات المعلقة بالترتيب — تقرير لكل ملف */
    public static function runPending(): array
    {
        $report = [];
        foreach (self::pending() as $file) {
            $report[basename($file)] = self::runFile($file);
        }
        return $report;
    }

    /** تشغيل ملف واحد مع تحمّل الأخطاء المتوقعة */
    public static function runFile(string $path): array
    {
        self::ensureLedger();
        $name = basename($path);
        $statements = self::splitStatements((string)file_get_contents($path));
        $ok = 0; $warned = 0; $error = null;

        foreach ($statements as $stmt) {
            // SOURCE أمر خاص بـ mysql CLI وليس SQL — يُحسب تحذيراً ويُتخطى
            if (preg_match('/^\s*SOURCE\s/i', $stmt)) {
                $warned++;
                continue;
            }
            $normalized = self::normalizeStatement($stmt);
            $subStatements = is_array($normalized) ? $normalized : [$normalized];
            foreach ($subStatements as $sub) {
                try {
                    Database::query($sub);
                    $ok++;
                } catch (\PDOException $e) {
                    $code = (int)($e->errorInfo[1] ?? 0);
                    if (in_array($code, self::TOLERATED, true)) {
                        $warned++;
                        continue;
                    }
                    $error = "خطأ [$code]: " . $e->getMessage() . ' — الجملة: ' . mb_substr(trim($sub), 0, 200);
                    break 2;
                }
            }
        }

        $status = $error ? 'failed' : ($warned > 0 ? 'warnings' : 'ok');
        Database::query(
            "INSERT INTO migrations (filename, status, statements_ok, statements_warned, error)
             VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), statements_ok = VALUES(statements_ok),
                   statements_warned = VALUES(statements_warned), error = VALUES(error)",
            [$name, $status, $ok, $warned, $error]
        );
        return ['status' => $status, 'ok' => $ok, 'warned' => $warned, 'error' => $error];
    }

    /**
     * مجزئ SQL حقيقي — يقسم عند المُحدِّد في أي موضع من النص،
     * مع احترام النصوص ('...', "...", `...`) والتعليقات وكتل DELIMITER.
     */
    public static function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $delimiter = ';';
        $len = strlen($sql);
        $i = 0;
        $inString = null;      // ' أو " أو `
        $inLineComment = false;
        $inBlockComment = false;

        while ($i < $len) {
            $ch = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';

            // نهاية تعليق سطري
            if ($inLineComment && ($ch === "\n")) {
                $inLineComment = false;
                $i++;
                continue;
            }
            if ($inLineComment) { $i++; continue; }

            // نهاية تعليق كتلي
            if ($inBlockComment) {
                if ($ch === '*' && $next === '/') { $inBlockComment = false; $i += 2; continue; }
                $i++;
                continue;
            }

            // داخل نص مقتبس
            if ($inString !== null) {
                $buffer .= $ch;
                if ($ch === '\\' && $inString !== '`' && $next !== '') {
                    $buffer .= $next; $i += 2; continue;
                }
                if ($ch === $inString) {
                    // علامة اقتباس مزدوجة '' داخل النص
                    if ($next === $inString && $inString !== '`') { $buffer .= $next; $i += 2; continue; }
                    $inString = null;
                }
                $i++;
                continue;
            }

            // بداية تعليقات
            if ($ch === '-' && $next === '-') { $inLineComment = true; $i += 2; continue; }
            if ($ch === '#') { $inLineComment = true; $i++; continue; }
            if ($ch === '/' && $next === '*') { $inBlockComment = true; $i += 2; continue; }

            // بداية نص مقتبس
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $inString = $ch;
                $buffer .= $ch;
                $i++;
                continue;
            }

            // كشف DELIMITER في بداية سطر
            if (($i === 0 || $sql[$i - 1] === "\n") && preg_match('/^DELIMITER\s+(\S+)/i', substr($sql, $i, 30), $m)) {
                $delimiter = $m[1];
                $i += strlen($m[0]);
                // تخطَّ بقية السطر
                while ($i < $len && $sql[$i] !== "\n") $i++;
                continue;
            }

            // نهاية جملة عند المُحدِّد الحالي
            if ($delimiter !== '' && substr($sql, $i, strlen($delimiter)) === $delimiter) {
                $stmt = trim($buffer);
                if (self::hasSqlContent($stmt)) $statements[] = $stmt;
                $buffer = '';
                $i += strlen($delimiter);
                continue;
            }

            $buffer .= $ch;
            $i++;
        }
        if (self::hasSqlContent($buffer)) $statements[] = trim($buffer);
        return $statements;
    }

    private static function hasSqlContent(string $s): bool
    {
        $s = preg_replace('!/\*.*?\*/!s', '', $s);
        foreach (preg_split('/\r?\n/', (string)$s) as $line) {
            $t = trim($line);
            if ($t !== '' && !str_starts_with($t, '--') && !str_starts_with($t, '#')) return true;
        }
        return false;
    }

    /**
     * تحويل جمل DDL غير مدعومة في MySQL 8 (مثل ADD COLUMN IF NOT EXISTS)
     * إلى جمل مدعومة. الأخطاء الناتجة عن وجود العمود/المفتاح مسبقاً
     * تُعالج لاحقاً كتحذيرات من خلال TOLERATED.
     *
     * إن كانت جملة ALTER TABLE تحتوي على عدة بنود (ADD/...) مفصولة بفاصلة،
     * تُفكك إلى جمل مستقلة لضمان تنفيذ كل بند على حدة، وإلا يفشل البند
     * كله عند وجود عمود واحد مسبقاً.
     *
     * @return string|string[]
     */
    private static function normalizeStatement(string $stmt)
    {
        $stmt = preg_replace('/\bADD COLUMN IF NOT EXISTS\b/i', 'ADD COLUMN', $stmt);
        $stmt = preg_replace('/\bADD INDEX IF NOT EXISTS\b/i', 'ADD INDEX', $stmt);
        $stmt = preg_replace('/\bADD FOREIGN KEY IF NOT EXISTS\b/i', 'ADD FOREIGN KEY', $stmt);

        if (preg_match('/^\s*ALTER\s+TABLE\s+((?:`[^`]+`|\w+))\s+(.+)$/is', $stmt, $m)) {
            $table = $m[1];
            $clauses = self::splitAlterClauses($m[2]);
            if (count($clauses) > 1) {
                return array_values(array_filter(
                    array_map(fn (string $c): string => "ALTER TABLE $table " . trim($c, ', '), $clauses)
                ));
            }
        }
        return $stmt;
    }

    /** تقسيم بند ALTER TABLE إلى بنود منفصلة عند كلمات ADD/DROP/MODIFY/CHANGE/ALTER. */
    private static function splitAlterClauses(string $rest): array
    {
        $parts = preg_split('/\b(ADD|DROP|MODIFY|CHANGE|ALTER)\b(?=\s)/i', $rest, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $clauses = [];
        for ($i = 0; $i < count($parts); $i += 2) {
            $keyword = $parts[$i] ?? '';
            $body = $parts[$i + 1] ?? '';
            if ($keyword === '' || $body === '') {
                continue;
            }
            $clauses[] = $keyword . ' ' . trim($body, ", \t\n\r\0\x0B");
        }
        return $clauses;
    }
}
