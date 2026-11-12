import 'package:flutter/foundation.dart';

class UpdatePolicy {
  const UpdatePolicy({
    required this.platform,
    required this.minimumBuild,
    required this.enabled,
    this.version = '',
  });
  final String platform;
  final int minimumBuild;
  final bool enabled;
  final String version;
  static UpdatePolicy? parse(dynamic data) {
    if (data is! Map ||
        !['android', 'ios'].contains(data['platform']) ||
        data['minimum_build'] is! int ||
        data['enabled'] is! bool)
      return null;
    return UpdatePolicy(
      platform: data['platform'] as String,
      minimumBuild: data['minimum_build'] as int,
      enabled: data['enabled'] as bool,
      version: data['version']?.toString() ?? '',
    );
  }

  bool blocks(String device, int build) =>
      enabled && platform == device && minimumBuild > 0 && build < minimumBuild;
  Map<String, dynamic> toJson() => {
    'platform': platform,
    'minimum_build': minimumBuild,
    'enabled': enabled,
    'version': version,
  };
  // Store URLs are fixed in the app: a server message cannot redirect users to an arbitrary download.
  Uri get store => Uri.parse(
    platform == 'ios'
        ? 'https://apps.apple.com/app/id6798642181'
        : 'https://play.google.com/store/apps/details?id=com.toporganic.top_organic_app',
  );
}

class UpdateRequiredSignal {
  static final policy = ValueNotifier<UpdatePolicy?>(null);
  static void accept(dynamic data) {
    final value = UpdatePolicy.parse(data);
    if (value != null) policy.value = value;
  }
}
