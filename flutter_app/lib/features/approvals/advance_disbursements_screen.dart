import 'package:printing/printing.dart';
import 'advance_receipt.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/network/api_client.dart';
import '../../core/locale/locale_provider.dart';
import 'workflow_details_screen.dart';

class AdvanceDisbursementsScreen extends StatefulWidget {
  const AdvanceDisbursementsScreen({super.key});
  @override
  State<AdvanceDisbursementsScreen> createState() =>
      _AdvanceDisbursementsScreenState();
}

class _AdvanceDisbursementsScreenState
    extends State<AdvanceDisbursementsScreen> {
  List<dynamic> _rows = [], _cash = [], _banks = [];
  bool _loading = true, _busy = false, _more = false;
  int _page = 1;
  String? _error;
  String t(String ar, String en) =>
      context.read<LocaleProvider>().isArabic ? ar : en;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({bool next = false}) async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final page = next ? _page + 1 : 1;
    try {
      final res = await context.read<ApiClient>().get(
        '/advances/disbursements?page=$page',
      );
      final data = res.data['data'];
      if (!mounted) return;
      setState(() {
        _rows = next
            ? [..._rows, ...data['advances']]
            : List.from(data['advances']);
        _cash = data['cash_registers'];
        _banks = data['bank_accounts'];
        _page = page;
        _more = data['has_more'] == true;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = _message(e);
        _loading = false;
      });
    }
  }

  String _message(Object e) => e is DioException && e.response?.data is Map
      ? '${e.response!.data['error'] ?? e.message}'
      : t('تعذر الاتصال، حاول مجدداً', 'Connection failed; please retry');

  Future<void> _action(Map row, bool pay) async {
    String method = 'cash';
    String? account;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, update) {
          final accounts = method == 'cash' ? _cash : _banks;
          return AlertDialog(
            title: Text(
              pay
                  ? t('تأكيد التسليم الفعلي', 'Confirm actual payment')
                  : t('اعتماد المحاسب للصرف', 'Accountant authorization'),
            ),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text('${row['full_name']}\n${row['amount']}'),
                Text(
                  pay
                      ? t(
                          'أكد فقط عند تسليم النقد أو تنفيذ التحويل. سيُسجل الصرف والقيد المحاسبي.',
                          'Confirm only when cash is delivered or the transfer is made. Payment and accounting entry will be recorded.',
                        )
                      : t(
                          'هذا الاعتماد يسمح بالصرف؛ لا يسجل دفعة أو قيداً محاسبياً.',
                          'Authorization permits payment; it does not record a payment or journal entry.',
                        ),
                ),
                if (pay) ...[
                  DropdownButtonFormField<String>(
                    initialValue: method,
                    items: [
                      DropdownMenuItem(
                        value: 'cash',
                        child: Text(t('نقدي', 'Cash')),
                      ),
                      DropdownMenuItem(
                        value: 'bank',
                        child: Text(t('بنك', 'Bank')),
                      ),
                    ],
                    onChanged: (v) => update(() {
                      method = v!;
                      account = null;
                    }),
                  ),
                  DropdownButtonFormField<String>(
                    key: ValueKey(method),
                    initialValue: account,
                    isExpanded: true,
                    hint: Text(
                      t(
                        'اختر الصندوق أو الحساب',
                        'Select cash register or bank account',
                      ),
                    ),
                    items: accounts
                        .map(
                          (a) => DropdownMenuItem(
                            value: '${a['id']}',
                            child: Text('${a['name']}'),
                          ),
                        )
                        .toList(),
                    onChanged: (v) => update(() => account = v),
                  ),
                ],
              ],
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: Text(t('إلغاء', 'Cancel')),
              ),
              FilledButton(
                onPressed: pay && account == null
                    ? null
                    : () => Navigator.pop(context, true),
                child: Text(t('تأكيد', 'Confirm')),
              ),
            ],
          );
        },
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _busy = true);
    try {
      await context.read<ApiClient>().post(
        '/advances/${row['id']}/${pay ? 'pay' : 'authorize'}',
        data: pay
            ? {
                'payment_method': method,
                method == 'bank' ? 'bank_account_id' : 'cash_register_id':
                    account,
              }
            : {},
      );
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('تمت العملية', 'Completed'))));
      await _load();
    } catch (e) {
      if (mounted)
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(_message(e))));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _receipt(Map row) async {
    setState(() => _busy = true);
    try {
      final response = await context.read<ApiClient>().get(
        '/advances/${row['id']}/receipt',
      );
      final data = Map<String, dynamic>.from(response.data['data']);
      if (!mounted) return;
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => Scaffold(
            appBar: AppBar(
              title: Text(t('سند صرف السلفة', 'Advance payment receipt')),
            ),
            body: PdfPreview(
              pdfFileName: 'ADV-${row['id']}.pdf',
              canChangeOrientation: false,
              canChangePageFormat: false,
              canDebug: false,
              build: (format) => buildAdvanceReceipt(data, format),
            ),
          ),
        ),
      );
    } catch (e) {
      if (mounted)
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(_message(e))));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    context.watch<LocaleProvider>();
    return Scaffold(
      appBar: AppBar(
        title: Text(t('اعتماد وتسليم السلف', 'Authorize and pay advances')),
        actions: [
          IconButton(
            onPressed: _busy || _loading ? null : () => _load(),
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: Column(
        children: [
          if (_loading || _busy) const LinearProgressIndicator(),
          if (_error != null)
            Padding(padding: const EdgeInsets.all(12), child: Text(_error!)),
          Expanded(
            child: ListView(
              children: [
                if (!_loading && _rows.isEmpty && _error == null)
                  ListTile(
                    title: Text(
                      t('لا توجد سلف معتمدة للصرف', 'No approved advances'),
                    ),
                  ),
                for (final item in _rows)
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '${item['full_name']} · ${item['emp_code']}',
                            style: const TextStyle(fontWeight: FontWeight.bold),
                          ),
                          Text('${item['amount']}'),
                          Text(
                            item['paid_at'] != null
                                ? '${t('تم الصرف', 'Paid')} · ${item['paid_at']}'
                                : item['accountant_approved_at'] != null
                                ? t('جاهزة للتسليم', 'Ready for payment')
                                : t(
                                    'بانتظار اعتماد المحاسب',
                                    'Awaiting accountant authorization',
                                  ),
                          ),
                          Wrap(
                            spacing: 8,
                            children: [
                              TextButton(
                                onPressed: () => Navigator.of(context).push(
                                  MaterialPageRoute(
                                    builder: (_) => WorkflowDetailsScreen(
                                      requestId: int.parse(
                                        '${item['request_id']}',
                                      ),
                                    ),
                                  ),
                                ),
                                child: Text(
                                  t('تفاصيل الطلب', 'Request details'),
                                ),
                              ),
                              if (item['paid_at'] != null)
                                TextButton.icon(
                                  onPressed: _busy
                                      ? null
                                      : () => _receipt(Map.from(item)),
                                  icon: const Icon(Icons.print),
                                  label: Text(
                                    t('سند الصرف / طباعة', 'Receipt / print'),
                                  ),
                                ),
                              if (item['can_authorize'] == true)
                                FilledButton(
                                  onPressed: _busy || _loading
                                      ? null
                                      : () => _action(Map.from(item), false),
                                  child: Text(t('اعتماد للصرف', 'Authorize')),
                                ),
                              if (item['can_pay'] == true)
                                FilledButton(
                                  onPressed: _busy || _loading
                                      ? null
                                      : () => _action(Map.from(item), true),
                                  child: Text(t('تسليم السلفة', 'Pay advance')),
                                ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                if (_more)
                  TextButton(
                    onPressed: _busy || _loading
                        ? null
                        : () => _load(next: true),
                    child: Text(t('المزيد', 'Load more')),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
