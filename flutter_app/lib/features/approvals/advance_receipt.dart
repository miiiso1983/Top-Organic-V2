import 'dart:typed_data';
import 'package:flutter/services.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;

Future<Uint8List> buildAdvanceReceipt(
  Map<String, dynamic> receipt,
  PdfPageFormat format,
) async {
  final font = pw.Font.ttf(
    await rootBundle.load('assets/fonts/Amiri-Regular.ttf'),
  );
  final doc = pw.Document();
  final rows = <String, String>{
    'رقم السند': 'ADV-${receipt['id']}',
    'الموظف': '${receipt['full_name']} — ${receipt['emp_code']}',
    'المبلغ المستلم': '${receipt['amount']}',
    'تاريخ التسليم': '${receipt['paid_at']}',
    'طريقة التسليم': receipt['payment_method'] == 'bank' ? 'بنك' : 'نقدي',
    'مسؤول التسليم': '${receipt['paid_by_name'] ?? ''}',
    'السبب': '${receipt['reason'] ?? ''}',
  };
  doc.addPage(
    pw.MultiPage(
      pageFormat: format,
      textDirection: pw.TextDirection.rtl,
      theme: pw.ThemeData.withFont(base: font, bold: font),
      build: (_) => [
        pw.Center(
          child: pw.Text(
            'سند صرف سلفة',
            style: const pw.TextStyle(fontSize: 26),
          ),
        ),
        pw.Center(
          child: pw.Text(
            'تم الصرف',
            style: const pw.TextStyle(fontSize: 22, color: PdfColors.green800),
          ),
        ),
        pw.SizedBox(height: 24),
        for (final row in rows.entries)
          pw.Container(
            padding: const pw.EdgeInsets.all(10),
            decoration: const pw.BoxDecoration(
              border: pw.Border(
                bottom: pw.BorderSide(color: PdfColors.grey300),
              ),
            ),
            child: pw.Row(
              crossAxisAlignment: pw.CrossAxisAlignment.start,
              children: [
                pw.Expanded(
                  child: pw.Text(
                    row.key,
                    style: const pw.TextStyle(fontSize: 15),
                  ),
                ),
                pw.Expanded(
                  flex: 2,
                  child: pw.Text(
                    row.value,
                    style: const pw.TextStyle(fontSize: 15),
                  ),
                ),
              ],
            ),
          ),
        pw.SizedBox(height: 60),
        pw.Text('توقيع المستلم: __________________'),
        pw.SizedBox(height: 20),
        pw.Text('توقيع مسؤول التسليم: __________________'),
      ],
    ),
  );
  return doc.save();
}
