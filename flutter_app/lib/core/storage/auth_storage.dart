import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// تخزين آمن لرمز الدخول وبيانات المستخدم وصلاحياته
class AuthStorage {
  static const _storage = FlutterSecureStorage();
  static const _tokenKey = 'auth_token';
  static const _userKey = 'auth_user';
  static const _permsKey = 'auth_permissions';

  Future<void> saveSession(
    String token,
    Map<String, dynamic> user, [
    List<dynamic>? permissions,
  ]) async {
    await _storage.write(key: _tokenKey, value: token);
    await _storage.write(key: _userKey, value: jsonEncode(user));
    await _storage.write(key: _permsKey, value: jsonEncode(permissions ?? []));
  }

  Future<String?> getToken() => _storage.read(key: _tokenKey);

  Future<Map<String, dynamic>?> getUser() async {
    final raw = await _storage.read(key: _userKey);
    if (raw == null) return null;
    return jsonDecode(raw) as Map<String, dynamic>;
  }

  Future<List<String>> getPermissions() async {
    final raw = await _storage.read(key: _permsKey);
    if (raw == null) return [];
    return (jsonDecode(raw) as List).map((e) => e.toString()).toList();
  }

  Future<void> savePermissions(List<dynamic> permissions) async {
    await _storage.write(key: _permsKey, value: jsonEncode(permissions));
  }

  Future<void> clear() async {
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _userKey);
    await _storage.delete(key: _permsKey);
  }
}
