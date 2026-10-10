import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:dio/dio.dart';
import '../../core/network/api_client.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';

/// تقديم طلب (نسيان بصمة أو إجازة) نيابةً عن أحد أعضاء الفريق التابعين للمدير الحالي
class TeamRequestScreen extends StatefulWidget {
  const TeamRequestScreen({
    super.key,
    required this.employeeId,
    required this.employeeName,
  });

  final int employeeId;
  final String employeeName;

  @override
  State<TeamRequestScreen> createState() => _TeamRequestScreenState();
}

class _TeamRequestScreenState extends State<TeamRequestScreen> {
  int _tab = 0; // 0 = نسيان بصمة، 1 = إجازة
  bool _submitting = false;

  // حقول طلب نسيان البصمة
  String _attType = 'in';
  DateTime? _clockIn;
  DateTime? _clockOut;
  final _attReasonController = TextEditingController();

  // حقول طلب الإجازة
  String _leaveType = 'unpaid';
  DateTime? _dateFrom;
  DateTime? _dateTo;
  final _leaveReasonController = TextEditingController();

  @override
  void dispose() {
    _attReasonController.dispose();
    _leaveReasonController.dispose();
    super.dispose();
  }

  String _fmtDate(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  String _fmtDateTime(DateTime d) =>
      '${_fmtDate(d)} ${d.hour.toString().padLeft(2, '0')}:${d.minute.toString().padLeft(2, '0')}:00';

  String t(String key) =>
      AppStrings.t(key, context.read<LocaleProvider>().locale.languageCode);

  Future<DateTime?> _pickDateTime(DateTime? initial) async {
    final now = DateTime.now();
    final date = await showDatePicker(
      context: context,
      initialDate: initial ?? now,
      firstDate: DateTime(2000),
      lastDate: DateTime(2100),
    );
    if (date == null || !mounted) return null;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(initial ?? now),
      builder: (context, child) =>
          Directionality(textDirection: TextDirection.rtl, child: child!),
    );
    if (time == null) return null;
    return DateTime(date.year, date.month, date.day, time.hour, time.minute);
  }

  Future<DateTime?> _pickDate(DateTime? initial) {
    final now = DateTime.now();
    return showDatePicker(
      context: context,
      initialDate: initial ?? now,
      firstDate: DateTime(2000),
      lastDate: DateTime(2100),
    );
  }

  void _showError(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  String _errorFrom(Object e, String fallbackKey) {
    if (e is DioException && e.response?.data is Map) {
      final data = e.response!.data as Map;
      return data['error']?.toString() ?? t(fallbackKey);
    }
    return t(fallbackKey);
  }

  Future<void> _submitMissingAttendance() async {
    if (_attType == 'in' && _clockIn == null) {
      _showError(t('team_request_att_clock_in_required'));
      return;
    }
    if (_attType == 'out' && _clockOut == null) {
      _showError(t('team_request_att_clock_out_required'));
      return;
    }
    if (_attType == 'both' && (_clockIn == null || _clockOut == null)) {
      _showError(t('team_request_att_both_required'));
      return;
    }
    if (_attReasonController.text.trim().isEmpty) {
      _showError(t('team_request_att_reason_required'));
      return;
    }
    setState(() => _submitting = true);
    try {
      final api = context.read<ApiClient>();
      final res = await api.post(
        '/team/employees/${widget.employeeId}/missing-attendance',
        data: {
          'request_type': _attType,
          'clock_in_at': _clockIn != null ? _fmtDateTime(_clockIn!) : null,
          'clock_out_at': _clockOut != null ? _fmtDateTime(_clockOut!) : null,
          'reason': _attReasonController.text,
        },
      );
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      if (body['ok'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(t('team_request_att_submitted'))),
        );
        Navigator.of(context).pop(true);
      } else {
        _showError(
          body['error']?.toString() ?? t('team_request_submit_failed'),
        );
      }
    } catch (e) {
      _showError(_errorFrom(e, 'team_request_submit_error'));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _submitLeave() async {
    if (_dateFrom == null || _dateTo == null) {
      _showError(t('team_request_leave_dates_required'));
      return;
    }
    if (_dateTo!.isBefore(_dateFrom!)) {
      _showError(t('team_request_leave_dates_invalid'));
      return;
    }
    setState(() => _submitting = true);
    try {
      final api = context.read<ApiClient>();
      final res = await api.post(
        '/team/employees/${widget.employeeId}/leave',
        data: {
          'leave_type': _leaveType,
          'date_from': _fmtDate(_dateFrom!),
          'date_to': _fmtDate(_dateTo!),
          'reason': _leaveReasonController.text,
        },
      );
      final body = res.data as Map<String, dynamic>;
      if (!mounted) return;
      if (body['ok'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(t('team_request_leave_submitted'))),
        );
        Navigator.of(context).pop(true);
      } else {
        _showError(
          body['error']?.toString() ?? t('team_request_submit_failed'),
        );
      }
    } catch (e) {
      _showError(_errorFrom(e, 'team_request_submit_error'));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Widget _buildMissingAttendanceForm() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          t('team_request_att_type_label'),
          style: const TextStyle(fontWeight: FontWeight.w600),
        ),
        const SizedBox(height: 6),
        DropdownButtonFormField<String>(
          initialValue: _attType,
          decoration: const InputDecoration(border: OutlineInputBorder()),
          items: [
            DropdownMenuItem(
              value: 'in',
              child: Text(t('team_request_att_type_in')),
            ),
            DropdownMenuItem(
              value: 'out',
              child: Text(t('team_request_att_type_out')),
            ),
            DropdownMenuItem(
              value: 'both',
              child: Text(t('team_request_att_type_both')),
            ),
          ],
          onChanged: (v) => setState(() => _attType = v ?? 'in'),
        ),
        const SizedBox(height: 16),
        if (_attType == 'in' || _attType == 'both') ...[
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(t('team_request_att_clock_in_label')),
            subtitle: Text(
              _clockIn != null
                  ? _fmtDateTime(_clockIn!)
                  : t('team_request_choose_datetime'),
            ),
            trailing: const Icon(Icons.access_time),
            onTap: () async {
              final picked = await _pickDateTime(_clockIn);
              if (picked != null) setState(() => _clockIn = picked);
            },
          ),
          const Divider(),
        ],
        if (_attType == 'out' || _attType == 'both') ...[
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(t('team_request_att_clock_out_label')),
            subtitle: Text(
              _clockOut != null
                  ? _fmtDateTime(_clockOut!)
                  : t('team_request_choose_datetime'),
            ),
            trailing: const Icon(Icons.access_time),
            onTap: () async {
              final picked = await _pickDateTime(_clockOut);
              if (picked != null) setState(() => _clockOut = picked);
            },
          ),
          const Divider(),
        ],
        const SizedBox(height: 8),
        TextField(
          controller: _attReasonController,
          maxLines: 3,
          decoration: InputDecoration(
            labelText: t('team_request_reason_label'),
            border: const OutlineInputBorder(),
          ),
        ),
        const SizedBox(height: 20),
        ElevatedButton.icon(
          onPressed: _submitting ? null : _submitMissingAttendance,
          icon: _submitting
              ? const SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: Colors.white,
                  ),
                )
              : const Icon(Icons.send),
          label: Text(
            _submitting
                ? t('team_request_submitting')
                : t('team_request_submit_button'),
          ),
        ),
      ],
    );
  }

  Widget _buildLeaveForm() {
    const leaveTypes = ['unpaid', 'hourly', 'emergency', 'sick', 'annual'];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          t('team_request_leave_type_label'),
          style: const TextStyle(fontWeight: FontWeight.w600),
        ),
        const SizedBox(height: 6),
        DropdownButtonFormField<String>(
          initialValue: _leaveType,
          decoration: const InputDecoration(border: OutlineInputBorder()),
          items: leaveTypes
              .map(
                (v) => DropdownMenuItem(
                  value: v,
                  child: Text(t('team_request_leave_type_$v')),
                ),
              )
              .toList(),
          onChanged: (v) => setState(() => _leaveType = v ?? 'unpaid'),
        ),
        const SizedBox(height: 16),
        ListTile(
          contentPadding: EdgeInsets.zero,
          title: Text(t('team_request_leave_from_label')),
          subtitle: Text(
            _dateFrom != null
                ? _fmtDate(_dateFrom!)
                : t('team_request_choose_date'),
          ),
          trailing: const Icon(Icons.calendar_today),
          onTap: () async {
            final picked = await _pickDate(_dateFrom);
            if (picked != null) setState(() => _dateFrom = picked);
          },
        ),
        const Divider(),
        ListTile(
          contentPadding: EdgeInsets.zero,
          title: Text(t('team_request_leave_to_label')),
          subtitle: Text(
            _dateTo != null
                ? _fmtDate(_dateTo!)
                : t('team_request_choose_date'),
          ),
          trailing: const Icon(Icons.calendar_today),
          onTap: () async {
            final picked = await _pickDate(_dateTo ?? _dateFrom);
            if (picked != null) setState(() => _dateTo = picked);
          },
        ),
        const Divider(),
        const SizedBox(height: 8),
        TextField(
          controller: _leaveReasonController,
          maxLines: 3,
          decoration: InputDecoration(
            labelText: t('team_request_reason_label'),
            border: const OutlineInputBorder(),
          ),
        ),
        const SizedBox(height: 20),
        ElevatedButton.icon(
          onPressed: _submitting ? null : _submitLeave,
          icon: _submitting
              ? const SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: Colors.white,
                  ),
                )
              : const Icon(Icons.send),
          label: Text(
            _submitting
                ? t('team_request_submitting')
                : t('team_request_submit_button'),
          ),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    context.watch<LocaleProvider>();
    return Scaffold(
      appBar: AppBar(
        title: Text('${t('team_request_title_prefix')}${widget.employeeName}'),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          SegmentedButton<int>(
            segments: [
              ButtonSegment(
                value: 0,
                label: Text(t('team_request_tab_attendance')),
                icon: const Icon(Icons.fingerprint),
              ),
              ButtonSegment(
                value: 1,
                label: Text(t('team_request_tab_leave')),
                icon: const Icon(Icons.beach_access),
              ),
            ],
            selected: {_tab},
            onSelectionChanged: (s) => setState(() => _tab = s.first),
          ),
          const SizedBox(height: 20),
          if (_tab == 0) _buildMissingAttendanceForm() else _buildLeaveForm(),
        ],
      ),
    );
  }
}
