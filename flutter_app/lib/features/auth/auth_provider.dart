import 'package:flutter/foundation.dart';
import '../../core/network/api_client.dart';
import '../../core/storage/auth_storage.dart';
import '../../core/services/push_notification_service.dart';

enum AuthState { unknown, authenticated, unauthenticated }

/// حالة تسجيل الدخول والمستخدم الحالي
class AuthProvider extends ChangeNotifier {
  AuthProvider(this._api, this._storage);

  final ApiClient _api;
  final AuthStorage _storage;

  AuthState state = AuthState.unknown;
  Map<String, dynamic>? user;
  List<String> permissions = [];
  String? errorMessage;
  bool loading = false;

  /// يُستدعى بعد تسجيل دخول ناجح (لتحديث عدّاد الإشعارات غير المقروءة فوراً مثلاً)
  void Function()? onLogin;

  /// التحقق من امتلاك المستخدم لصلاحية معيّنة (مدير النظام لديه كل الصلاحيات مسبقاً من الخادم)
  bool can(String permission) => permissions.contains(permission);

  /// يتحقق ما إذا كان المستخدم الحالي بدور "كابتن" (نادل يأخذ الطلبات من الطاولات)
  /// حساب الكابتن يُعرض له شاشة واحدة فقط (شاشة الكابتن) بواجهة التابلت دائماً
  bool get isCaptain {
    final role = user?['role']?.toString() ?? '';
    return role.contains('كابتن') || role.toLowerCase().contains('captain');
  }

  /// يتحقق من إذا كان المستخدم يحمل دوراً يحتوي على [keyword] في اسمه
  bool hasRole(String keyword) {
    final role = user?['role']?.toString().toLowerCase() ?? '';
    return role.contains(keyword.toLowerCase());
  }

  /// يتحقق من إذا كان المستخدم مديراً أو أعلى (مدير / مدير النظام / manager / admin)
  bool get isManagerOrHigher {
    final role = user?['role']?.toString().toLowerCase() ?? '';
    return role.contains('مدير') ||
        role.contains('manager') ||
        role.contains('admin') ||
        role.contains('super');
  }

  Future<void> bootstrap() async {
    final token = await _storage.getToken();
    if (token == null) {
      state = AuthState.unauthenticated;
    } else {
      user = await _storage.getUser();
      permissions = await _storage.getPermissions();
      state = AuthState.authenticated;
      _refreshPermissions();
    }
    notifyListeners();
  }

  /// تحديث الصلاحيات من الخادم دون الحاجة لإعادة تسجيل الدخول (مثلاً بعد تعديل صلاحيات الدور)
  Future<void> _refreshPermissions() async {
    try {
      final res = await _api.get('/permissions');
      final data = res.data as Map<String, dynamic>;
      if (data['ok'] == true) {
        permissions = ((data['data'] as List?) ?? [])
            .map((e) => e.toString())
            .toList();
        await _storage.savePermissions(permissions);
        notifyListeners();
      }
    } catch (_) {
      // تجاهل الخطأ: تُستخدم الصلاحيات المخزّنة محلياً عند تعذر الاتصال
    }
  }

  Future<bool> login(String username, String password) async {
    loading = true;
    errorMessage = null;
    notifyListeners();
    try {
      final res = await _api.post(
        '/login',
        data: {'username': username, 'password': password},
      );
      final data = res.data as Map<String, dynamic>;
      if (data['ok'] == true) {
        final perms = ((data['permissions'] as List?) ?? [])
            .map((e) => e.toString())
            .toList();
        await _storage.saveSession(
          data['token'] as String,
          data['user'] as Map<String, dynamic>,
          perms,
        );
        user = data['user'] as Map<String, dynamic>;
        permissions = perms;
        state = AuthState.authenticated;
        PushNotificationService().sendCurrentToken();
        onLogin?.call();
        return true;
      }
      errorMessage = data['error']?.toString() ?? 'فشل تسجيل الدخول';
      return false;
    } catch (e) {
      errorMessage = ApiClient.isNetworkError(e)
          ? 'تعذر الاتصال بالخادم، تحقق من الإنترنت'
          : 'بيانات الدخول غير صحيحة';
      return false;
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<void> logout() async {
    await _storage.clear();
    user = null;
    permissions = [];
    state = AuthState.unauthenticated;
    notifyListeners();
  }
}
