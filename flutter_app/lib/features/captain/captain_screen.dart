import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../auth/auth_provider.dart';
import 'captain_order_screen.dart';
import 'captain_tables_screen.dart';

/// شاشة مخصّصة لحساب الكابتن: تُعرض بمفردها بدون أي تنقل آخر.
/// تعرض الآن خطوة اختيار الطاولة ثم شاشة الطلب مطابقة للويب.
class CaptainScreen extends StatefulWidget {
  const CaptainScreen({super.key});

  @override
  State<CaptainScreen> createState() => _CaptainScreenState();
}

class _CaptainScreenState extends State<CaptainScreen> {
  Map<String, dynamic>? _selectedTable;

  void _selectTable(Map<String, dynamic> table) =>
      setState(() => _selectedTable = table);
  void _backToTables() => setState(() => _selectedTable = null);

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    return Scaffold(
      appBar: AppBar(
        title: Text(
          _selectedTable == null
              ? t('captain_screen_title')
              : '${t('captain_order_title_prefix')}${(_selectedTable!['name'] ?? '')}',
        ),
        automaticallyImplyLeading: false,
        actions: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12),
            child: Center(
              child: Text(
                auth.user?['name']?.toString() ?? '',
                style: const TextStyle(fontSize: 14),
              ),
            ),
          ),
          IconButton(
            tooltip: t('captain_logout_tooltip'),
            icon: const Icon(Icons.logout),
            onPressed: () => auth.logout(),
          ),
        ],
      ),
      body: AnimatedSwitcher(
        duration: const Duration(milliseconds: 250),
        child: _selectedTable == null
            ? CaptainTablesScreen(
                key: const ValueKey('tables'),
                onSelect: _selectTable,
              )
            : CaptainOrderScreen(
                key: ValueKey('order-${_selectedTable!['id']}'),
                table: _selectedTable!,
                onBack: _backToTables,
              ),
      ),
    );
  }
}
