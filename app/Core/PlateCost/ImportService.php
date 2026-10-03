<?php
declare(strict_types=1);

namespace App\Core\PlateCost;

use App\Models\PlateCostImportRun;
use App\Models\PlateCostPurchasePrice;
use App\Models\PlateCostPreparedComponentBom;
use App\Models\PlateCostDishMaterialBom;
use App\Models\PlateCostDish;

class ImportService
{
    public static function import(string $filePath, string $filename, ?int $userId = null): int
    {
        $runId = PlateCostImportRun::create($filename, $userId);
        $parsed = ExcelParser::parse($filePath);
        $errors = $parsed['errors'];
        $warnings = $parsed['warnings'];

        if ($errors) {
            PlateCostImportRun::updateStatus($runId, 'error', $errors, $warnings);
            return $runId;
        }

        $result = CalculationService::calculate($parsed);
        $errors = array_merge($errors, $result->errors);
        $warnings = array_merge($warnings, $result->warnings);

        self::persist($runId, $result);

        $status = $errors ? 'partial' : ($warnings ? 'partial' : 'success');
        PlateCostImportRun::updateStatus($runId, $status, $errors, $warnings);
        return $runId;
    }

    private static function persist(int $runId, CalculationResultDTO $result): void
    {
        $prices = [];
        foreach ($result->purchasePrices as $p) {
            $prices[] = get_object_vars($p);
        }
        PlateCostPurchasePrice::bulkInsert($runId, $prices);

        $prepared = [];
        foreach ($result->preparedComponentLines as $l) {
            $prepared[] = get_object_vars($l);
        }
        PlateCostPreparedComponentBom::bulkInsert($runId, $prepared);

        $dishMaterials = [];
        foreach ($result->dishMaterialLines as $l) {
            $dishMaterials[] = get_object_vars($l);
        }
        PlateCostDishMaterialBom::bulkInsert($runId, $dishMaterials);

        $dishes = [];
        foreach ($result->dishes as $d) {
            $dishes[] = get_object_vars($d);
        }
        PlateCostDish::bulkInsert($runId, $dishes);
    }
}
