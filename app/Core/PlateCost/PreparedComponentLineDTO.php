<?php
declare(strict_types=1);

namespace App\Core\PlateCost;

readonly class PreparedComponentLineDTO
{
    public function __construct(
        public string $prepared_name,
        public string $ingredient_name,
        public ?string $ingredient_code,
        public string $base_quantity,
        public ?string $unit,
        public string $unit_price,
        public string $raw_waste_rate,
        public string $raw_waste_over_yield,
        public string $raw_waste_quantity,
        public string $raw_waste_cost,
        public string $department_waste_rate,
        public string $department_waste_over_yield,
        public string $department_waste_quantity,
        public string $department_waste_cost,
        public string $actual_consumed_quantity,
        public string $line_cost
    ) {}
}
