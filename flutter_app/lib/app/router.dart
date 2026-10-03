import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../features/auth/auth_provider.dart';
import '../features/auth/login_screen.dart';
import '../features/dashboard/dashboard_screen.dart';
import '../features/notifications/notifications_screen.dart';
import '../features/approvals/approvals_screen.dart';
import '../features/approvals/workflow_create_screen.dart';
import '../features/approvals/workflow_edit_screen.dart';
import '../features/approvals/archive_screen.dart';
import '../features/maintenance/maintenance_screen.dart';
import '../features/maintenance/maintenance_create_screen.dart';
import '../features/maintenance/maintenance_detail_screen.dart';
import '../features/sales/sales_screen.dart';
import '../features/kitchen/kitchen_screen.dart';
import '../features/inventory/inventory_screen.dart';
import '../features/purchasing/purchasing_screen.dart';
import '../features/hr/hr_screen.dart';
import '../features/hr/attendance_screen.dart';
import '../features/hr/exceptional_advance_screen.dart';
import '../features/hr/manager_shift_assign_screen.dart';
import '../features/hr/overtime_request_screen.dart';
import '../features/hr/overtime_manager_screen.dart';
import '../features/hr/team_evaluation_screen.dart';
import '../features/hr/salary_screen.dart';
import '../features/reservations/reservations_screen.dart';
import '../features/tasks/tasks_screen.dart';
import '../features/assets/assets_screen.dart';
import '../features/reports/reports_screen.dart';
import '../features/inventory/barcode_scan_screen.dart';
import '../features/inventory/stock_entry_screen.dart';
import '../features/inventory/stock_exit_screen.dart';
import '../features/inventory/transfer_screen.dart';
import '../features/inventory/stocktake_screen.dart';
import '../features/inventory/manufacture_screen.dart';
import '../features/inventory/reports_screen.dart';
import '../features/custody/custody_issue_screen.dart';
import '../features/captain/captain_screen.dart';

import '../features/call_center/call_center_screen.dart';
import '../features/ptt/ptt_screen.dart';
import '../features/housing/housing_screen.dart';
import '../features/profile/profile_screen.dart';
import 'app_shell.dart';
import 'nav_items.dart';

// ملاحظة: يمكن إضافة routes.observers أو errorBuilder لاحقاً عند الحاجة
GoRouter buildRouter(AuthProvider auth) {
  return GoRouter(
    initialLocation: '/dashboard',
    refreshListenable: auth,
    errorBuilder: (context, state) => Scaffold(
      body: Center(
        child: Text(
          'خطأ في التنقل: ${state.error?.message ?? 'غير معروف'}',
          textDirection: TextDirection.rtl,
          textAlign: TextAlign.center,
        ),
      ),
    ),
    redirect: (context, state) {
      final loggingIn = state.matchedLocation == '/login';
      if (auth.state == AuthState.unknown) return null;
      if (auth.state == AuthState.unauthenticated) {
        return loggingIn ? null : '/login';
      }

      final isCaptain = auth.isCaptain;
      if (loggingIn) return isCaptain ? '/captain' : '/dashboard';

      final location = state.matchedLocation;

      // حساب الكابتن مقيّد بشاشة واحدة فقط (شاشة الكابتن) دون أي تنقل آخر في التطبيق
      if (isCaptain) {
        return location == '/captain' ? null : '/captain';
      }
      // شاشة الكابتن مخصّصة لأصحاب هذا الحساب فقط
      if (location == '/captain') {
        return '/dashboard';
      }

      // منع الوصول المباشر (رابط/تنقل برمجي) لصفحة لا يملك المستخدم صلاحيتها
      final matches = kNavItems.where((e) => location.startsWith(e.path));
      final item = matches.isEmpty ? null : matches.first;
      if (item != null && !navItemVisible(item, auth)) {
        // السماح للمدراء بالوصول إلى قسم الموافقات/إنشاء الطلب والأرشيف لاستخدام طلب تغيير الوردية
        if ((location.startsWith('/approvals') ||
                location.startsWith('/archive')) &&
            auth.isManagerOrHigher) {
          return null;
        }
        // السماح للمدراء بالوصول إلى شاشات الموارد البشرية المخصصة للمدراء
        if (location.startsWith('/hr') && auth.isManagerOrHigher) {
          return null;
        }
        return '/dashboard';
      }
      return null;
    },
    routes: [
      GoRoute(path: '/login', builder: (context, state) => const LoginScreen()),
      GoRoute(
        path: '/captain',
        builder: (context, state) => const CaptainScreen(),
      ),
      ShellRoute(
        builder: (context, state, child) =>
            AppShell(location: state.matchedLocation, child: child),
        routes: [
          GoRoute(
            path: '/dashboard',
            builder: (context, state) => const DashboardScreen(),
          ),
          GoRoute(
            path: '/notifications',
            builder: (context, state) => const NotificationsScreen(),
          ),
          GoRoute(
            path: '/approvals',
            builder: (context, state) => const ApprovalsScreen(),
          ),
          GoRoute(
            path: '/approvals/create',
            builder: (context, state) =>
                WorkflowCreateScreen(initialTemplateId: state.extra as int?),
          ),
          GoRoute(
            path: '/approvals/edit/:id',
            builder: (context, state) => WorkflowEditScreen(
              requestId: int.parse(state.pathParameters['id']!),
            ),
          ),
          GoRoute(
            path: '/archive',
            builder: (context, state) => const ArchiveScreen(),
          ),
          GoRoute(
            path: '/sales',
            builder: (context, state) => const SalesScreen(),
          ),
          GoRoute(
            path: '/kitchen',
            builder: (context, state) => const KitchenScreen(),
          ),
          GoRoute(
            path: '/inventory',
            builder: (context, state) => const InventoryScreen(),
          ),
          GoRoute(
            path: '/inventory/scan',
            builder: (context, state) => const BarcodeScanScreen(),
          ),
          GoRoute(
            path: '/inventory/entry',
            builder: (context, state) =>
                StockEntryScreen(item: state.extra as Map<String, dynamic>?),
          ),
          GoRoute(
            path: '/inventory/exit',
            builder: (context, state) =>
                StockExitScreen(item: state.extra as Map<String, dynamic>?),
          ),
          GoRoute(
            path: '/inventory/transfer',
            builder: (context, state) => const TransferScreen(),
          ),
          GoRoute(
            path: '/inventory/stocktake',
            builder: (context, state) => const StocktakeScreen(),
          ),
          GoRoute(
            path: '/inventory/manufacture',
            builder: (context, state) => const ManufactureScreen(),
          ),
          GoRoute(
            path: '/inventory/reports',
            builder: (context, state) => const InventoryReportsScreen(),
          ),
          GoRoute(
            path: '/custody/issue',
            builder: (context, state) =>
                CustodyIssueScreen(item: state.extra as Map<String, dynamic>?),
          ),
          GoRoute(
            path: '/purchasing',
            builder: (context, state) => const PurchasingScreen(),
          ),
          GoRoute(path: '/hr', builder: (context, state) => const HrScreen()),
          GoRoute(
            path: '/hr/exceptional-advance',
            builder: (context, state) => const ExceptionalAdvanceScreen(),
          ),
          GoRoute(
            path: '/hr/manager-shift-assign',
            builder: (context, state) => const ManagerShiftAssignScreen(),
          ),
          GoRoute(
            path: '/hr/overtime-request',
            builder: (context, state) => const OvertimeRequestScreen(),
          ),
          GoRoute(
            path: '/hr/overtime-manager',
            builder: (context, state) => const OvertimeManagerScreen(),
          ),
          GoRoute(
            path: '/hr/team-evaluations',
            builder: (context, state) => const TeamEvaluationScreen(),
          ),
          GoRoute(
            path: '/hr/salary',
            builder: (context, state) => const SalaryScreen(),
          ),
          GoRoute(
            path: '/reservations',
            builder: (context, state) => const ReservationsScreen(),
          ),
          GoRoute(
            path: '/tasks',
            builder: (context, state) => const TasksScreen(),
          ),
          GoRoute(
            path: '/maintenance',
            builder: (context, state) => const MaintenanceScreen(),
          ),
          GoRoute(
            path: '/maintenance/create',
            builder: (context, state) => const MaintenanceCreateScreen(),
          ),
          GoRoute(
            path: '/maintenance/:id',
            builder: (context, state) => MaintenanceDetailScreen(
              id: int.parse(state.pathParameters['id']!),
            ),
          ),
          GoRoute(
            path: '/assets',
            builder: (context, state) => const AssetsScreen(),
          ),
          GoRoute(
            path: '/reports',
            builder: (context, state) => const ReportsScreen(),
          ),
          GoRoute(
            path: '/clock',
            builder: (context, state) => const AttendanceScreen(),
          ),
          GoRoute(
            path: '/call-center',
            builder: (context, state) => const CallCenterScreen(),
          ),
          GoRoute(path: '/ptt', builder: (context, state) => const PttScreen()),
          GoRoute(
            path: '/housing',
            builder: (context, state) => const HousingScreen(),
          ),
          GoRoute(
            path: '/profile',
            builder: (context, state) => const ProfileScreen(),
          ),
        ],
      ),
    ],
  );
}
