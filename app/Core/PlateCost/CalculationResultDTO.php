<?php
declare(strict_types=1);

namespace App\Core\PlateCost;

readonly class CalculationResultDTO
{
    /**
     * @param PurchasePriceDTO[] $purchasePrices
     * @param PreparedComponentLineDTO[] $preparedComponentLines
     * @param DishMaterialLineDTO[] $dishMaterialLines
     * @param DishDTO[] $dishes
     * @param string[] $errors
     * @param string[] $warnings
     */
    public function __construct(
        public array $purchasePrices,
        public array $preparedComponentLines,
        public array $dishMaterialLines,
        public array $dishes,
        public array $errors = [],
        public array $warnings = []
    ) {}
}
