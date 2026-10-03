<?php
namespace App\Controllers;

use App\Core\{Controller, Database, Auth, Response, ReservationService, ReservationTypeService, CustomerService, Settings, Helpers};

class CallCenterController extends Controller
{
    public function index(): void
    {
        if (!Auth::can('call_center.manage') && !Auth::can('reservations.call_center')) {
            Response::redirect('/dashboard');
        }
        $branches = Database::query("SELECT id, name FROM branches WHERE is_active=1 ORDER BY name")->fetchAll();
        $types = ReservationTypeService::list(true);
        $defaultBranch = (int)Settings::get('call_center_default_branch', $branches[0]['id'] ?? 1);
        $this->view('sales/call_center', [
            'title' => 'Call Center - الحجوزات',
            'branches' => $branches,
            'types' => $types,
            'defaultBranch' => $defaultBranch,
        ]);
    }

    public function types(): void
    {
        $this->authorize();
        Response::json(['ok' => true, 'types' => ReservationTypeService::list(true)]);
    }

    public function searchCustomers(): void
    {
        $this->authorize();
        $q = trim((string)($_GET['q'] ?? ''));
        $customers = $q ? CustomerService::search($q) : [];
        Response::json(['ok' => true, 'customers' => $customers]);
    }

    public function storeCustomer(): void
    {
        $this->authorize();
        $b = $this->jsonInput();
        try {
            $id = CustomerService::create($b);
            Response::json(['ok' => true, 'customer' => CustomerService::getById($id)]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function getCustomer(int $id): void
    {
        $this->authorize();
        $customer = CustomerService::getById($id);
        if (!$customer) {
            Response::json(['ok' => false, 'error' => 'العميل غير موجود']);
            return;
        }
        $reservations = CustomerService::getReservations($id, null, 50);
        $cancelled = array_filter($reservations, fn($r) => $r['status'] === 'cancelled');
        Response::json(['ok' => true, 'customer' => $customer, 'reservations' => $reservations, 'cancelled' => array_values($cancelled)]);
    }

    public function storeReservation(): void
    {
        $this->authorize();
        $b = $this->jsonInput();
        try {
            $customerId = (int)($b['customer_id'] ?? 0);
            if (!$customerId) {
                $customerId = $this->resolveOrCreateCustomer($b);
            }
            $data = [
                'customer_id' => $customerId,
                'customer_name' => (string)($b['customer_name'] ?? ''),
                'customer_phone' => (string)($b['customer_phone'] ?? ''),
                'customer_email' => (string)($b['customer_email'] ?? ''),
                'branch_id' => (int)($b['branch_id'] ?? 0) ?: null,
                'table_id' => $b['table_id'] ?: null,
                'guests' => (int)($b['guests'] ?? 2),
                'reserved_at' => (string)($b['reserved_at'] ?? ''),
                'reservation_type' => (string)($b['reservation_type'] ?? 'regular'),
                'duration_minutes' => (int)($b['duration_minutes'] ?? 90),
                'source' => 'call_center',
                'notes' => (string)($b['notes'] ?? ''),
                'customer_notes' => (string)($b['customer_notes'] ?? ''),
                'call_center_notes' => (string)($b['call_center_notes'] ?? ''),
            ];
            $id = ReservationService::create($data, (int)Auth::id());
            Response::json(['ok' => true, 'reservation' => ReservationService::getWithDetails($id)]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function updateReservation(int $id): void
    {
        $this->authorize();
        $b = $this->jsonInput();
        try {
            $customerId = (int)($b['customer_id'] ?? 0);
            if (!$customerId) {
                $customerId = $this->resolveOrCreateCustomer($b);
            }
            $b['customer_id'] = $customerId;
            ReservationService::update($id, $b, (int)Auth::id());
            Response::json(['ok' => true, 'reservation' => ReservationService::getWithDetails($id)]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function cancelReservation(int $id): void
    {
        $this->authorize();
        $b = $this->jsonInput();
        try {
            ReservationService::cancel($id, (string)($b['reason'] ?? ''), (int)Auth::id());
            Response::json(['ok' => true]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function rescheduleReservation(int $id): void
    {
        $this->authorize();
        $b = $this->jsonInput();
        try {
            ReservationService::reschedule($id, (string)($b['reserved_at'] ?? ''), (int)Auth::id(), (int)($b['duration_minutes'] ?? 90));
            Response::json(['ok' => true, 'reservation' => ReservationService::getWithDetails($id)]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function status(int $id): void
    {
        $this->authorize();
        $b = $this->jsonInput();
        try {
            match ((string)($b['status'] ?? '')) {
                'confirmed' => ReservationService::confirm($id, (int)Auth::id()),
                'seated' => ReservationService::seat($id, (int)Auth::id()),
                'completed' => ReservationService::complete($id, (int)Auth::id()),
                'waiting' => ReservationService::moveToWaiting($id, (int)Auth::id()),
                'cancelled' => ReservationService::cancel($id, (string)($b['reason'] ?? ''), (int)Auth::id()),
                'rejected' => ReservationService::reject($id, (string)($b['reason'] ?? ''), (int)Auth::id()),
                default => throw new \RuntimeException('حالة غير معروفة'),
            };
            Response::json(['ok' => true, 'reservation' => ReservationService::getWithDetails($id)]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function occupancy(): void
    {
        $this->authorize();
        $branchId = (int)($_GET['branch_id'] ?? 0);
        $date = (string)($_GET['date'] ?? date('Y-m-d'));
        $open = (string)Settings::get('reservation_opening_time', '09:00');
        $close = (string)Settings::get('reservation_closing_time', '23:00');
        $interval = (int)Settings::get('reservation_time_interval', 30);
        $capacity = ReservationService::getBranchCapacity($branchId);
        $start = \DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $open);
        $end = \DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $close);
        $slots = [];
        $totalMinutes = 0;
        $occupiedMinutes = 0;
        while ($start < $end) {
            $slotEnd = (clone $start)->modify("+{$interval} minutes");
            $slot = ReservationService::countReservationsInSlot($branchId, $start->format('Y-m-d H:i:s'), $slotEnd->format('Y-m-d H:i:s'));
            $occupancy = $capacity > 0 ? min(100, round(($slot['total_guests'] / $capacity) * 100)) : 0;
            $slots[] = [
                'time' => $start->format('H:i'),
                'guests' => $slot['total_guests'],
                'capacity' => $capacity,
                'occupancy' => $occupancy,
                'full' => $occupancy >= 90,
            ];
            $totalMinutes += $interval;
            if ($occupancy >= 90) $occupiedMinutes += $interval;
            $start = $slotEnd;
        }
        Response::json([
            'ok' => true,
            'capacity' => $capacity,
            'occupancy_rate' => $totalMinutes > 0 ? round(($occupiedMinutes / $totalMinutes) * 100) : 0,
            'slots' => $slots,
        ]);
    }

    public function availableTables(): void
    {
        $this->authorize();
        $branchId = (int)($_GET['branch_id'] ?? 0);
        $date = (string)($_GET['date'] ?? '');
        $time = (string)($_GET['time'] ?? '');
        $guests = (int)($_GET['guests'] ?? 2);
        $duration = (int)($_GET['duration'] ?? 90);
        $reservedAt = $date && $time ? $date . ' ' . $time : null;
        $tables = $reservedAt ? ReservationService::findAvailableTables($guests, $reservedAt, $duration, null, $branchId) : [];
        Response::json(['ok' => true, 'tables' => $tables]);
    }

    public function availableSlots(): void
    {
        $this->authorize();
        $branchId = (int)($_GET['branch_id'] ?? 0);
        $date = (string)($_GET['date'] ?? '');
        $guests = (int)($_GET['guests'] ?? 2);
        $typeCode = (string)($_GET['type'] ?? 'regular');
        if (!$branchId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            Response::json(['ok' => false, 'error' => 'بيانات غير كافية']);
            return;
        }
        $slots = ReservationService::getPublicSlots($branchId, $date, $guests, $typeCode);
        Response::json(['ok' => true, 'slots' => $slots]);
    }

    private function resolveOrCreateCustomer(array $b): int
    {
        $phone = trim((string)($b['customer_phone'] ?? ''));
        $name = trim((string)($b['customer_name'] ?? ''));
        if (!$name) {
            throw new \RuntimeException('اسم العميل مطلوب');
        }
        if ($phone) {
            $existing = CustomerService::getByPhone($phone);
            if ($existing) {
                return (int)$existing['id'];
            }
        }
        return CustomerService::create([
            'name' => $name,
            'phone' => $phone,
            'email' => null,
            'address' => null,
            'is_vip' => 0,
            'notes' => '',
        ]);
    }

    private function authorize(): void
    {
        if (!Auth::can('call_center.manage') && !Auth::can('reservations.call_center')) {
            Response::json(['ok' => false, 'error' => 'ليس لديك صلاحية']);
            exit;
        }
    }
}
