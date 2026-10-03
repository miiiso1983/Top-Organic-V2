<?php
declare(strict_types=1);

namespace App\Core\PlateCost;

readonly class PurchasePriceDTO
{
    public function __construct(
        public string $material_name,
        public ?string $material_category,
        public string $unit_price,
        public string $raw_waste_rate,
        public string $raw_waste_over_yield,
        public ?string $currency = null,
        public ?string $price_as_of = null,
        public ?int $supplier_id = null,
        public ?int $source_file_id = null
    ) {}
}
