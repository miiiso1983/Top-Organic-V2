import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../core/data/cached_repository.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/barcode_scanner.dart';
import '../../shared/widgets/offline_banner.dart';

class BarcodeScanScreen extends StatefulWidget {
  const BarcodeScanScreen({super.key});

  @override
  State<BarcodeScanScreen> createState() => _BarcodeScanScreenState();
}

class _BarcodeScanScreenState extends State<BarcodeScanScreen> {
  final _codeCtrl = TextEditingController();
  Map<String, dynamic>? _item;
  bool _loading = false;
  String? _error;
  String? _offlineNotice;

  Future<void> _lookup(String code) async {
    final lang = context.read<LocaleProvider>().locale.languageCode;
    setState(() {
      _loading = true;
      _error = null;
      _item = null;
      _offlineNotice = null;
    });
    try {
      final repo = context.read<CachedRepository>();
      final res = await repo.getJson(
        '/inventory/barcode',
        'inventory_barcode_$code',
        query: {'code': code},
      );
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      if (body['ok'] == true) {
        setState(() {
          _item = body['data'] as Map<String, dynamic>?;
          _offlineNotice = res.fromCache ? res.errorMessage : null;
        });
      } else {
        setState(
          () => _error =
              body['error']?.toString() ??
              AppStrings.t('inventory_barcode_scan_item_not_found', lang),
        );
      }
    } catch (e) {
      if (!mounted) return;
      setState(
        () =>
            _error = AppStrings.t('inventory_barcode_scan_search_error', lang),
      );
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _go(String path) => context.push(path, extra: _item);

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    return Scaffold(
      appBar: AppBar(title: Text(t('inventory_barcode_scan_title'))),
      body: Column(
        children: [
          OfflineBanner(message: _offlineNotice),
          Padding(
            padding: const EdgeInsets.all(12),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _codeCtrl,
                    decoration: InputDecoration(
                      labelText: t('inventory_barcode_scan_manual_hint'),
                    ),
                    keyboardType: TextInputType.text,
                  ),
                ),
                BarcodeScanButton(
                  onCode: (code) {
                    _codeCtrl.text = code;
                    _lookup(code);
                  },
                ),
                IconButton(
                  icon: const Icon(Icons.search),
                  onPressed: () => _lookup(_codeCtrl.text.trim()),
                ),
              ],
            ),
          ),
          if (_loading) const LinearProgressIndicator(),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.all(12),
              child: Text(
                _error!,
                style: const TextStyle(color: Colors.red),
                textAlign: TextAlign.center,
              ),
            ),
          Expanded(
            child: _item == null
                ? Center(child: Text(t('inventory_barcode_scan_empty_hint')))
                : _buildDetails(t),
          ),
        ],
      ),
    );
  }

  Widget _buildDetails(String Function(String) t) {
    final it = _item!;
    return ListView(
      padding: const EdgeInsets.all(12),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  it['name']?.toString() ?? '',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 8),
                Text(
                  '${t('inventory_barcode_scan_type_label')}${it['category_type'] ?? ''}',
                ),
                Text(
                  '${t('inventory_barcode_scan_unit_label')}${it['unit_name'] ?? ''}',
                ),
                Text(
                  '${t('inventory_barcode_scan_stock_label')}${it['warehouse_balance'] ?? 0}',
                ),
                Text(
                  '${t('inventory_barcode_scan_total_label')}${it['total_balance'] ?? 0}',
                ),
                Text(
                  '${t('inventory_barcode_scan_barcode_label')}${it['barcode'] ?? ''}',
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          alignment: WrapAlignment.center,
          children: [
            ElevatedButton.icon(
              onPressed: () => _go('/inventory/entry'),
              icon: const Icon(Icons.arrow_downward),
              label: Text(t('inventory_barcode_scan_action_in')),
            ),
            ElevatedButton.icon(
              onPressed: () => _go('/inventory/exit'),
              icon: const Icon(Icons.arrow_upward),
              label: Text(t('inventory_barcode_scan_action_out')),
            ),
            ElevatedButton.icon(
              onPressed: () => _go('/inventory/transfer'),
              icon: const Icon(Icons.swap_horiz),
              label: Text(t('inventory_barcode_scan_action_transfer')),
            ),
            ElevatedButton.icon(
              onPressed: () => _go('/custody/issue'),
              icon: const Icon(Icons.handshake),
              label: Text(t('inventory_barcode_scan_action_custody')),
            ),
          ],
        ),
      ],
    );
  }

  @override
  void dispose() {
    _codeCtrl.dispose();
    super.dispose();
  }
}
