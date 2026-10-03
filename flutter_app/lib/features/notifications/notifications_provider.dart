import 'dart:async';
import 'package:flutter/foundation.dart';
import '../../core/network/api_client.dart';

/// يدير عدد الإشعارات غير المقروءة لعرضه كرمز (Badge) على أيقونة الجرس
/// في كل واجهات التطبيق، ويحدّثه دورياً وعند وصول إشعار Push جديد.
class NotificationsProvider extends ChangeNotifier {
  int _unreadCount = 0;
  Timer? _timer;

  int get unreadCount => _unreadCount;

  void setCount(int count) {
    if (count == _unreadCount) return;
    _unreadCount = count < 0 ? 0 : count;
    notifyListeners();
  }

  /// زيادة فورية (تفاؤلية) عند وصول إشعار أثناء فتح التطبيق، قبل المزامنة مع الخادم
  void bump() {
    _unreadCount += 1;
    notifyListeners();
  }

  /// جلب العدد الفعلي من الخادم
  Future<void> refresh(ApiClient api) async {
    try {
      final res = await api.get('/notifications');
      final body = res.data as Map<String, dynamic>;
      setCount((body['unread'] as num?)?.toInt() ?? 0);
    } catch (_) {
      // تجاهل أخطاء الشبكة، العدد الحالي يبقى كما هو
    }
  }

  /// بدء تحديث دوري (كل 60 ثانية) طوال عمر التطبيق
  void startAutoRefresh(ApiClient api) {
    _timer?.cancel();
    refresh(api);
    _timer = Timer.periodic(const Duration(seconds: 60), (_) => refresh(api));
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }
}
