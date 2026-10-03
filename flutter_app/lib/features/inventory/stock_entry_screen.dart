import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import 'stock_movement_screen.dart';

class StockEntryScreen extends StatelessWidget {
  const StockEntryScreen({super.key, this.item});
  final Map<String, dynamic>? item;

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    return StockMovementScreen(
      movementType: 'in',
      title: AppStrings.t('inventory_stock_entry_title', lang),
      item: item,
    );
  }
}
