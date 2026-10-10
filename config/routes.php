<?php
/**
 * تعريف مسارات النظام
 */
use App\Core\Router;
use App\Controllers\{AuthController, DashboardController, PosController, OrderController,
    CaptainController, TableController, ReservationController, BookingController, ReservationTypeController, CallCenterController, AiAssistantController, ReceptionController, WaitingListController, ReservationsModuleController, MenuController, KitchenController, KitchenSectionController,
    PreparationKitchenController, RecipeController, ProductionController, InventoryController, WarehouseController, CustodyController,
    PurchasingController, SupplierController, FinanceController, HrController,
    OrgChartController, ApprovalController, ReportController, AdminController, ApiController,
    AccountingController, FixedAssetController, ARAPController, BankController, CostCenterController, FinancialReportController, WorkflowController, WorkflowDesignerController, ItemCardController, ItemCostSheetController, PreparedItemsController, ItemPriceController, PlateCostImportController, MaterialRequestController, MobileController, ModulesController, PttController, NotificationsController, ShiftController, MaintenanceController, HousingController};

$router = new Router();

// المصادقة
$router->get('/', [AuthController::class, 'loginForm']);
$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);
$router->get('/mobile/web-auth', [AuthController::class, 'webAuth']);

// الرئيسية
$router->get('/dashboard', [DashboardController::class, 'index'], ['auth']);

// نقطة البيع والطلبات
$router->get('/pos', [PosController::class, 'index'], ['auth', 'perm:pos.use']);
$router->post('/pos/checkout', [PosController::class, 'checkout'], ['auth', 'perm:pos.use']);
$router->get('/captain', [CaptainController::class, 'index'], ['auth', 'perm:sales.view']);
$router->get('/captain/{table_id}', [CaptainController::class, 'table'], ['auth', 'perm:sales.view']);
$router->post('/captain/order', [CaptainController::class, 'store'], ['auth', 'perm:sales.view']);
$router->post('/captain/orders/{id}/print-bill', [CaptainController::class, 'printBill'], ['auth', 'perm:sales.view']);
$router->post('/captain/orders/{id}/settle', [CaptainController::class, 'settlePayment'], ['auth', 'perm:sales.view']);
$router->get('/orders', [OrderController::class, 'index'], ['auth', 'perm:sales.view']);
$router->get('/orders/export', [OrderController::class, 'export'], ['auth', 'perm:sales.view']);
$router->get('/orders/{id}', [OrderController::class, 'show'], ['auth', 'perm:sales.view']);
$router->get('/orders/{id}/receipt', [OrderController::class, 'receipt'], ['auth', 'perm:sales.view']);
$router->post('/orders/{id}/status', [OrderController::class, 'updateStatus'], ['auth', 'perm:sales.view']);
$router->post('/orders/{id}/cancel', [OrderController::class, 'cancel'], ['auth', 'perm:sales.return']);
$router->post('/orders/{id}/return', [OrderController::class, 'returnItems'], ['auth', 'perm:sales.return']);
$router->post('/orders/{id}/pay', [OrderController::class, 'pay'], ['auth', 'perm:sales.view']);
$router->post('/orders/{id}/split', [OrderController::class, 'splitOrder'], ['auth', 'perm:sales.view']);
$router->post('/orders/{id}/cancel-item', [OrderController::class, 'cancelItem'], ['auth', 'perm:sales.return']);
$router->post('/orders/{id}/move-item', [OrderController::class, 'moveItem'], ['auth', 'perm:sales.view']);
$router->post('/orders/{id}/move', [OrderController::class, 'moveOrder'], ['auth', 'perm:sales.view']);

// الطاولات والحجوزات
$router->get('/tables', [TableController::class, 'index'], ['auth', 'perm:sales.view']);
$router->post('/tables', [TableController::class, 'store'], ['auth', 'perm:sales.view']);
$router->get('/tables/{id}/edit', [TableController::class, 'edit'], ['auth', 'perm:sales.view']);
$router->post('/tables/{id}/edit', [TableController::class, 'update'], ['auth', 'perm:sales.view']);
$router->post('/tables/{id}/delete', [TableController::class, 'destroy'], ['auth', 'perm:sales.view']);
$router->post('/tables/{id}/status', [TableController::class, 'updateStatus'], ['auth', 'perm:sales.view']);
$router->post('/tables/{id}/floor', [TableController::class, 'assignFloor'], ['auth', 'perm:sales.view']);
$router->post('/tables/{id}/cleaning', [TableController::class, 'setCleaning'], ['auth', 'perm:floor_plan.manage']);
$router->post('/tables/{id}/split', [TableController::class, 'split'], ['auth', 'perm:floor_plan.merge']);
$router->post('/tables/merge', [TableController::class, 'merge'], ['auth', 'perm:floor_plan.merge']);
$router->post('/tables/floors', [TableController::class, 'storeFloor'], ['auth', 'perm:sales.view']);
$router->post('/tables/floors/{id}/update', [TableController::class, 'updateFloor'], ['auth', 'perm:sales.view']);
$router->post('/tables/floors/{id}/delete', [TableController::class, 'destroyFloor'], ['auth', 'perm:sales.view']);
$router->get('/reservations-module', [ReservationsModuleController::class, 'index'], ['auth']);
$router->get('/module/{name}', [ModulesController::class, 'section'], ['auth']);
$router->get('/reservations', [ReservationController::class, 'index'], ['auth', 'perm:reservations.manage']);
$router->get('/reservations/tomorrow-reminders', [BookingController::class, 'tomorrowReminders'], ['auth', 'perm:reservations.manage']);
$router->post('/reservations', [ReservationController::class, 'store'], ['auth', 'perm:reservations.manage']);
$router->get('/reservations/available-tables', [ReservationController::class, 'availableTables'], ['auth', 'perm:reservations.manage']);
$router->get('/reservations/suggest-table', [ReservationController::class, 'suggestTable'], ['auth', 'perm:reservations.manage']);
$router->get('/reservations/floor-plan', [ReservationController::class, 'floorPlan'], ['auth', 'perm:reservations.manage']);
$router->get('/reservations/{id}', [ReservationController::class, 'show'], ['auth', 'perm:reservations.view']);
$router->get('/reservations/{id}/edit', [ReservationController::class, 'edit'], ['auth', 'perm:reservations.manage']);
$router->post('/reservations/{id}', [ReservationController::class, 'update'], ['auth', 'perm:reservations.manage']);
$router->post('/reservations/{id}/status', [ReservationController::class, 'updateStatus'], ['auth', 'perm:reservations.manage']);
$router->post('/reservations/{id}/reschedule', [ReservationController::class, 'reschedule'], ['auth', 'perm:reservations.manage']);
$router->get('/reservations/{id}/check-in', [ReservationController::class, 'checkIn'], ['auth', 'perm:reservations.manage']);
$router->get('/reservations/{id}/check-out', [ReservationController::class, 'checkOut'], ['auth', 'perm:reservations.manage']);
$router->get('/reservations/{id}/logs', [ReservationController::class, 'logs'], ['auth', 'perm:reservations.view']);

$router->get('/admin/reservation-types', [ReservationTypeController::class, 'index'], ['auth', 'perm:reservations.types.manage']);
$router->post('/admin/reservation-types', [ReservationTypeController::class, 'store'], ['auth', 'perm:reservations.types.manage']);
$router->post('/admin/reservation-types/{id}', [ReservationTypeController::class, 'update'], ['auth', 'perm:reservations.types.manage']);
$router->post('/admin/reservation-types/{id}/delete', [ReservationTypeController::class, 'delete'], ['auth', 'perm:reservations.types.manage']);

$router->get('/call-center', [CallCenterController::class, 'index'], ['auth', 'perm:call_center.manage']);
$router->get('/call-center/types', [CallCenterController::class, 'types'], ['auth', 'perm:call_center.manage']);
$router->get('/call-center/customers/search', [CallCenterController::class, 'searchCustomers'], ['auth', 'perm:call_center.manage']);
$router->post('/call-center/customers', [CallCenterController::class, 'storeCustomer'], ['auth', 'perm:call_center.manage']);
$router->get('/call-center/customers/{id}', [CallCenterController::class, 'getCustomer'], ['auth', 'perm:call_center.manage']);
$router->post('/call-center/reservations', [CallCenterController::class, 'storeReservation'], ['auth', 'perm:call_center.manage']);
$router->post('/call-center/reservations/{id}', [CallCenterController::class, 'updateReservation'], ['auth', 'perm:call_center.manage']);
$router->post('/call-center/reservations/{id}/status', [CallCenterController::class, 'status'], ['auth', 'perm:call_center.manage']);
$router->post('/call-center/reservations/{id}/cancel', [CallCenterController::class, 'cancelReservation'], ['auth', 'perm:call_center.manage']);
$router->post('/call-center/reservations/{id}/reschedule', [CallCenterController::class, 'rescheduleReservation'], ['auth', 'perm:call_center.manage']);
$router->get('/call-center/occupancy', [CallCenterController::class, 'occupancy'], ['auth', 'perm:call_center.manage']);
$router->get('/call-center/slots', [CallCenterController::class, 'availableSlots'], ['auth', 'perm:call_center.manage']);
$router->get('/call-center/tables', [CallCenterController::class, 'availableTables'], ['auth', 'perm:call_center.manage']);

$router->get('/book', [ReservationController::class, 'publicForm']);
$router->post('/book', [ReservationController::class, 'publicStore']);
$router->get('/book/slots', [ReservationController::class, 'publicSlots']);
$router->post('/book/send-otp', [ReservationController::class, 'publicSendOtp']);

$router->get('/ai-assistant', [AiAssistantController::class, 'index']);
$router->post('/ai-assistant/chat', [AiAssistantController::class, 'chat']);
$router->post('/ai-assistant/webhook/{channel}', [AiAssistantController::class, 'webhook']);
$router->get('/admin/ai-conversations', [AiAssistantController::class, 'admin'], ['auth', 'perm:ai_assistant.view']);
$router->get('/admin/ai-conversations/{id}', [AiAssistantController::class, 'conversation'], ['auth', 'perm:ai_assistant.view']);
$router->post('/admin/ai-conversations/{id}/reply', [AiAssistantController::class, 'reply'], ['auth', 'perm:ai_assistant.handoff']);
$router->get('/admin/openai-settings', [AdminController::class, 'openAiSettings'], ['auth', 'perm:ai_assistant.manage']);
$router->post('/admin/openai-settings', [AdminController::class, 'saveOpenAiSettings'], ['auth', 'perm:ai_assistant.manage']);

$router->get('/reception', [ReceptionController::class, 'index'], ['auth', 'perm:reception.manage']);
$router->post('/reception/{id}/check-in', [ReceptionController::class, 'checkIn'], ['auth', 'perm:reception.manage']);
$router->post('/reception/{id}/no-show', [ReceptionController::class, 'noShow'], ['auth', 'perm:reception.manage']);
$router->post('/reception/{id}/table', [ReceptionController::class, 'assignTable'], ['auth', 'perm:reception.manage']);
$router->post('/reception/{id}/complete', [ReceptionController::class, 'complete'], ['auth', 'perm:reception.manage']);
$router->post('/reception/{id}/notes', [ReceptionController::class, 'addNotes'], ['auth', 'perm:reception.manage']);

$router->get('/waiting-list', [WaitingListController::class, 'index'], ['auth', 'perm:waiting_list.manage']);
$router->post('/waiting-list', [WaitingListController::class, 'store'], ['auth', 'perm:waiting_list.manage']);
$router->post('/waiting-list/{id}/check-in', [WaitingListController::class, 'directCheckIn'], ['auth', 'perm:waiting_list.manage']);
$router->post('/waiting-list/{id}/remove', [WaitingListController::class, 'remove'], ['auth', 'perm:waiting_list.manage']);
$router->post('/waiting-list/{id}/notify', [WaitingListController::class, 'notify'], ['auth', 'perm:waiting_list.notify']);
$router->get('/waiting-list/{id}/suggest-table', [WaitingListController::class, 'suggestTable'], ['auth', 'perm:waiting_list.manage']);

// قائمة الطعام (العرض متاح لمن يملك sales.view، والإدارة تتطلب recipes.manage)
$router->get('/menu', [MenuController::class, 'index'], ['auth', 'perm:sales.view']);
$router->post('/menu/menus', [MenuController::class, 'storeMenu'], ['auth', 'perm:recipes.manage']);
$router->post('/menu/menus/{id}', [MenuController::class, 'updateMenu'], ['auth', 'perm:recipes.manage']);
$router->post('/menu/menus/{id}/delete', [MenuController::class, 'destroyMenu'], ['auth', 'perm:recipes.manage']);
$router->post('/menu/menus/{id}/floors', [MenuController::class, 'assignFloors'], ['auth', 'perm:recipes.manage']);
$router->post('/menu/categories', [MenuController::class, 'storeCategory'], ['auth', 'perm:recipes.manage']);
$router->post('/menu/categories/{id}', [MenuController::class, 'updateCategory'], ['auth', 'perm:recipes.manage']);
$router->post('/menu/categories/{id}/delete', [MenuController::class, 'destroyCategory'], ['auth', 'perm:recipes.manage']);
$router->post('/menu/items', [MenuController::class, 'storeItem'], ['auth', 'perm:recipes.manage']);
$router->post('/menu/items/{id}', [MenuController::class, 'updateItem'], ['auth', 'perm:recipes.manage']);
$router->post('/menu/items/{id}/delete', [MenuController::class, 'destroyItem'], ['auth', 'perm:recipes.manage']);
$router->post('/menu/import', [MenuController::class, 'importItems'], ['auth', 'perm:recipes.manage']);
$router->get('/menu/template.csv', [MenuController::class, 'downloadTemplate'], ['auth', 'perm:recipes.manage']);
$router->post('/menu/import-excel', [MenuController::class, 'importItemsExcel'], ['auth', 'perm:recipes.manage']);
$router->get('/menu/template.xlsx', [MenuController::class, 'downloadTemplateExcel'], ['auth', 'perm:recipes.manage']);
$router->post('/menu/categories', [MenuController::class, 'storeCategory'], ['auth', 'perm:recipes.manage']);

// المطبخ
$router->get('/kitchen', [KitchenController::class, 'index'], ['auth', 'perm:kitchen.view']);
$router->get('/kitchen/data', [KitchenController::class, 'data'], ['auth', 'perm:kitchen.view']);
$router->post('/kitchen/item/{id}/status', [KitchenController::class, 'updateItemStatus'], ['auth', 'perm:kitchen.view']);
$router->post('/kitchen/order/{id}/ready', [KitchenController::class, 'readyOrder'], ['auth', 'perm:kitchen.view']);
$router->post('/kitchen/order/{id}/cancel', [KitchenController::class, 'cancelOrder'], ['auth', 'perm:kitchen.view']);
$router->get('/kitchen/section/{id}', [KitchenController::class, 'section'], ['auth', 'perm:kitchen.view']);
$router->get('/kitchen/section/{id}/data', [KitchenController::class, 'sectionData'], ['auth', 'perm:kitchen.view']);
$router->get('/kitchen/section/{id}/receipt', [KitchenController::class, 'sectionReceipt'], ['auth', 'perm:kitchen.view']);
$router->post('/kitchen/section/{sectionId}/item/{itemId}/status', [KitchenController::class, 'sectionItemStatus'], ['auth', 'perm:kitchen.view']);
$router->post('/kitchen/section/{sectionId}/order/{orderId}/ready', [KitchenController::class, 'sectionReady'], ['auth', 'perm:kitchen.view']);
$router->get('/kitchen/preparation/{id}', [KitchenController::class, 'preparation'], ['auth', 'perm:kitchen.view']);
$router->get('/kitchen/preparation/{id}/data', [KitchenController::class, 'preparationData'], ['auth', 'perm:kitchen.view']);
$router->get('/kitchen/preparation/{id}/receipt', [KitchenController::class, 'preparationReceipt'], ['auth', 'perm:kitchen.view']);
$router->get('/kitchen/order/{id}/receipt', [KitchenController::class, 'orderReceipt'], ['auth', 'perm:kitchen.view']);
$router->get('/kitchen/order/{id}/preparation/{kitchenId}/receipt', [KitchenController::class, 'orderPreparationReceipt'], ['auth', 'perm:kitchen.view']);
$router->post('/kitchen/preparation/{kitchenId}/item/{itemId}/status', [KitchenController::class, 'preparationItemStatus'], ['auth', 'perm:kitchen.view']);
$router->post('/kitchen/preparation/{kitchenId}/order/{orderId}/ready', [KitchenController::class, 'preparationReady'], ['auth', 'perm:kitchen.view']);
$router->get('/kitchen/sections', [KitchenSectionController::class, 'index'], ['auth', 'perm:kitchen.manage']);
$router->post('/kitchen/sections', [KitchenSectionController::class, 'store'], ['auth', 'perm:kitchen.manage']);
$router->post('/kitchen/sections/{id}', [KitchenSectionController::class, 'update'], ['auth', 'perm:kitchen.manage']);
$router->post('/kitchen/sections/{id}/delete', [KitchenSectionController::class, 'delete'], ['auth', 'perm:kitchen.manage']);

// مراكز التحضير (إدارة)
$router->get('/admin/preparation-kitchens', [PreparationKitchenController::class, 'index'], ['auth', 'perm:kitchen.manage']);
$router->post('/admin/preparation-kitchens', [PreparationKitchenController::class, 'store'], ['auth', 'perm:kitchen.manage']);
$router->post('/admin/preparation-kitchens/{id}', [PreparationKitchenController::class, 'update'], ['auth', 'perm:kitchen.manage']);
$router->post('/admin/preparation-kitchens/{id}/delete', [PreparationKitchenController::class, 'delete'], ['auth', 'perm:kitchen.manage']);

// الوصفات والإنتاج
$router->get('/recipes', [RecipeController::class, 'index'], ['auth', 'perm:recipes.manage']);
$router->get('/recipes/{id}', [RecipeController::class, 'edit'], ['auth', 'perm:recipes.manage']);
$router->post('/recipes/{id}', [RecipeController::class, 'save'], ['auth', 'perm:recipes.manage']);
$router->get('/production', [ProductionController::class, 'index'], ['auth', 'perm:production.manage']);
$router->post('/production', [ProductionController::class, 'store'], ['auth', 'perm:production.manage']);
$router->post('/production/{id}/complete', [ProductionController::class, 'complete'], ['auth', 'perm:production.manage']);
$router->post('/waste', [ProductionController::class, 'logWaste'], ['auth', 'perm:production.manage']);
$router->get('/production/manufacture', [ProductionController::class, 'manufactureForm'], ['auth', 'perm:production.manage']);
$router->post('/production/manufacture', [ProductionController::class, 'manufacture'], ['auth', 'perm:production.manage']);

// المخزون
$router->get('/inventory', [InventoryController::class, 'index'], ['auth', 'perm:inventory.view']);
$router->post('/inventory/categories', [InventoryController::class, 'storeCategory'], ['auth', 'perm:inventory.manage']);
$router->post('/inventory/items', [InventoryController::class, 'storeItem'], ['auth', 'perm:inventory.manage']);
$router->get('/inventory/items/{id}/price', [InventoryController::class, 'priceForm'], ['auth', 'perm:inventory.manage']);
$router->post('/inventory/items/{id}/price', [InventoryController::class, 'updatePrice'], ['auth', 'perm:inventory.manage']);
$router->post('/inventory/items/{id}/delete', [InventoryController::class, 'deleteItem'], ['auth', 'perm:inventory.manage']);
$router->get('/inventory/plate-costs', [InventoryController::class, 'plateCosts'], ['auth', 'perm:inventory.view']);
$router->get('/inventory/movements', [InventoryController::class, 'movements'], ['auth', 'perm:inventory.view']);
$router->post('/inventory/movements', [InventoryController::class, 'storeMovement'], ['auth', 'perm:inventory.manage']);
$router->get('/inventory/transfers', [InventoryController::class, 'transfers'], ['auth', 'perm:inventory.transfer']);
$router->post('/inventory/transfers', [InventoryController::class, 'storeTransfer'], ['auth', 'perm:inventory.transfer']);
$router->post('/inventory/transfers/{id}/deliver', [InventoryController::class, 'deliverTransfer'], ['auth', 'perm:inventory.transfer']);
$router->get('/inventory/stocktakes', [InventoryController::class, 'stocktakes'], ['auth', 'perm:inventory.stocktake']);
$router->post('/inventory/stocktakes', [InventoryController::class, 'storeStocktake'], ['auth', 'perm:inventory.stocktake']);

$router->get('/inventory/item-cards', [ItemCardController::class, 'index'], ['auth', 'perm:inventory.view']);
$router->get('/inventory/item-cards/export', [ItemCardController::class, 'exportList'], ['auth', 'perm:inventory.view']);
$router->get('/inventory/item-cards/{id}/edit', [ItemCardController::class, 'edit'], ['auth', 'perm:inventory.manage']);
$router->get('/inventory/item-cards/{id}/export-pdf', [ItemCardController::class, 'exportPdf'], ['auth', 'perm:inventory.view']);
$router->get('/inventory/item-cards/{id}', [ItemCardController::class, 'show'], ['auth', 'perm:inventory.view']);
$router->post('/inventory/item-cards/{id}', [ItemCardController::class, 'update'], ['auth', 'perm:inventory.manage']);

$router->get('/inventory/cost-sheets', [ItemCostSheetController::class, 'index'], ['auth', 'perm:inventory.view']);
$router->get('/inventory/cost-sheets/import', [ItemCostSheetController::class, 'importForm'], ['auth', 'perm:inventory.manage']);
$router->post('/inventory/cost-sheets/import', [ItemCostSheetController::class, 'import'], ['auth', 'perm:inventory.manage']);
$router->get('/inventory/cost-sheets/template.xlsx', [ItemCostSheetController::class, 'downloadTemplate'], ['auth', 'perm:inventory.manage']);
$router->get('/inventory/cost-sheets/{id}/edit', [ItemCostSheetController::class, 'edit'], ['auth', 'perm:inventory.manage']);
$router->post('/inventory/cost-sheets/{id}/delete', [ItemCostSheetController::class, 'delete'], ['auth', 'perm:inventory.manage']);
$router->get('/inventory/cost-sheets/{id}/export-pdf', [ItemCostSheetController::class, 'exportPdf'], ['auth', 'perm:inventory.view']);
$router->get('/inventory/cost-sheets/{id}/export-excel', [ItemCostSheetController::class, 'exportExcel'], ['auth', 'perm:inventory.view']);
$router->get('/inventory/cost-sheets/{id}', [ItemCostSheetController::class, 'show'], ['auth', 'perm:inventory.view']);
$router->post('/inventory/cost-sheets/{id}', [ItemCostSheetController::class, 'save'], ['auth', 'perm:inventory.manage']);

// استيراد تكلفة الأطباق (Plate Cost Importer)
$router->get('/inventory/plate-cost-imports', [PlateCostImportController::class, 'index'], ['auth', 'perm:inventory.view']);
$router->post('/inventory/plate-cost-imports', [PlateCostImportController::class, 'store'], ['auth', 'perm:inventory.manage']);
$router->get('/inventory/plate-cost-imports/{id}', [PlateCostImportController::class, 'show'], ['auth', 'perm:inventory.view']);
$router->get('/inventory/plate-cost-imports/{id}/recipes', [PlateCostImportController::class, 'recipes'], ['auth', 'perm:inventory.view']);
$router->get('/inventory/plate-cost-imports/{id}/plate-cost', [PlateCostImportController::class, 'plateCost'], ['auth', 'perm:inventory.view']);
$router->post('/inventory/plate-cost-imports/{id}/plate-cost', [PlateCostImportController::class, 'updateDishLine'], ['auth', 'perm:inventory.manage']);
$router->get('/inventory/plate-cost-imports/{id}/prepared-cost', [PlateCostImportController::class, 'preparedCost'], ['auth', 'perm:inventory.view']);
$router->post('/inventory/plate-cost-imports/{id}/prepared-cost', [PlateCostImportController::class, 'updatePreparedLine'], ['auth', 'perm:inventory.manage']);
$router->get('/inventory/plate-cost-imports/{id}/prices', [PlateCostImportController::class, 'prices'], ['auth', 'perm:inventory.view']);
$router->post('/inventory/plate-cost-imports/{id}/prices', [PlateCostImportController::class, 'updatePrice'], ['auth', 'perm:inventory.manage']);

// الأصناف المحضرة (Prepared Items / Semi-finished Goods)
$router->get('/inventory/prepared-items', [PreparedItemsController::class, 'index'], ['auth', 'perm:inventory.view']);
$router->get('/inventory/prepared-items/import', [PreparedItemsController::class, 'importForm'], ['auth', 'perm:inventory.manage']);
$router->post('/inventory/prepared-items/import', [PreparedItemsController::class, 'import'], ['auth', 'perm:inventory.manage']);
$router->get('/inventory/prepared-items/{id}', [PreparedItemsController::class, 'show'], ['auth', 'perm:inventory.view']);
$router->post('/inventory/prepared-items/{id}/recalculate', [PreparedItemsController::class, 'recalculate'], ['auth', 'perm:inventory.manage']);

// تحديث أسعار المواد (Item Price Management)
$router->get('/inventory/prices', [ItemPriceController::class, 'index'], ['auth', 'perm:inventory.view']);
$router->post('/inventory/prices', [ItemPriceController::class, 'store'], ['auth', 'perm:inventory.manage']);

$router->get('/warehouses', [WarehouseController::class, 'index'], ['auth', 'perm:inventory.view']);
$router->post('/warehouses', [WarehouseController::class, 'store'], ['auth', 'perm:inventory.manage']);
$router->post('/warehouses/{id}', [WarehouseController::class, 'update'], ['auth', 'perm:inventory.manage']);
$router->post('/warehouses/{id}/delete', [WarehouseController::class, 'destroy'], ['auth', 'perm:inventory.manage']);
$router->get('/warehouses/items', [WarehouseController::class, 'items'], ['auth', 'perm:inventory.view']);
$router->get('/warehouses/items/export', [WarehouseController::class, 'exportItems'], ['auth', 'perm:inventory.view']);
$router->get('/inventory/barcode', [InventoryController::class, 'barcode'], ['auth', 'perm:inventory.view']);
$router->get('/inventory/reports', [InventoryController::class, 'reports'], ['auth', 'perm:reports.view']);
$router->get('/inventory/export', [InventoryController::class, 'exportInventory'], ['auth', 'perm:inventory.view']);
$router->get('/inventory/import-template', [InventoryController::class, 'importTemplate'], ['auth', 'perm:inventory.manage']);
$router->post('/inventory/import', [InventoryController::class, 'importInventory'], ['auth', 'perm:inventory.manage']);
$router->get('/custody', [CustodyController::class, 'index'], ['auth', 'perm:custody.manage']);
$router->post('/custody', [CustodyController::class, 'store'], ['auth', 'perm:custody.manage']);
$router->post('/custody/{id}/return', [CustodyController::class, 'returnItem'], ['auth', 'perm:custody.manage']);

// المشتريات والموردون
$router->get('/purchasing/requests', [PurchasingController::class, 'requests'], ['auth', 'perm:purchasing.view']);
$router->post('/purchasing/requests', [PurchasingController::class, 'storeRequest'], ['auth', 'perm:purchasing.view']);
$router->get('/purchasing/purchase-request-items', [PurchasingController::class, 'purchaseRequestItems'], ['auth', 'perm:purchasing.manage']);
$router->post('/purchasing/purchase-request-items', [PurchasingController::class, 'storePurchaseRequestItem'], ['auth', 'perm:purchasing.manage']);
$router->post('/purchasing/purchase-request-items/{id}/delete', [PurchasingController::class, 'deletePurchaseRequestItem'], ['auth', 'perm:purchasing.manage']);
$router->post('/purchasing/purchase-request-items/{id}/toggle', [PurchasingController::class, 'togglePurchaseRequestItem'], ['auth', 'perm:purchasing.manage']);
$router->get('/purchasing/orders', [PurchasingController::class, 'orders'], ['auth', 'perm:purchasing.view']);
$router->post('/purchasing/orders', [PurchasingController::class, 'storeOrder'], ['auth', 'perm:purchasing.manage']);
$router->get('/purchasing/receipts', [PurchasingController::class, 'receipts'], ['auth', 'perm:purchasing.view']);
$router->post('/purchasing/receipts', [PurchasingController::class, 'storeReceipt'], ['auth', 'perm:purchasing.manage']);
$router->get('/suppliers', [SupplierController::class, 'index'], ['auth', 'perm:suppliers.manage']);
$router->post('/suppliers', [SupplierController::class, 'store'], ['auth', 'perm:suppliers.manage']);
$router->post('/suppliers/{id}/evaluate', [SupplierController::class, 'evaluate'], ['auth', 'perm:suppliers.manage']);
$router->get('/suppliers/template', [SupplierController::class, 'downloadTemplate'], ['auth', 'perm:suppliers.manage']);
$router->post('/suppliers/import', [SupplierController::class, 'importSuppliers'], ['auth', 'perm:suppliers.manage']);

// طلبات المواد والمشتريات
$router->get('/material-requests', [MaterialRequestController::class, 'index'], ['auth', 'perm:material_request.view']);
$router->get('/material-requests/create', [MaterialRequestController::class, 'create'], ['auth', 'perm:material_request.manage']);
$router->post('/material-requests', [MaterialRequestController::class, 'store'], ['auth', 'perm:material_request.manage']);
$router->get('/material-requests/{id}', [MaterialRequestController::class, 'show'], ['auth', 'perm:material_request.view']);
$router->get('/material-requests/{id}/edit', [MaterialRequestController::class, 'edit'], ['auth', 'perm:material_request.manage']);
$router->post('/material-requests/{id}/edit', [MaterialRequestController::class, 'update'], ['auth', 'perm:material_request.manage']);
$router->post('/material-requests/{id}/submit', [MaterialRequestController::class, 'submit'], ['auth', 'perm:material_request.manage']);
$router->post('/material-requests/{id}/cancel', [MaterialRequestController::class, 'cancel'], ['auth', 'perm:material_request.manage']);
$router->get('/material-requests/{id}/fulfillment', [MaterialRequestController::class, 'fulfillmentPlan'], ['auth', 'perm:material_request.fulfill']);
$router->post('/material-requests/{id}/fulfillment', [MaterialRequestController::class, 'executeFulfillment'], ['auth', 'perm:material_request.fulfill']);
$router->get('/material-requests/transfer/{id}/signature', [MaterialRequestController::class, 'transferSignature'], ['auth', 'perm:material_request.fulfill']);
$router->post('/material-requests/transfer/{id}/prepare', [MaterialRequestController::class, 'prepareTransfer'], ['auth', 'perm:material_request.fulfill']);
$router->post('/material-requests/transfer/{id}/deliver', [MaterialRequestController::class, 'deliverTransfer'], ['auth', 'perm:material_request.fulfill']);

// RFQ وعروض الأسعار وتتبع أوامر الشراء
$router->get('/purchasing/rfqs', [PurchasingController::class, 'rfqs'], ['auth', 'perm:purchasing.view']);
$router->get('/purchasing/rfqs/{id}', [PurchasingController::class, 'showRfq'], ['auth', 'perm:purchasing.view']);
$router->post('/purchasing/rfqs/{id}/quotes', [PurchasingController::class, 'storeQuote'], ['auth', 'perm:purchasing.manage']);
$router->post('/purchasing/quotes/{id}/select', [PurchasingController::class, 'selectQuote'], ['auth', 'perm:purchasing.manage']);
$router->post('/purchasing/requests/{id}/create-po', [PurchasingController::class, 'createPoFromPr'], ['auth', 'perm:purchasing.manage']);
$router->get('/purchasing/orders/{id}', [PurchasingController::class, 'showOrder'], ['auth', 'perm:purchasing.view']);
$router->post('/purchasing/orders/{id}/approve', [PurchasingController::class, 'approvePo'], ['auth', 'perm:purchasing.manage']);
$router->post('/purchasing/orders/{id}/tracking', [PurchasingController::class, 'storePoTracking'], ['auth', 'perm:purchasing.manage']);
$router->post('/purchasing/orders/{id}/shipments', [PurchasingController::class, 'storePoShipment'], ['auth', 'perm:purchasing.manage']);
$router->post('/purchasing/shipments/{id}/receive', [PurchasingController::class, 'receiveShipment'], ['auth', 'perm:purchasing.manage']);

// فواتير الشراء والمطابقة والدفع
$router->get('/purchasing/invoices', [PurchasingController::class, 'invoices'], ['auth', 'perm:purchasing.view']);
$router->get('/purchasing/invoices/{id}', [PurchasingController::class, 'showInvoice'], ['auth', 'perm:purchasing.view']);
$router->post('/purchasing/invoices/{id}/payment', [PurchasingController::class, 'recordInvoicePayment'], ['auth', 'perm:finance.manage']);
$router->post('/purchasing/invoices/{id}/archive', [PurchasingController::class, 'archiveInvoiceDocument'], ['auth', 'perm:purchasing.manage']);

// المالية
$router->get('/finance/cash', [FinanceController::class, 'cash'], ['auth', 'perm:finance.view']);
$router->post('/finance/cash', [FinanceController::class, 'storeCashTrx'], ['auth', 'perm:finance.manage']);
$router->get('/finance/expenses', [FinanceController::class, 'expenses'], ['auth', 'perm:finance.view']);
$router->post('/finance/expenses', [FinanceController::class, 'storeExpense'], ['auth', 'perm:finance.view']);
$router->get('/finance/payments', [FinanceController::class, 'payments'], ['auth', 'perm:finance.view']);
$router->post('/finance/payments', [FinanceController::class, 'storePayment'], ['auth', 'perm:finance.manage']);
$router->get('/finance/accounts', [FinanceController::class, 'accounts'], ['auth', 'perm:finance.view']);

// البنوك والصندوق
$router->get('/finance/banking', [BankController::class, 'index'], ['auth', 'perm:finance.view']);
$router->get('/finance/banking/cash/{id}', [BankController::class, 'cashRegister'], ['auth', 'perm:finance.view']);
$router->post('/finance/banking/cash', [BankController::class, 'storeCashRegister'], ['auth', 'perm:finance.manage']);
$router->post('/finance/banking/cash/{id}/edit', [BankController::class, 'updateCashRegister'], ['auth', 'perm:finance.manage']);
$router->post('/finance/banking/cash/{id}/delete', [BankController::class, 'deleteCashRegister'], ['auth', 'perm:finance.manage']);
$router->get('/finance/banking/banks/{id}', [BankController::class, 'bankAccount'], ['auth', 'perm:finance.view']);
$router->post('/finance/banking/banks', [BankController::class, 'storeBankAccount'], ['auth', 'perm:finance.manage']);
$router->post('/finance/banking/banks/{id}/edit', [BankController::class, 'updateBankAccount'], ['auth', 'perm:finance.manage']);
$router->post('/finance/banking/banks/{id}/delete', [BankController::class, 'deleteBankAccount'], ['auth', 'perm:finance.manage']);
$router->post('/finance/banking/deposits', [BankController::class, 'storeDeposit'], ['auth', 'perm:finance.manage']);
$router->post('/finance/banking/withdrawals', [BankController::class, 'storeWithdrawal'], ['auth', 'perm:finance.manage']);
$router->get('/finance/banking/transfers', [BankController::class, 'transfers'], ['auth', 'perm:finance.view']);
$router->post('/finance/banking/transfers', [BankController::class, 'storeTransfer'], ['auth', 'perm:finance.manage']);
$router->get('/finance/banking/checks', [BankController::class, 'checks'], ['auth', 'perm:finance.view']);
$router->post('/finance/banking/checks', [BankController::class, 'storeCheck'], ['auth', 'perm:finance.manage']);
$router->post('/finance/banking/checks/{id}/clear', [BankController::class, 'clearCheck'], ['auth', 'perm:finance.manage']);
$router->post('/finance/banking/checks/{id}/bounce', [BankController::class, 'bounceCheck'], ['auth', 'perm:finance.manage']);
$router->get('/finance/banking/reconciliations', [BankController::class, 'reconciliations'], ['auth', 'perm:finance.view']);
$router->post('/finance/banking/reconciliations', [BankController::class, 'storeReconciliation'], ['auth', 'perm:finance.manage']);
$router->get('/finance/banking/cash-advances', [BankController::class, 'cashAdvances'], ['auth', 'perm:finance.view']);
$router->post('/finance/banking/cash-advances', [BankController::class, 'storeCashAdvance'], ['auth', 'perm:finance.manage']);
$router->post('/finance/banking/cash-advances/{id}/approve', [BankController::class, 'approveCashAdvance'], ['auth', 'perm:finance.manage']);
$router->post('/finance/banking/cash-advances/{id}/settle', [BankController::class, 'settleCashAdvance'], ['auth', 'perm:finance.manage']);
$router->get('/finance/banking/custodies', [BankController::class, 'custodies'], ['auth', 'perm:finance.view']);
$router->post('/finance/banking/custodies', [BankController::class, 'storeCustody'], ['auth', 'perm:finance.manage']);
$router->post('/finance/banking/custodies/{id}/expenses', [BankController::class, 'addCustodyExpense'], ['auth', 'perm:finance.manage']);
$router->post('/finance/banking/custodies/{id}/return', [BankController::class, 'returnCustody'], ['auth', 'perm:finance.manage']);
$router->post('/finance/banking/custodies/{id}/settle', [BankController::class, 'settleCustody'], ['auth', 'perm:finance.manage']);

// مراكز الكلفة
$router->get('/finance/cost-centers', [CostCenterController::class, 'index'], ['auth', 'perm:finance.view']);
$router->post('/finance/cost-centers', [CostCenterController::class, 'store'], ['auth', 'perm:finance.manage']);
$router->get('/finance/cost-centers/{id}/edit', [CostCenterController::class, 'edit'], ['auth', 'perm:finance.view']);
$router->post('/finance/cost-centers/{id}/edit', [CostCenterController::class, 'update'], ['auth', 'perm:finance.manage']);
$router->post('/finance/cost-centers/{id}/delete', [CostCenterController::class, 'delete'], ['auth', 'perm:finance.manage']);
$router->get('/finance/cost-centers/{id}/report', [CostCenterController::class, 'report'], ['auth', 'perm:finance.view']);
$router->get('/finance/cost-centers/allocations', [CostCenterController::class, 'allocations'], ['auth', 'perm:finance.view']);
$router->post('/finance/cost-centers/allocations', [CostCenterController::class, 'storeAllocation'], ['auth', 'perm:finance.manage']);
$router->post('/finance/cost-centers/link', [CostCenterController::class, 'link'], ['auth', 'perm:finance.manage']);

// المحاسبة
$router->get('/accounting', [AccountingController::class, 'index'], ['auth', 'perm:accounting.view']);
$router->get('/accounting/accounts', [AccountingController::class, 'accounts'], ['auth', 'perm:accounting.view']);
$router->post('/accounting/accounts', [AccountingController::class, 'storeAccount'], ['auth', 'perm:accounting.manage']);
$router->get('/accounting/accounts/template', [AccountingController::class, 'accountsTemplate'], ['auth', 'perm:accounting.manage']);
$router->post('/accounting/accounts/import', [AccountingController::class, 'importAccounts'], ['auth', 'perm:accounting.manage']);
$router->post('/accounting/accounts/import-coa', [AccountingController::class, 'importCoa'], ['auth', 'perm:accounting.manage']);
$router->get('/accounting/accounts/{id}/edit', [AccountingController::class, 'editAccount'], ['auth', 'perm:accounting.manage']);
$router->post('/accounting/accounts/{id}/edit', [AccountingController::class, 'updateAccount'], ['auth', 'perm:accounting.manage']);
$router->post('/accounting/accounts/{id}/delete', [AccountingController::class, 'deleteAccount'], ['auth', 'perm:accounting.manage']);
$router->get('/accounting/journal', [AccountingController::class, 'journal'], ['auth', 'perm:accounting.view']);
$router->post('/accounting/journal', [AccountingController::class, 'storeJournal'], ['auth', 'perm:accounting.manage']);
$router->get('/accounting/journal/template', [AccountingController::class, 'journalTemplate'], ['auth', 'perm:accounting.manage']);
$router->post('/accounting/journal/import', [AccountingController::class, 'importJournal'], ['auth', 'perm:accounting.manage']);
$router->get('/accounting/journal/{id}/edit', [AccountingController::class, 'editJournal'], ['auth', 'perm:accounting.manage']);
$router->post('/accounting/journal/{id}/edit', [AccountingController::class, 'updateJournal'], ['auth', 'perm:accounting.manage']);
$router->post('/accounting/journal/{id}/reverse', [AccountingController::class, 'reverseEntry'], ['auth', 'perm:accounting.manage']);
// الأصول الثابتة
$router->get('/accounting/fixed-assets', [FixedAssetController::class, 'index'], ['auth', 'perm:accounting.view']);
$router->get('/accounting/fixed-assets/create', [FixedAssetController::class, 'create'], ['auth', 'perm:accounting.manage']);
$router->post('/accounting/fixed-assets', [FixedAssetController::class, 'store'], ['auth', 'perm:accounting.manage']);
$router->get('/accounting/fixed-assets/scan', [FixedAssetController::class, 'scan'], ['auth', 'perm:accounting.view']);
$router->get('/accounting/fixed-assets/reports', [FixedAssetController::class, 'reports'], ['auth', 'perm:accounting.reports']);
$router->get('/accounting/fixed-assets/import', [FixedAssetController::class, 'importForm'], ['auth', 'perm:accounting.manage']);
$router->post('/accounting/fixed-assets/import', [FixedAssetController::class, 'importAssets'], ['auth', 'perm:accounting.manage']);
$router->get('/accounting/fixed-assets/import/template', [FixedAssetController::class, 'importTemplate'], ['auth', 'perm:accounting.manage']);
$router->get('/accounting/fixed-assets/alerts', [FixedAssetController::class, 'maintenanceAlerts'], ['auth', 'perm:accounting.view']);
$router->post('/accounting/fixed-assets/transactions', [FixedAssetController::class, 'storeTransaction'], ['auth', 'perm:accounting.manage']);
$router->post('/accounting/fixed-assets/maintenance', [FixedAssetController::class, 'storeMaintenance'], ['auth', 'perm:accounting.manage']);
$router->post('/accounting/fixed-assets/depreciate', [FixedAssetController::class, 'runDepreciation'], ['auth', 'perm:accounting.manage']);
$router->post('/accounting/fixed-assets/production-units', [FixedAssetController::class, 'storeProductionUnits'], ['auth', 'perm:accounting.manage']);
$router->get('/accounting/fixed-assets/{id}', [FixedAssetController::class, 'show'], ['auth', 'perm:accounting.view']);
$router->get('/accounting/fixed-assets/{id}/edit', [FixedAssetController::class, 'edit'], ['auth', 'perm:accounting.manage']);
$router->post('/accounting/fixed-assets/{id}/edit', [FixedAssetController::class, 'update'], ['auth', 'perm:accounting.manage']);
$router->get('/accounting/fixed-assets/{id}/qr', [FixedAssetController::class, 'printQr'], ['auth', 'perm:accounting.view']);
$router->get('/accounting/assets', [FixedAssetController::class, 'index'], ['auth', 'perm:accounting.view']);

// الحسابات المدينة والدائنة
$router->get('/accounting/receivables', [ARAPController::class, 'receivables'], ['auth', 'perm:accounting.view']);
$router->get('/accounting/receivables/{id}', [ARAPController::class, 'customerStatement'], ['auth', 'perm:accounting.view']);
$router->get('/accounting/payables', [ARAPController::class, 'payables'], ['auth', 'perm:accounting.view']);
$router->get('/accounting/payables/{id}', [ARAPController::class, 'supplierStatement'], ['auth', 'perm:accounting.view']);
$router->post('/accounting/arap/payments', [ARAPController::class, 'storePayment'], ['auth', 'perm:accounting.manage']);
$router->post('/accounting/arap/adjustments', [ARAPController::class, 'storeAdjustment'], ['auth', 'perm:accounting.manage']);
$router->get('/accounting/arap/aging', [ARAPController::class, 'agingReport'], ['auth', 'perm:accounting.view']);
$router->get('/accounting/arap/overdue', [ARAPController::class, 'overdueReport'], ['auth', 'perm:accounting.view']);

$router->get('/accounting/ledger', [AccountingController::class, 'ledger'], ['auth', 'perm:accounting.view']);
$router->get('/accounting/reports', [AccountingController::class, 'reports'], ['auth', 'perm:accounting.reports']);
$router->get('/accounting/reports/trial-balance', [AccountingController::class, 'trialBalance'], ['auth', 'perm:accounting.reports']);
$router->get('/accounting/reports/profit-loss', [AccountingController::class, 'profitLoss'], ['auth', 'perm:accounting.reports']);
$router->get('/accounting/reports/balance-sheet', [AccountingController::class, 'balanceSheet'], ['auth', 'perm:accounting.reports']);
$router->get('/accounting/material-consumption', [AccountingController::class, 'consumption'], ['auth', 'perm:accounting.view']);
$router->get('/accounting/accounts/template', [AccountingController::class, 'accountsTemplate'], ['auth', 'perm:accounting.manage']);
$router->post('/accounting/accounts/import', [AccountingController::class, 'importAccounts'], ['auth', 'perm:accounting.manage']);

// الموارد البشرية
$router->get('/hr/employees', [HrController::class, 'employees'], ['auth', 'perm:hr.view']);
$router->post('/hr/employees/sync-accounts', [HrController::class, 'syncEmployeeAccounts'], ['auth', 'perm:hr.manage']);
$router->get('/hr/employees/create', [HrController::class, 'createEmployeeForm'], ['auth', 'perm:hr.manage,hr.employee.create']);
$router->post('/hr/employees', [HrController::class, 'storeEmployee'], ['auth', 'perm:hr.manage,hr.employee.create']);
$router->get('/hr/employees/logs', [HrController::class, 'employeeStatusLogs'], ['auth', 'perm:hr.view']);
$router->post('/hr/employees/{id}/delete', [HrController::class, 'deleteEmployee'], ['auth', 'perm:hr.manage']);
$router->post('/hr/employees/{id}/toggle', [HrController::class, 'toggleFile'], ['auth', 'perm:hr.file.manage']);
$router->get('/hr/employees/template', [HrController::class, 'employeesTemplate'], ['auth', 'perm:hr.manage']);
$router->post('/hr/employees/import', [HrController::class, 'importEmployees'], ['auth', 'perm:hr.manage']);
$router->get('/hr/employees/export', [HrController::class, 'exportEmployees'], ['auth', 'perm:hr.view']);
$router->get('/hr/employees/ids-export', [HrController::class, 'exportEmployeeIds'], ['auth', 'perm:hr.manage']);
$router->get('/hr/movements', [HrController::class, 'movements'], ['auth', 'perm:hr.view']);
$router->get('/hr/movements/export', [HrController::class, 'exportMovements'], ['auth', 'perm:hr.view']);
$router->get('/hr/movements/template/{type}', [HrController::class, 'movementsTemplate'], ['auth', 'perm:hr.manage']);
$router->post('/hr/movements/import/{type}', [HrController::class, 'importMovements'], ['auth', 'perm:hr.file.manage']);
$router->get('/hr/attendance', [HrController::class, 'attendance'], ['auth', 'perm:hr.view']);
$router->get('/hr/attendance/export', [HrController::class, 'exportAttendance'], ['auth', 'perm:hr.view']);
$router->get('/hr/attendance/alerts', [HrController::class, 'attendanceAlerts'], ['auth', 'perm:hr.alerts.manage']);
$router->post('/hr/attendance/alerts/{id}/status', [HrController::class, 'updateAlertStatus'], ['auth', 'perm:hr.alerts.manage']);
$router->post('/hr/leaves/{id}/decide', [HrController::class, 'decideLeave'], ['auth', 'perm:approvals.decide,hr.leave.approve,workflow.decide']);
$router->get('/api/attendance/check-alerts', [HrController::class, 'runMissingClockInAlerts'], ['api_or_synckey']);
$router->post('/hr/attendance/biometric', [HrController::class, 'biometricImport'], ['auth', 'perm:hr.biometric']);
$router->get('/hr/clock', [HrController::class, 'clockPage'], ['auth', 'perm:hr.clock,mobile.attendance,attendance.self']);
$router->post('/hr/clock', [HrController::class, 'clockInOut'], ['auth', 'perm:hr.clock,mobile.attendance,attendance.self']);
$router->get('/hr/leaves', [HrController::class, 'leaves'], ['auth', 'perm:hr.view']);
$router->post('/hr/leaves', [HrController::class, 'storeLeave'], ['auth', 'perm:hr.view']);
$router->get('/hr/leaves/{id}', [HrController::class, 'showLeave'], ['auth', 'perm:hr.view']);
$router->get('/hr/leave-balances', [HrController::class, 'leaveBalances'], ['auth', 'perm:hr.leave_balance']);
$router->post('/hr/leave-balances', [HrController::class, 'updateLeaveBalance'], ['auth', 'perm:hr.leave_balance']);
$router->get('/hr/notification-rules', [HrController::class, 'notificationRules'], ['auth', 'perm:hr.notification_rules']);
$router->post('/hr/notification-rules', [HrController::class, 'storeNotificationRule'], ['auth', 'perm:hr.notification_rules']);
$router->get('/hr/notification-rules/{id}/toggle', [HrController::class, 'toggleNotificationRule'], ['auth', 'perm:hr.notification_rules']);
$router->get('/hr/announcements', [HrController::class, 'announcements'], ['auth', 'perm:hr.notifications.view']);
$router->post('/hr/announcements', [HrController::class, 'sendAnnouncement'], ['auth', 'perm:hr.notifications.send']);
$router->get('/hr/notifications-history', [HrController::class, 'notificationHistory'], ['auth', 'perm:hr.notifications.history']);
$router->get('/hr/notifications-history/{id}', [HrController::class, 'showNotificationHistory'], ['auth', 'perm:hr.notifications.history']);
$router->post('/hr/notifications/read', [HrController::class, 'markNotificationRead'], ['auth']);
$router->get('/hr/announcement-reminders/{id}/toggle', [HrController::class, 'toggleAnnouncementReminder'], ['auth', 'perm:hr.notifications.reminders']);
$router->get('/hr/announcement-reminders/{id}/delete', [HrController::class, 'deleteAnnouncementReminder'], ['auth', 'perm:hr.notifications.reminders']);
$router->get('/hr/employees/{id}', [HrController::class, 'showEmployeeProfile'], ['auth', 'perm:hr.employee.profile']);
$router->get('/hr/employees/{id}/edit', [HrController::class, 'editEmployee'], ['auth', 'perm:hr.manage']);
$router->post('/hr/employees/{id}/edit', [HrController::class, 'updateEmployee'], ['auth', 'perm:hr.manage']);
$router->post('/hr/employees/{id}/reference-photo/delete', [HrController::class, 'deleteReferencePhoto'], ['auth', 'perm:hr.manage']);
$router->get('/hr/employees/{id}/documents', [HrController::class, 'employeeDocuments'], ['auth', 'perm:hr.employee.documents']);
$router->post('/hr/employees/{id}/documents', [HrController::class, 'storeEmployeeDocument'], ['auth', 'perm:hr.employee.documents']);
$router->post('/hr/employees/documents/{id}/delete', [HrController::class, 'deleteEmployeeDocument'], ['auth', 'perm:hr.employee.documents']);
$router->get('/hr/employees/{id}/audit', [HrController::class, 'employeeAudit'], ['auth', 'perm:hr.employee.audit']);
$router->post('/hr/employees/{id}/contracts', [HrController::class, 'storeEmployeeContract'], ['auth', 'perm:hr.manage']);

// الصيانة
$router->get('/maintenance', [MaintenanceController::class, 'index'], ['auth']);
$router->get('/maintenance/create', [MaintenanceController::class, 'create'], ['auth']);
$router->post('/maintenance', [MaintenanceController::class, 'store'], ['auth']);
$router->get('/maintenance/{id}', [MaintenanceController::class, 'show'], ['auth']);
$router->post('/maintenance/{id}/assign', [MaintenanceController::class, 'assign'], ['auth']);
$router->post('/maintenance/{id}/start', [MaintenanceController::class, 'start'], ['auth']);
$router->post('/maintenance/{id}/finish', [MaintenanceController::class, 'finish'], ['auth']);
$router->post('/maintenance/{id}/confirm', [MaintenanceController::class, 'confirm'], ['auth']);
$router->post('/hr/employees/contracts/{id}/delete', [HrController::class, 'deleteEmployeeContract'], ['auth', 'perm:hr.manage']);
$router->get('/hr/departments', [HrController::class, 'departments'], ['auth', 'perm:hr.department.manage']);
$router->post('/hr/departments', [HrController::class, 'storeDepartment'], ['auth', 'perm:hr.department.manage']);
$router->get('/hr/departments/template', [HrController::class, 'departmentsTemplate'], ['auth', 'perm:hr.department.manage']);
$router->post('/hr/departments/import', [HrController::class, 'importDepartments'], ['auth', 'perm:hr.department.manage']);
$router->get('/hr/departments/export', [HrController::class, 'exportDepartments'], ['auth', 'perm:hr.department.manage']);
$router->post('/hr/departments/{id}', [HrController::class, 'updateDepartment'], ['auth', 'perm:hr.department.manage']);
$router->post('/hr/departments/{id}/delete', [HrController::class, 'deleteDepartment'], ['auth', 'perm:hr.department.manage']);
$router->get('/hr/jobs', [HrController::class, 'jobs'], ['auth', 'perm:hr.job.manage']);
$router->post('/hr/jobs', [HrController::class, 'storeJob'], ['auth', 'perm:hr.job.manage']);
$router->get('/hr/jobs/template', [HrController::class, 'jobsTemplate'], ['auth', 'perm:hr.job.manage']);
$router->post('/hr/jobs/import', [HrController::class, 'importJobs'], ['auth', 'perm:hr.job.manage']);
$router->get('/hr/jobs/export', [HrController::class, 'exportJobs'], ['auth', 'perm:hr.job.manage']);
$router->post('/hr/jobs/{id}', [HrController::class, 'updateJob'], ['auth', 'perm:hr.job.manage']);
$router->post('/hr/jobs/{id}/delete', [HrController::class, 'deleteJob'], ['auth', 'perm:hr.job.manage']);
$router->get('/hr/advances', [HrController::class, 'advances'], ['auth', 'perm:hr.view']);
$router->post('/hr/advances', [HrController::class, 'storeAdvance'], ['auth', 'perm:hr.view']);
$router->get('/hr/advances/{id}', [HrController::class, 'showAdvance'], ['auth', 'perm:hr.view']);
$router->post('/hr/advances/{id}/decide', [HrController::class, 'decideAdvance'], ['auth', 'perm:hr.advance.approve,approvals.decide,workflow.decide']);
$router->post('/hr/advances/{id}/pay', [HrController::class, 'payAdvance'], ['auth', 'perm:payroll.manage']);

$router->get('/hr/overtime', [HrController::class, 'overtime'], ['auth', 'perm:hr.view']);
$router->post('/hr/overtime', [HrController::class, 'storeOvertime'], ['auth', 'perm:hr.view']);
$router->get('/hr/overtime/{id}', [HrController::class, 'showOvertime'], ['auth', 'perm:hr.view']);
$router->post('/hr/overtime/{id}/decide', [HrController::class, 'decideOvertime'], ['auth', 'perm:hr.overtime.approve,approvals.decide,workflow.decide']);

$router->get('/hr/holidays', [HrController::class, 'holidays'], ['auth', 'perm:hr.manage']);
$router->post('/hr/holidays', [HrController::class, 'storeHoliday'], ['auth', 'perm:hr.manage']);
$router->post('/hr/holidays/{id}/delete', [HrController::class, 'deleteHoliday'], ['auth', 'perm:hr.manage']);
$router->post('/hr/holidays/months', [HrController::class, 'storeHolidayMonth'], ['auth', 'perm:hr.manage']);
$router->post('/hr/holidays/months/{id}/delete', [HrController::class, 'deleteHolidayMonth'], ['auth', 'perm:hr.manage']);

$router->get('/hr/payroll', [HrController::class, 'payroll'], ['auth', 'perm:payroll.manage']);
$router->post('/hr/payroll/generate', [HrController::class, 'generatePayroll'], ['auth', 'perm:payroll.manage']);
$router->post('/hr/payroll/absence-mode', [HrController::class, 'setPayrollAbsenceMode'], ['auth', 'perm:payroll.manage']);
$router->get('/hr/payroll/messages', [HrController::class, 'payrollMessages'], ['auth', 'perm:payroll.manage']);
$router->post('/hr/payroll/messages/send', [HrController::class, 'sendPayrollMessages'], ['auth', 'perm:payroll.manage']);
$router->get('/hr/payroll/import', [HrController::class, 'payrollImport'], ['auth', 'perm:payroll.manage']);
$router->post('/hr/payroll/import/{component}', [HrController::class, 'importPayrollComponent'], ['auth', 'perm:payroll.manage']);
$router->get('/hr/payroll/import-template/{component}', [HrController::class, 'payrollImportTemplate'], ['auth', 'perm:payroll.manage']);
$router->get('/hr/payroll/import-template', [HrController::class, 'payrollImportUnifiedTemplate'], ['auth', 'perm:payroll.manage']);
$router->post('/hr/payroll/import-all', [HrController::class, 'importPayrollUnified'], ['auth', 'perm:payroll.manage']);
$router->get('/hr/payroll/import-historical-template', [HrController::class, 'payrollImportHistoricalTemplate'], ['auth', 'perm:payroll.manage']);
$router->post('/hr/payroll/import-historical', [HrController::class, 'importPayrollHistorical'], ['auth', 'perm:payroll.manage']);
$router->get('/hr/payroll/{id}/item-details', [HrController::class, 'payrollItemDetails'], ['auth', 'perm:payroll.manage']);
$router->get('/hr/headcount', [HrController::class, 'headcount'], ['auth', 'perm:payroll.manage']);
$router->post('/hr/headcount', [HrController::class, 'saveHeadcount'], ['auth', 'perm:payroll.manage']);
$router->post('/hr/headcount/{id}/delete', [HrController::class, 'deleteHeadcount'], ['auth', 'perm:payroll.manage']);
$router->post('/hr/payroll/bulk-approve', [HrController::class, 'bulkApprovePayroll'], ['auth', 'perm:payroll.approve']);
$router->post('/hr/payroll/{id}/approve', [HrController::class, 'approvePayroll'], ['auth', 'perm:payroll.approve']);
$router->post('/hr/payroll/{id}/unapprove', [HrController::class, 'unapprovePayroll'], ['auth', 'perm:payroll.approve']);
$router->post('/hr/payroll/{id}/overtime', [HrController::class, 'addOvertime'], ['auth', 'perm:payroll.overtime']);
$router->get('/hr/payroll/allowances', [HrController::class, 'employeeAllowances'], ['auth', 'perm:payroll.manage']);
$router->post('/hr/payroll/allowances', [HrController::class, 'employeeAllowances'], ['auth', 'perm:payroll.manage']);
$router->get('/hr/payroll/tips', [HrController::class, 'monthlyTips'], ['auth', 'perm:payroll.manage']);
$router->post('/hr/payroll/tips', [HrController::class, 'storeMonthlyTips'], ['auth', 'perm:payroll.manage']);
$router->post('/hr/payroll/tips/{id}/delete', [HrController::class, 'deleteMonthlyTips'], ['auth', 'perm:payroll.manage']);
$router->get('/hr/payroll/social-security', [HrController::class, 'monthlySocialSecurity'], ['auth', 'perm:payroll.manage']);
$router->post('/hr/payroll/social-security', [HrController::class, 'storeMonthlySocialSecurity'], ['auth', 'perm:payroll.manage']);
$router->post('/hr/payroll/social-security/{id}/delete', [HrController::class, 'deleteMonthlySocialSecurity'], ['auth', 'perm:payroll.manage']);
$router->get('/hr/payroll/travel-tickets', [HrController::class, 'travelTickets'], ['auth', 'perm:payroll.manage']);
$router->post('/hr/payroll/travel-tickets', [HrController::class, 'storeTravelTicket'], ['auth', 'perm:payroll.manage']);
$router->post('/hr/payroll/travel-tickets/{id}/delete', [HrController::class, 'deleteTravelTicket'], ['auth', 'perm:payroll.manage']);
$router->get('/hr/payroll/loadings', [HrController::class, 'monthlyLoadings'], ['auth', 'perm:payroll.manage']);
$router->post('/hr/payroll/loadings', [HrController::class, 'storeMonthlyLoading'], ['auth', 'perm:payroll.manage']);
$router->post('/hr/payroll/loadings/{id}/delete', [HrController::class, 'deleteMonthlyLoading'], ['auth', 'perm:payroll.manage']);
$router->get('/hr/payroll/tax-brackets', [HrController::class, 'taxBrackets'], ['auth', 'perm:payroll.manage']);
$router->post('/hr/payroll/tax-brackets', [HrController::class, 'storeTaxBracket'], ['auth', 'perm:payroll.manage']);
$router->post('/hr/payroll/tax-brackets/{id}/toggle', [HrController::class, 'toggleTaxBracket'], ['auth', 'perm:payroll.manage']);
$router->post('/hr/payroll/tax-brackets/{id}/delete', [HrController::class, 'deleteTaxBracket'], ['auth', 'perm:payroll.manage']);
$router->post('/hr/payroll/{id}/pay', [HrController::class, 'payPayroll'], ['auth', 'perm:payroll.manage']);
$router->get('/hr/payroll/{id}/slip', [HrController::class, 'paySlip'], ['auth', 'perm:hr.payslip.view']);
$router->get('/hr/adjustments', [HrController::class, 'adjustments'], ['auth', 'perm:hr.adjustments.manage']);
$router->post('/hr/adjustments', [HrController::class, 'storeAdjustment'], ['auth', 'perm:hr.adjustments.manage']);
$router->get('/hr/adjustments/{id}', [HrController::class, 'showAdjustment'], ['auth', 'perm:hr.adjustments.manage']);
$router->post('/hr/adjustments/{id}/decide', [HrController::class, 'decideAdjustment'], ['auth', 'perm:hr.adjustments.manage,approvals.decide,workflow.decide']);
$router->post('/hr/adjustments/{id}/cancel', [HrController::class, 'cancelAdjustment'], ['auth', 'perm:hr.adjustments.manage']);
$router->post('/hr/adjustments/warnings', [HrController::class, 'storeWarning'], ['auth', 'perm:hr.warnings.manage']);
$router->post('/hr/adjustments/warnings/{id}/resolve', [HrController::class, 'resolveWarning'], ['auth', 'perm:hr.warnings.manage']);
$router->get('/hr/reports', [HrController::class, 'reports'], ['auth', 'perm:hr.reports.view']);
$router->get('/hr/reports/regularity', [HrController::class, 'regularityReport'], ['auth', 'perm:hr.reports.view']);
$router->get('/hr/reports/export', [HrController::class, 'exportReports'], ['auth', 'perm:hr.reports.view']);
$router->get('/hr/evaluations', [HrController::class, 'evaluations'], ['auth', 'perm:hr.evaluation.view']);
$router->post('/hr/evaluations', [HrController::class, 'storeEvaluation'], ['auth', 'perm:hr.evaluation.manage']);
$router->get('/hr/evaluations/{id}', [HrController::class, 'showEvaluation'], ['auth', 'perm:hr.evaluation.view']);
$router->post('/hr/evaluations/{id}/finalize', [HrController::class, 'finalizeEvaluation'], ['auth', 'perm:hr.evaluation.manage']);
$router->post('/hr/reports/evaluations/save', [HrController::class, 'saveJobPerformanceEvaluation'], ['auth', 'perm:hr.evaluation.manage']);
$router->post('/hr/reports/evaluations/delete', [HrController::class, 'deleteJobPerformanceEvaluation'], ['auth', 'perm:hr.evaluation.manage']);
$router->get('/hr/kpis', [HrController::class, 'kpis'], ['auth', 'perm:hr.evaluation.manage']);
$router->post('/hr/kpis', [HrController::class, 'storeKpi'], ['auth', 'perm:hr.evaluation.manage']);
$router->post('/hr/kpis/{id}', [HrController::class, 'updateKpi'], ['auth', 'perm:hr.evaluation.manage']);
$router->post('/hr/kpis/{id}/toggle', [HrController::class, 'toggleKpi'], ['auth', 'perm:hr.evaluation.manage']);
$router->post('/hr/kpis/{id}/delete', [HrController::class, 'deleteKpi'], ['auth', 'perm:hr.evaluation.manage']);
$router->get('/hr/goals', [HrController::class, 'employeeGoals'], ['auth', 'perm:hr.goals.manage']);
$router->post('/hr/goals', [HrController::class, 'storeGoal'], ['auth', 'perm:hr.goals.manage']);
$router->post('/hr/goals/{id}', [HrController::class, 'updateGoal'], ['auth', 'perm:hr.goals.manage']);
$router->post('/hr/goals/{id}/delete', [HrController::class, 'deleteGoal'], ['auth', 'perm:hr.goals.manage']);
$router->get('/hr/accounting-settings', [HrController::class, 'hrAccountingSettings'], ['auth', 'perm:hr.accounting.settings']);
$router->post('/hr/accounting-settings', [HrController::class, 'hrAccountingSettings'], ['auth', 'perm:hr.accounting.settings']);
$router->get('/hr/tasks', [HrController::class, 'tasks'], ['auth', 'perm:tasks.self,mobile.tasks']);
$router->get('/hr/tasks/export', [HrController::class, 'exportTasks'], ['auth', 'perm:tasks.self,mobile.tasks']);
$router->post('/hr/tasks', [HrController::class, 'storeTask'], ['auth', 'perm:tasks.self,mobile.tasks']);
$router->post('/hr/tasks/{id}/status', [HrController::class, 'updateTaskStatus'], ['auth', 'perm:tasks.self,mobile.tasks']);

// طلبات المدير (تعيين / إيقاف دوام) — تم نقلها إلى نظام Workflow الديناميكي (/workflows)

// الورديات
// المسارات الثابتة يجب أن تكون قبل المسارات الديناميكية مثل /hr/shifts/{id}
$router->get('/hr/shifts', [ShiftController::class, 'index'], ['auth', 'perm:hr.manage']);
$router->post('/hr/shifts', [ShiftController::class, 'store'], ['auth', 'perm:hr.manage']);
$router->get('/hr/shifts/template', [ShiftController::class, 'template'], ['auth', 'perm:hr.manage']);
$router->post('/hr/shifts/import', [ShiftController::class, 'import'], ['auth', 'perm:hr.manage']);
$router->post('/hr/shifts/bulk-delete', [ShiftController::class, 'bulkDelete'], ['auth', 'perm:hr.manage']);
$router->post('/hr/shifts/{id}/delete', [ShiftController::class, 'destroy'], ['auth', 'perm:hr.manage']);
$router->post('/hr/shifts/{id}', [ShiftController::class, 'update'], ['auth', 'perm:hr.manage']);

// إدارة العهد
$router->get('/hr/custody', [HrController::class, 'custody'], ['auth', 'perm:hr.custody.view']);
$router->post('/hr/custody', [HrController::class, 'storeCustody'], ['auth', 'perm:hr.custody.manage']);
$router->get('/hr/custody/employee/{id}', [HrController::class, 'employeeCustody'], ['auth', 'perm:hr.custody.view']);
$router->post('/hr/custody/{id}/return', [HrController::class, 'returnCustody'], ['auth', 'perm:hr.custody.manage']);
$router->get('/hr/custody/alerts', [HrController::class, 'custodyAlerts'], ['auth', 'perm:hr.custody.view']);
$router->post('/hr/custody/alerts/{id}/ack', [HrController::class, 'acknowledgeCustodyAlert'], ['auth', 'perm:hr.custody.manage']);
$router->get('/api/custody/check-alerts', [HrController::class, 'runCustodyAlerts'], ['api_or_synckey']);

// إدارة السكن
$router->get('/hr/housing', [HousingController::class, 'index'], ['auth', 'perm:hr.housing.manage']);
$router->post('/hr/housing/apartments', [HousingController::class, 'storeApartment'], ['auth', 'perm:hr.housing.manage']);
$router->post('/hr/housing/apartments/{id}/delete', [HousingController::class, 'deleteApartment'], ['auth', 'perm:hr.housing.manage']);
$router->post('/hr/housing/rooms', [HousingController::class, 'storeRoom'], ['auth', 'perm:hr.housing.manage']);
$router->post('/hr/housing/rooms/{id}/delete', [HousingController::class, 'deleteRoom'], ['auth', 'perm:hr.housing.manage']);
$router->post('/hr/housing/rooms/{id}/update', [HousingController::class, 'updateRoom'], ['auth', 'perm:hr.housing.manage']);
$router->get('/hr/housing/apartments-by-site/{siteId}', [HousingController::class, 'apartmentsForSite'], ['auth', 'perm:hr.housing.manage']);
$router->get('/hr/housing/rooms-by-apartment/{apartmentId}', [HousingController::class, 'roomsForApartment'], ['auth', 'perm:hr.housing.manage']);
$router->post('/hr/housing/assign', [HousingController::class, 'assignEmployee'], ['auth', 'perm:hr.housing.manage']);
$router->post('/hr/housing/assignments/{id}/end', [HousingController::class, 'endAssignment'], ['auth', 'perm:hr.housing.manage']);
$router->post('/hr/housing/assignments/bulk-end', [HousingController::class, 'bulkEndAssignments'], ['auth', 'perm:hr.housing.manage']);
$router->post('/hr/housing/sites/{id}/deduction', [HousingController::class, 'updateSiteDeduction'], ['auth', 'perm:hr.housing.manage']);
$router->get('/hr/housing/import/template', [HousingController::class, 'importTemplate'], ['auth', 'perm:hr.housing.manage']);
$router->post('/hr/housing/import', [HousingController::class, 'import'], ['auth', 'perm:hr.housing.manage']);
$router->get('/hr/housing/structure/template', [HousingController::class, 'structureImportTemplate'], ['auth', 'perm:hr.housing.manage']);
$router->post('/hr/housing/structure/import', [HousingController::class, 'importStructure'], ['auth', 'perm:hr.housing.manage']);
$router->post('/hr/housing/assignments/{id}/update', [HousingController::class, 'updateAssignment'], ['auth', 'perm:hr.housing.manage']);
$router->get('/hr/housing/export', [HousingController::class, 'exportAssignments'], ['auth', 'perm:hr.housing.manage']);

// الهيكل التنظيمي
$router->get('/hr/orgchart', [OrgChartController::class, 'index'], ['auth', 'perm:hr.view']);
$router->post('/hr/orgchart', [OrgChartController::class, 'store'], ['auth', 'perm:hr.manage']);
$router->post('/hr/orgchart/{id}', [OrgChartController::class, 'update'], ['auth', 'perm:hr.manage']);
$router->post('/hr/orgchart/{id}/delete', [OrgChartController::class, 'destroy'], ['auth', 'perm:hr.manage']);

// الموافقات
$router->get('/approvals', [ApprovalController::class, 'index'], ['auth', 'perm:approvals.decide,mobile.approvals,workflow.decide']);
$router->get('/approvals/{id}', [ApprovalController::class, 'show'], ['auth', 'perm:approvals.decide,mobile.approvals']);
$router->get('/approvals/{id}/decide', [ApprovalController::class, 'show'], ['auth', 'perm:approvals.decide,mobile.approvals']);
$router->post('/approvals/{id}/decide', [ApprovalController::class, 'decide'], ['auth', 'perm:approvals.decide,mobile.approvals']);

// سير العمل والموافقات (Workflow Engine)
$router->get('/workflows', [WorkflowController::class, 'index'], ['auth']);
$router->get('/workflows/inbox', [WorkflowController::class, 'inbox'], ['auth']);
$router->get('/workflows/templates', [WorkflowController::class, 'templates'], ['auth', 'perm:workflow.manage']);
$router->post('/workflows/templates', [WorkflowController::class, 'storeTemplate'], ['auth', 'perm:workflow.manage']);
$router->get('/workflows/templates/{id}/steps', [WorkflowController::class, 'steps'], ['auth', 'perm:workflow.manage']);
$router->post('/workflows/templates/{id}/steps', [WorkflowController::class, 'storeStep'], ['auth', 'perm:workflow.manage']);
$router->post('/workflows/templates/{id}/steps/{stepId}/delete', [WorkflowController::class, 'destroyStep'], ['auth', 'perm:workflow.manage']);
$router->post('/workflows/templates/{id}/delete', [WorkflowController::class, 'destroyTemplate'], ['auth', 'perm:workflow.manage']);
$router->get('/workflows/new', [WorkflowController::class, 'newRequest'], ['auth', 'perm:workflow.create,mobile.approvals']);
$router->get('/workflows/create/{template_id}', [WorkflowController::class, 'createRequestForm'], ['auth', 'perm:workflow.create,mobile.approvals']);
$router->get('/workflows/employees/{id}/advance-limit', [WorkflowController::class, 'employeeAdvanceLimit'], ['auth']);
$router->post('/workflows/requests', [WorkflowController::class, 'storeRequest'], ['auth', 'perm:workflow.create,mobile.approvals']);
$router->get('/workflows/requests', [WorkflowController::class, 'requests'], ['auth']);
$router->get('/workflows/requests/{id}', [WorkflowController::class, 'showRequest'], ['auth']);
$router->get('/workflows/requests/{id}/edit', [WorkflowController::class, 'editRequestForm'], ['auth', 'perm:workflow.create,mobile.approvals,workflow.decide,approvals.decide']);
$router->post('/workflows/requests/{id}/edit', [WorkflowController::class, 'updateRequest'], ['auth', 'perm:workflow.create,mobile.approvals,workflow.decide,approvals.decide']);
$router->post('/workflows/requests/{id}/submit', [WorkflowController::class, 'submitRequest'], ['auth', 'perm:workflow.create,mobile.approvals']);
$router->post('/workflows/requests/{id}/decide', [WorkflowController::class, 'decideRequest'], ['auth', 'perm:workflow.decide,approvals.decide']);
$router->post('/workflows/requests/{id}/resubmit', [WorkflowController::class, 'resubmitRequest'], ['auth', 'perm:workflow.create,mobile.approvals']);
$router->post('/workflows/requests/{id}/comment', [WorkflowController::class, 'commentRequest'], ['auth']);
$router->post('/workflows/requests/{id}/cancel', [WorkflowController::class, 'cancelRequest'], ['auth', 'perm:workflow.create,mobile.approvals']);

// محرك Workflow الديناميكي + المصمم المرئي (Drag & Drop)
$router->get('/workflow-engine', [WorkflowDesignerController::class, 'index'], ['auth', 'perm:workflow.manage']);
$router->get('/workflow-engine/new', [WorkflowDesignerController::class, 'create'], ['auth', 'perm:workflow.manage']);
$router->post('/workflow-engine', [WorkflowDesignerController::class, 'store'], ['auth', 'perm:workflow.manage']);
$router->post('/workflow-engine/{id}/update', [WorkflowDesignerController::class, 'update'], ['auth', 'perm:workflow.manage']);
$router->post('/workflow-engine/{id}/delete', [WorkflowDesignerController::class, 'destroy'], ['auth', 'perm:workflow.manage']);
$router->get('/workflow-engine/{id}/design', [WorkflowDesignerController::class, 'design'], ['auth', 'perm:workflow.manage']);
$router->get('/workflow-engine/{id}/meta', [WorkflowDesignerController::class, 'metaJson'], ['auth', 'perm:workflow.manage']);
$router->get('/workflow-engine/versions/{versionId}/graph', [WorkflowDesignerController::class, 'graphJson'], ['auth', 'perm:workflow.manage']);
$router->post('/workflow-engine/versions/{versionId}/graph', [WorkflowDesignerController::class, 'saveGraph'], ['auth', 'perm:workflow.manage']);
$router->post('/workflow-engine/versions/{versionId}/publish', [WorkflowDesignerController::class, 'publish'], ['auth', 'perm:workflow.manage']);
$router->get('/workflow-engine/delegations', [WorkflowDesignerController::class, 'delegations'], ['auth', 'perm:workflow.manage']);
$router->post('/workflow-engine/delegations', [WorkflowDesignerController::class, 'storeDelegation'], ['auth', 'perm:workflow.manage']);
$router->post('/workflow-engine/delegations/{id}/toggle', [WorkflowDesignerController::class, 'toggleDelegation'], ['auth', 'perm:workflow.manage']);
$router->post('/workflow-engine/delegations/{id}/delete', [WorkflowDesignerController::class, 'destroyDelegation'], ['auth', 'perm:workflow.manage']);

// cron التصعيد التلقائي (عام — محمي بتوكن من الإعدادات)
$router->get('/cron/workflow-escalation', [WorkflowDesignerController::class, 'cronEscalate']);
// cron إشعارات الورديات الثابتة (عام — محمي بتوكن من الإعدادات)
$router->get('/cron/shift-notifications', [HrController::class, 'cronShiftNotifications']);

// مركز الإشعارات الموحد
$router->get('/notifications', [NotificationsController::class, 'index'], ['auth']);
$router->get('/notifications/count', [NotificationsController::class, 'countJson'], ['auth']);
$router->get('/notifications/{id}/open', [NotificationsController::class, 'open'], ['auth']);
$router->post('/notifications/{id}/read', [NotificationsController::class, 'markRead'], ['auth']);
$router->post('/notifications/read-all', [NotificationsController::class, 'readAll'], ['auth']);
$router->post('/push/subscribe', [NotificationsController::class, 'pushSubscribe'], ['auth']);
$router->post('/push/unsubscribe', [NotificationsController::class, 'pushUnsubscribe'], ['auth']);

// ===== Push-to-Talk (PTT) — الويب =====
$router->get('/ptt', [PttController::class, 'index'], ['auth', 'perm:ptt.use']);
$router->get('/ptt/channels/{id}', [PttController::class, 'channel'], ['auth', 'perm:ptt.use']);
$router->post('/ptt/send', [PttController::class, 'send'], ['auth', 'perm:ptt.use']);
$router->post('/ptt/messages/{id}/played', [PttController::class, 'played'], ['auth', 'perm:ptt.use']);
$router->post('/ptt/messages/{id}/ack', [PttController::class, 'ack'], ['auth', 'perm:ptt.use']);
$router->post('/ptt/messages/{id}/delete', [PttController::class, 'deleteMessage'], ['auth', 'perm:ptt.use']);
$router->post('/ptt/messages/{id}/transcribe', [PttController::class, 'transcribe'], ['auth', 'perm:ptt.use']);
$router->post('/ptt/channels/{id}/live/token', [PttController::class, 'liveToken'], ['auth', 'perm:ptt.use']);
$router->post('/ptt/channels/{id}/live/stop', [PttController::class, 'liveStop'], ['auth', 'perm:ptt.use']);
$router->get('/ptt/dashboard', [PttController::class, 'dashboard'], ['auth', 'perm:ptt.manage']);
$router->get('/ptt/search', [PttController::class, 'search'], ['auth', 'perm:ptt.use']);
$router->get('/ptt/admin', [PttController::class, 'admin'], ['auth', 'perm:ptt.manage']);
$router->post('/ptt/admin/service/stop', [PttController::class, 'serviceStop'], ['auth', 'perm:ptt.service_control']);
$router->post('/ptt/admin/service/restart', [PttController::class, 'serviceRestart'], ['auth', 'perm:ptt.service_control']);
$router->post('/ptt/admin/channels', [PttController::class, 'storeChannel'], ['auth', 'perm:ptt.manage']);
$router->get('/ptt/admin/channels/template', [PttController::class, 'channelsTemplate'], ['auth', 'perm:ptt.manage']);
$router->post('/ptt/admin/channels/import', [PttController::class, 'importChannels'], ['auth', 'perm:ptt.manage']);
// المسارات الثابتة يجب أن تكون قبل المسار الديناميكي {id}
$router->post('/ptt/admin/channels/sync-all', [PttController::class, 'syncAllChannels'], ['auth', 'perm:ptt.sync_departments']);
$router->post('/ptt/admin/channels/{id}/delete', [PttController::class, 'deleteChannel'], ['auth', 'perm:ptt.manage']);
$router->post('/ptt/admin/channels/{id}/sync', [PttController::class, 'syncChannel'], ['auth', 'perm:ptt.sync_departments']);
$router->get('/ptt/admin/channels/{id}/members', [PttController::class, 'members'], ['auth', 'perm:ptt.manage']);
$router->post('/ptt/admin/channels/{id}/members', [PttController::class, 'storeMember'], ['auth', 'perm:ptt.manage']);
$router->post('/ptt/admin/channels/{id}/members/{userId}/delete', [PttController::class, 'destroyMember'], ['auth', 'perm:ptt.manage']);
$router->post('/ptt/admin/channels/{id}', [PttController::class, 'updateChannel'], ['auth', 'perm:ptt.manage']);

// التقارير
$router->get('/reports', [ReportController::class, 'index'], ['auth', 'perm:reports.view']);
$router->get('/reports/export/{tab}', [ReportController::class, 'export'], ['auth', 'perm:reports.view']);
$router->get('/reports/sales', [ReportController::class, 'sales'], ['auth', 'perm:reports.view']);
$router->get('/reports/inventory', [ReportController::class, 'inventory'], ['auth', 'perm:reports.view']);
$router->get('/reports/profit', [ReportController::class, 'profit'], ['auth', 'perm:reports.view']);
$router->get('/reports/hr', [ReportController::class, 'hr'], ['auth', 'perm:reports.view']);
$router->get('/reports/reservations', [ReportController::class, 'reservations'], ['auth', 'perm:reports.view']);

// تقارير المشتريات والمواد والمخزون
$router->get('/reports/procurement', [ReportController::class, 'procurementReports'], ['auth', 'perm:reports.view']);
$router->get('/reports/procurement/{type}', [ReportController::class, 'procurementReport'], ['auth', 'perm:reports.view']);

// التقارير المالية
$router->get('/reports/financial/dashboard', [FinancialReportController::class, 'dashboard'], ['auth', 'perm:reports.view']);
$router->get('/reports/financial/trial-balance', [FinancialReportController::class, 'trialBalance'], ['auth', 'perm:reports.view']);
$router->get('/reports/financial/income-statement', [FinancialReportController::class, 'incomeStatement'], ['auth', 'perm:reports.view']);
$router->get('/reports/financial/balance-sheet', [FinancialReportController::class, 'balanceSheet'], ['auth', 'perm:reports.view']);
$router->get('/reports/financial/cash-flow', [FinancialReportController::class, 'cashFlow'], ['auth', 'perm:reports.view']);
$router->get('/reports/financial/general-ledger', [FinancialReportController::class, 'generalLedger'], ['auth', 'perm:reports.view']);
$router->get('/reports/financial/bank-statement', [FinancialReportController::class, 'bankStatement'], ['auth', 'perm:reports.view']);
$router->get('/reports/financial/cash-statement', [FinancialReportController::class, 'cashStatement'], ['auth', 'perm:reports.view']);
$router->get('/reports/financial/detailed-trial-balance', [FinancialReportController::class, 'detailedTrialBalance'], ['auth', 'perm:reports.view']);
$router->get('/reports/financial/subledger', [FinancialReportController::class, 'subledger'], ['auth', 'perm:reports.view']);
$router->get('/reports/financial/expenses', [FinancialReportController::class, 'expenses'], ['auth', 'perm:reports.view']);
$router->get('/reports/financial/revenue', [FinancialReportController::class, 'revenue'], ['auth', 'perm:reports.view']);
$router->get('/reports/financial/ar', [FinancialReportController::class, 'ar'], ['auth', 'perm:reports.view']);
$router->get('/reports/financial/ap', [FinancialReportController::class, 'ap'], ['auth', 'perm:reports.view']);
$router->get('/reports/financial/assets', [FinancialReportController::class, 'assets'], ['auth', 'perm:reports.view']);
$router->get('/reports/financial/depreciation', [FinancialReportController::class, 'depreciation'], ['auth', 'perm:reports.view']);
$router->get('/reports/financial/cost-centers', [FinancialReportController::class, 'costCenters'], ['auth', 'perm:reports.view']);
$router->get('/reports/financial/material-consumption', [FinancialReportController::class, 'materialConsumption'], ['auth', 'perm:reports.view']);
$router->get('/reports/financial/profitability-branch', [FinancialReportController::class, 'profitabilityByBranch'], ['auth', 'perm:reports.view']);
$router->get('/reports/financial/profitability-product', [FinancialReportController::class, 'profitabilityByProduct'], ['auth', 'perm:reports.view']);

// إدارة النظام
$router->get('/admin/users', [AdminController::class, 'users'], ['auth', 'perm:admin.users']);
$router->post('/admin/users', [AdminController::class, 'storeUser'], ['auth', 'perm:admin.users']);
$router->get('/admin/users/{id}/edit', [AdminController::class, 'editUser'], ['auth', 'perm:admin.users']);
$router->post('/admin/users/{id}/edit', [AdminController::class, 'updateUser'], ['auth', 'perm:admin.users']);
$router->post('/admin/users/{id}/delete', [AdminController::class, 'deleteUser'], ['auth', 'perm:admin.users']);
$router->post('/admin/roles/{id}/permissions', [AdminController::class, 'savePermissions'], ['auth', 'perm:admin.users']);
$router->get('/admin/roles', function () {
    \App\Core\Response::redirect('/admin/users');
}, ['auth', 'perm:admin.users']);
$router->post('/admin/roles', [AdminController::class, 'storeRole'], ['auth', 'perm:admin.users']);
$router->post('/admin/roles/{id}', [AdminController::class, 'updateRole'], ['auth', 'perm:admin.users']);
$router->post('/admin/roles/{id}/delete', [AdminController::class, 'deleteRole'], ['auth', 'perm:admin.users']);

// تشخيص الصلاحيات (موقت تشغيلي للمساعدة في الفحص)
$router->get('/debug/perms', function () {
    require BASE_PATH . '/app/Views/debug/permissions.php';
}, ['auth']);
$router->get('/admin/printer', [AdminController::class, 'printerSettings'], ['auth', 'perm:admin.settings']);
$router->post('/admin/printer', [AdminController::class, 'savePrinterSettings'], ['auth', 'perm:admin.settings']);
$router->post('/admin/printers', [AdminController::class, 'storePrinter'], ['auth', 'perm:admin.settings']);
$router->post('/admin/printers/{id}', [AdminController::class, 'updatePrinter'], ['auth', 'perm:admin.settings']);
$router->post('/admin/printers/{id}/delete', [AdminController::class, 'deletePrinter'], ['auth', 'perm:admin.settings']);
$router->get('/admin/branches', [AdminController::class, 'branches'], ['auth', 'perm:hr.manage,admin.settings']);
$router->post('/admin/branches', [AdminController::class, 'storeBranch'], ['auth', 'perm:hr.manage,admin.settings']);
$router->post('/admin/branches/{id}', [AdminController::class, 'updateBranch'], ['auth', 'perm:hr.manage,admin.settings']);
$router->post('/admin/branches/{id}/delete', [AdminController::class, 'deleteBranch'], ['auth', 'perm:hr.manage,admin.settings']);
$router->get('/admin/sync', [AdminController::class, 'sync'], ['auth', 'perm:admin.settings']);
$router->post('/admin/sync/run', [AdminController::class, 'runSync'], ['auth', 'perm:admin.settings']);
$router->get('/admin/migrate', [AdminController::class, 'migrate'], ['auth', 'perm:admin.settings']);
$router->post('/admin/migrate/run', [AdminController::class, 'runMigrations'], ['auth', 'perm:admin.settings']);
$router->post('/admin/migrate/run-file', [AdminController::class, 'runMigrationFile'], ['auth', 'perm:admin.settings']);
$router->get('/admin/push-settings', [AdminController::class, 'pushSettings'], ['auth', 'perm:admin.settings']);
$router->post('/admin/push-settings', [AdminController::class, 'savePushSettings'], ['auth', 'perm:admin.settings']);
$router->post('/admin/push-settings/test', [AdminController::class, 'testPush'], ['auth', 'perm:admin.settings']);
$router->get('/admin/face-recognition-settings', [AdminController::class, 'faceRecognitionSettings'], ['auth', 'perm:admin.settings']);
$router->post('/admin/face-recognition-settings', [AdminController::class, 'saveFaceRecognitionSettings'], ['auth', 'perm:admin.settings']);
$router->post('/admin/face-recognition-settings/test', [AdminController::class, 'testFaceRecognitionConnection'], ['auth', 'perm:admin.settings']);
$router->get('/admin/app-types', [AdminController::class, 'appTypes'], ['auth', 'perm:admin.settings']);
$router->post('/admin/app-types', [AdminController::class, 'storeAppType'], ['auth', 'perm:admin.settings']);
$router->post('/admin/app-types/{id}', [AdminController::class, 'updateAppType'], ['auth', 'perm:admin.settings']);
$router->post('/admin/app-types/{id}/delete', [AdminController::class, 'deleteAppType'], ['auth', 'perm:admin.settings']);

// ===== REST API لتطبيق الموبايل =====
// ملاحظة: 'api' يتحقق من الرمز المميز فقط. أضفنا 'perm:xxx' على كل مسار حساس
// بنفس صلاحيات نظيره في الويب لمنع أي وصول غير مصرح به عبر تطبيق الموبايل.
$router->post('/api/login', [ApiController::class, 'login']);
$router->get('/api/permissions', [ApiController::class, 'permissions'], ['api']);
$router->get('/api/dashboard', [ApiController::class, 'dashboard'], ['api']);
$router->get('/api/orders', [ApiController::class, 'orders'], ['api', 'perm:sales.view']);
$router->get('/api/inventory/low-stock', [ApiController::class, 'lowStock'], ['api', 'perm:inventory.view']);
$router->get('/api/notifications', [ApiController::class, 'notifications'], ['api']);
$router->post('/api/notifications/{id}/read', [ApiController::class, 'notificationRead'], ['api']);
$router->post('/api/notifications/read-all', [ApiController::class, 'notificationsReadAll'], ['api']);
$router->get('/api/mail', [ApiController::class, 'mailInbox'], ['api']);
$router->get('/api/mail/{id}', [ApiController::class, 'mailShow'], ['api']);
$router->post('/api/mail/{id}/read', [ApiController::class, 'mailRead'], ['api']);
$router->post('/api/mail/read-all', [ApiController::class, 'mailReadAll'], ['api']);
$router->get('/api/approvals', [ApiController::class, 'approvals'], ['api', 'perm:mobile.approvals']);
$router->post('/api/approvals/{id}/decide', [ApiController::class, 'decideApproval'], ['api', 'perm:mobile.approvals']);
$router->get('/api/attendance/status', [ApiController::class, 'attendanceStatus'], ['api', 'perm:mobile.attendance']);
$router->post('/api/attendance/check', [ApiController::class, 'attendanceCheck'], ['api', 'perm:mobile.attendance']);
$router->post('/api/attendance/verify-face', [ApiController::class, 'verifyFace'], ['api', 'perm:mobile.attendance']);
$router->post('/api/attendance/reference-photo', [ApiController::class, 'updateReferencePhoto'], ['api', 'perm:mobile.attendance']);
$router->post('/api/attendance/biometric', [ApiController::class, 'biometricAttendanceImport'], ['api', 'perm:hr.biometric']);

// الورديات (API)
$router->get('/api/shifts', [ApiController::class, 'listShifts'], ['api', 'perm:mobile.attendance,hr.view,workflow.create']);
$router->post('/api/employees/{id}/shift', [ApiController::class, 'updateEmployeeShift'], ['api', 'perm:mobile.attendance,hr.view']);
$router->post('/api/employee-shifts', [ApiController::class, 'setEmployeeShiftOverride'], ['api', 'perm:mobile.attendance,hr.view']);

// طلبات المدير: تعيين وإيقاف دوام (موبايل)
$router->get('/api/manager/dashboard', [ApiController::class, 'managerDashboard'], ['api']);
$router->get('/api/manager/subordinates', [ApiController::class, 'subordinates'], ['api']);
$router->get('/api/hiring-requests', [ApiController::class, 'hiringRequestsApi'], ['api']);
$router->post('/api/hiring-requests', [ApiController::class, 'storeHiringRequestApi'], ['api']);
$router->post('/api/hiring-requests/{id}/decide', [ApiController::class, 'decideHiringRequestApi'], ['api']);
$router->get('/api/suspension-requests', [ApiController::class, 'suspensionRequestsApi'], ['api']);
$router->post('/api/suspension-requests', [ApiController::class, 'storeSuspensionRequestApi'], ['api']);
$router->post('/api/suspension-requests/{id}/decide', [ApiController::class, 'decideSuspensionRequestApi'], ['api']);

// بوابة الموبايل
$router->get('/mobile', [MobileController::class, 'index'], ['auth']);
$router->post('/api/leaves', [ApiController::class, 'requestLeave'], ['api', 'perm:attendance.self']);
$router->get('/api/leaves', [ApiController::class, 'employeeLeaves'], ['api', 'perm:hr.leave.request']);
$router->get('/api/leaves/pending', [ApiController::class, 'pendingLeaveRequests'], ['api', 'perm:hr.leave.approve']);
$router->post('/api/leaves/{id}/decide', [ApiController::class, 'decideLeaveApi'], ['api', 'perm:hr.leave.approve']);
$router->post('/api/advances', [ApiController::class, 'requestAdvance'], ['api', 'perm:attendance.self']);
$router->get('/api/advances', [ApiController::class, 'employeeAdvances'], ['api', 'perm:attendance.self']);
$router->get('/api/advances/pending', [ApiController::class, 'pendingAdvanceRequests'], ['api', 'perm:hr.advance.approve']);
$router->post('/api/advances/{id}/decide', [ApiController::class, 'decideAdvanceApi'], ['api', 'perm:hr.advance.approve']);

$router->post('/api/overtime', [ApiController::class, 'requestOvertime'], ['api', 'perm:hr.overtime.request']);
$router->get('/api/overtime', [ApiController::class, 'employeeOvertime'], ['api', 'perm:hr.overtime.request']);
$router->get('/api/overtime/pending', [ApiController::class, 'pendingOvertime'], ['api', 'perm:hr.overtime.approve']);
$router->get('/api/overtime/{id}/attendance', [ApiController::class, 'overtimeAttendance'], ['api', 'perm:hr.overtime.approve']);
$router->post('/api/overtime/{id}/decide', [ApiController::class, 'decideOvertimeApi'], ['api', 'perm:hr.overtime.approve']);

$router->post('/api/missing-attendance', [ApiController::class, 'requestMissingAttendance'], ['api', 'perm:hr.missing_attendance.request']);
$router->get('/api/missing-attendance', [ApiController::class, 'employeeMissingAttendance'], ['api', 'perm:hr.missing_attendance.request']);
$router->get('/api/missing-attendance/pending', [ApiController::class, 'pendingMissingAttendance'], ['api', 'perm:hr.missing_attendance.approve']);
$router->post('/api/missing-attendance/{id}/decide', [ApiController::class, 'decideMissingAttendanceApi'], ['api', 'perm:hr.missing_attendance.approve']);

$router->get('/api/employee/history', [ApiController::class, 'employeeHistory'], ['api', 'perm:attendance.self']);
$router->get('/api/payroll/history', [ApiController::class, 'myPayrollHistory'], ['api', 'perm:attendance.self']);
$router->get('/api/payroll/{id}', [ApiController::class, 'myPayrollDetail'], ['api', 'perm:attendance.self']);
$router->post('/api/sync', [ApiController::class, 'sync'], ['api_or_synckey']);

// المبيعات ونقطة البيع
$router->get('/api/pos/menu', [ApiController::class, 'menu'], ['api', 'perm:pos.use']);
$router->get('/api/pos/tables', [ApiController::class, 'tables'], ['api', 'perm:sales.view']);
$router->post('/api/pos/checkout', [ApiController::class, 'checkout'], ['api', 'perm:pos.use']);
$router->post('/api/captain/order', [ApiController::class, 'captainOrder'], ['api', 'perm:sales.view']);
$router->get('/api/orders/{id}', [ApiController::class, 'orderShow'], ['api', 'perm:sales.view']);
$router->post('/api/orders/{id}/status', [ApiController::class, 'updateOrderStatus'], ['api', 'perm:sales.view']);
$router->post('/api/orders/{id}/manage', [ApiController::class, 'manageOrder'], ['api', 'perm:sales.view']);

// المنيو حسب الطابق/الصالة + إدارة أصناف المنيو
// الكابتن والكاشير يحتاجان رؤية المنيو؛ نعتمد sales.view بدلاً من pos.use لأن الكابتن يأخذ الطلبات.
$router->get('/api/menu', [ApiController::class, 'menu'], ['api', 'perm:sales.view']);
$router->get('/api/floors', [ApiController::class, 'floors'], ['api', 'perm:sales.view']);
$router->post('/api/menu/items', [ApiController::class, 'storeMenuItem'], ['api', 'perm:recipes.manage']);
$router->post('/api/menu/items/{id}', [ApiController::class, 'updateMenuItem'], ['api', 'perm:recipes.manage']);
$router->post('/api/menu/items/{id}/delete', [ApiController::class, 'deleteMenuItem'], ['api', 'perm:recipes.manage']);

// الحجوزات
$router->get('/api/reservations', [ApiController::class, 'reservations'], ['api', 'perm:reservations.manage']);
$router->post('/api/reservations', [ApiController::class, 'storeReservation'], ['api', 'perm:reservations.manage']);
$router->get('/api/reservations/{id}', [ApiController::class, 'getReservation'], ['api', 'perm:reservations.manage']);
$router->post('/api/reservations/{id}', [ApiController::class, 'updateReservation'], ['api', 'perm:reservations.manage']);
$router->post('/api/reservations/{id}/status', [ApiController::class, 'updateReservationStatus'], ['api', 'perm:reservations.manage']);
$router->get('/api/reservations/available-tables', [ApiController::class, 'reservationAvailableTables'], ['api', 'perm:reservations.manage']);

// المطبخ
$router->get('/api/kitchen/orders', [ApiController::class, 'kitchenOrders'], ['api', 'perm:kitchen.view']);
$router->post('/api/kitchen/item/{id}/status', [ApiController::class, 'kitchenItemStatus'], ['api', 'perm:kitchen.manage']);

// المخزون
$router->get('/api/warehouses', [ApiController::class, 'warehouses'], ['api', 'perm:inventory.view']);
$router->get('/api/recipes', [ApiController::class, 'recipes'], ['api', 'perm:production.mobile']);
$router->get('/api/employees', [ApiController::class, 'employees'], ['api', 'perm:mobile.employees,mobile.attendance,hr.view']);
$router->get('/api/departments', [ApiController::class, 'departments'], ['api', 'perm:custody.mobile']);

// تقييم أداء الفريق من الموبايل (الهيكل الإداري)
$router->get('/api/team/evaluations', [ApiController::class, 'teamEvaluations'], ['api', 'perm:mobile.evaluations,hr.evaluation.manage']);
$router->get('/api/team/employees/{id}/kpis', [ApiController::class, 'teamEmployeeKpis'], ['api', 'perm:mobile.evaluations,hr.evaluation.manage']);
$router->post('/api/team/evaluations', [ApiController::class, 'storeTeamEvaluation'], ['api', 'perm:mobile.evaluations,hr.evaluation.manage']);
$router->get('/api/team/evaluations/{id}', [ApiController::class, 'teamEvaluationDetail'], ['api', 'perm:mobile.evaluations,hr.evaluation.manage']);
$router->post('/api/team/employees/{id}/missing-attendance', [ApiController::class, 'teamRequestMissingAttendance'], ['api', 'perm:mobile.evaluations,hr.evaluation.manage']);
$router->post('/api/team/employees/{id}/leave', [ApiController::class, 'teamRequestLeave'], ['api', 'perm:mobile.evaluations,hr.evaluation.manage']);
$router->get('/api/housing/sites', [ApiController::class, 'housingSites'], ['api']);
$router->get('/api/housing/apartments', [ApiController::class, 'housingApartments'], ['api']);
$router->get('/api/housing/rooms', [ApiController::class, 'housingRooms'], ['api']);
$router->get('/api/housing/employees', [ApiController::class, 'housingEmployees'], ['api']);
$router->post('/api/housing/employees/{id}', [ApiController::class, 'updateHousingAssignment'], ['api']);
$router->get('/api/inventory', [ApiController::class, 'inventory'], ['api', 'perm:inventory.view']);
$router->get('/api/inventory/barcode', [ApiController::class, 'inventoryBarcode'], ['api', 'perm:inventory.view']);
$router->get('/api/mobile/items', [ApiController::class, 'mobileItems'], ['api', 'perm:material_request.mobile']);
$router->get('/api/mobile/purchase-request-items', [ApiController::class, 'mobilePurchaseRequestItems'], ['api', 'perm:mobile.approvals,purchasing.view']);
$router->get('/api/inventory/movements', [ApiController::class, 'inventoryMovements'], ['api', 'perm:inventory.view']);
$router->post('/api/inventory/movements', [ApiController::class, 'storeInventoryMovement'], ['api', 'perm:inventory.manage']);
$router->get('/api/inventory/transfers', [ApiController::class, 'inventoryTransfers'], ['api', 'perm:inventory.transfer']);
$router->post('/api/inventory/transfers', [ApiController::class, 'storeInventoryTransfer'], ['api', 'perm:inventory.transfer']);
$router->post('/api/inventory/transfers/{id}/deliver', [ApiController::class, 'deliverInventoryTransfer'], ['api', 'perm:inventory.transfer']);
$router->get('/api/inventory/stocktakes', [ApiController::class, 'inventoryStocktakes'], ['api', 'perm:inventory.stocktake']);
$router->post('/api/inventory/stocktakes', [ApiController::class, 'storeInventoryStocktake'], ['api', 'perm:inventory.stocktake']);
$router->post('/api/inventory/manufacture', [ApiController::class, 'inventoryManufacture'], ['api', 'perm:production.mobile']);

// المشتريات
$router->get('/api/purchasing/requests', [ApiController::class, 'purchaseRequests'], ['api', 'perm:purchasing.view']);
$router->post('/api/purchasing/requests', [ApiController::class, 'storePurchaseRequest'], ['api', 'perm:purchasing.view']);
$router->get('/api/purchasing/orders', [ApiController::class, 'purchaseOrders'], ['api', 'perm:purchasing.view']);
$router->get('/api/purchasing/receipts', [ApiController::class, 'goodsReceipts'], ['api', 'perm:purchasing.view']);
$router->get('/api/purchasing/suppliers', [ApiController::class, 'suppliers'], ['api', 'perm:purchasing.view']);
$router->get('/api/material-requests', [MaterialRequestController::class, 'apiList'], ['api', 'perm:material_request.mobile']);
$router->post('/api/material-requests', [MaterialRequestController::class, 'apiStore'], ['api', 'perm:material_request.mobile']);
$router->get('/api/material-requests/{id}', [MaterialRequestController::class, 'apiShow'], ['api', 'perm:material_request.mobile']);
$router->post('/api/material-requests/{id}/submit', [MaterialRequestController::class, 'apiSubmit'], ['api', 'perm:material_request.mobile']);
$router->get('/api/material-requests/{id}/plan', [MaterialRequestController::class, 'apiPlan'], ['api', 'perm:material_request.mobile']);
$router->post('/api/material-requests/{id}/fulfillment', [MaterialRequestController::class, 'apiExecuteFulfillment'], ['api', 'perm:material_request.mobile']);
$router->get('/api/production/orders', [ApiController::class, 'productionOrders'], ['api', 'perm:production.mobile']);
$router->post('/api/production/orders', [ApiController::class, 'storeProductionOrder'], ['api', 'perm:production.mobile']);
$router->get('/api/kitchen/sections', [ApiController::class, 'kitchenSections'], ['api', 'perm:kitchen.view']);
$router->post('/api/kitchen/material-request', [ApiController::class, 'storeKitchenMaterialRequest'], ['api', 'perm:material_request.mobile']);
$router->get('/api/procurement/ai-snapshot', [ApiController::class, 'procurementAiSnapshot'], ['api', 'perm:procurement.ai']);

// التقارير
$router->get('/api/reports/sales', [ApiController::class, 'reportSales'], ['api', 'perm:reports.view']);
$router->get('/api/reports/inventory', [ApiController::class, 'reportInventory'], ['api', 'perm:reports.view']);
$router->get('/api/reports/profit', [ApiController::class, 'reportProfit'], ['api', 'perm:reports.view']);
$router->get('/api/reports/hr', [ApiController::class, 'reportHr'], ['api', 'perm:reports.view']);

// المهام
$router->get('/api/tasks', [ApiController::class, 'tasks'], ['api', 'perm:mobile.tasks']);
$router->post('/api/tasks', [ApiController::class, 'storeTask'], ['api', 'perm:mobile.tasks']);
$router->post('/api/tasks/{id}/status', [ApiController::class, 'updateTaskStatus'], ['api', 'perm:mobile.tasks']);

// العهدة / الأصول
$router->get('/api/custody', [ApiController::class, 'custody'], ['api', 'perm:custody.manage']);
$router->post('/api/custody', [ApiController::class, 'storeCustody'], ['api', 'perm:custody.manage']);
$router->post('/api/custody/{id}/return', [ApiController::class, 'returnCustody'], ['api', 'perm:custody.manage']);
$router->post('/api/custody/issue', [ApiController::class, 'issueCustody'], ['api', 'perm:custody.mobile']);

// سير العمل والموافقات (Workflow) للموبايل
$router->get('/api/workflows/templates', [ApiController::class, 'workflowTemplates'], ['api', 'perm:workflow.create,mobile.approvals']);
$router->get('/api/workflows/templates/{id}', [ApiController::class, 'workflowTemplateDetail'], ['api', 'perm:workflow.create,mobile.approvals']);
$router->get('/api/employees/{id}/advance-limit', [ApiController::class, 'employeeAdvanceLimit'], ['api', 'perm:workflow.create,mobile.approvals']);
$router->post('/api/workflows/requests', [ApiController::class, 'workflowStoreRequest'], ['api', 'perm:workflow.create,mobile.approvals']);
$router->get('/api/workflows/my-requests', [ApiController::class, 'workflowMyRequests'], ['api', 'perm:workflow.create,mobile.approvals']);
$router->get('/api/workflows/pending', [ApiController::class, 'workflowPending'], ['api', 'perm:mobile.approvals,workflow.decide,approvals.decide']);
$router->get('/api/workflows/inbox', [ApiController::class, 'workflowInbox'], ['api', 'perm:mobile.approvals,workflow.decide,approvals.decide']);
$router->post('/api/workflows/requests/{id}/delegate', [ApiController::class, 'workflowDelegate'], ['api', 'perm:mobile.approvals,workflow.decide,approvals.decide']);
$router->post('/api/change-password', [ApiController::class, 'changePassword'], ['api']);
$router->post('/api/push/subscribe', [ApiController::class, 'pushSubscribe'], ['api']);
$router->post('/api/push/unsubscribe', [ApiController::class, 'pushUnsubscribe'], ['api']);

// الصيانة — API الموبايل
$router->get('/api/maintenance/employees', [ApiController::class, 'maintenanceEmployees'], ['api']);
$router->get('/api/maintenance', [ApiController::class, 'maintenanceList'], ['api']);
$router->get('/api/maintenance/{id}', [ApiController::class, 'maintenanceShow'], ['api']);
$router->post('/api/maintenance', [ApiController::class, 'maintenanceStore'], ['api']);
$router->post('/api/maintenance/{id}/assign', [ApiController::class, 'maintenanceAssign'], ['api']);
$router->post('/api/maintenance/{id}/start', [ApiController::class, 'maintenanceStart'], ['api']);
$router->post('/api/maintenance/{id}/finish', [ApiController::class, 'maintenanceFinish'], ['api']);
$router->post('/api/maintenance/{id}/confirm', [ApiController::class, 'maintenanceConfirm'], ['api']);

// PTT — API الموبايل
$router->get('/api/ptt/channels', [ApiController::class, 'pttChannels'], ['api', 'perm:ptt.use']);
$router->get('/api/ptt/inbox', [ApiController::class, 'pttInbox'], ['api', 'perm:ptt.use']);
$router->get('/api/ptt/channels/{id}/messages', [ApiController::class, 'pttChannelMessages'], ['api', 'perm:ptt.use']);
$router->post('/api/ptt/channels/{id}/live/token', [ApiController::class, 'pttLiveToken'], ['api', 'perm:ptt.use']);
$router->post('/api/ptt/channels/{id}/live/stop', [ApiController::class, 'pttLiveStop'], ['api', 'perm:ptt.use']);
$router->post('/api/ptt/send', [ApiController::class, 'pttSend'], ['api', 'perm:ptt.use']);
$router->post('/api/ptt/messages/{id}/played', [ApiController::class, 'pttPlayed'], ['api', 'perm:ptt.use']);
$router->post('/api/ptt/messages/{id}/ack', [ApiController::class, 'pttAck'], ['api', 'perm:ptt.use']);

// Call Center — API الموبايل native
$router->get('/api/call-center/types', [CallCenterController::class, 'types'], ['api', 'perm:call_center.manage,reservations.call_center']);
$router->get('/api/call-center/customers/search', [CallCenterController::class, 'searchCustomers'], ['api', 'perm:call_center.manage,reservations.call_center']);
$router->post('/api/call-center/customers', [CallCenterController::class, 'storeCustomer'], ['api', 'perm:call_center.manage,reservations.call_center']);
$router->get('/api/call-center/customers/{id}', [CallCenterController::class, 'getCustomer'], ['api', 'perm:call_center.manage,reservations.call_center']);
$router->get('/api/call-center/slots', [CallCenterController::class, 'availableSlots'], ['api', 'perm:call_center.manage,reservations.call_center']);
$router->get('/api/call-center/tables', [CallCenterController::class, 'availableTables'], ['api', 'perm:call_center.manage,reservations.call_center']);
$router->get('/api/call-center/occupancy', [CallCenterController::class, 'occupancy'], ['api', 'perm:call_center.manage,reservations.call_center']);
$router->post('/api/call-center/reservations', [CallCenterController::class, 'storeReservation'], ['api', 'perm:call_center.manage,reservations.call_center']);
$router->post('/api/call-center/reservations/{id}', [CallCenterController::class, 'updateReservation'], ['api', 'perm:call_center.manage,reservations.call_center']);
$router->post('/api/call-center/reservations/{id}/status', [CallCenterController::class, 'status'], ['api', 'perm:call_center.manage,reservations.call_center']);
$router->post('/api/call-center/reservations/{id}/cancel', [CallCenterController::class, 'cancelReservation'], ['api', 'perm:call_center.manage,reservations.call_center']);
$router->post('/api/call-center/reservations/{id}/reschedule', [CallCenterController::class, 'rescheduleReservation'], ['api', 'perm:call_center.manage,reservations.call_center']);
$router->get('/api/workflows/requests/{id}', [ApiController::class, 'workflowShowRequest'], ['api', 'perm:workflow.create,workflow.decide,approvals.decide,mobile.approvals']);
$router->post('/api/workflows/requests/{id}/decide', [ApiController::class, 'workflowDecide'], ['api', 'perm:mobile.approvals,workflow.decide,approvals.decide']);
$router->post('/api/workflows/requests/{id}/resubmit', [ApiController::class, 'workflowResubmit'], ['api', 'perm:workflow.create']);
$router->post('/api/workflows/requests/{id}/edit', [ApiController::class, 'workflowEditRequest'], ['api', 'perm:workflow.create,mobile.approvals,workflow.decide,approvals.decide']);
$router->post('/api/workflows/requests/{id}/cancel', [ApiController::class, 'workflowCancelRequest'], ['api', 'perm:workflow.create,mobile.approvals']);
$router->post('/api/workflows/requests/{id}/comment', [ApiController::class, 'workflowComment'], ['api', 'perm:workflow.create']);

// === مسار مؤقت لتنزيل ملفات بناء التطبيق (AAB/APK) لرفعها على المتاجر - يُحذف بعد الاستخدام ===
$router->get('/sys-dl/{token}/{file}', function (string $token, string $file) {
    $expected = '8556d3cf9f2450d8cfce5449177c80eb';
    if (!hash_equals($expected, $token)) { http_response_code(404); exit('Not found'); }
    $allowed = ['aab' => 'top-organic-1.2.1-51.aab', 'apk' => 'top-organic-1.2.1-51.apk'];
    if (!isset($allowed[$file])) { http_response_code(404); exit('Not found'); }
    $path = __DIR__ . '/../_rel_' . $expected . '/' . $allowed[$file];
    if (!is_file($path)) { http_response_code(404); exit('Not found'); }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $allowed[$file] . '"');
    header('Content-Length: ' . filesize($path));
    header('X-Accel-Buffering: no');
    readfile($path);
    exit;
});

return $router;
