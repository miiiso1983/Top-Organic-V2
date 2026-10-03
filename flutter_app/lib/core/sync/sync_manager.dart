import 'dart:async';
import 'dart:convert';
import 'package:connectivity_plus/connectivity_plus.dart';
import '../db/local_db.dart';
import '../network/api_client.dart';

/// يدير المزامنة الهجينة: يراقب الاتصال ويرسل العمليات المعلّقة تلقائياً
class SyncManager {
  SyncManager(this._api);

  final ApiClient _api;
  final LocalDb _db = LocalDb.instance;
  StreamSubscription<List<ConnectivityResult>>? _sub;
  bool _syncing = false;

  final _statusController = StreamController<SyncStatus>.broadcast();
  Stream<SyncStatus> get statusStream => _statusController.stream;

  void start() {
    _sub = Connectivity().onConnectivityChanged.listen((results) {
      if (!results.contains(ConnectivityResult.none)) {
        syncNow();
      }
    });
    syncNow();
  }

  void dispose() {
    _sub?.cancel();
    _statusController.close();
  }

  /// تنفيذ فوري لمزامنة العمليات المعلّقة (استدعِها بعد أي حفظ محلي أو عند فتح التطبيق)
  Future<void> syncNow() async {
    if (_syncing) return;
    _syncing = true;
    _statusController.add(SyncStatus.syncing);
    try {
      final pending = await _db.pendingOperations();
      for (final op in pending) {
        try {
          await _api.request(
            op['method'] as String,
            op['path'] as String,
            data: jsonDecode(op['payload'] as String),
          );
          await _db.markSynced(op['id'] as int);
        } catch (e) {
          if (ApiClient.isNetworkError(e)) {
            // لا يوجد اتصال فعلي، أوقف المحاولة وأعد لاحقاً
            break;
          }
          // خطأ من الخادم (بيانات غير صالحة مثلاً): تجاهل هذا العنصر ولا تكرر المحاولة لباقي القائمة
          await _db.markSynced(op['id'] as int);
        }
      }
      _statusController.add(SyncStatus.idle);
    } catch (_) {
      _statusController.add(SyncStatus.error);
    } finally {
      _syncing = false;
    }
  }

  Future<int> pendingCount() => _db.pendingCount();

  /// حفظ عملية محلياً لإرسالها لاحقاً (تُستخدم عند فشل الاتصال أثناء الحفظ المباشر)
  Future<void> queue({
    required String method,
    required String path,
    required Map<String, dynamic> payload,
    required String entity,
  }) {
    return _db.enqueue(
      method: method,
      path: path,
      payloadJson: jsonEncode(payload),
      entity: entity,
    );
  }
}

enum SyncStatus { idle, syncing, error }
