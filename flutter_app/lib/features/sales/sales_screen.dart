import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import 'orders_tab.dart';
import 'pos_tab.dart';

class SalesScreen extends StatelessWidget {
  const SalesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    return DefaultTabController(
      length: 2,
      child: Column(
        children: [
          Material(
            color: Colors.white,
            child: TabBar(
              tabs: [
                Tab(
                  text: t('sales_tab_orders'),
                  icon: const Icon(Icons.receipt_long),
                ),
                Tab(
                  text: t('sales_tab_pos'),
                  icon: const Icon(Icons.point_of_sale),
                ),
              ],
            ),
          ),
          const Expanded(child: TabBarView(children: [OrdersTab(), PosTab()])),
        ],
      ),
    );
  }
}
