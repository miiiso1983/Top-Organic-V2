import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/utils/formatters.dart';
import 'captain_order_controller.dart';

class CartPanel extends StatelessWidget {
  const CartPanel({
    super.key,
    required this.controller,
    required this.notesController,
    required this.onBack,
    required this.onSubmit,
  });

  final CaptainOrderController controller;
  final TextEditingController notesController;
  final VoidCallback onBack;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    final tableName = controller.table['name']?.toString() ?? '';
    return Container(
      color: Colors.white,
      child: Column(
        children: [
          Container(
            padding: const EdgeInsets.all(14),
            decoration: const BoxDecoration(
              border: Border(bottom: BorderSide(color: Color(0xFFE5E7EB))),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  '${t('captain_cart_order_table_prefix')}$tableName',
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                TextButton.icon(
                  onPressed: onBack,
                  icon: const Icon(Icons.arrow_back),
                  label: Text(t('captain_cart_back')),
                ),
              ],
            ),
          ),
          Expanded(
            child: ListView(
              padding: const EdgeInsets.all(12),
              children: [
                if (controller.currentOrder != null)
                  _currentOrderBox(controller, t),
                Text(
                  t('captain_cart_title'),
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 8),
                if (controller.cart.isEmpty)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    child: Center(child: Text(t('captain_cart_empty'))),
                  )
                else
                  ...controller.cart.entries.map(
                    (e) => _cartLine(e.value, e.key),
                  ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      t('captain_cart_total_label'),
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    Text(
                      '${Formatters.number(controller.cartTotal)} IQD',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                        color: AppTheme.brandDark,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                TextField(
                  controller: notesController,
                  decoration: InputDecoration(
                    labelText: t('captain_cart_notes_label'),
                    border: const OutlineInputBorder(),
                    contentPadding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 8,
                    ),
                  ),
                  maxLines: 2,
                ),
                const SizedBox(height: 8),
                ElevatedButton(
                  onPressed: controller.cart.isEmpty || controller.submitting
                      ? null
                      : onSubmit,
                  child: controller.submitting
                      ? const SizedBox(
                          height: 18,
                          width: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : Text(
                          controller.currentOrder != null
                              ? t('captain_cart_add_to_order')
                              : t('captain_cart_send_order'),
                        ),
                ),
                const SizedBox(height: 4),
                OutlinedButton(onPressed: onBack, child: Text(t('cancel'))),
                if (controller.submitMessage != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Text(
                      controller.submitMessage!,
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: controller.submitFailed
                            ? Colors.red
                            : AppTheme.brandDark,
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _currentOrderBox(CaptainOrderController c, String Function(String) t) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: const Color(0xFFF0F9FF),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            '${t('captain_cart_open_order_prefix')}${c.currentOrder!['order_no']}',
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w700,
              color: Color(0xFF0369A1),
            ),
          ),
          const Divider(color: Color(0xFFBAE6FD)),
          ...c.currentItems.map(
            (it) => Padding(
              padding: const EdgeInsets.symmetric(vertical: 2),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    '${it['name']} × ${it['qty']}',
                    style: const TextStyle(fontSize: 13),
                  ),
                  Text(
                    '${Formatters.number(it['total'])} IQD',
                    style: const TextStyle(fontSize: 13),
                  ),
                ],
              ),
            ),
          ),
          const Divider(color: Color(0xFFBAE6FD)),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                t('captain_cart_current_total'),
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              Text(
                '${Formatters.number(c.currentOrder!['total'])} IQD',
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _cartLine(CartLine line, int id) {
    final price = double.tryParse(line.item['price']?.toString() ?? '0') ?? 0;
    return ListTile(
      contentPadding: EdgeInsets.zero,
      dense: true,
      title: Text(
        line.item['name']?.toString() ?? '',
        style: const TextStyle(fontSize: 14),
      ),
      subtitle: Text(
        '${Formatters.number(price)} IQD',
        style: const TextStyle(fontSize: 12),
      ),
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
            icon: const Icon(Icons.remove_circle_outline),
            onPressed: () => controller.decItem(id),
            iconSize: 20,
          ),
          Text(
            '${line.qty}',
            style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
          ),
          IconButton(
            icon: const Icon(Icons.add_circle_outline),
            onPressed: () => controller.incItem(id),
            iconSize: 20,
          ),
        ],
      ),
    );
  }
}
