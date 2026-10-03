<?php
namespace App\Models;

use App\Core\Database;

class PlateCostImportRun
{
    public static function create(string $filename, ?int $userId = null): int
    {
        Database::query(
            "INSERT INTO plate_cost_import_runs (source_filename, status, created_by) VALUES (?, 'pending', ?)",
            [$filename, $userId]
        );
        return (int) Database::lastInsertId();
    }

    public static function get(int $id): ?array
    {
        $row = Database::query("SELECT * FROM plate_cost_import_runs WHERE id = ?", [$id])->fetch();
        return $row ?: null;
    }

    public static function list(): array
    {
        return Database::query("SELECT * FROM plate_cost_import_runs ORDER BY id DESC")->fetchAll();
    }

    public static function updateStatus(int $id, string $status, array $errors = [], array $warnings = []): void
    {
        Database::query(
            "UPDATE plate_cost_import_runs SET status = ?, errors_json = ?, warnings_json = ? WHERE id = ?",
            [$status, json_encode($errors, JSON_UNESCAPED_UNICODE), json_encode($warnings, JSON_UNESCAPED_UNICODE), $id]
        );
    }
}
