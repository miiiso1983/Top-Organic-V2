import 'package:flutter/services.dart';
import '../../core/config.dart';
import '../../core/storage/auth_storage.dart';

/// Native listening sessions own playback while Flutter is suspended.
class PttReceiver {
  static const _channel = MethodChannel('toporganic/ptt_receiver');
  static Future<Map<String, dynamic>> state() async {
    final result = await _channel.invokeMapMethod<String, dynamic>('state');
    return result ?? {'enabled': false};
  }

  static Future<void> enable() async {
    final storage = AuthStorage();
    final token = await storage.getToken();
    final user = await storage.getUser();
    if (token == null || user == null) throw StateError('يجب تسجيل الدخول');
    await _channel.invokeMethod('enable', {
      'base': AppConfig.apiBaseUrl,
      'token': token,
      'user': '${user['id']}',
    });
  }

  static Future<void> disable() async {
    try {
      await _channel.invokeMethod('disable');
    } on MissingPluginException {
      /* Unsupported platform. */
    }
  }

  static Future<void> pause(bool paused) async {
    try {
      await _channel.invokeMethod('pause', {'paused': paused});
    } on MissingPluginException {
      /* Unsupported platform. */
    }
  }
}
