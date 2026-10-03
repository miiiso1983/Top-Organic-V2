<?php
namespace App\Core;

/**
 * النموذج الأساسي: عمليات CRUD عامة
 */
abstract class Model
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';

    public static function all(string $orderBy = 'id DESC'): array
    {
        return Database::query("SELECT * FROM " . static::$table . " ORDER BY $orderBy")->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $row = Database::query(
            "SELECT * FROM " . static::$table . " WHERE " . static::$primaryKey . " = ?",
            [$id]
        )->fetch();
        return $row ?: null;
    }

    public static function where(string $condition, array $params = [], string $orderBy = 'id DESC'): array
    {
        return Database::query(
            "SELECT * FROM " . static::$table . " WHERE $condition ORDER BY $orderBy",
            $params
        )->fetchAll();
    }

    public static function create(array $data): int
    {
        $cols = array_keys($data);
        $sql  = "INSERT INTO " . static::$table . " (" . implode(',', $cols) . ") VALUES ("
              . implode(',', array_fill(0, count($cols), '?')) . ")";
        Database::query($sql, array_values($data));
        return (int) Database::lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $sets = implode(',', array_map(fn($c) => "$c = ?", array_keys($data)));
        $sql  = "UPDATE " . static::$table . " SET $sets WHERE " . static::$primaryKey . " = ?";
        Database::query($sql, [...array_values($data), $id]);
    }

    public static function delete(int $id): void
    {
        Database::query(
            "DELETE FROM " . static::$table . " WHERE " . static::$primaryKey . " = ?",
            [$id]
        );
    }

    public static function count(string $condition = '1=1', array $params = []): int
    {
        return (int) Database::query(
            "SELECT COUNT(*) AS c FROM " . static::$table . " WHERE $condition",
            $params
        )->fetch()['c'];
    }
}
