import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/data/cached_repository.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/offline_banner.dart';

class ManufactureScreen extends StatefulWidget {
  const ManufactureScreen({super.key});

  @override
  State<ManufactureScreen> createState() => _ManufactureScreenState();
}

class _ManufactureScreenState extends State<ManufactureScreen> {
  final _qtyCtrl = TextEditingController();
  int? _recipeId;
  int? _fromWarehouse;
  int? _toWarehouse;
  Future<Map<String, List<Map<String, dynamic>>>>? _optionsFuture;
  bool _submitting = false;
  String? _error;
  String? _offlineNotice;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _optionsFuture ??= _loadOptions();
  }

  Future<Map<String, List<Map<String, dynamic>>>> _loadOptions() async {
    final repo = context.read<CachedRepository>();
    final recipes = await repo.getJson('/recipes', 'recipes');
    final warehouses = await repo.getJson('/warehouses', 'warehouses');
    return {
      'recipes': ((recipes.data['data'] as List?) ?? [])
          .map((e) => e as Map<String, dynamic>)
          .toList(),
      'warehouses': ((warehouses.data['data'] as List?) ?? [])
          .map((e) => e as Map<String, dynamic>)
          .toList(),
    };
  }

  Future<void> _submit() async {
    if (_recipeId == null ||
        _fromWarehouse == null ||
        _toWarehouse == null ||
        _qtyCtrl.text.trim().isEmpty)
      return;
    final lang = context.read<LocaleProvider>().locale.languageCode;
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final repo = context.read<CachedRepository>();
      final res = await repo.postOrQueue('/inventory/manufacture', {
        'recipe_id': _recipeId,
        'qty': double.tryParse(_qtyCtrl.text) ?? 0,
        'from_warehouse_id': _fromWarehouse,
        'to_warehouse_id': _toWarehouse,
      }, entity: 'inventory_manufacture');
      if (!mounted) return;
      if (res.data?['ok'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(AppStrings.t('inventory_manufacture_success', lang)),
          ),
        );
        Navigator.of(context).pop();
      } else {
        setState(
          () => _error =
              res.errorMessage ??
              res.data?['error']?.toString() ??
              AppStrings.t('inventory_manufacture_failed', lang),
        );
      }
    } catch (e) {
      if (!mounted) return;
      setState(
        () => _error = AppStrings.t('inventory_manufacture_submit_error', lang),
      );
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    return Scaffold(
      appBar: AppBar(title: Text(t('inventory_manufacture_title'))),
      body: Column(
        children: [
          OfflineBanner(message: _offlineNotice),
          Expanded(
            child: FutureBuilder<Map<String, List<Map<String, dynamic>>>>(
              future: _optionsFuture,
              builder: (context, snap) {
                if (snap.connectionState == ConnectionState.waiting)
                  return const Center(child: CircularProgressIndicator());
                final recipes = snap.data?['recipes'] ?? [];
                final warehouses = snap.data?['warehouses'] ?? [];
                return ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    DropdownButtonFormField<int>(
                      initialValue: _recipeId,
                      decoration: InputDecoration(
                        labelText: t('inventory_manufacture_recipe_label'),
                      ),
                      items: recipes
                          .map(
                            (r) => DropdownMenuItem<int>(
                              value: r['id'] as int,
                              child: Text(r['name'].toString()),
                            ),
                          )
                          .toList(),
                      onChanged: (v) => setState(() => _recipeId = v),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _qtyCtrl,
                      decoration: InputDecoration(
                        labelText: t('inventory_manufacture_qty_label'),
                      ),
                      keyboardType: const TextInputType.numberWithOptions(
                        decimal: true,
                      ),
                    ),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<int>(
                      initialValue: _fromWarehouse,
                      decoration: InputDecoration(
                        labelText: t(
                          'inventory_manufacture_from_warehouse_label',
                        ),
                      ),
                      items: warehouses
                          .map(
                            (w) => DropdownMenuItem<int>(
                              value: w['id'] as int,
                              child: Text(w['name'].toString()),
                            ),
                          )
                          .toList(),
                      onChanged: (v) => setState(() => _fromWarehouse = v),
                    ),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<int>(
                      initialValue: _toWarehouse,
                      decoration: InputDecoration(
                        labelText: t(
                          'inventory_manufacture_to_warehouse_label',
                        ),
                      ),
                      items: warehouses
                          .map(
                            (w) => DropdownMenuItem<int>(
                              value: w['id'] as int,
                              child: Text(w['name'].toString()),
                            ),
                          )
                          .toList(),
                      onChanged: (v) => setState(() => _toWarehouse = v),
                    ),
                    const SizedBox(height: 16),
                    if (_error != null)
                      Text(_error!, style: const TextStyle(color: Colors.red)),
                    ElevatedButton(
                      onPressed: _submitting ? null : _submit,
                      child: _submitting
                          ? const CircularProgressIndicator()
                          : Text(t('inventory_manufacture_submit_button')),
                    ),
                  ],
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  @override
  void dispose() {
    _qtyCtrl.dispose();
    super.dispose();
  }
}
