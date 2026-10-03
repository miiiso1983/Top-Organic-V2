<?php
namespace App\Controllers;

use App\Core\{Controller, Auth, Response, ReservationTypeService, WorkflowTemplateService};

class ReservationTypeController extends Controller
{
    public function index(): void
    {
        if (!Auth::can('reservations.types.manage')) {
            Response::redirect('/dashboard');
        }
        $types = ReservationTypeService::list(false);
        $templates = WorkflowTemplateService::getAllTemplates() ?? [];
        $this->view('admin/reservation_types', [
            'title' => 'أنواع الحجز',
            'types' => $types,
            'templates' => $templates,
        ]);
    }

    public function store(): void
    {
        if (!Auth::can('reservations.types.manage')) {
            Response::redirect('/dashboard');
        }
        try {
            ReservationTypeService::create($this->typeInput());
            $this->flash('success', 'تم إضافة نوع الحجز');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/admin/reservation-types');
    }

    public function update(int $id): void
    {
        if (!Auth::can('reservations.types.manage')) {
            Response::redirect('/dashboard');
        }
        try {
            ReservationTypeService::update($id, $this->typeInput());
            $this->flash('success', 'تم تحديث نوع الحجز');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/admin/reservation-types');
    }

    public function delete(int $id): void
    {
        if (!Auth::can('reservations.types.manage')) {
            Response::redirect('/dashboard');
        }
        try {
            ReservationTypeService::delete($id);
            $this->flash('success', 'تم حذف نوع الحجز');
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        Response::redirect('/admin/reservation-types');
    }

    private function typeInput(): array
    {
        $data = [
            'code' => (string)$this->input('code'),
            'name_ar' => (string)$this->input('name_ar'),
            'name_en' => (string)$this->input('name_en'),
            'description' => (string)$this->input('description'),
            'icon' => (string)$this->input('icon'),
            'color' => (string)$this->input('color'),
            'min_guests' => (int)$this->input('min_guests', 1),
            'max_guests' => (int)$this->input('max_guests', 50),
            'default_guests' => (int)$this->input('default_guests', 2),
            'min_advance_hours' => (int)$this->input('min_advance_hours', 0),
            'max_advance_days' => (int)$this->input('max_advance_days', 30),
            'duration_minutes' => (int)$this->input('duration_minutes', 90),
            'price_per_person' => $this->input('price_per_person') !== '' ? (string)$this->input('price_per_person') : null,
            'deposit_amount' => $this->input('deposit_amount') !== '' ? (string)$this->input('deposit_amount') : null,
            'deposit_required' => (int)$this->input('deposit_required', 0),
            'requires_approval' => (int)$this->input('requires_approval', 0),
            'requires_manager_confirmation' => (int)$this->input('requires_manager_confirmation', 0),
            'requires_contract' => (int)$this->input('requires_contract', 0),
            'allows_partial_payment' => (int)$this->input('allows_partial_payment', 0),
            'workflow_template_id' => (int)$this->input('workflow_template_id') ?: null,
            'permission_code' => (string)$this->input('permission_code') ?: null,
            'message_template_confirm' => (string)$this->input('message_template_confirm'),
            'message_template_reminder' => (string)$this->input('message_template_reminder'),
            'message_template_cancel' => (string)$this->input('message_template_cancel'),
            'requirements' => (string)$this->input('requirements'),
            'default_notes' => (string)$this->input('default_notes'),
            'is_active' => (int)$this->input('is_active', 1),
            'sort_order' => (int)$this->input('sort_order', 0),
        ];
        if (empty($data['code'])) {
            $data['code'] = null;
        }
        return $data;
    }
}
