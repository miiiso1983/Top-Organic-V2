import 'package:flutter/foundation.dart';
import '../../core/data/cached_repository.dart';
import '../../core/locale/app_strings.dart';

class CartLine {
  CartLine(this.item, this.qty);
  final Map<String, dynamic> item;
  int qty;
}

class CaptainOrderController extends ChangeNotifier {
  CaptainOrderController({
    required this.table,
    required this.repo,
    this.lang = 'ar',
  });

  final Map<String, dynamic> table;
  final CachedRepository repo;

  /// رمز اللغة الحالية، يحدثه المستخدم عند تغير اللغة لترجمة الرسائل اللاحقة
  String lang;

  bool loading = true;
  String? error;
  String? offlineNotice;
  List<dynamic> categories = [];
  List<dynamic> items = [];
  int? selectedCategoryId;
  final Map<int, CartLine> _cart = {};
  Map<String, dynamic>? currentOrder;
  List<dynamic> currentItems = [];
  bool submitting = false;
  String? submitMessage;
  bool submitFailed = false;

  Future<void> load() async {
    loading = true;
    error = null;
    offlineNotice = null;
    notifyListeners();
    try {
      final floorId = table['floor_id']?.toString();
      final menuKey = floorId != null
          ? 'captain_menu_$floorId'
          : 'captain_menu';
      final menuRes = await repo.getJson(
        '/menu',
        menuKey,
        query: floorId != null ? {'floor_id': floorId} : null,
      );
      final menuBody = menuRes.data as Map<String, dynamic>;
      final data = (menuBody['data'] as Map).cast<String, dynamic>();
      categories = (data['categories'] as List?) ?? [];
      items = (data['items'] as List?) ?? [];
      offlineNotice = menuRes.fromCache ? menuRes.errorMessage : null;

      final orderId = table['current_order_id'];
      if (orderId != null && orderId.toString().isNotEmpty) {
        try {
          final orderRes = await repo.getJson(
            '/api/orders/$orderId',
            'captain_order_$orderId',
          );
          final orderBody = orderRes.data as Map<String, dynamic>;
          final orderData = (orderBody['data'] as Map).cast<String, dynamic>();
          currentOrder = orderData['order'] as Map<String, dynamic>?;
          final rawItems = (orderData['items'] as List?) ?? [];
          currentItems = rawItems
              .where(
                (it) =>
                    (it as Map)['kitchen_status']?.toString() != 'cancelled',
              )
              .toList();
        } catch (_) {
          // نستمر حتى لو فشل جلب الطلب المفتوح
        }
      }
    } catch (e) {
      error = AppStrings.t('captain_controller_load_error', lang);
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  void selectCategory(int? id) {
    selectedCategoryId = id;
    notifyListeners();
  }

  List<dynamic> get visibleItems {
    if (selectedCategoryId == null) return items;
    return items.where((i) {
      final cat = (i as Map)['category_id'];
      return cat.toString() == selectedCategoryId.toString();
    }).toList();
  }

  Map<int, CartLine> get cart => _cart;

  double get cartTotal => _cart.values.fold(
    0.0,
    (s, l) =>
        s + (double.tryParse(l.item['price']?.toString() ?? '0') ?? 0) * l.qty,
  );

  int cartQty(int id) => _cart[id]?.qty ?? 0;

  void addItem(Map item) {
    final id = item['id'] as int;
    _cart.putIfAbsent(id, () => CartLine(item.cast<String, dynamic>(), 0));
    _cart[id]!.qty++;
    notifyListeners();
  }

  void incItem(int id) {
    _cart[id]?.qty++;
    notifyListeners();
  }

  void decItem(int id) {
    final line = _cart[id];
    if (line == null) return;
    if (line.qty <= 1) {
      _cart.remove(id);
    } else {
      line.qty--;
    }
    notifyListeners();
  }

  Future<bool> submit(String notes) async {
    if (_cart.isEmpty) return false;
    submitting = true;
    submitMessage = null;
    notifyListeners();
    try {
      final cartPayload = _cart.values
          .map(
            (l) => {'id': l.item['id'], 'price': l.item['price'], 'qty': l.qty},
          )
          .toList();
      final payload = {
        'table_id': table['id'],
        'cart': cartPayload,
        'notes': notes,
        if (currentOrder?['id'] != null) 'order_id': currentOrder!['id'],
      };
      final res = await repo.postOrQueue(
        '/api/captain/order',
        payload,
        entity: 'orders',
      );
      final body = res.data;
      final bool success = body?['success'] == true || res.fromCache;
      if (!success) {
        submitMessage =
            body?['error']?.toString() ??
            res.errorMessage ??
            AppStrings.t('captain_controller_send_failed', lang);
        submitFailed = true;
        submitting = false;
        notifyListeners();
        return false;
      }
      submitMessage = res.fromCache
          ? AppStrings.t('captain_controller_queued_offline', lang)
          : AppStrings.t(
              currentOrder != null
                  ? 'captain_controller_items_added'
                  : 'captain_controller_order_sent',
              lang,
            );
      submitFailed = false;
      _cart.clear();
      final newOrderId = body?['order_id'];
      if (currentOrder == null && newOrderId != null) {
        table['current_order_id'] = newOrderId;
      }
      submitting = false;
      notifyListeners();
      await load();
      return true;
    } catch (e) {
      submitMessage = AppStrings.t('captain_controller_send_failed', lang);
      submitFailed = true;
      submitting = false;
      notifyListeners();
      return false;
    }
  }
}
