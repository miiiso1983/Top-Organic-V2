<?php
declare(strict_types=1);

define('BASE_PATH', __DIR__ . '/..');
require BASE_PATH . '/vendor/autoload.php';
require BASE_PATH . '/app/Core/Decimal.php';
require BASE_PATH . '/app/Core/PlateCost/PurchasePriceDTO.php';
require BASE_PATH . '/app/Core/PlateCost/PreparedComponentLineDTO.php';
require BASE_PATH . '/app/Core/PlateCost/DishMaterialLineDTO.php';
require BASE_PATH . '/app/Core/PlateCost/DishDTO.php';
require BASE_PATH . '/app/Core/PlateCost/CalculationResultDTO.php';
require BASE_PATH . '/app/Core/PlateCost/CalculationService.php';

use App\Core\PlateCost\CalculationService;

function eq(string $a, string $b, string $msg): void
{
    if (bccomp($a, $b, 6) !== 0) {
        throw new RuntimeException("$msg: expected $b, got $a");
    }
}

function price(string $name, string $unitPrice, string $waste): array
{
    return ['material_name' => $name, 'material_category' => '', 'unit_price' => $unitPrice, 'raw_waste_rate' => $waste];
}

// 1) Zero waste raw material
$parsed = [
    'purchase_prices' => [price('sugar', '10', '0')],
    'prepared_components' => [],
    'dish_materials' => [
        ['dish_name' => 'D1', 'dish_code' => null, 'dish_category' => '', 'material_type' => 'مادة اولية', 'material_name' => 'sugar', 'unit' => 'g', 'base_quantity' => 2, 'department_waste_rate' => '0'],
    ],
    'dishes' => [
        ['dish_name' => 'D1', 'dish_code' => null, 'dish_category' => '', 'sale_unit' => 'PCS', 'sale_price' => '100'],
    ],
];
$r = CalculationService::calculate($parsed);
eq($r->dishes[0]->dish_cost, '20', 'zero waste cost');
eq($r->dishes[0]->gross_profit, '80', 'zero waste profit');

// 2) 25% waste uplift: actual = 100 / 0.75 = 133.333...
$parsed = [
    'purchase_prices' => [price('sugar', '3', '0.25')],
    'prepared_components' => [],
    'dish_materials' => [
        ['dish_name' => 'D2', 'dish_code' => null, 'dish_category' => '', 'material_type' => 'مادة اولية', 'material_name' => 'sugar', 'unit' => 'g', 'base_quantity' => 100, 'department_waste_rate' => '0'],
    ],
    'dishes' => [
        ['dish_name' => 'D2', 'dish_code' => null, 'dish_category' => '', 'sale_unit' => 'PCS', 'sale_price' => '1000'],
    ],
];
$r = CalculationService::calculate($parsed);
eq($r->dishMaterialLines[0]->actual_consumed_quantity, '133.333333', '25% waste actual qty');
eq($r->dishes[0]->dish_cost, '399.999999', '25% waste cost');

// 3) Negative waste (swelling): over_yield = -0.1, actual = 90
$parsed = [
    'purchase_prices' => [price('rice', '2', '-0.1')],
    'prepared_components' => [],
    'dish_materials' => [
        ['dish_name' => 'D3', 'dish_code' => null, 'dish_category' => '', 'material_type' => 'مادة اولية', 'material_name' => 'rice', 'unit' => 'g', 'base_quantity' => 100, 'department_waste_rate' => '0'],
    ],
    'dishes' => [
        ['dish_name' => 'D3', 'dish_code' => null, 'dish_category' => '', 'sale_unit' => 'PCS', 'sale_price' => '500'],
    ],
];
$r = CalculationService::calculate($parsed);
eq($r->dishMaterialLines[0]->actual_consumed_quantity, '90', 'negative waste actual qty');
eq($r->dishes[0]->dish_cost, '180', 'negative waste cost');

// 4) Multi-level component: prepared uses sugar at 10/g zero waste, dish uses prepared
$parsed = [
    'purchase_prices' => [price('sugar', '10', '0')],
    'prepared_components' => [
        ['prepared_name' => 'syrup', 'ingredient_name' => 'sugar', 'ingredient_code' => null, 'base_quantity' => 2, 'unit' => 'g', 'department_waste_rate' => '0'],
    ],
    'dish_materials' => [
        ['dish_name' => 'D4', 'dish_code' => null, 'dish_category' => '', 'material_type' => 'محضر', 'material_name' => 'syrup', 'unit' => 'ml', 'base_quantity' => 3, 'department_waste_rate' => '0'],
    ],
    'dishes' => [
        ['dish_name' => 'D4', 'dish_code' => null, 'dish_category' => '', 'sale_unit' => 'PCS', 'sale_price' => '500'],
    ],
];
$r = CalculationService::calculate($parsed);
eq($r->preparedComponentLines[0]->line_cost, '20', 'prepared line cost');
eq($r->dishMaterialLines[0]->resolved_unit_cost, '20', 'resolved prepared unit cost');
eq($r->dishes[0]->dish_cost, '60', 'multi-level dish cost');

// 5) Department waste on top of raw waste
$parsed = [
    'purchase_prices' => [price('sugar', '10', '0.25')],
    'prepared_components' => [],
    'dish_materials' => [
        ['dish_name' => 'D5', 'dish_code' => null, 'dish_category' => '', 'material_type' => 'مادة اولية', 'material_name' => 'sugar', 'unit' => 'g', 'base_quantity' => 100, 'department_waste_rate' => '0.2'],
    ],
    'dishes' => [
        ['dish_name' => 'D5', 'dish_code' => null, 'dish_category' => '', 'sale_unit' => 'PCS', 'sale_price' => '5000'],
    ],
];
$r = CalculationService::calculate($parsed);
// raw over = 0.33333333, dept over = 0.25, actual = 100 + 33.3333 + 25 = 158.3333
$expected = bcmul('158.333333', '10', 6);
eq($r->dishMaterialLines[0]->actual_consumed_quantity, '158.333333', 'dept waste actual qty');
eq($r->dishes[0]->dish_cost, $expected, 'dept waste cost');

echo "All PlateCost calculation tests passed.\n";
