import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/data/cached_repository.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/utils/timezone_helper.dart';
import '../../shared/widgets/offline_banner.dart';

class ReservationsScreen extends StatefulWidget {
  const ReservationsScreen({super.key});

  @override
  State<ReservationsScreen> createState() => _ReservationsScreenState();
}

class _ReservationsScreenState extends State<ReservationsScreen> {
  bool _loading = true;
  String? _error;
  String? _offlineNotice;
  List<dynamic> _reservations = [];

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
      final res = await repo.getJson('/reservations', 'reservations');
      if (!mounted) return;
      setState(() {
        _reservations = ((res.data as Map)['data'] as List?) ?? [];
        _offlineNotice = res.fromCache ? res.errorMessage : null;
      });
    } catch (e) {
      if (!mounted) return;
      final lang = context.read<LocaleProvider>().locale.languageCode;
      setState(() => _error = AppStrings.t('reservations_load_error', lang));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _openForm() {
    final lang = context.read<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    final nameCtrl = TextEditingController();
    final phoneCtrl = TextEditingController();
    final guestsCtrl = TextEditingController(text: '2');
    final dateCtrl = TextEditingController(
      text: BaghdadTime.formatDateTime(
        BaghdadTime.now().add(const Duration(hours: 2)),
      ),
    );
    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        title: Text(t('reservations_new_title')),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: nameCtrl,
              decoration: InputDecoration(
                labelText: t('reservations_name_label'),
              ),
            ),
            TextField(
              controller: phoneCtrl,
              decoration: InputDecoration(
                labelText: t('reservations_phone_label'),
              ),
            ),
            TextField(
              controller: guestsCtrl,
              decoration: InputDecoration(
                labelText: t('reservations_guests_label'),
              ),
              keyboardType: TextInputType.number,
            ),
            TextField(
              controller: dateCtrl,
              decoration: InputDecoration(
                labelText: t('reservations_datetime_label'),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text(t('cancel')),
          ),
          ElevatedButton(
            onPressed: () async {
              Navigator.pop(context);
              final repo = context.read<CachedRepository>();
              final res = await repo.postOrQueue('/reservations', {
                'customer_name': nameCtrl.text,
                'customer_phone': phoneCtrl.text,
                'guests': int.tryParse(guestsCtrl.text) ?? 2,
                'reserved_at': dateCtrl.text,
              }, entity: 'reservations');
              if (mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(
                      res.errorMessage ?? t('reservations_create_success'),
                    ),
                  ),
                );
              }
              _load();
            },
            child: Text(t('save')),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    return Scaffold(
      floatingActionButton: FloatingActionButton(
        onPressed: _openForm,
        child: const Icon(Icons.add),
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: Column(
          children: [
            OfflineBanner(message: _offlineNotice),
            Expanded(
              child: AsyncStateView(
                loading: _loading,
                error: _error,
                onRetry: _load,
                child: _reservations.isEmpty
                    ? Center(child: Text(t('reservations_empty')))
                    : ListView.separated(
                        itemCount: _reservations.length,
                        separatorBuilder: (a, b) => const Divider(height: 1),
                        itemBuilder: (context, i) {
                          final r = _reservations[i] as Map;
                          return ListTile(
                            leading: const Icon(Icons.event_seat),
                            title: Text(
                              '${r['customer_name']} — ${r['guests']}${t('reservations_guests_suffix')}',
                            ),
                            subtitle: Text(
                              '${r['reserved_at']} • ${r['table_name'] ?? t('reservations_no_table')}',
                            ),
                            trailing: Chip(label: Text(r['status'].toString())),
                          );
                        },
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
