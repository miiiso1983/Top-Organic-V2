import 'dart:ui' as ui;

import 'package:flutter/foundation.dart';
import 'package:image/image.dart' as img;

// 240 KiB encodes to 320 KiB in JSON, below the verified ingress budget.
const attendanceSelfieMaxBytes = 240 * 1024;

Future<Uint8List> prepareAttendanceSelfie(Uint8List source) async {
  final buffer = await ui.ImmutableBuffer.fromUint8List(source);
  try {
    final descriptor = await ui.ImageDescriptor.encoded(buffer);
    try {
      final isJpeg =
          source.length > 3 &&
          source[0] == 0xff &&
          source[1] == 0xd8 &&
          source[2] == 0xff;
      if (isJpeg && source.length <= attendanceSelfieMaxBytes) return source;
      final portrait = descriptor.height > descriptor.width;
      final codec = await descriptor.instantiateCodec(
        targetWidth: !portrait && descriptor.width > 1024 ? 1024 : null,
        targetHeight: portrait && descriptor.height > 1024 ? 1024 : null,
      );
      try {
        final frame = await codec.getNextFrame();
        try {
          final pixels = await frame.image.toByteData(
            format: ui.ImageByteFormat.rawRgba,
          );
          if (pixels == null) throw const FormatException('Unreadable selfie');
          return await compute(_encode, (
            frame.image.width,
            frame.image.height,
            pixels.buffer.asUint8List(
              pixels.offsetInBytes,
              pixels.lengthInBytes,
            ),
          ));
        } finally {
          frame.image.dispose();
        }
      } finally {
        codec.dispose();
      }
    } finally {
      descriptor.dispose();
    }
  } finally {
    buffer.dispose();
  }
}

Uint8List _encode((int, int, Uint8List) input) {
  var photo = img.Image.fromBytes(
    width: input.$1,
    height: input.$2,
    bytes: input.$3.buffer,
    bytesOffset: input.$3.offsetInBytes,
    numChannels: 4,
  );
  while (true) {
    for (final quality in [88, 76, 64]) {
      final jpeg = img.encodeJpg(photo, quality: quality);
      if (jpeg.length <= attendanceSelfieMaxBytes) return jpeg;
    }
    if (photo.width <= 640 && photo.height <= 640) {
      throw const FormatException('Selfie exceeds upload budget');
    }
    photo = img.copyResize(
      photo,
      width: photo.width >= photo.height ? 640 : null,
      height: photo.height > photo.width ? 640 : null,
    );
  }
}
