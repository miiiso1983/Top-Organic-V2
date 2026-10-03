import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/data/cached_repository.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/offline_banner.dart';

class AssetsScreen extends StatefulWidget {
  const AssetsScreen({super.key});

  @override
  State<AssetsScreen> createState() => _AssetsScreenState();
}

class _AssetsScreenState extends State<AssetsScreen> {
  bool _loading = true;
  String? _error;
  String? _offlineNotice;
  List<dynamic> _custody = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final repo = context.read<CachedRepository>();
      final res = await repo.getJson('/custody', 'custody');
      if (!mounted) return;
      setState(() {
        _custody = ((res.data as Map)['data'] as List?) ?? [];
        _offlineNotice = res.fromCache ? res.errorMessage : null;
      });
    } catch (e) {
      if (!mounted) return;
      final lang = context.read<LocaleProvider>().locale.languageCode;
      setState(() => _error = AppStrings.t('assets_load_error', lang));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _returnCustody(int id) async {
    final repo = context.read<CachedRepository>();
    final lang = mounted
        ? context.read<LocaleProvider>().locale.languageCode
        : 'ar';
    final res = await repo.postOrQueue('/custody/$id/return', {
      'status': 'returned',
    }, entity: 'custody');
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            res.errorMessage ?? AppStrings.t('assets_returned_default', lang),
          ),
        ),
      );
    }
    _load();
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    return RefreshIndicator(
      onRefresh: _load,
      child: Column(
        children: [
          OfflineBanner(message: _offlineNotice),
          Expanded(
            child: AsyncStateView(
              loading: _loading,
              error: _error,
              onRetry: _load,
              child: _custody.isEmpty
                  ? Center(child: Text(t('assets_empty')))
                  : ListView.separated(
                      itemCount: _custody.length,
                      separatorBuilder: (a, b) => const Divider(height: 1),
                      itemBuilder: (context, i) {
                        final c = _custody[i] as Map;
                        final active = c['status'] == 'active';
                        return ListTile(
                          leading: Icon(
                            Icons.inventory,
                            color: active ? Colors.orange : Colors.green,
                          ),
                          title: Text('${c['item_name']} × ${c['qty']}'),
                          subtitle: Text(
                            '${t('assets_held_by_label')}${c['employee_name'] ?? c['department'] ?? '-'}',
                          ),
                          trailing: active
                              ? TextButton(
                                  onPressed: () =>
                                      _returnCustody(c['id'] as int),
                                  child: Text(t('assets_return_button')),
                                )
                              : Chip(label: Text(c['status'].toString())),
                        );
                      },
                    ),
            ),
          ),
        ],
      ),
    );
  }
}
