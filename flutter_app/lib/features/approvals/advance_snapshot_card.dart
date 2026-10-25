import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/locale/locale_provider.dart';
import 'advance_policy.dart';

class AdvanceSnapshotCard extends StatelessWidget {
  const AdvanceSnapshotCard({
    super.key,
    required this.snapshot,
    required this.code,
  });
  final Map<String, dynamic> snapshot;
  final String code;

  @override
  Widget build(BuildContext context) {
    final en = context.watch<LocaleProvider>().locale.languageCode == 'en';
    String t(String ar, String english) => en ? english : ar;
    Widget row(String label, dynamic value) => Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          Expanded(child: Text(label)),
          const SizedBox(width: 12),
          Text(AdvancePolicy.number(value).toStringAsFixed(2)),
        ],
      ),
    );
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              t('الاستحقاق والمديونية', 'Entitlement and debt'),
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
            Text('${t('حتى', 'As of')} ${snapshot['as_of_date'] ?? ''}'),
            row(t('الراتب الأساسي', 'Base salary'), snapshot['salary']),
            row(
              t('الاستحقاق المتراكم', 'Accrued earnings'),
              snapshot['accrued_earnings'],
            ),
            row(t('الاستقطاعات', 'Deductions'), snapshot['deductions']),
            row(
              t('كامل المديونية المتبقية', 'Full outstanding debt'),
              snapshot['outstanding_debt'],
            ),
            row(
              t('صافي الاستحقاق', 'Net entitlement'),
              snapshot['net_entitlement'],
            ),
            if (code != 'hr_long_advance') ...[
              row(t('25٪ من الراتب', '25% of salary'), snapshot['salary_cap']),
              row(
                t(
                  'المتاح بعد الطلبات القائمة',
                  'Available after existing requests',
                ),
                snapshot['remaining'],
              ),
              Text(
                t(
                  'المتاح هو الأقل بين 25٪ من الراتب وصافي الاستحقاق بعد المديونية والاستقطاعات.',
                  'Available amount is the lesser of 25% of salary and net entitlement after debt and deductions.',
                ),
              ),
            ],
            if (code == 'hr_advance')
              Text(
                t(
                  'التقديم من يوم 13، مرة واحدة في الشهر. المرفوض أو الملغى لا يمنع طلباً بديلاً.',
                  'Apply from day 13, once per month. Rejected or cancelled requests allow a replacement.',
                ),
              ),
            if (code == 'hr_exceptional_advance')
              Text(
                t(
                  'يمكن التقديم قبل يوم 13، مع ذكر السبب.',
                  'Applications before day 13 are allowed; a reason is required.',
                ),
              ),
            if (code == 'hr_long_advance')
              Text(
                t(
                  'اذكر السبب وحدد قسطين على الأقل. لا يطبق حد 25٪ على السلفة طويلة الأجل.',
                  'Provide a reason and at least two installments. The 25% limit does not apply to long-term advances.',
                ),
              ),
            if (snapshot['error'] != null)
              Text(
                '${snapshot['error']}',
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            if ((snapshot['previous_advances'] as List? ?? []).isNotEmpty)
              ExpansionTile(
                title: Text(
                  t(
                    'السلف السابقة والأقساط',
                    'Previous advances and installments',
                  ),
                ),
                children: [
                  for (final a in snapshot['previous_advances'] as List)
                    ListTile(
                      title: Text('${t('سلفة', 'Advance')} #${a['id']}'),
                      subtitle: Text(
                        '${t('الأقساط المتبقية', 'Remaining installments')}: ${a['remaining_installments']} × ${AdvancePolicy.number(a['installment_amount']).toStringAsFixed(2)}\n${t('المتبقي', 'Outstanding')}: ${AdvancePolicy.number(a['remaining_amount']).toStringAsFixed(2)}',
                      ),
                    ),
                ],
              ),
          ],
        ),
      ),
    );
  }
}
