import 'dart:convert';
import 'package:dio/dio.dart';
import '../db/local_db.dart';
import '../network/api_client.dart';

/// نتيجة جلب بيانات: تحدد إن كانت من الخادم مباشرة أو من التخزين المؤقت (أوف لاين)
class ApiResult<T> {
  ApiResult(this.data, {required this.fromCache, this.errorMessage});
  final T data;
  final bool fromCache;
  final String? errorMessage;
}

/// مستودع عام: يجلب من API ويخزّن مؤقتاً محلياً، ويرجع للتخزين المؤقت عند تعذر الاتصال
class CachedRepository {
  CachedRepository(this._api);
  final ApiClient _api;
  final LocalDb _db = LocalDb.instance;

  Future<ApiResult<dynamic>> getJson(
    String path,
    String cacheKey, {
    Map<String, dynamic>? query,
    int? ttlSeconds,
  }) async {
    try {
      final res = await _api.get(path, query: query);
      final payload = res.data;
      await _db.putCache(cacheKey, jsonEncode(payload));
      return ApiResult(payload, fromCache: false);
    } catch (e) {
      final cached = await _db.getCache(cacheKey);
      if (cached != null) {
        return ApiResult(
          jsonDecode(cached),
          fromCache: true,
          errorMessage: 'أنت غير متصل، تُعرض آخر بيانات محفوظة',
        );
      }
      rethrow;
    }
  }

  /// إرسال عملية كتابة (POST). عند فشل الاتصال تُحفظ في طابور المزامنة لإرسالها لاحقاً
  Future<ApiResult<Map<String, dynamic>?>> postOrQueue(
    String path,
    Map<String, dynamic> data, {
    required String entity,
  }) async {
    try {
      final res = await _api.post(path, data: data);
      return ApiResult(res.data as Map<String, dynamic>?, fromCache: false);
    } catch (e) {
      if (ApiClient.isNetworkError(e)) {
        await _db.enqueue(
          method: 'POST',
          path: path,
          payloadJson: jsonEncode(data),
          entity: entity,
        );
        return ApiResult(
          null,
          fromCache: true,
          errorMessage: 'لا يوجد اتصال، سيتم الإرسال تلقائياً لاحقاً',
        );
      }
      if (e is DioException && e.response?.data is Map) {
        final msg = (e.response!.data as Map)['error']?.toString();
        return ApiResult(
          null,
          fromCache: false,
          errorMessage: msg ?? 'حدث خطأ',
        );
      }
      rethrow;
    }
  }

  /// إرسال POST مباشر دون حفظه في طابور المزامنة (يُستخدم للعمليات التي تحتاج رد فوري)
  Future<ApiResult<Map<String, dynamic>?>> postJson(
    String path,
    Map<String, dynamic> data,
  ) async {
    try {
      final res = await _api.post(
        path,
        data: data,
        options: Options(contentType: 'application/json'),
      );
      return ApiResult(res.data as Map<String, dynamic>?, fromCache: false);
    } catch (e) {
      if (e is DioException && e.response?.data is Map) {
        final msg = (e.response!.data as Map)['error']?.toString();
        return ApiResult(
          null,
          fromCache: false,
          errorMessage: msg ?? 'حدث خطأ',
        );
      }
      rethrow;
    }
  }
}
