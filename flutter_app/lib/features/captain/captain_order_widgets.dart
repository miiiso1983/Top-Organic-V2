import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:provider/provider.dart';
import '../../core/config.dart';
import '../../core/theme.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/utils/formatters.dart';
import 'captain_order_controller.dart';

Widget buildCategoriesColumn(BuildContext context, CaptainOrderController c) {
  final lang = context.watch<LocaleProvider>().locale.languageCode;
  String t(String key) => AppStrings.t(key, lang);
  return Container(
    color: Colors.white,
    child: ListView(
      padding: const EdgeInsets.all(12),
      children: [
        _catButton(c, null, t('captain_category_all')),
        ...c.categories.map(
          (cat) =>
              _catButton(c, (cat as Map)['id'] as int, cat['name'].toString()),
        ),
      ],
    ),
  );
}

Widget _catButton(CaptainOrderController c, int? id, String label) {
  final active = c.selectedCategoryId == id;
  return Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Material(
      color: active ? AppTheme.sidebar : Colors.white,
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        onTap: () => c.selectCategory(id),
        borderRadius: BorderRadius.circular(10),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 14),
          decoration: BoxDecoration(
            border: Border.all(color: const Color(0xFFE5E7EB)),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Text(
            label,
            style: TextStyle(
              color: active ? Colors.white : const Color(0xFF1F2937),
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ),
    ),
  );
}

Widget buildItemsGrid(BuildContext context, CaptainOrderController c) {
  final lang = context.watch<LocaleProvider>().locale.languageCode;
  final items = c.visibleItems;
  return LayoutBuilder(
    builder: (context, constraints) {
      final count = (constraints.maxWidth / 150).floor().clamp(2, 5);
      return items.isEmpty
          ? Center(
              child: Text(AppStrings.t('captain_items_empty_category', lang)),
            )
          : GridView.builder(
              padding: const EdgeInsets.all(10),
              gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: count,
                childAspectRatio: 0.95,
                crossAxisSpacing: 10,
                mainAxisSpacing: 10,
              ),
              itemCount: items.length,
              itemBuilder: (context, i) => _ItemCard(c, items[i] as Map),
            );
    },
  );
}

class _ItemCard extends StatelessWidget {
  const _ItemCard(this.c, this.item);
  final CaptainOrderController c;
  final Map item;

  @override
  Widget build(BuildContext context) {
    final id = item['id'] as int;
    final qty = c.cartQty(id);
    final image = item['image'] as String?;
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => c.addItem(item),
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
                    Expanded(
                      child: Text(
                        item['name']?.toString() ?? '',
                        textAlign: TextAlign.center,
                        style: const TextStyle(fontSize: 14),
                      ),
                    ),
                    Text(
                      '${Formatters.number(item['price'])} IQD',
                      style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w800,
                        color: AppTheme.brandDark,
                      ),
                    ),
                    if (qty > 0)
                      Container(
                        margin: const EdgeInsets.only(top: 4),
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 2,
                        ),
                        decoration: BoxDecoration(
                          color: AppTheme.brand,
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          '× $qty',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 12,
                          ),
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
}
