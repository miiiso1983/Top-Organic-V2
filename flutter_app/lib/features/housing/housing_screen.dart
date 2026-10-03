import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'package:provider/provider.dart';
import '../../core/network/api_client.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';

/// إدارة سكن موظفي قسم الحراسة (متاحة فقط لهذا القسم - الخادم يتحقق أيضاً)
class HousingScreen extends StatefulWidget {
  const HousingScreen({super.key});

  @override
  State<HousingScreen> createState() => _HousingScreenState();
}

class _HousingScreenState extends State<HousingScreen> {
  bool _loading = true;
  String? _error;
  List<dynamic> _employees = [];
  String _search = '';

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
      final api = context.read<ApiClient>();
      final res = await api.get('/housing/employees');
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      setState(() {
        _employees = (body['data'] as List?) ?? [];
        _loading = false;
      });
    } on DioException catch (e) {
      if (!mounted) return;
      final data = e.response?.data;
      final message = (data is Map ? data['error']?.toString() : null);
      setState(() {
        _error =
            message ??
            AppStrings.t(
              'housing_load_error',
              context.read<LocaleProvider>().locale.languageCode,
            );
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = AppStrings.t(
          'housing_load_error',
          context.read<LocaleProvider>().locale.languageCode,
        );
        _loading = false;
      });
    }
  }

  Future<void> _openEditDialog(Map<String, dynamic> emp, String lang) async {
    final updated = await showDialog<bool>(
      context: context,
      builder: (_) => _HousingEditDialog(employee: emp, lang: lang),
    );
    if (updated == true) _load();
  }

  @override
  Widget build(BuildContext context) {
    final localeProvider = context.watch<LocaleProvider>();
    final lang = localeProvider.locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);

    final query = _search.trim().toLowerCase();
    final filtered = query.isEmpty
        ? _employees
        : _employees.where((item) {
            final e = item as Map<String, dynamic>;
            final haystack = [
              e['full_name']?.toString() ?? '',
              e['emp_code']?.toString() ?? '',
              e['department_name']?.toString() ?? '',
              e['site_name']?.toString() ?? '',
            ].join(' ').toLowerCase();
            return haystack.contains(query);
          }).toList();

    return Directionality(
      textDirection: localeProvider.textDirection,
      child: Scaffold(
        appBar: AppBar(title: Text(t('housing_title'))),
        body: _loading
            ? const Center(child: CircularProgressIndicator())
            : _error != null
            ? Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(_error!, textAlign: TextAlign.center),
                    const SizedBox(height: 12),
                    OutlinedButton(onPressed: _load, child: Text(t('retry'))),
                  ],
                ),
              )
            : Column(
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(12, 12, 12, 0),
                    child: TextField(
                      decoration: InputDecoration(
                        prefixIcon: const Icon(Icons.search),
                        hintText: t('housing_search_hint'),
                        isDense: true,
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(10),
                        ),
                      ),
                      onChanged: (v) => setState(() => _search = v),
                    ),
                  ),
                  Expanded(
                    child: RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.builder(
                        padding: const EdgeInsets.all(12),
                        itemCount: filtered.length,
                        itemBuilder: (context, i) {
                          final e = filtered[i] as Map<String, dynamic>;
                          final hasAssignment = e['site_name'] != null;
                          final subtitle = [
                            if (e['department_name'] != null)
                              e['department_name'].toString(),
                            if (hasAssignment) ...[
                              e['site_name']?.toString(),
                              if (e['apartment_number'] != null)
                                '${t('housing_apartment')} ${e['apartment_number']}',
                              if (e['room_number'] != null)
                                '${t('housing_room')} ${e['room_number']}',
                            ] else
                              t('housing_no_assignment'),
                          ].whereType<String>().join(' • ');
                          return Card(
                            child: ListTile(
                              title: Text(e['full_name']?.toString() ?? ''),
                              subtitle: Text(subtitle),
                              trailing: IconButton(
                                icon: const Icon(Icons.edit_outlined),
                                tooltip: t('housing_edit'),
                                onPressed: () => _openEditDialog(e, lang),
                              ),
                            ),
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

/// حقل نصي يفتح ذكية بحث عند الضغط (بديل عن DropdownButtonFormField العادي)
class _SearchablePickerField extends StatelessWidget {
  const _SearchablePickerField({
    required this.label,
    required this.valueText,
    required this.enabled,
    required this.onTap,
    this.helperText,
  });
  final String label;
  final String? valueText;
  final bool enabled;
  final String? helperText;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: enabled ? onTap : null,
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: label,
          helperText: helperText,
          suffixIcon: const Icon(Icons.arrow_drop_down),
          enabled: enabled,
        ),
        child: Text(
          (valueText == null || valueText!.isEmpty) ? '—' : valueText!,
          overflow: TextOverflow.ellipsis,
        ),
      ),
    );
  }
}

class _HousingEditDialog extends StatefulWidget {
  const _HousingEditDialog({required this.employee, required this.lang});
  final Map<String, dynamic> employee;
  final String lang;

  @override
  State<_HousingEditDialog> createState() => _HousingEditDialogState();
}

class _HousingEditDialogState extends State<_HousingEditDialog> {
  List<dynamic> _sites = [];
  List<dynamic> _apartments = [];
  List<dynamic> _rooms = [];
  int? _siteId;
  int? _apartmentId;
  int? _roomId;
  bool _loadingSites = true;
  bool _loadingApartments = false;
  bool _loadingRooms = false;
  bool _submitting = false;
  String? _error;

  String t(String key) => AppStrings.t(key, widget.lang);

  /// نافذة بحث ذكية لاختيار عنصر من قائمة طويلة (موقع/شقة/غرفة)، بدلاً من DropdownButtonFormField غير القابل للبحث
  Future<int?> _pickSearchable({
    required String title,
    required List<dynamic> items,
    required String Function(Map<String, dynamic>) labelOf,
    int? selectedId,
  }) {
    return showModalBottomSheet<int>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) {
        var query = '';
        return StatefulBuilder(
          builder: (ctx, setSheetState) {
            final filtered = items.where((it) {
              final e = it as Map<String, dynamic>;
              return query.isEmpty ||
                  labelOf(e).toLowerCase().contains(query.toLowerCase());
            }).toList();
            return SafeArea(
              child: Padding(
                padding: EdgeInsets.only(
                  bottom: MediaQuery.of(ctx).viewInsets.bottom,
                ),
                child: SizedBox(
                  height: MediaQuery.of(ctx).size.height * 0.7,
                  child: Column(
                    children: [
                      Padding(
                        padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                        child: Text(
                          title,
                          style: Theme.of(ctx).textTheme.titleMedium,
                        ),
                      ),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        child: TextField(
                          autofocus: true,
                          decoration: InputDecoration(
                            prefixIcon: const Icon(Icons.search),
                            hintText: t('housing_search_hint'),
                            isDense: true,
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(10),
                            ),
                          ),
                          onChanged: (v) => setSheetState(() => query = v),
                        ),
                      ),
                      const SizedBox(height: 8),
                      Expanded(
                        child: filtered.isEmpty
                            ? Center(child: Text(t('housing_no_results')))
                            : ListView.builder(
                                itemCount: filtered.length,
                                itemBuilder: (ctx, i) {
                                  final e = filtered[i] as Map<String, dynamic>;
                                  final id = (e['id'] as num).toInt();
                                  return ListTile(
                                    title: Text(labelOf(e)),
                                    selected: id == selectedId,
                                    trailing: id == selectedId
                                        ? const Icon(
                                            Icons.check,
                                            color: Colors.green,
                                          )
                                        : null,
                                    onTap: () => Navigator.of(ctx).pop(id),
                                  );
                                },
                              ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        );
      },
    );
  }

  @override
  void initState() {
    super.initState();
    _siteId = (widget.employee['site_id'] as num?)?.toInt();
    _loadSites(
      initialApartmentId: (widget.employee['apartment_id'] as num?)?.toInt(),
      initialRoomId: (widget.employee['room_id'] as num?)?.toInt(),
    );
  }

  Future<void> _loadSites({int? initialApartmentId, int? initialRoomId}) async {
    setState(() => _loadingSites = true);
    try {
      final api = context.read<ApiClient>();
      final res = await api.get('/housing/sites');
      final body = res.data as Map<String, dynamic>;
      setState(() {
        _sites = (body['data'] as List?) ?? [];
        _loadingSites = false;
      });
      if (_siteId != null) {
        await _loadApartments(
          _siteId!,
          preserveApartmentId: initialApartmentId,
          preserveRoomId: initialRoomId,
        );
      }
    } catch (_) {
      if (mounted)
        setState(() {
          _error = t('housing_load_error');
          _loadingSites = false;
        });
    }
  }

  /// يحمّل شقق الموقع المحدّد. يُفرّغ القيمة الحالية فوراً ضمن نفس setState الذي يفرغّ القائمة
  /// (حتى لا تحمل قيمة غير موجودة ضمن items أثناء إعادة التحميل)،
  /// ثم تُستعاد فقط إذا وُجدت فعلاً ضمن النتيجة الجديدة.
  Future<void> _loadApartments(
    int siteId, {
    int? preserveApartmentId,
    int? preserveRoomId,
  }) async {
    setState(() {
      _loadingApartments = true;
      _apartments = [];
      _rooms = [];
      _apartmentId = null;
      _roomId = null;
    });
    try {
      final api = context.read<ApiClient>();
      final res = await api.get(
        '/housing/apartments',
        query: {'site_id': siteId},
      );
      final body = res.data as Map<String, dynamic>;
      final apartments = (body['data'] as List?) ?? [];
      final restoredApartmentId =
          (preserveApartmentId != null &&
              apartments.any(
                (a) => (a['id'] as num).toInt() == preserveApartmentId,
              ))
          ? preserveApartmentId
          : null;
      setState(() {
        _apartments = apartments;
        _loadingApartments = false;
        _apartmentId = restoredApartmentId;
      });
      if (restoredApartmentId != null) {
        await _loadRooms(restoredApartmentId, preserveRoomId: preserveRoomId);
      }
    } catch (_) {
      if (mounted) setState(() => _loadingApartments = false);
    }
  }

  Future<void> _loadRooms(int apartmentId, {int? preserveRoomId}) async {
    setState(() {
      _loadingRooms = true;
      _rooms = [];
      _roomId = null;
    });
    try {
      final api = context.read<ApiClient>();
      final res = await api.get(
        '/housing/rooms',
        query: {
          'apartment_id': apartmentId,
          'exclude_id': widget.employee['assignment_id'] ?? 0,
        },
      );
      final body = res.data as Map<String, dynamic>;
      final rooms = (body['data'] as List?) ?? [];
      final restoredRoomId =
          (preserveRoomId != null &&
              rooms.any((r) => (r['id'] as num).toInt() == preserveRoomId))
          ? preserveRoomId
          : null;
      setState(() {
        _rooms = rooms;
        _loadingRooms = false;
        _roomId = restoredRoomId;
      });
    } catch (_) {
      if (mounted) setState(() => _loadingRooms = false);
    }
  }

  Future<void> _submit() async {
    if (_siteId == null) return;
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final api = context.read<ApiClient>();
      final res = await api.post(
        '/housing/employees/${widget.employee['id']}',
        data: {
          'site_id': _siteId,
          'apartment_id': _apartmentId,
          'room_id': _roomId,
        },
      );
      final body = res.data as Map<String, dynamic>;
      if (body['ok'] == true) {
        if (mounted) Navigator.of(context).pop(true);
      } else if (mounted) {
        setState(() => _error = body['error']?.toString());
      }
    } on DioException catch (e) {
      final data = e.response?.data;
      final message = (data is Map ? data['error']?.toString() : null);
      if (mounted) setState(() => _error = message ?? t('housing_load_error'));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(
        '${t('housing_edit')} — ${widget.employee['full_name'] ?? ''}',
      ),
      content: SizedBox(
        width: double.maxFinite,
        child: _loadingSites
            ? const SizedBox(
                height: 100,
                child: Center(child: CircularProgressIndicator()),
              )
            : Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (_error != null) ...[
                    Text(_error!, style: const TextStyle(color: Colors.red)),
                    const SizedBox(height: 8),
                  ],
                  _SearchablePickerField(
                    label: t('housing_site'),
                    valueText: _sites
                        .cast<Map<String, dynamic>>()
                        .firstWhere(
                          (s) => (s['id'] as num).toInt() == _siteId,
                          orElse: () => const {},
                        )['name']
                        ?.toString(),
                    enabled: true,
                    onTap: () async {
                      final v = await _pickSearchable(
                        title: t('housing_site'),
                        items: _sites,
                        labelOf: (e) => e['name']?.toString() ?? '',
                        selectedId: _siteId,
                      );
                      if (v == null) return;
                      setState(() {
                        _siteId = v;
                        _apartmentId = null;
                        _roomId = null;
                        _apartments = [];
                        _rooms = [];
                      });
                      _loadApartments(v);
                    },
                  ),
                  const SizedBox(height: 12),
                  _SearchablePickerField(
                    label: t('housing_apartment'),
                    helperText: _siteId == null
                        ? t('housing_select_site_first')
                        : null,
                    valueText: _apartments
                        .cast<Map<String, dynamic>>()
                        .firstWhere(
                          (a) => (a['id'] as num).toInt() == _apartmentId,
                          orElse: () => const {},
                        )['apartment_number']
                        ?.toString(),
                    enabled: _siteId != null && !_loadingApartments,
                    onTap: () async {
                      final v = await _pickSearchable(
                        title: t('housing_apartment'),
                        items: _apartments,
                        labelOf: (e) => e['apartment_number']?.toString() ?? '',
                        selectedId: _apartmentId,
                      );
                      if (v == null) return;
                      setState(() {
                        _apartmentId = v;
                        _roomId = null;
                        _rooms = [];
                      });
                      _loadRooms(v);
                    },
                  ),
                  const SizedBox(height: 12),
                  _SearchablePickerField(
                    label: t('housing_room'),
                    helperText: _apartmentId == null
                        ? t('housing_select_apartment_first')
                        : null,
                    valueText: _rooms
                        .cast<Map<String, dynamic>>()
                        .firstWhere(
                          (r) => (r['id'] as num).toInt() == _roomId,
                          orElse: () => const {},
                        )['room_number']
                        ?.toString(),
                    enabled: _apartmentId != null && !_loadingRooms,
                    onTap: () async {
                      final v = await _pickSearchable(
                        title: t('housing_room'),
                        items: _rooms,
                        labelOf: (e) =>
                            '${e['room_number']} (${t('housing_available_beds')}: ${e['available_beds']})',
                        selectedId: _roomId,
                      );
                      if (v == null) return;
                      setState(() => _roomId = v);
                    },
                  ),
                ],
              ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(false),
          child: Text(t('cancel')),
        ),
        ElevatedButton(
          onPressed: (_siteId == null || _submitting) ? null : _submit,
          child: _submitting
              ? const SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : Text(t('save')),
        ),
      ],
    );
  }
}
