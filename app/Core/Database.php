<?php
namespace App\Core;

use PDO;
use PDOException;

/**
 * طبقة الاتصال بقاعدة البيانات (Singleton PDO)
 */
class Database
{
    private static ?PDO $instance = null;

    public static function connect(): PDO
    {
        if (self::$instance === null) {
            $cfg = App::config('db');
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $cfg['host'], $cfg['port'], $cfg['name'], $cfg['charset']
            );
            try {
                self::$instance = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
                self::$instance->exec("SET time_zone = '+03:00'");
            } catch (PDOException $e) {
                http_response_code(500);
                die('فشل الاتصال بقاعدة البيانات: ' . $e->getMessage());
            }
        }
        return self::$instance;
    }

    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function lastInsertId(): string
    {
        return self::connect()->lastInsertId();
    }

    public static function beginTransaction(): void { self::connect()->beginTransaction(); }
    public static function commit(): void { self::connect()->commit(); }
    public static function rollBack(): void
    {
        if (self::connect()->inTransaction()) {
            self::connect()->rollBack();
        }
    }
}
