<?php
declare(strict_types=1);

namespace App\Core\PlateCost;

readonly class DishDTO
{
    public function __construct(
        public string $dish_name,
        public ?string $dish_code,
        public ?string $dish_category,
        public ?string $sale_unit,
        public string $dish_cost,
        public string $sale_price,
        public string $gross_profit,
        public string $markup_on_cost
    ) {}
}
