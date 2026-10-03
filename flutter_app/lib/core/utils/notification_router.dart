/// يحدّد مسار التطبيق (Flutter route) المناسب لفتحه عند الضغط على إشعار،
/// اعتماداً على نوع الكيان (entity_type)، نوع الإجراء (action_type)،
/// كود القالب (template_code — متاح فقط من قائمة الإشعارات داخل التطبيق)،
/// والرابط الأصلي (link) المُرسل من الخادم كخيار احتياطي.
///
/// أمثلة:
/// - إشعار "طلب موافقة بانتظارك" (معلّق) → تفتح شاشة "الطلبات والموافقات".
/// - إشعار اعتماد/رفض/تعديل الطلب (بعد اتخاذ القرار) → تفتح شاشة "الأرشيف".
/// - إشعار متعلق بطلب نسيان بصمة/حضور → تفتح شاشة "البصمة" (تسجيل الحضور).
/// - إشعار اعتماد الراتب/تسوية مالية → تفتح شاشة "كشف الراتب".
/// - إشعار تذكير تقييم الأداء → تفتح شاشة "تقييم أداء الفريق".
String resolveNotificationRoute({
  String? entityType,
  String? actionType,
  String? link,
  String? templateCode,
}) {
  const decidedActions = {
    'approved',
    'rejected',
    'returned_for_edit',
    'status_update',
    'escalated',
  };

  // ١. أدق تصنيف متاح: كود قالب الطلب (متوفر فقط من قائمة الإشعارات داخل التطبيق)
  switch (templateCode) {
    case 'hr_missing_attendance':
      return '/clock';
    case 'hr_overtime':
      return decidedActions.contains(actionType)
          ? '/archive'
          : '/hr/overtime-request';
    case 'purchase_request':
      return '/purchasing';
    case 'material_request':
      return '/inventory/transfer';
  }

  // ٢. تصنيف الكيان (entity_type) المُرسل مع Push (متاح دائماً تقريباً)
  switch (entityType) {
    case 'request':
      return decidedActions.contains(actionType) ? '/archive' : '/approvals';
    case 'kpi_evaluation':
      return '/hr/team-evaluations';
    case 'missing_attendance_request':
      return '/clock';
    case 'hr_adjustment':
      return '/hr/salary';
    case 'purchase_request':
      return '/purchasing';
    case 'material_request':
    case 'material_request_transfer':
    case 'stock_transfer':
      return '/inventory/transfer';
    case 'leave_request':
      return '/hr';
  }

  // ٣. احتياطي: تحليل الرابط الأصلي (link) المُرسل من الخادم
  final l = link ?? '';
  if (l.contains('/workflows/requests/')) {
    return decidedActions.contains(actionType) ? '/archive' : '/approvals';
  }
  if (l.contains('/payroll') || l.contains('/salary')) return '/hr/salary';
  if (l.contains('/evaluations')) return '/hr/team-evaluations';
  if (l.contains('/adjustments')) return '/hr/salary';
  if (l.contains('/purchasing')) return '/purchasing';
  if (l.contains('/inventory/transfer')) return '/inventory/transfer';
  if (l.contains('/reservations')) return '/reservations';
  if (l.contains('/maintenance')) return '/maintenance';
  if (l.contains('/ptt') || l.contains('صوتية')) return '/ptt';
  if (l.contains('/clock') || l.contains('attendance')) return '/clock';

  // ٤. لا يوجد تصنيف كافٍ — افتح شاشة الإشعارات نفسها ليختار المستخدم
  return '/notifications';
}
