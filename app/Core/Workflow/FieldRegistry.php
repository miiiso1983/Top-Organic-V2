<?php
namespace App\Core\Workflow;

/**
 * كتالوج الحقول المتاحة داخل الشروط لكل نوع طلب.
 * أي وحدة جديدة تسجّل حقولها بسطر واحد:
 *   FieldRegistry::register('purchase_request', fn() => [...]);
 */
final class FieldRegistry
{
    /** @var array<string, callable> */
    private static array $registrars = [];
    private static bool $booted = false;

    /** تسجيل حقول إضافية لنوع طلب */
    public static function register(string $requestType, callable $registrar): void
    {
        self::$registrars[$requestType][] = $registrar;
    }

    /**
     * كل الحقول المتاحة لقالب/نوع طلب.
     * تعيد: [['name'=>'data.amount','label'=>'المبلغ','type'=>'number'], ...]
     */
    public static function fieldsFor(string $requestType, ?array $template = null): array
    {
        self::bootDefaults();
        $fields = self::baseFields();

        // حقول النموذج الديناميكي للقالب (fields_json)
        foreach ((array)($template['fields'] ?? []) as $f) {
            if (empty($f['name'])) continue;
            $fields[] = [
                'name'  => 'data.' . $f['name'],
                'label' => (string)($f['label'] ?? $f['name']),
                'type'  => (string)($f['type'] ?? 'text'),
            ];
        }

        // حقول الوحدات المسجلة برمجياً
        foreach (self::$registrars[$requestType] ?? [] as $registrar) {
            foreach ((array)$registrar() as $f) {
                $fields[] = $f;
            }
        }

        // حقول الكيان المرتبط بهذا النوع (entity.*) من سجل الوحدات
        foreach (EntityRegistry::fieldCatalog($requestType) as $f) {
            $fields[] = $f;
        }

        // إزالة التكرار حسب الاسم
        $unique = [];
        foreach ($fields as $f) {
            $unique[$f['name']] = $f;
        }
        return array_values($unique);
    }

    /** الحقول الأساسية المتاحة لأي طلب */
    public static function baseFields(): array
    {
        return [
            ['name' => 'amount',                 'label' => 'قيمة الطلب',        'type' => 'number'],
            ['name' => 'request.title',          'label' => 'عنوان الطلب',       'type' => 'text'],
            ['name' => 'request.branch_id',      'label' => 'الفرع',             'type' => 'number'],
            ['name' => 'request.created_at',     'label' => 'تاريخ الإنشاء',     'type' => 'date'],
            ['name' => 'requester.full_name',    'label' => 'اسم مقدم الطلب',    'type' => 'text'],
            ['name' => 'requester.department',   'label' => 'قسم مقدم الطلب',    'type' => 'text'],
            ['name' => 'requester.admin_level',  'label' => 'المستوى الإداري',   'type' => 'number'],
            ['name' => 'requester.position_title','label' => 'منصب مقدم الطلب',  'type' => 'text'],
            ['name' => 'requester.role_name',    'label' => 'دور مقدم الطلب',    'type' => 'text'],
            ['name' => 'requester.branch_id',    'label' => 'فرع مقدم الطلب',    'type' => 'number'],
        ];
    }

    private static function bootDefaults(): void
    {
        if (self::$booted) return;
        self::$booted = true;
        self::register('leave_request', fn() => self::leaveFields());
        self::register('hr_leave', fn() => self::leaveFields());
    }

    private static function leaveFields(): array
    {
        return [
            ['name' => 'entity.days', 'label' => 'عدد أيام الإجازة', 'type' => 'number'],
        ];
    }
}
