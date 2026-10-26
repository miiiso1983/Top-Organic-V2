import 'package:flutter/material.dart';
import '../features/auth/auth_provider.dart';

class NavItem {
  const NavItem({
    required this.path,
    required this.label,
    required this.icon,
    this.permissions = const [],
    this.customCheck,
  });
  final String path;
  final String label;
  final IconData icon;

  /// الصلاحيات التي تمنح إمكانية الوصول لهذا العنصر (يكفي امتلاك واحدة منها).
  /// قائمة فارغة تعني أن العنصر متاح لكل مستخدم مسجّل دخول (بدون شرط صلاحية).
  final List<String> permissions;

  /// شرط وصول مخصّص (مثل التحقق من القسم) يُستخدم بدلاً من/بالإضافة للصلاحيات.
  /// عند توفره يُحتكم إليه وحده لتحديد ظهور العنصر وإمكانية فتحه.
  final bool Function(AuthProvider auth)? customCheck;
}

/// يحدّد ما إذا كان عنصر تنقّل معيّن متاحاً للمستخدم الحالي
bool navItemVisible(NavItem item, AuthProvider auth) {
  if (item.customCheck != null) return item.customCheck!(auth);
  return item.permissions.isEmpty || item.permissions.any(auth.can);
}

/// عناصر التنقل الرئيسية للتطبيق (تُستخدم في الـ Drawer/BottomNav/NavigationRail)
/// تُفلتر هذه القائمة بحسب صلاحيات المستخدم قبل عرضها (انظر AppShell/AuthProvider.can).
final List<NavItem> kNavItems = [
  NavItem(
    path: '/dashboard',
    label: 'nav_dashboard',
    icon: Icons.dashboard_outlined,
  ),
  NavItem(
    path: '/notifications',
    label: 'nav_notifications',
    icon: Icons.notifications_outlined,
  ),
  // بلا شرط صلاحية: صندوق البريد متاح لكل مستخدم مسجّل دخول في جميع الأقسام
  NavItem(path: '/mail', label: 'nav_mail', icon: Icons.mail_outline),
  // بلا شرط صلاحية: كل موظف مسجّل دخول يمكنه تقديم طلب صيانة ومتابعة طلباته هنا
  NavItem(
    path: '/approvals',
    label: 'nav_approvals',
    icon: Icons.fact_check_outlined,
  ),
  NavItem(path: '/archive', label: 'nav_archive', icon: Icons.archive_outlined),
  NavItem(
    path: '/team',
    label: 'nav_team_records',
    icon: Icons.groups_outlined,
  ),
  NavItem(
    path: '/sales',
    label: 'nav_sales',
    icon: Icons.point_of_sale_outlined,
    permissions: ['sales.view', 'sales.create', 'pos.use'],
  ),
  NavItem(
    path: '/kitchen',
    label: 'nav_kitchen',
    icon: Icons.soup_kitchen_outlined,
    permissions: ['kitchen.view', 'kitchen.manage'],
  ),
  NavItem(
    path: '/inventory',
    label: 'nav_inventory',
    icon: Icons.inventory_2_outlined,
    permissions: ['inventory.view', 'inventory.manage', 'inventory.mobile'],
  ),
  NavItem(
    path: '/purchasing',
    label: 'nav_purchasing',
    icon: Icons.shopping_cart_outlined,
    permissions: [
      'purchasing.view',
      'purchasing.manage',
      'mobile.requests',
      'material_request.mobile',
    ],
  ),
  NavItem(
    path: '/hr',
    label: 'nav_hr',
    icon: Icons.badge_outlined,
    permissions: [
      'attendance.self',
      'mobile.attendance',
      'hr.view',
      'hr.clock',
    ],
  ),
  NavItem(
    path: '/clock',
    label: 'nav_clock',
    icon: Icons.fingerprint_outlined,
    permissions: [
      'attendance.self',
      'mobile.attendance',
      'hr.view',
      'hr.clock',
    ],
  ),
  NavItem(
    path: '/call-center',
    label: 'nav_call_center',
    icon: Icons.phone_in_talk_outlined,
    permissions: [
      'reservations.manage',
      'reservations.view',
      'call_center.manage',
      'reception.manage',
    ],
  ),
  NavItem(
    path: '/ptt',
    label: 'PTT',
    icon: Icons.mic_outlined,
    permissions: ['ptt.use', 'ptt.view', 'ptt.manage'],
  ),
  NavItem(
    path: '/reservations',
    label: 'nav_reservations',
    icon: Icons.event_seat_outlined,
    permissions: [
      'reservations.manage',
      'reservations.view',
      'call_center.manage',
      'reception.manage',
    ],
  ),
  NavItem(
    path: '/tasks',
    label: 'nav_tasks',
    icon: Icons.task_alt_outlined,
    permissions: ['tasks.self', 'mobile.tasks'],
  ),
  NavItem(
    path: '/assets',
    label: 'nav_assets',
    icon: Icons.inventory_outlined,
    permissions: ['custody.manage', 'custody.mobile', 'hr.custody.view'],
  ),
  NavItem(
    path: '/reports',
    label: 'nav_reports',
    icon: Icons.bar_chart_outlined,
    permissions: ['reports.view', 'hr.reports.view', 'accounting.reports'],
  ),
  NavItem(
    path: '/housing',
    label: 'nav_housing',
    icon: Icons.home_work_outlined,
    // يظهر لموظفي قسم الحراسة (تسكين ذاتي لقسمهم) أو لأي مستخدم يملك صلاحية
    // hr.housing.manage (مثل المدير المالي ومدير الموارد البشرية) لإدارة سكن كل الموظفين
    customCheck: (auth) =>
        (auth.user?['department']?.toString().trim() ?? '') == 'الحراسة' ||
        auth.can('hr.housing.manage'),
  ),
  NavItem(path: '/profile', label: 'profile_title', icon: Icons.person_outline),
];

/// عناصر القائمة السفلية (Bottom Navigation) — 5 عناصر فقط
const List<NavItem> kBottomNavItems = [
  NavItem(
    path: '/dashboard',
    label: 'nav_dashboard',
    icon: Icons.dashboard_outlined,
  ),
  NavItem(
    path: '/approvals',
    label: 'nav_approvals',
    icon: Icons.fact_check_outlined,
  ),
  NavItem(
    path: '/notifications',
    label: 'nav_notifications',
    icon: Icons.notifications_outlined,
  ),
  NavItem(
    path: '/clock',
    label: 'nav_clock_short',
    icon: Icons.fingerprint_outlined,
    permissions: [
      'attendance.self',
      'mobile.attendance',
      'hr.view',
      'hr.clock',
    ],
  ),
  NavItem(
    path: '/ptt',
    label: 'PTT',
    icon: Icons.mic_outlined,
    permissions: ['ptt.use', 'ptt.view', 'ptt.manage'],
  ),
];
