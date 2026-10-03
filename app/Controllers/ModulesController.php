<?php
namespace App\Controllers;

use App\Core\{Controller, Auth};

class ModulesController extends Controller
{
    private const SECTIONS = [
        'sales' => [
            'title' => 'وحدة المبيعات',
            'icon' => 'fa-cash-register',
        ],
        'kitchen' => [
            'title' => 'وحدة المطبخ',
            'icon' => 'fa-fire-burner',
        ],
        'inventory' => [
            'title' => 'وحدة المخزون',
            'icon' => 'fa-boxes-stacked',
        ],
        'purchasing' => [
            'title' => 'وحدة المشتريات',
            'icon' => 'fa-cart-shopping',
        ],
        'finance' => [
            'title' => 'وحدة المالية',
            'icon' => 'fa-sack-dollar',
        ],
        'financial-reports' => [
            'title' => 'وحدة التقارير المالية',
            'icon' => 'fa-chart-pie',
        ],
        'accounting' => [
            'title' => 'وحدة المحاسبة',
            'icon' => 'fa-chart-pie',
        ],
        'employee' => [
            'title' => 'وحدة الموظف',
            'icon' => 'fa-user',
        ],
        'hr' => [
            'title' => 'وحدة الموارد البشرية',
            'icon' => 'fa-users-gear',
        ],
        'management' => [
            'title' => 'وحدة الإدارة',
            'icon' => 'fa-screwdriver-wrench',
        ],
    ];

    public function section(string $name): void
    {
        if (!isset(self::SECTIONS[$name])) {
            header('Location: /dashboard');
            exit;
        }
        $meta = self::SECTIONS[$name];
        $this->view('modules/' . $name, [
            'title' => $meta['title'],
            'section_icon' => $meta['icon'],
        ]);
    }
}
