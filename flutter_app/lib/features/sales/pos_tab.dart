import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:provider/provider.dart';
import '../../core/config.dart';
import '../../core/data/cached_repository.dart';
import '../../core/theme.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/offline_banner.dart';
import '../../shared/utils/formatters.dart';

class PosTab extends StatefulWidget {
  const PosTab({super.key});

  @override
  State<PosTab> createState() => _PosTabState();
}

class _CartLine {
  _CartLine(this.item, this.qty);
  final Map item;
  int qty;
}

class _PosTabState extends State<PosTab> {
  bool _loading = true;
  String? _error;
  String? _offlineNotice;
  List<dynamic> _categories = [];
  List<dynamic> _items = [];
  List<dynamic> _floors = [];
  List<dynamic> _tables = [];
  int? _selectedCategory;
  int? _selectedFloorId;
  int? _selectedTableId;
  final Map<int, _CartLine> _cart = {};
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _loadFloors();
    _load();
  }

  /// يحمّل قائمة الطوابق/الصالات (لاختيار المنيو والطاولة المناسبة تلقائياً)
  Future<void> _loadFloors() async {
    try {
      final repo = context.read<CachedRepository>();
      final res = await repo.getJson('/floors', 'pos_floors');
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      setState(() => _floors = (body['data'] as List?) ?? []);
    } catch (_) {
      // اختياري: تجاهل الخطأ، النظام يعمل بمنيو افتراضي واحد
    }
  }

  /// يحمّل منيو الطابق المحدد (أو المنيو الافتراضي) وطاولات نفس الطابق
  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final repo = context.read<CachedRepository>();
      final query = _selectedFloorId != null
          ? {'floor_id': _selectedFloorId.toString()}
          : null;
      final menuKey = _selectedFloorId != null
          ? 'pos_menu_$_selectedFloorId'
          : 'pos_menu';
      final res = await repo.getJson('/menu', menuKey, query: query);
      final body = res.data as Map<String, dynamic>;
      final data = (body['data'] as Map).cast<String, dynamic>();

      final tablesRes = await repo.getJson(
        '/pos/tables',
        'pos_tables_${_selectedFloorId ?? 'all'}',
        query: query,
      );
      final tablesBody = tablesRes.data as Map<String, dynamic>;

      if (!mounted) return;
      setState(() {
        _categories = (data['categories'] as List?) ?? [];
        _items = (data['items'] as List?) ?? [];
        _tables = (tablesBody['data'] as List?) ?? [];
        if (_selectedTableId != null &&
            !_tables.any(
              (t) => (t as Map)['id'].toString() == _selectedTableId.toString(),
            )) {
          _selectedTableId = null;
        }
        _offlineNotice = res.fromCache ? res.errorMessage : null;
      });
    } catch (e) {
      if (!mounted) return;
      final lang = context.read<LocaleProvider>().locale.languageCode;
      setState(() => _error = AppStrings.t('pos_load_error', lang));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _onFloorChanged(int? floorId) {
    setState(() {
      _selectedFloorId = floorId;
      _selectedTableId = null;
      _selectedCategory = null;
    });
    _load();
  }

  double get _total => _cart.values.fold(
    0.0,
    (s, l) => s + Formatters.toDouble(l.item['price']) * l.qty,
  );

  int get _cartCount => _cart.values.fold(0, (s, l) => s + l.qty);

  void _addToCart(Map item) {
    final id = item['id'] as int;
    setState(() {
      _cart.putIfAbsent(id, () => _CartLine(item, 0));
      _cart[id]!.qty++;
    });
  }

  void _incQty(int id) => setState(() => _cart[id]?.qty++);

  void _decQty(int id) {
    setState(() {
      final line = _cart[id];
      if (line == null) return;
      if (line.qty <= 1) {
        _cart.remove(id);
      } else {
        line.qty--;
      }
    });
  }

  Future<void> _checkout() async {
    if (_cart.isEmpty) return;
    setState(() => _submitting = true);
    final repo = context.read<CachedRepository>();
    final cart = _cart.values
        .map(
          (l) => {'id': l.item['id'], 'price': l.item['price'], 'qty': l.qty},
        )
        .toList();
    final payload = {
      'order_type': 'dine_in',
      'cart': cart,
      if (_selectedTableId != null) 'table_id': _selectedTableId,
    };
    final res = await repo.postOrQueue(
      '/pos/checkout',
      payload,
      entity: 'orders',
    );
    if (!mounted) return;
    setState(() => _submitting = false);
    final lang = context.read<LocaleProvider>().locale.languageCode;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          res.errorMessage ?? AppStrings.t('pos_checkout_success', lang),
        ),
      ),
    );
    if (res.errorMessage == null || res.fromCache) {
      setState(() => _cart.clear());
    }
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    final visibleItems = _selectedCategory == null
        ? _items
        : _items.where((i) {
            final cat = (i as Map)['category_id'];
            return cat.toString() == _selectedCategory.toString();
          }).toList();
    final isTablet =
        MediaQuery.sizeOf(context).width >= AppConfig.tabletBreakpoint;

    return Column(
      children: [
        OfflineBanner(message: _offlineNotice),
        Expanded(
          child: AsyncStateView(
            loading: _loading,
            error: _error,
            onRetry: _load,
            child: isTablet
                ? Row(
                    children: [
                      Expanded(flex: 3, child: _menuColumn(visibleItems, t)),
                      const VerticalDivider(width: 1),
                      Expanded(flex: 2, child: _cartPanel(t: t)),
                    ],
                  )
                : Stack(
                    children: [
                      Positioned.fill(
                        bottom: _cart.isEmpty ? 0 : 64,
                        child: _menuColumn(visibleItems, t),
                      ),
                      if (_cart.isNotEmpty)
                        Align(
                          alignment: Alignment.bottomCenter,
                          child: _mobileCartBar(t),
                        ),
                    ],
                  ),
          ),
        ),
      ],
    );
  }

  Widget _menuColumn(List<dynamic> visibleItems, String Function(String) t) {
    return Column(
      children: [
        if (_floors.isNotEmpty || _tables.isNotEmpty) _floorTableRow(t),
        SizedBox(
          height: 48,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 4),
            children: [
              _catChip(null, t('pos_category_all')),
              ..._categories.map(
                (c) => _catChip((c as Map)['id'] as int, c['name'].toString()),
              ),
            ],
          ),
        ),
        Expanded(
          child: visibleItems.isEmpty
              ? Center(child: Text(t('pos_no_items_in_category')))
              : LayoutBuilder(
                  builder: (context, constraints) {
                    final crossAxisCount = (constraints.maxWidth / 170)
                        .floor()
                        .clamp(2, 5);
                    return GridView.builder(
                      padding: const EdgeInsets.all(8),
                      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: crossAxisCount,
                        childAspectRatio: 0.95,
                        mainAxisSpacing: 8,
                        crossAxisSpacing: 8,
                      ),
                      itemCount: visibleItems.length,
                      itemBuilder: (context, i) =>
                          _menuCard(visibleItems[i] as Map),
                    );
                  },
                ),
        ),
      ],
    );
  }

  Widget _menuCard(Map item) {
    final id = item['id'] as int;
    final qty = _cart[id]?.qty ?? 0;
    final image = item['image'] as String?;
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => _addToCart(item),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (image != null && image.isNotEmpty)
              AspectRatio(
                aspectRatio: 16 / 9,
                child: CachedNetworkImage(
                  imageUrl: '${AppConfig.baseUrl}$image',
                  fit: BoxFit.cover,
                  placeholder: (c, u) =>
                      Container(color: AppTheme.brand.withValues(alpha: 0.08)),
                  errorWidget: (c, u, e) => Container(
                    color: AppTheme.brand.withValues(alpha: 0.08),
                    child: Icon(Icons.restaurant_menu, color: AppTheme.brand),
                  ),
                ),
              ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        if (image == null || image.isEmpty)
                          Container(
                            width: 36,
                            height: 36,
                            decoration: BoxDecoration(
                              color: AppTheme.brand.withValues(alpha: 0.1),
                              shape: BoxShape.circle,
                            ),
                            child: Icon(
                              Icons.restaurant_menu,
                              color: AppTheme.brand,
                              size: 18,
                            ),
                          )
                        else
                          const SizedBox.shrink(),
                        if (qty > 0)
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 3,
                            ),
                            decoration: BoxDecoration(
                              color: AppTheme.brand,
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: Text(
                              '$qty',
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 12,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                      ],
                    ),
                    const Spacer(),
                    Text(
                      item['name'].toString(),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 13,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      Formatters.currency(item['price']),
                      style: TextStyle(
                        color: AppTheme.brand,
                        fontWeight: FontWeight.bold,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _floorTableRow(String Function(String) t) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      child: Row(
        children: [
          if (_floors.isNotEmpty)
            Expanded(
              child: DropdownButtonFormField<int?>(
                initialValue: _selectedFloorId,
                decoration: InputDecoration(
                  labelText: t('pos_floor_label'),
                  isDense: true,
                ),
                items: [
                  DropdownMenuItem(
                    value: null,
                    child: Text(t('pos_floor_default_all')),
                  ),
                  ..._floors.map(
                    (f) => DropdownMenuItem(
                      value: (f as Map)['id'] as int,
                      child: Text(f['name'].toString()),
                    ),
                  ),
                ],
                onChanged: _onFloorChanged,
              ),
            ),
          if (_floors.isNotEmpty && _tables.isNotEmpty)
            const SizedBox(width: 8),
          if (_tables.isNotEmpty)
            Expanded(
              child: DropdownButtonFormField<int?>(
                initialValue: _selectedTableId,
                decoration: InputDecoration(
                  labelText: t('pos_table_label'),
                  isDense: true,
                ),
                items: [
                  DropdownMenuItem(value: null, child: Text(t('pos_no_table'))),
                  ..._tables.map(
                    (tb) => DropdownMenuItem(
                      value: (tb as Map)['id'] as int,
                      child: Text(tb['name'].toString()),
                    ),
                  ),
                ],
                onChanged: (v) => setState(() => _selectedTableId = v),
              ),
            ),
        ],
      ),
    );
  }

  Widget _catChip(int? id, String label) {
    final selected = _selectedCategory == id;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      child: ChoiceChip(
        label: Text(label),
        selected: selected,
        onSelected: (_) => setState(() => _selectedCategory = id),
      ),
    );
  }

  Widget _mobileCartBar(String Function(String) t) {
    return Material(
      elevation: 8,
      color: AppTheme.brand,
      child: InkWell(
        onTap: () => _openCartSheet(t),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          child: Row(
            children: [
              CircleAvatar(
                radius: 13,
                backgroundColor: Colors.white,
                child: Text(
                  '$_cartCount',
                  style: TextStyle(
                    color: AppTheme.brand,
                    fontWeight: FontWeight.bold,
                    fontSize: 12,
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  t('pos_view_cart'),
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
              Text(
                Formatters.currency(_total),
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(width: 8),
              const Icon(Icons.keyboard_arrow_up, color: Colors.white),
            ],
          ),
        ),
      ),
    );
  }

  void _openCartSheet(String Function(String) t) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (ctx) => DraggableScrollableSheet(
        expand: false,
        initialChildSize: 0.6,
        minChildSize: 0.4,
        maxChildSize: 0.9,
        builder: (ctx2, scrollController) => StatefulBuilder(
          builder: (ctx3, setModalState) => _cartPanel(
            t: t,
            scrollController: scrollController,
            onChanged: () => setModalState(() {}),
          ),
        ),
      ),
    );
  }

  Widget _cartPanel({
    required String Function(String) t,
    ScrollController? scrollController,
    VoidCallback? onChanged,
  }) {
    void notify() {
      if (onChanged != null) onChanged();
    }

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(12),
          child: Text(
            t('pos_cart_title'),
            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
        ),
        Expanded(
          child: _cart.isEmpty
              ? Center(child: Text(t('pos_cart_empty')))
              : ListView(
                  controller: scrollController,
                  children: _cart.values.map((l) {
                    final id = l.item['id'] as int;
                    return ListTile(
                      title: Text(
                        l.item['name'].toString(),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      subtitle: Text(Formatters.currency(l.item['price'])),
                      trailing: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          IconButton(
                            icon: const Icon(Icons.remove_circle_outline),
                            onPressed: () {
                              _decQty(id);
                              notify();
                            },
                          ),
                          Text(
                            '${l.qty}',
                            style: const TextStyle(fontWeight: FontWeight.bold),
                          ),
                          IconButton(
                            icon: Icon(
                              Icons.add_circle_outline,
                              color: AppTheme.brand,
                            ),
                            onPressed: () {
                              _incQty(id);
                              notify();
                            },
                          ),
                        ],
                      ),
                    );
                  }).toList(),
                ),
        ),
        SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      t('pos_total'),
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                    Text(
                      Formatters.currency(_total),
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: _submitting || _cart.isEmpty
                        ? null
                        : () async {
                            await _checkout();
                            notify();
                          },
                    child: _submitting
                        ? const CircularProgressIndicator(color: Colors.white)
                        : Text(t('pos_complete_sale')),
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
