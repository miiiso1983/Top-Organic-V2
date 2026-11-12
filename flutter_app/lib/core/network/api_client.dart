import 'package:dio/dio.dart';
import '../config.dart';
import '../storage/auth_storage.dart';
import '../updates/app_release.dart';
import '../updates/update_policy.dart';

/// عميل REST موحّد يتصل بخادم Top Organic (PHP MVC)
class ApiClient {
  ApiClient({AuthStorage? authStorage, Dio? dio})
    : _authStorage = authStorage ?? AuthStorage() {
    _dio = dio ?? Dio(
      BaseOptions(
        baseUrl: AppConfig.apiBaseUrl,
        connectTimeout: AppConfig.connectTimeout,
        receiveTimeout: AppConfig.receiveTimeout,
        headers: {'Accept': 'application/json'},
      ),
    );
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          try {
            options.headers.addAll((await AppRelease.current()).headers);
          } catch (_) {}
          final token = await _authStorage.getToken();
          if (token != null) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          handler.next(options);
        },
        onError: (error, handler) {
          if (error.response?.statusCode == 426 &&
              error.response?.data is Map &&
              error.response?.data['error_code'] == 'app_update_required') {
            UpdateRequiredSignal.accept(error.response?.data['update']);
          }
          if (error.response?.statusCode == 401) {
            onUnauthorized?.call();
          }
          handler.next(error);
        },
      ),
    );
  }

  final AuthStorage _authStorage;
  late final Dio _dio;

  /// يُستدعى عند انتهاء صلاحية الجلسة (401) لإعادة التوجيه لتسجيل الدخول
  void Function()? onUnauthorized;

  Future<Response> get(String path, {Map<String, dynamic>? query}) {
    return _dio.get(path, queryParameters: query);
  }

  Future<Response> post(String path, {dynamic data, Options? options}) {
    return _dio.post(path, data: data, options: options);
  }

  /// تنفيذ طلب عام (يُستخدم في إعادة تشغيل طابور المزامنة أوف لاين)
  Future<Response> request(String method, String path, {dynamic data}) {
    return _dio.request(
      path,
      data: data,
      options: Options(method: method),
    );
  }

  static bool isNetworkError(Object error) {
    if (error is DioException) {
      return error.type == DioExceptionType.connectionError ||
          error.type == DioExceptionType.connectionTimeout ||
          error.type == DioExceptionType.receiveTimeout ||
          error.type == DioExceptionType.sendTimeout;
    }
    return false;
  }
}
