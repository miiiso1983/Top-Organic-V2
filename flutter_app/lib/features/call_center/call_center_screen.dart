import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/data/cached_repository.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/utils/timezone_helper.dart';

/// شاشة كول سنتر أصلية: بحث عن العملاء وإنشاء حجوزات
class CallCenterScreen extends StatefulWidget {
  const CallCenterScreen({super.key});

  @override
  State<CallCenterScreen> createState() => _CallCenterScreenState();
}

class _CallCenterScreenState extends State<CallCenterScreen> {
  bool _loadingTypes = true;
  bool _loading = false;
  String? _error;
  List<dynamic> _types = [];
  List<dynamic> _customers = [];
  Map<String, dynamic>? _customer;
  List<dynamic> _reservations = [];
  List<dynamic> _slots = [];
  List<dynamic> _tables = [];
  Map<String, dynamic>? _occupancy;

  final _searchCtrl = TextEditingController();
  final _nameCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _guestsCtrl = TextEditingController(text: '2');
  final _dateCtrl = TextEditingController();
  final _timeCtrl = TextEditingController();
  final _durationCtrl = TextEditingController(text: '90');
  final _notesCtrl = TextEditingController();
  final _ccNotesCtrl = TextEditingController();

  String _typeCode = 'regular';
  final int _branchId = 1;
  int? _tableId;
  int? _reservationId;

  @override
  void initState() {
    super.initState();
    _dateCtrl.text = _today();
    _loadTypes();
  }

  String _today() => BaghdadTime.today();

  Future<void> _loadTypes() async {
    setState(() => _loadingTypes = true);
    try {
      final repo = context.read<CachedRepository>();
      final res = await repo.getJson(
        '/call-center/types',
        'call_center_types',
        ttlSeconds: 300,
      );
      if (!mounted) return;
      setState(() {
        _types = (res.data['types'] as List?) ?? [];
        if (_types.isNotEmpty)
          _typeCode = _types.first['code']?.toString() ?? 'regular';
      });
    } catch (e) {
      if (!mounted) return;
      final lang = context.read<LocaleProvider>().locale.languageCode;
      setState(() => _error = AppStrings.t('cc_load_types_error', lang));
    } finally {
      if (mounted) setState(() => _loadingTypes = false);
    }
  }

  Future<void> _search() async {
    final q = _searchCtrl.text.trim();
    if (q.length < 2) return;
    setState(() => _loading = true);
    try {
      final repo = context.read<CachedRepository>();
      final res = await repo.getJson(
        '/call-center/customers/search?q=${Uri.encodeComponent(q)}',
        'cc_search_$q',
        ttlSeconds: 0,
      );
      if (!mounted) return;
      setState(() => _customers = (res.data['customers'] as List?) ?? []);
    } catch (e) {
      if (!mounted) return;
      final lang = context.read<LocaleProvider>().locale.languageCode;
      setState(() => _error = AppStrings.t('cc_search_error', lang));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _selectCustomer(dynamic c) async {
    setState(() => _loading = true);
    try {
      final repo = context.read<CachedRepository>();
      final res = await repo.getJson(
        '/call-center/customers/${c['id']}',
        'cc_customer_${c['id']}',
        ttlSeconds: 0,
      );
      if (!mounted) return;
      final data = res.data;
      setState(() {
        _customer = data['customer'] as Map<String, dynamic>?;
        _reservations = (data['reservations'] as List?) ?? [];
        _nameCtrl.text = _customer?['name']?.toString() ?? '';
        _phoneCtrl.text = _customer?['phone']?.toString() ?? '';
        _customers = [];
      });
    } catch (e) {
      if (!mounted) return;
      final lang = context.read<LocaleProvider>().locale.languageCode;
      setState(() => _error = AppStrings.t('cc_customer_load_error', lang));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _loadSlots() async {
    final branch = _branchId;
    final date = _dateCtrl.text;
    final guests = int.tryParse(_guestsCtrl.text) ?? 2;
    if (date.isEmpty) return;
    try {
      final repo = context.read<CachedRepository>();
      final res = await repo.getJson(
        '/call-center/slots?branch_id=$branch&date=$date&guests=$guests&type=$_typeCode',
        'cc_slots_${branch}_$date',
        ttlSeconds: 0,
      );
      if (!mounted) return;
      setState(() => _slots = (res.data['slots'] as List?) ?? []);
    } catch (_) {}
  }

  Future<void> _loadTables() async {
    final branch = _branchId;
    final date = _dateCtrl.text;
    final time = _timeCtrl.text;
    final guests = int.tryParse(_guestsCtrl.text) ?? 2;
    final duration = int.tryParse(_durationCtrl.text) ?? 90;
    if (date.isEmpty || time.isEmpty) return;
    try {
      final repo = context.read<CachedRepository>();
      final res = await repo.getJson(
        '/call-center/tables?branch_id=$branch&date=$date&time=$time&guests=$guests&duration=$duration',
        'cc_tables_${branch}_${date}_$time',
        ttlSeconds: 0,
      );
      if (!mounted) return;
      setState(() => _tables = (res.data['tables'] as List?) ?? []);
    } catch (_) {}
  }

  Future<void> _loadOccupancy() async {
    final branch = _branchId;
    final date = _dateCtrl.text;
    if (date.isEmpty) return;
    try {
      final repo = context.read<CachedRepository>();
      final res = await repo.getJson(
        '/call-center/occupancy?branch_id=$branch&date=$date',
        'cc_occ_${branch}_$date',
        ttlSeconds: 0,
      );
      if (!mounted) return;
      setState(() => _occupancy = res.data as Map<String, dynamic>?);
    } catch (_) {}
  }

  Future<void> _save() async {
    final lang = context.read<LocaleProvider>().locale.languageCode;
    final name = _nameCtrl.text.trim();
    if (name.isEmpty) {
      setState(() => _error = AppStrings.t('cc_name_required', lang));
      return;
    }
    final date = _dateCtrl.text;
    final time = _timeCtrl.text;
    if (date.isEmpty || time.isEmpty) {
      setState(() => _error = AppStrings.t('cc_date_time_required', lang));
      return;
    }
    setState(() => _loading = true);
    try {
      final repo = context.read<CachedRepository>();
      final body = {
        'customer_id': _customer?['id'],
        'customer_name': name,
        'customer_phone': _phoneCtrl.text.trim(),
        'branch_id': _branchId,
        'reservation_type': _typeCode,
        'guests': int.tryParse(_guestsCtrl.text) ?? 2,
        'reserved_at': '$date $time:00',
        'duration_minutes': int.tryParse(_durationCtrl.text) ?? 90,
        'table_id': _tableId,
        'notes': _notesCtrl.text,
        'call_center_notes': _ccNotesCtrl.text,
      };
      final url = _reservationId == null
          ? '/call-center/reservations'
          : '/call-center/reservations/$_reservationId';
      final res = await repo.postJson(url, body);
      if (!mounted) return;
      final data = res.data ?? {};
      if (data['ok'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(AppStrings.t('cc_save_success', lang))),
        );
        _reset();
      } else {
        setState(
          () => _error =
              data['error']?.toString() ?? AppStrings.t('cc_save_failed', lang),
        );
      }
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = AppStrings.t('cc_save_error', lang));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _reset() {
    _customer = null;
    _reservations = [];
    _slots = [];
    _tables = [];
    _occupancy = null;
    _reservationId = null;
    _tableId = null;
    _searchCtrl.clear();
    _nameCtrl.clear();
    _phoneCtrl.clear();
    _notesCtrl.clear();
    _ccNotesCtrl.clear();
    _dateCtrl.text = _today();
    _timeCtrl.clear();
    _guestsCtrl.text = '2';
    _durationCtrl.text = '90';
    if (mounted) setState(() {});
  }

  Widget _buildSearch(String Function(String) t) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        TextField(
          controller: _searchCtrl,
          textDirection: TextDirection.rtl,
          decoration: InputDecoration(
            hintText: t('cc_search_hint'),
            suffixIcon: IconButton(
              icon: const Icon(Icons.search),
              onPressed: _search,
            ),
          ),
          onSubmitted: (_) => _search(),
        ),
        Wrap(
          children: _customers
              .map(
                (c) => ActionChip(
                  label: Text('${c['name']} ${c['phone'] ?? ''}'),
                  onPressed: () => _selectCustomer(c),
                ),
              )
              .toList(),
        ),
      ],
    );
  }

  Widget _buildForm(String Function(String) t) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        TextField(
          controller: _nameCtrl,
          textDirection: TextDirection.rtl,
          decoration: InputDecoration(labelText: t('cc_form_name_label')),
        ),
        TextField(
          controller: _phoneCtrl,
          textDirection: TextDirection.ltr,
          decoration: InputDecoration(labelText: t('cc_phone_label')),
        ),
        InputDecorator(
          decoration: InputDecoration(labelText: t('cc_type_label')),
          child: DropdownButtonHideUnderline(
            child: DropdownButton<String>(
              value: _typeCode,
              isExpanded: true,
              items: _types
                  .map(
                    (ty) => DropdownMenuItem(
                      value: ty['code'].toString(),
                      child: Text(
                        ty['name_ar']?.toString() ?? ty['code'].toString(),
                      ),
                    ),
                  )
                  .toList(),
              onChanged: (v) => setState(() => _typeCode = v ?? 'regular'),
            ),
          ),
        ),
        Row(
          children: [
            Expanded(
              child: TextField(
                controller: _guestsCtrl,
                keyboardType: TextInputType.number,
                decoration: InputDecoration(labelText: t('cc_guests_label')),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: TextField(
                controller: _durationCtrl,
                keyboardType: TextInputType.number,
                decoration: InputDecoration(labelText: t('cc_duration_label')),
              ),
            ),
          ],
        ),
        Row(
          children: [
            Expanded(
              child: TextField(
                controller: _dateCtrl,
                decoration: InputDecoration(labelText: t('cc_date_label')),
                onTap: () async {
                  final now = BaghdadTime.now();
                  final d = await showDatePicker(
                    context: context,
                    initialDate: now,
                    firstDate: now.subtract(const Duration(days: 1)),
                    lastDate: now.add(const Duration(days: 90)),
                  );
                  if (d != null) {
                    _dateCtrl.text =
                        '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
                    _loadSlots();
                    _loadOccupancy();
                  }
                },
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: TextField(
                controller: _timeCtrl,
                decoration: InputDecoration(labelText: t('cc_time_label')),
                onTap: () async {
                  final tod = await showTimePicker(
                    context: context,
                    initialTime: TimeOfDay.fromDateTime(BaghdadTime.now()),
                  );
                  if (tod != null) {
                    _timeCtrl.text =
                        '${tod.hour.toString().padLeft(2, '0')}:${tod.minute.toString().padLeft(2, '0')}';
                    _loadTables();
                  }
                },
              ),
            ),
          ],
        ),
        TextField(
          controller: _notesCtrl,
          textDirection: TextDirection.rtl,
          decoration: InputDecoration(labelText: t('cc_notes_label')),
          maxLines: 2,
        ),
        TextField(
          controller: _ccNotesCtrl,
          textDirection: TextDirection.rtl,
          decoration: InputDecoration(labelText: t('cc_cc_notes_label')),
          maxLines: 2,
        ),
      ],
    );
  }

  Widget _buildSlots() {
    return Wrap(
      spacing: 6,
      runSpacing: 6,
      children: _slots.map((s) {
        final available = s['available'] == true;
        final selected = _timeCtrl.text == s['time']?.toString();
        return ChoiceChip(
          label: Text(s['time']?.toString() ?? '—'),
          selected: selected,
          selectedColor: available ? Colors.green : Colors.red,
          onSelected: available
              ? (_) {
                  _timeCtrl.text = s['time'].toString();
                  _loadTables();
                }
              : null,
        );
      }).toList(),
    );
  }

  Widget _buildTables() {
    return Wrap(
      spacing: 6,
      runSpacing: 6,
      children: _tables.map((t) {
        final id = t['id'];
        final selected = _tableId == id;
        return ChoiceChip(
          label: Text(
            '${t['name']} (${t['max_guests'] ?? t['capacity'] ?? t['seats']})',
          ),
          selected: selected,
          onSelected: (_) => setState(() => _tableId = selected ? null : id),
        );
      }).toList(),
    );
  }

  Widget _buildHistory(String Function(String) t) {
    if (_reservations.isEmpty) return const SizedBox.shrink();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          t('cc_history_title'),
          style: const TextStyle(fontWeight: FontWeight.bold),
        ),
        ..._reservations.map(
          (r) => ListTile(
            dense: true,
            title: Text(
              '${r['reservation_no']} — ${r['reserved_at']}',
              textDirection: TextDirection.rtl,
            ),
            subtitle: Text(
              '${r['guests']}${t('cc_guests_suffix')}${r['status']}',
              textDirection: TextDirection.rtl,
            ),
            onTap: () {
              _reservationId = r['id'];
              _typeCode = r['reservation_type']?.toString() ?? 'regular';
              _guestsCtrl.text = r['guests']?.toString() ?? '2';
              _dateCtrl.text =
                  r['reserved_at']?.toString().split(' ')[0] ?? _today();
              _timeCtrl.text =
                  r['reserved_at']?.toString().split(' ')[1].substring(0, 5) ??
                  '';
              _durationCtrl.text = r['duration_minutes']?.toString() ?? '90';
              _tableId = r['table_id'];
              _notesCtrl.text = r['notes']?.toString() ?? '';
              _ccNotesCtrl.text = r['call_center_notes']?.toString() ?? '';
              setState(() {});
            },
          ),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    return Scaffold(
      appBar: AppBar(title: Text(t('cc_title'))),
      body: _loadingTypes
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (_error != null) ...[
                    Text(
                      _error!,
                      textDirection: TextDirection.rtl,
                      style: const TextStyle(color: Colors.red),
                    ),
                    const SizedBox(height: 8),
                  ],
                  _buildSearch(t),
                  const SizedBox(height: 12),
                  if (_customer != null) ...[
                    Text(
                      '${t('cc_customer_prefix')}${_customer!['name']}',
                      textDirection: TextDirection.rtl,
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                    _buildHistory(t),
                    const SizedBox(height: 12),
                  ],
                  _buildForm(t),
                  const SizedBox(height: 12),
                  if (_occupancy != null)
                    Text(
                      '${t('cc_occupancy_prefix')}${_occupancy!['occupancy_rate']}%',
                      textDirection: TextDirection.rtl,
                    ),
                  const SizedBox(height: 8),
                  _buildSlots(),
                  const SizedBox(height: 8),
                  _buildTables(),
                  const SizedBox(height: 16),
                  ElevatedButton.icon(
                    onPressed: _loading ? null : _save,
                    icon: _loading
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.save),
                    label: Text(t('cc_save_button')),
                  ),
                  TextButton(
                    onPressed: _reset,
                    child: Text(t('cc_new_button')),
                  ),
                ],
              ),
            ),
    );
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    _nameCtrl.dispose();
    _phoneCtrl.dispose();
    _guestsCtrl.dispose();
    _dateCtrl.dispose();
    _timeCtrl.dispose();
    _durationCtrl.dispose();
    _notesCtrl.dispose();
    _ccNotesCtrl.dispose();
    super.dispose();
  }
}
