import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/config.dart';
import '../../core/data/cached_repository.dart';
import '../../core/theme.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/offline_banner.dart';
import 'captain_order_cart.dart';
import 'captain_order_controller.dart';
import 'captain_order_widgets.dart';

class CaptainOrderScreen extends StatefulWidget {
  const CaptainOrderScreen({
    super.key,
    required this.table,
    required this.onBack,
  });
  final Map<String, dynamic> table;
  final VoidCallback onBack;

  @override
  State<CaptainOrderScreen> createState() => _CaptainOrderScreenState();
}

class _CaptainOrderScreenState extends State<CaptainOrderScreen> {
  final _notes = TextEditingController();

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (_) => CaptainOrderController(
        table: widget.table,
        repo: context.read<CachedRepository>(),
        lang: context.read<LocaleProvider>().locale.languageCode,
      )..load(),
      child: Builder(
        builder: (context) {
          final c = context.watch<CaptainOrderController>();
          final lang = context.watch<LocaleProvider>().locale.languageCode;
          c.lang = lang;
          String t(String key) => AppStrings.t(key, lang);
          return Scaffold(
            appBar: AppBar(
              title: Text(
                '${t('captain_order_title_prefix')}${widget.table['name'] ?? ''}',
              ),
              leading: IconButton(
                icon: const Icon(Icons.arrow_back),
                onPressed: widget.onBack,
              ),
            ),
            body: Column(
              children: [
                OfflineBanner(message: c.offlineNotice),
                Expanded(
                  child: c.loading
                      ? const Center(child: CircularProgressIndicator())
                      : c.error != null
                      ? Center(
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              const Icon(
                                Icons.error_outline,
                                color: Colors.red,
                                size: 40,
                              ),
                              const SizedBox(height: 8),
                              Text(c.error!, textAlign: TextAlign.center),
                              TextButton(
                                onPressed: () => c.load(),
                                child: Text(t('retry')),
                              ),
                            ],
                          ),
                        )
                      : _body(context, c, t),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _body(
    BuildContext context,
    CaptainOrderController c,
    String Function(String) t,
  ) {
    final isTablet =
        MediaQuery.sizeOf(context).width >= AppConfig.tabletBreakpoint;
    return isTablet ? _tabletLayout(context, c) : _mobileLayout(context, c, t);
  }

  Widget _tabletLayout(BuildContext context, CaptainOrderController c) {
    return Row(
      children: [
        Expanded(flex: 2, child: buildCategoriesColumn(context, c)),
        const VerticalDivider(width: 1),
        Expanded(flex: 5, child: buildItemsGrid(context, c)),
        const VerticalDivider(width: 1),
        Expanded(
          flex: 3,
          child: CartPanel(
            controller: c,
            notesController: _notes,
            onBack: widget.onBack,
            onSubmit: () => _submit(c),
          ),
        ),
      ],
    );
  }

  Widget _mobileLayout(
    BuildContext context,
    CaptainOrderController c,
    String Function(String) t,
  ) {
    return Column(
      children: [
        SizedBox(
          height: 48,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            children: [
              _mobileCatChip(c, null, t('captain_category_all')),
              ...c.categories.map(
                (cat) => _mobileCatChip(
                  c,
                  (cat as Map)['id'] as int,
                  cat['name'].toString(),
                ),
              ),
            ],
          ),
        ),
        Expanded(child: buildItemsGrid(context, c)),
        SizedBox(
          height: 320,
          child: CartPanel(
            controller: c,
            notesController: _notes,
            onBack: widget.onBack,
            onSubmit: () => _submit(c),
          ),
        ),
      ],
    );
  }

  Widget _mobileCatChip(CaptainOrderController c, int? id, String label) {
    final active = c.selectedCategoryId == id;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      child: FilterChip(
        selected: active,
        label: Text(label),
        selectedColor: AppTheme.sidebar,
        labelStyle: TextStyle(color: active ? Colors.white : Colors.black),
        onSelected: (_) => c.selectCategory(id),
      ),
    );
  }

  Future<void> _submit(CaptainOrderController c) async {
    await c.submit(_notes.text);
  }
}
