/// The server remains authoritative; these checks give immediate form feedback.
class AdvancePolicy {
  static const codes = [
    'hr_advance',
    'hr_exceptional_advance',
    'hr_long_advance',
  ];
  static bool isAdvance(dynamic code) => codes.contains(code);
  static double number(dynamic value) => double.tryParse('$value') ?? 0;

  static String? validateAmount(
    String? value,
    String code,
    Map<String, dynamic>? snapshot, {
    bool english = false,
  }) {
    String text(String ar, String en) => english ? en : ar;
    final amount = double.tryParse(value ?? '');
    if (amount == null ||
        !amount.isFinite ||
        amount <= 0 ||
        (amount * 100 - (amount * 100).round()).abs() > 0.00001) {
      return text(
        'أدخل مبلغاً موجباً بمنزلتين عشريتين كحد أقصى',
        'Enter a positive amount with up to two decimal places',
      );
    }
    if (snapshot == null)
      return text(
        'اختر الموظف وانتظر تحميل الاستحقاق',
        'Select an employee and wait for entitlement',
      );
    if (snapshot['error'] != null) return snapshot['error'].toString();
    if (code == 'hr_advance' && snapshot['monthly_open'] != true)
      return text('يبدأ التقديم من يوم 13', 'Applications open on day 13');
    if (code != 'hr_long_advance' && amount > number(snapshot['remaining']))
      return text(
        'المبلغ يتجاوز المتاح بعد خصم المديونية والاستقطاعات',
        'Amount exceeds available entitlement after debt and deductions',
      );
    return null;
  }
}
