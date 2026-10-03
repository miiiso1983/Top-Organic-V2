import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/data/cached_repository.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../shared/widgets/barcode_scanner.dart';
import '../../shared/widgets/offline_banner.dart';

class CustodyIssueScreen extends StatefulWidget {
  const CustodyIssueScreen({super.key, this.item});
  final Map<String, dynamic>? item;

  @override
  State<CustodyIssueScreen> createState() => _CustodyIssueScreenState();
}

class _CustodyIssueScreenState extends State<CustodyIssueScreen> {
  final _codeCtrl = TextEditingController();
  final _qtyCtrl = TextEditingController(text: '1');
  Map<String, dynamic>? _item;
  int? _warehouseId;
  int? _employeeId;
  String? _department;
  String _custodianType = 'employee';
  Future<Map<String, List<Map<String, dynamic>>>>? _optionsFuture;
  bool _submitting = false;
  String? _error;
  String? _offlineNotice;

  /// ترجمة نص حسب اللغة الحالية، قابلة للاستخدام خارج build() أيضاً
  String t(String key) =>
      AppStrings.t(key, context.read<LocaleProvider>().locale.languageCode);

  @override
  void initState() {
    super.initState();
    _item = widget.item;
    if (_item != null && _item!['category_type'] == 'clothing')
      _custodianType = 'employee';
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _optionsFuture ??= _loadOptions();
  }

  Future<Map<String, List<Map<String, dynamic>>>> _loadOptions() async {
    final repo = context.read<CachedRepository>();
    final employees = await repo.getJson('/employees', 'employees');
    final departments = await repo.getJson('/departments', 'departments');
    final warehouses = await repo.getJson('/warehouses', 'warehouses');
    return {
      'employees': ((employees.data['data'] as List?) ?? [])
          .map((e) => e as Map<String, dynamic>)
          .toList(),
      'departments': ((departments.data['data'] as List?) ?? [])
          .map((e) => e as Map<String, dynamic>)
          .toList(),
      'warehouses': ((warehouses.data['data'] as List?) ?? [])
          .map((e) => e as Map<String, dynamic>)
          .toList(),
    };
  }

  Future<void> _lookup(String code) async {
    setState(() {
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
          if (_item!['category_type'] == 'clothing')
            _custodianType = 'employee';
          _offlineNotice = res.fromCache ? res.errorMessage : null;
        });
      } else {
        setState(
          () => _error =
              body['error']?.toString() ?? t('custody_issue_item_not_found'),
        );
      }
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = t('custody_issue_search_error'));
    }
  }

  Future<void> _submit() async {
    if (_item == null || _warehouseId == null || _qtyCtrl.text.trim().isEmpty)
      return;
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final repo = context.read<CachedRepository>();
      final payload = {
        'barcode': _item!['barcode'],
        'qty': double.tryParse(_qtyCtrl.text) ?? 1,
        'unit_id': _item!['unit_id'],
        'warehouse_id': _warehouseId,
        'custodian_type': _custodianType,
        'employee_id': _custodianType == 'employee' ? _employeeId : null,
        'department': _custodianType == 'department' ? _department : null,
      };
      final res = await repo.postOrQueue(
        '/custody/issue',
        payload,
        entity: 'custody_issue',
      );
      if (!mounted) return;
      if (res.data?['ok'] == true) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(t('custody_issue_success'))));
        Navigator.of(context).pop();
      } else {
        setState(
          () => _error =
              res.errorMessage ??
              res.data?['error']?.toString() ??
              t('custody_issue_failed'),
        );
      }
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = t('custody_issue_submit_error'));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Widget _buildEmployeePicker(List<Map<String, dynamic>> employees) {
    final selected = employees.firstWhere(
      (e) => e['id'] == _employeeId,
      orElse: () => const <String, dynamic>{},
    );
    final textController = TextEditingController(
      text: selected.isEmpty ? '' : (selected['full_name']?.toString() ?? ''),
    );
    return TextFormField(
      controller: textController,
      readOnly: true,
      decoration: InputDecoration(labelText: t('custody_issue_employee_label')),
      onTap: () async {
        final searchController = TextEditingController();
        final picked = await showModalBottomSheet<Map<String, dynamic>>(
          context: context,
          isScrollControlled: true,
          builder: (ctx) {
            return StatefulBuilder(
              builder: (ctx, setModalState) {
                final query = searchController.text.trim().toLowerCase();
                final filtered = query.isEmpty
                    ? employees
                    : employees
                          .where(
                            (e) => (e['full_name']?.toString() ?? '')
                                .toLowerCase()
                                .contains(query),
                          )
                          .toList();
                return Padding(
                  padding: EdgeInsets.only(
                    bottom: MediaQuery.of(ctx).viewInsets.bottom,
                  ),
                  child: SizedBox(
                    height: MediaQuery.of(ctx).size.height * 0.6,
                    child: Column(
                      children: [
                        Padding(
                          padding: const EdgeInsets.all(12),
                          child: TextField(
                            controller: searchController,
                            decoration: InputDecoration(
                              prefixIcon: const Icon(Icons.search),
                              hintText: t('custody_issue_search_hint'),
                              border: const OutlineInputBorder(),
                            ),
                            onChanged: (_) => setModalState(() {}),
                          ),
                        ),
                        Expanded(
                          child: filtered.isEmpty
                              ? Center(
                                  child: Text(t('custody_issue_no_results')),
                                )
                              : ListView.builder(
                                  itemCount: filtered.length,
                                  itemBuilder: (ctx, i) {
                                    final e = filtered[i];
                                    return ListTile(
                                      title: Text(
                                        e['full_name']?.toString() ?? '',
                                      ),
                                      onTap: () => Navigator.pop(ctx, e),
                                    );
                                  },
                                ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            );
          },
        );
        if (picked != null) {
          setState(() => _employeeId = picked['id'] as int);
        }
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final isClothing = _item?['category_type'] == 'clothing';
    return Scaffold(
      appBar: AppBar(title: Text(t('custody_issue_title'))),
      body: Column(
        children: [
          OfflineBanner(message: _offlineNotice),
          Expanded(
            child: FutureBuilder<Map<String, List<Map<String, dynamic>>>>(
              future: _optionsFuture,
              builder: (context, snap) {
                final employees = snap.data?['employees'] ?? [];
                final departments = snap.data?['departments'] ?? [];
                return ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    if (_item == null)
                      Row(
                        children: [
                          Expanded(
                            child: TextField(
                              controller: _codeCtrl,
                              decoration: InputDecoration(
                                labelText: t('custody_issue_barcode_label'),
                              ),
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
                    if (_item != null)
                      Card(
                        child: ListTile(
                          title: Text(_item!['name']?.toString() ?? ''),
                          subtitle: Text(
                            '${(_item!['category_type'] ?? '')} \u2022 ${(_item!['unit_name'] ?? '')}',
                          ),
                          trailing: IconButton(
                            icon: const Icon(Icons.clear),
                            onPressed: () => setState(() => _item = null),
                          ),
                        ),
                      ),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<int>(
                      initialValue: _warehouseId,
                      decoration: InputDecoration(
                        labelText: t('custody_issue_warehouse_label'),
                      ),
                      items: (snap.data?['warehouses'] ?? [])
                          .map(
                            (w) => DropdownMenuItem<int>(
                              value: w['id'] as int,
                              child: Text(w['name'].toString()),
                            ),
                          )
                          .toList(),
                      onChanged: (v) => setState(() => _warehouseId = v),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _qtyCtrl,
                      decoration: InputDecoration(
                        labelText:
                            '${t('custody_issue_qty_label')} ${_item != null ? '(${_item!['unit_name'] ?? ''})' : ''}',
                      ),
                      keyboardType: const TextInputType.numberWithOptions(
                        decimal: true,
                      ),
                    ),
                    const SizedBox(height: 12),
                    if (!isClothing)
                      SegmentedButton<String>(
                        segments: [
                          ButtonSegment(
                            value: 'employee',
                            label: Text(t('custody_issue_employee_segment')),
                          ),
                          ButtonSegment(
                            value: 'department',
                            label: Text(t('custody_issue_department_segment')),
                          ),
                        ],
                        selected: {_custodianType},
                        onSelectionChanged: (v) => setState(() {
                          _custodianType = v.first;
                          _employeeId = null;
                          _department = null;
                        }),
                      ),
                    if (isClothing)
                      Text(
                        t('custody_issue_clothing_employee_only'),
                        style: const TextStyle(color: Colors.grey),
                      ),
                    const SizedBox(height: 12),
                    if (_custodianType == 'employee')
                      _buildEmployeePicker(employees),
                    if (_custodianType == 'department')
                      DropdownButtonFormField<String>(
                        initialValue: _department,
                        decoration: InputDecoration(
                          labelText: t('custody_issue_department_label'),
                        ),
                        items: departments
                            .map(
                              (d) => DropdownMenuItem<String>(
                                value: d['name'].toString(),
                                child: Text(d['name'].toString()),
                              ),
                            )
                            .toList(),
                        onChanged: (v) => setState(() => _department = v),
                      ),
                    const SizedBox(height: 16),
                    if (_error != null)
                      Text(_error!, style: const TextStyle(color: Colors.red)),
                    ElevatedButton(
                      onPressed: _submitting ? null : _submit,
                      child: _submitting
                          ? const CircularProgressIndicator()
                          : Text(t('custody_issue_submit_button')),
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
    _codeCtrl.dispose();
    _qtyCtrl.dispose();
    super.dispose();
  }
}
