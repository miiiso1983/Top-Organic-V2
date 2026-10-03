<?php
namespace App\Core;

/**
 * خدمة إدارة الأصول الثابتة: دورة حياة، صيانة، مواقع، وإهلاك
 */
class FixedAssetService
{
    const METHODS = ['straight_line' => 'القسط الثابت', 'declining_balance' => 'المتناقص', 'units_of_production' => 'وحدات الإنتاج'];
    const TYPES = ['purchase' => 'شراء', 'sale' => 'بيع', 'transfer' => 'تحويل', 'maintenance' => 'صيانة', 'disposal' => 'استبعاد', 'revaluation' => 'إعادة تقييم', 'inventory' => 'جرد', 'depreciation' => 'إهلاك'];

    public static function list(array $filters = []): array
    {
        $sql = "SELECT fa.*, ac.name AS category_name, b.name AS branch_name, cc.name AS cost_center_name, u.full_name AS responsible_name, op.title AS department_name
                FROM fixed_assets fa
                LEFT JOIN asset_categories ac ON ac.id = fa.category_id
                LEFT JOIN branches b ON b.id = fa.branch_id
                LEFT JOIN cost_centers cc ON cc.id = fa.cost_center_id
                LEFT JOIN users u ON u.id = fa.responsible_user_id
                LEFT JOIN org_positions op ON op.id = fa.department_id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['status'])) { $sql .= " AND fa.status = ?"; $params[] = $filters['status']; }
        if (!empty($filters['branch_id'])) { $sql .= " AND fa.branch_id = ?"; $params[] = (int)$filters['branch_id']; }
        if (!empty($filters['category_id'])) { $sql .= " AND fa.category_id = ?"; $params[] = (int)$filters['category_id']; }
        if (!empty($filters['q'])) { $sql .= " AND (fa.name LIKE ? OR fa.asset_no LIKE ? OR fa.barcode LIKE ?)"; $v = '%' . $filters['q'] . '%'; $params = array_merge($params, [$v, $v, $v]); }
        $sql .= " ORDER BY fa.id DESC";
        return Database::query($sql, $params)->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $row = Database::query(
            "SELECT fa.*, ac.name AS category_name, b.name AS branch_name, u.full_name AS responsible_name, op.title AS department_name
             FROM fixed_assets fa
             LEFT JOIN asset_categories ac ON ac.id = fa.category_id
             LEFT JOIN branches b ON b.id = fa.branch_id
             LEFT JOIN users u ON u.id = fa.responsible_user_id
             LEFT JOIN org_positions op ON op.id = fa.department_id
             WHERE fa.id = ?",
            [$id]
        )->fetch();
        return $row ?: null;
    }

    public static function create(array $data, int $userId): int
    {
        $no = Helpers::docNumber('AST', 'fixed_assets', 'asset_no');
        $cost = (float)($data['purchase_cost'] ?? 0);
        $salvage = (float)($data['salvage_value'] ?? 0);
        $residual = (float)($data['residual_value'] ?? $salvage);
        $barcode = trim((string)($data['barcode'] ?? ''));
        if (!$barcode) $barcode = $no;
        $qr = trim((string)($data['qr_code'] ?? ''));
        if (!$qr) $qr = self::generateQr($no);
        Database::query(
            "INSERT INTO fixed_assets (asset_no, barcode, qr_code, name, category_id, purchase_date, purchase_cost, book_value, salvage_value, residual_value, life_years, depreciation_method, branch_id, cost_center_id, department_id, responsible_user_id, location, notes, status, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [$no, $barcode, $qr, trim($data['name']), (int)(($data['category_id'] ?? '') ?: 0) ?: null, $data['purchase_date'], $cost, $cost, $salvage, $residual, (int)($data['life_years'] ?? 5), $data['depreciation_method'] ?? 'straight_line',
             (int)(($data['branch_id'] ?? '') ?: 0) ?: null, (int)(($data['cost_center_id'] ?? '') ?: 0) ?: null, (int)(($data['department_id'] ?? '') ?: 0) ?: null, (int)(($data['responsible_user_id'] ?? '') ?: 0) ?: null, trim($data['location'] ?? ''), trim($data['notes'] ?? ''), 'active', $userId]
        );
        $id = (int)Database::lastInsertId();
        self::recordLocation($id, $data['purchase_date'] ?? date('Y-m-d'), $data, $userId);
        self::recordTransaction($id, ['transaction_type' => 'purchase', 'transaction_date' => $data['purchase_date'] ?? date('Y-m-d'), 'amount' => $cost, 'notes' => 'تسجيل شراء الأصل'], $userId);
        if ($cost > 0) {
            $assetAcc = AccountingService::accountId('1200');
            $cashAcc = AccountingService::accountId('1000') ?: AccountingService::accountId('1010');
            if ($assetAcc && $cashAcc) {
                $entryId = AccountingService::post([
                    'entry_date' => $data['purchase_date'] ?? date('Y-m-d'),
                    'description' => 'شراء أصل ثابت - ' . $no,
                    'source_type' => 'fixed_asset', 'source_id' => $id,
                    'lines' => [['account_id' => $assetAcc, 'debit' => $cost, 'credit' => 0], ['account_id' => $cashAcc, 'debit' => 0, 'credit' => $cost]],
                ]);
                Database::query("UPDATE asset_transactions SET journal_entry_id = ? WHERE asset_id = ? AND transaction_type = 'purchase' ORDER BY id DESC LIMIT 1", [$entryId, $id]);
            }
        }
        return $id;
    }

    public static function update(int $id, array $data): void
    {
        Database::query(
            "UPDATE fixed_assets SET name = ?, category_id = ?, branch_id = ?, cost_center_id = ?, department_id = ?, responsible_user_id = ?, location = ?, barcode = ?, qr_code = ?, purchase_date = ?, life_years = ?, depreciation_method = ?, salvage_value = ?, residual_value = ?, notes = ? WHERE id = ?",
            [trim($data['name']), (int)($data['category_id'] ?: 0) ?: null, (int)($data['branch_id'] ?: 0) ?: null, (int)($data['cost_center_id'] ?: 0) ?: null, (int)($data['department_id'] ?: 0) ?: null, (int)($data['responsible_user_id'] ?: 0) ?: null, trim($data['location'] ?? ''), trim($data['barcode'] ?? ''), trim($data['qr_code'] ?? ''), $data['purchase_date'], (int)($data['life_years'] ?? 5), $data['depreciation_method'] ?? 'straight_line', (float)($data['salvage_value'] ?? 0), (float)($data['residual_value'] ?? 0), trim($data['notes'] ?? ''), $id]
        );
    }

    public static function recordTransaction(int $assetId, array $data, int $userId): int
    {
        Database::query(
            "INSERT INTO asset_transactions (asset_id, transaction_type, transaction_date, amount, qty, from_branch_id, to_branch_id, from_department_id, to_department_id, from_user_id, to_user_id, notes, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [$assetId, $data['transaction_type'], $data['transaction_date'], (float)($data['amount'] ?? 0), (float)($data['qty'] ?? 1), (int)($data['from_branch_id'] ?? 0) ?: null, (int)($data['to_branch_id'] ?? 0) ?: null, (int)($data['from_department_id'] ?? 0) ?: null, (int)($data['to_department_id'] ?? 0) ?: null, (int)($data['from_user_id'] ?? 0) ?: null, (int)($data['to_user_id'] ?? 0) ?: null, trim($data['notes'] ?? ''), $userId]
        );
        return (int)Database::lastInsertId();
    }

    public static function recordMaintenance(int $assetId, array $data, int $userId): int
    {
        $cost = (float)($data['cost'] ?? 0);
        Database::query(
            "INSERT INTO asset_maintenance (asset_id, maintenance_date, next_due_date, cost, provider, description, created_by) VALUES (?,?,?,?,?,?,?)",
            [$assetId, $data['maintenance_date'], $data['next_due_date'] ?: null, $cost, trim($data['provider'] ?? ''), trim($data['description'] ?? ''), $userId]
        );
        $id = (int)Database::lastInsertId();
        self::recordTransaction($assetId, ['transaction_type' => 'maintenance', 'transaction_date' => $data['maintenance_date'], 'amount' => $cost, 'notes' => 'صيانة: ' . ($data['provider'] ?? '')], $userId);
        if ($cost > 0) {
            $maintAcc = AccountingService::accountId('5200');
            $payAcc = AccountingService::accountId('2000');
            if ($maintAcc && $payAcc) {
                AccountingService::post([
                    'entry_date' => $data['maintenance_date'],
                    'description' => 'صيانة أصل - ' . ($data['provider'] ?? '') . ' (#' . $id . ')',
                    'source_type' => 'asset_maintenance', 'source_id' => $id,
                    'lines' => [
                        ['account_id' => $maintAcc, 'debit' => $cost, 'credit' => 0],
                        ['account_id' => $payAcc, 'debit' => 0, 'credit' => $cost],
                    ],
                ]);
            }
        }
        return $id;
    }

    public static function recordLocation(int $assetId, string $date, array $data, int $userId): int
    {
        Database::query(
            "INSERT INTO asset_locations (asset_id, location, branch_id, department_id, user_id, effective_date, notes, created_by) VALUES (?,?,?,?,?,?,?,?)",
            [$assetId, trim($data['location'] ?? ''), (int)($data['branch_id'] ?? 0) ?: null, (int)($data['department_id'] ?? 0) ?: null, (int)($data['responsible_user_id'] ?? 0) ?: null, $date, trim($data['notes'] ?? ''), $userId]
        );
        return (int)Database::lastInsertId();
    }

    public static function generateQr(string $no): string
    {
        return base64_encode('ASSET:' . $no . ':' . uniqid());
    }

    public static function bookValue(int $assetId): float
    {
        $asset = self::find($assetId);
        if (!$asset) return 0;
        $accum = (float)Database::query("SELECT COALESCE(SUM(amount),0) AS s FROM depreciation_entries WHERE asset_id = ?", [$assetId])->fetch()['s'];
        return max(0, (float)$asset['purchase_cost'] - $accum);
    }

    public static function revalue(int $assetId, float $newValue, string $date, string $notes, int $userId): void
    {
        $asset = self::find($assetId);
        if (!$asset) return;
        $old = (float)$asset['purchase_cost'];
        Database::query("UPDATE fixed_assets SET purchase_cost = ?, book_value = ? WHERE id = ?", [$newValue, $newValue, $assetId]);
        self::recordTransaction($assetId, ['transaction_type' => 'revaluation', 'transaction_date' => $date, 'amount' => $newValue - $old, 'notes' => $notes], $userId);
        if ($newValue != $old) {
            $gainAcc = AccountingService::accountId('4200');
            $lossAcc = AccountingService::accountId('5300');
            $assetAcc = AccountingService::accountId('1200');
            if ($assetAcc) {
                $diff = abs($newValue - $old);
                $lines = [];
                if ($newValue > $old) {
                    $lines = [['account_id' => $assetAcc, 'debit' => $diff, 'credit' => 0], ['account_id' => $gainAcc, 'debit' => 0, 'credit' => $diff]];
                } else {
                    $lines = [['account_id' => $lossAcc, 'debit' => $diff, 'credit' => 0], ['account_id' => $assetAcc, 'debit' => 0, 'credit' => $diff]];
                }
                $entryId = AccountingService::post(['entry_date' => $date, 'description' => 'إعادة تقييم أصل - ' . $asset['asset_no'], 'source_type' => 'fixed_asset', 'source_id' => $assetId, 'lines' => $lines]);
                Database::query("UPDATE asset_transactions SET journal_entry_id = ? WHERE asset_id = ? AND transaction_type = 'revaluation' ORDER BY id DESC LIMIT 1", [$entryId, $assetId]);
            }
        }
    }

    public static function dispose(int $assetId, string $type, string $date, float $amount, string $notes, int $userId): void
    {
        $asset = self::find($assetId);
        if (!$asset) return;
        $status = $type === 'sale' ? 'sold' : 'disposed';
        $book = self::bookValue($assetId);
        Database::query("UPDATE fixed_assets SET status = ?, disposal_date = ?, disposal_amount = ?, sale_amount = ? WHERE id = ?", [$status, $date, $type === 'disposal' ? $amount : 0, $type === 'sale' ? $amount : 0, $assetId]);
        self::recordTransaction($assetId, ['transaction_type' => $type, 'transaction_date' => $date, 'amount' => $amount, 'notes' => $notes], $userId);
        $assetAcc = AccountingService::accountId('1200');
        $accumAcc = AccountingService::accountId('1210');
        $cashAcc = AccountingService::accountId('1000') ?: AccountingService::accountId('1010');
        $lossAcc = AccountingService::accountId('5300');
        $gainAcc = AccountingService::accountId('4200');
        if ($assetAcc && $accumAcc) {
            $accum = (float)Database::query("SELECT COALESCE(SUM(amount),0) AS s FROM depreciation_entries WHERE asset_id = ?", [$assetId])->fetch()['s'];
            $lines = [];
            $lines[] = ['account_id' => $accumAcc, 'debit' => $accum, 'credit' => 0];
            if ($type === 'sale' && $cashAcc) $lines[] = ['account_id' => $cashAcc, 'debit' => $amount, 'credit' => 0];
            if ($book - $amount > 0 && $lossAcc) $lines[] = ['account_id' => $lossAcc, 'debit' => $book - $amount, 'credit' => 0];
            if ($amount - $book > 0 && $gainAcc) $lines[] = ['account_id' => $gainAcc, 'debit' => 0, 'credit' => $amount - $book];
            $lines[] = ['account_id' => $assetAcc, 'debit' => 0, 'credit' => (float)$asset['purchase_cost']];
            $entryId = AccountingService::post(['entry_date' => $date, 'description' => ($type === 'sale' ? 'بيع' : 'استبعاد') . ' أصل - ' . $asset['asset_no'], 'source_type' => 'fixed_asset', 'source_id' => $assetId, 'lines' => $lines]);
            Database::query("UPDATE asset_transactions SET journal_entry_id = ? WHERE asset_id = ? AND transaction_type = ? ORDER BY id DESC LIMIT 1", [$entryId, $assetId, $type]);
        }
    }

    public static function transfer(int $assetId, array $data, int $userId): void
    {
        self::recordTransaction($assetId, array_merge($data, ['transaction_type' => 'transfer']), $userId);
        self::recordLocation($assetId, $data['transaction_date'], $data, $userId);
        Database::query(
            "UPDATE fixed_assets SET branch_id = ?, department_id = ?, responsible_user_id = ?, location = ? WHERE id = ?",
            [(int)($data['to_branch_id'] ?? 0) ?: null, (int)($data['to_department_id'] ?? 0) ?: null, (int)($data['to_user_id'] ?? 0) ?: null, trim($data['location'] ?? ''), $assetId]
        );
    }

    public static function setProductionUnits(int $assetId, string $period, float $units, float $total, int $userId): void
    {
        Database::query(
            "INSERT INTO asset_production_units (asset_id, period, units_produced, total_estimated_units) VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE units_produced = ?, total_estimated_units = ?",
            [$assetId, $period, $units, $total, $units, $total]
        );
    }

    public static function depreciate(int $assetId, string $period): float
    {
        $asset = self::find($assetId);
        if (!$asset || $asset['status'] !== 'active') return 0;
        $exists = Database::query("SELECT id FROM depreciation_entries WHERE asset_id = ? AND period = ?", [$assetId, $period])->fetch();
        if ($exists) return 0;
        $cost = (float)$asset['purchase_cost'];
        $salvage = (float)$asset['salvage_value'];
        $life = (int)$asset['life_years'];
        if ($life <= 0) return 0;
        $method = $asset['depreciation_method'] ?: 'straight_line';
        $accum = (float)Database::query("SELECT COALESCE(SUM(amount),0) AS s FROM depreciation_entries WHERE asset_id = ?", [$assetId])->fetch()['s'];
        $amount = 0;
        if ($method === 'straight_line') {
            $amount = ($cost - $salvage) / ($life * 12);
        } elseif ($method === 'declining_balance') {
            $rate = 2 / $life;
            $amount = (($cost - $accum) * $rate) / 12;
        } elseif ($method === 'units_of_production') {
            $pu = Database::query("SELECT * FROM asset_production_units WHERE asset_id = ? AND period = ?", [$assetId, $period])->fetch();
            if (!$pu) return 0;
            $total = (float)$pu['total_estimated_units'];
            if ($total <= 0) return 0;
            $amount = (($cost - $salvage) / $total) * (float)$pu['units_produced'];
        }
        $amount = max(0, round($amount, 2));
        if ($amount > $cost - $accum - $salvage) $amount = max(0, $cost - $accum - $salvage);
        if ($amount <= 0) return 0;
        $expAcc = AccountingService::accountId('5300');
        $accumAcc = AccountingService::accountId('1210');
        if (!$expAcc || !$accumAcc) return 0;
        $entryId = AccountingService::post([
            'entry_date' => $period . '-01',
            'description' => 'إهلاك أصل - ' . $asset['asset_no'] . ' (' . $period . ')',
            'source_type' => 'depreciation', 'source_id' => $assetId,
            'lines' => [['account_id' => $expAcc, 'debit' => $amount, 'credit' => 0], ['account_id' => $accumAcc, 'debit' => 0, 'credit' => $amount]],
        ]);
        Database::query("INSERT INTO depreciation_entries (asset_id, period, amount, journal_entry_id) VALUES (?,?,?,?)", [$assetId, $period, $amount, $entryId]);
        Database::query("UPDATE fixed_assets SET accumulated_depreciation = accumulated_depreciation + ?, book_value = ? WHERE id = ?", [$amount, max(0, $cost - $accum - $amount), $assetId]);
        return $amount;
    }

    public static function depreciateAll(string $period): array
    {
        $assets = Database::query("SELECT id FROM fixed_assets WHERE status='active'")->fetchAll();
        $out = [];
        foreach ($assets as $a) {
            $amount = self::depreciate((int)$a['id'], $period);
            if ($amount > 0) $out[] = ['asset_id' => (int)$a['id'], 'amount' => $amount];
        }
        return $out;
    }

    public static function scanByBarcode(string $code): ?array
    {
        return Database::query("SELECT * FROM fixed_assets WHERE barcode = ? OR qr_code = ? OR asset_no = ? LIMIT 1", [$code, $code, $code])->fetch() ?: null;
    }

    public static function transactions(int $assetId): array
    {
        return Database::query("SELECT * FROM asset_transactions WHERE asset_id = ? ORDER BY transaction_date DESC, id DESC", [$assetId])->fetchAll();
    }

    public static function maintenanceHistory(int $assetId): array
    {
        return Database::query("SELECT * FROM asset_maintenance WHERE asset_id = ? ORDER BY maintenance_date DESC", [$assetId])->fetchAll();
    }

    public static function locationHistory(int $assetId): array
    {
        return Database::query(
            "SELECT al.*, b.name AS branch_name, op.title AS department_name, u.full_name AS user_name
             FROM asset_locations al
             LEFT JOIN branches b ON b.id = al.branch_id
             LEFT JOIN org_positions op ON op.id = al.department_id
             LEFT JOIN users u ON u.id = al.user_id
             WHERE al.asset_id = ? ORDER BY al.effective_date DESC, al.id DESC",
            [$assetId]
        )->fetchAll();
    }
}
