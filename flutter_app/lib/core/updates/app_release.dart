import 'package:flutter/foundation.dart';
import 'package:package_info_plus/package_info_plus.dart';

class AppRelease {
  const AppRelease(this.platform, this.build, this.version);
  final String platform;
  final int build;
  final String version;
  static Future<AppRelease>? _current;
  static Future<AppRelease> current() => _current ??= _load();
  static Future<AppRelease> _load() async {
    final platform = kIsWeb
        ? 'web'
        : switch (defaultTargetPlatform) {
            TargetPlatform.android => 'android',
            TargetPlatform.iOS => 'ios',
            _ => 'other',
          };
    if (platform != 'android' && platform != 'ios')
      return AppRelease(platform, 0, '');
    final info = await PackageInfo.fromPlatform();
    return AppRelease(
      platform,
      int.tryParse(info.buildNumber) ?? 0,
      info.version,
    );
  }

  Map<String, String> get headers => {
    'X-App-Platform': platform,
    'X-App-Build': '$build',
    'X-App-Version': version,
  };
}
