import 'dart:convert';
import 'dart:math';
import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';
import 'package:image/image.dart' as img;
import 'package:top_organic_app/features/hr/attendance_selfie.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test(
    'oversized portrait preserves aspect ratio and fits both JSON requests',
    () async {
      final rng = Random(17);
      final raw = Uint8List.fromList(
        List.generate(1200 * 1600 * 3, (_) => rng.nextInt(256)),
      );
      final jpeg = img.encodeJpg(
        img.Image.fromBytes(
          width: 1200,
          height: 1600,
          bytes: raw.buffer,
          numChannels: 3,
        ),
        quality: 95,
      );
      expect(jpeg.length, greaterThan(1024 * 1024));
      final prepared = await prepareAttendanceSelfie(jpeg);
      expect(prepared.length, lessThanOrEqualTo(attendanceSelfieMaxBytes));
      final photo = img.decodeJpg(prepared)!;
      expect(photo.width / photo.height, closeTo(0.75, 0.002));
      for (final json in [
        {'selfie': base64Encode(prepared)},
        {
          'check_type': 'out',
          'latitude': 33.3,
          'longitude': 44.4,
          'photo': base64Encode(prepared),
        },
      ]) {
        expect(utf8.encode(jsonEncode(json)).length, lessThan(360000));
      }
    },
  );
  test('small JPEG stays unchanged and invalid capture fails', () async {
    final jpeg = img.encodeJpg(img.Image(width: 320, height: 240));
    expect(await prepareAttendanceSelfie(jpeg), same(jpeg));
    await expectLater(
      prepareAttendanceSelfie(Uint8List.fromList([1, 2, 3])),
      throwsA(anything),
    );
  });
}
