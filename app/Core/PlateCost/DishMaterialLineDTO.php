<?php
declare(strict_types=1);

namespace App\Core\PlateCost;

readonly class DishMaterialLineDTO
{
    public function __construct(
        public string $dish_name,
        public ?string $dish_code,
        public ?string $dish_category,
        public string $material_type,
        public string $material_name,
        public ?string $unit,
        public string $base_quantity,
        public string $resolved_unit_cost,
        public string $raw_waste_over_yield,
        public string $department_waste_rate,
        public string $department_waste_over_yield,
        public string $actual_consumed_quantity,
        public string $line_cost
    ) {}
}
