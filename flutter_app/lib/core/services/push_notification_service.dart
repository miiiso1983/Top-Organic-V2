import 'dart:convert';
import 'dart:async';
import 'dart:io';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:go_router/go_router.dart';
import 'package:permission_handler/permission_handler.dart';
import '../../firebase_options.dart';
import '../network/api_client.dart';
import '../utils/notification_router.dart';
import '../storage/auth_storage.dart';

/// خدمة الإشعارات الفورية (FCM + Local Notifications).
/// تُرسل توكن الجهاز إلى الخادم ليتمكن من إرسال Push Notifications،
/// وتفتح الواجهة المناسبة في التطبيق عند الضغط على الإشعار (Deep Linking).
class PushNotificationService {
  static final PushNotificationService _instance =
      PushNotificationService._internal();
  factory PushNotificationService() => _instance;
  PushNotificationService._internal();

  FirebaseMessaging? _messaging;
  final FlutterLocalNotificationsPlugin _localNotifications =
      FlutterLocalNotificationsPlugin();
  ApiClient? _apiClient;
  GoRouter? _router;
  bool _initialized = false;
  bool _loggingOut = false;
  bool _sessionReady = false;
  Map<String, dynamic>? _pendingNavigation;
  Future<void> _registration = Future<void>.value();
  final AuthStorage _storage = AuthStorage();

  void setSessionReady(bool ready) {
    _sessionReady = ready;
    if (ready && _pendingNavigation != null) {
      final data = _pendingNavigation!;
      _pendingNavigation = null;
      WidgetsBinding.instance.addPostFrameCallback(
        (_) => _navigateFromData(data),
      );
    }
  }

  Future<bool> _isIntendedRecipient(Map<String, dynamic> data) async {
    if (_loggingOut || await _storage.getToken() == null) return false;
    final user = await _storage.getUser();
    return notificationBelongsToUser(data, user?['id']);
  }

  /// يُستدعى عند استقبال إشعار (أثناء فتح التطبيق) لتحديث عدّاد غير المقروء فوراً
  void Function()? onNotificationReceived;

  Future<void> initialize(ApiClient apiClient, {GoRouter? router}) async {
    _router = router ?? _router;
    if (!kFirebaseConfigured) {
      debugPrint('Firebase not configured. Push notifications disabled.');
      return;
    }
    if (_initialized) return;
    _apiClient = apiClient;

    try {
      await Firebase.initializeApp(
        options: DefaultFirebaseOptions.currentPlatform,
      );
    } catch (e, s) {
      debugPrint('Firebase initialization failed: $e\n$s');
      return;
    }

    _messaging = FirebaseMessaging.instance;

    // طلب إذن الإشعارات
    if (Platform.isAndroid) {
      await Permission.notification.request();
    } else if (Platform.isIOS) {
      await _messaging?.requestPermission();
    }

    // قناة الإشعارات على Android — يجب أن تطابق channel_id المرسل من الخادم
    const AndroidNotificationChannel channel = AndroidNotificationChannel(
      'ptt_channel',
      'الإشعارات',
      description: 'قناة الإشعارات العامة للتطبيق',
      importance: Importance.max,
      playSound: true,
    );
    await _localNotifications
        .resolvePlatformSpecificImplementation<
          AndroidFlutterLocalNotificationsPlugin
        >()
        ?.createNotificationChannel(channel);

    const InitializationSettings initSettings = InitializationSettings(
      android: AndroidInitializationSettings('@mipmap/ic_launcher'),
      iOS: DarwinInitializationSettings(),
    );
    await _localNotifications.initialize(
      settings: initSettings,
      onDidReceiveNotificationResponse: _onLocalNotificationTap,
    );

    // Local notifications provide one foreground alert on iOS (avoid duplicate sounds).
    await _messaging?.setForegroundNotificationPresentationOptions(
      alert: false,
      badge: false,
      sound: false,
    );
    final localLaunch = await _localNotifications
        .getNotificationAppLaunchDetails();
    if (localLaunch?.didNotificationLaunchApp == true &&
        localLaunch?.notificationResponse != null) {
      _onLocalNotificationTap(localLaunch!.notificationResponse!);
    }

    // الإشعارات أثناء فتح التطبيق
    FirebaseMessaging.onMessage.listen(_showLocalNotification);

    // الإشعارات في الخلفية
    FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);

    // الضغط على الإشعار والتطبيق في الخلفية (وليس مغلقاً تماماً)
    FirebaseMessaging.onMessageOpenedApp.listen(
      (m) => _navigateFromData(m.data),
    );

    // الضغط على الإشعار وقد كان التطبيق مغلقاً تماماً (بدء بارد)
    final initialMessage = await _messaging?.getInitialMessage();
    if (initialMessage != null) {
      _navigateFromData(initialMessage.data);
    }

    // التوكن
    await _handleToken();
    _messaging?.onTokenRefresh.listen(_sendToken);

    _initialized = true;
  }

  /// تعيين موجّه التنقل (GoRouter) بعد إنشائه (يُستدعى من main.dart)
  void attachRouter(GoRouter router) {
    _router = router;
  }

  void _onLocalNotificationTap(NotificationResponse response) {
    final payload = response.payload;
    if (payload == null || payload.isEmpty) return;
    try {
      final data = Map<String, dynamic>.from(jsonDecode(payload) as Map);
      _navigateFromData(data);
    } catch (e) {
      debugPrint('Failed to parse notification payload: $e');
    }
  }

  Future<void> _navigateFromData(Map<String, dynamic> data) async {
    if (!_sessionReady) {
      _pendingNavigation = data;
      return;
    }
    if (_router == null || !await _isIntendedRecipient(data)) return;
    final route = resolveNotificationRoute(
      entityType: data['entity_type']?.toString(),
      actionType: data['action_type']?.toString(),
      link: data['link']?.toString(),
    );
    _router!.go(route);
  }

  Future<void> sendCurrentToken() async {
    _loggingOut = false;
    if (!_initialized) return;
    try {
      await _handleToken();
    } catch (e) {
      debugPrint('Push registration unavailable: $e');
    }
  }

  Future<void> _handleToken() async {
    final token = await _messaging?.getToken();
    if (token != null) await _sendToken(token);
  }

  Future<void> _sendToken(String token) {
    _registration = _registration.then((_) => _registerToken(token)).catchError(
      (Object e) {
        debugPrint('Push registration unavailable: $e');
      },
    );
    return _registration;
  }

  Future<void> _registerToken(String token) async {
    if (_apiClient == null || _loggingOut || await _storage.getToken() == null)
      return;
    try {
      await _apiClient!.post(
        '/push/subscribe',
        data: {
          'endpoint': token,
          'platform': Platform.isIOS ? 'ios' : 'android',
          'keys': <String, String>{},
        },
      );
      debugPrint('FCM token registered with backend');
    } catch (e) {
      debugPrint('Failed to send FCM token: $e');
    }
  }

  /// Called before clearing credentials, so unsubscribe is authenticated as the old account.
  Future<void> unregisterCurrentDevice() async {
    _loggingOut = true;
    _sessionReady = false;
    _pendingNavigation = null;
    await _registration;
    try {
      final token = await _messaging?.getToken();
      if (token != null &&
          _apiClient != null &&
          await _storage.getToken() != null) {
        await _apiClient!.post('/push/unsubscribe', data: {'endpoint': token});
      }
    } catch (e) {
      debugPrint('Push unsubscribe unavailable: $e');
    } finally {
      // Token rotation also prevents the previous account receiving notifications
      // if the logout request failed because the device was offline.
      try {
        await _messaging?.deleteToken();
      } catch (_) {}
      try {
        await _localNotifications.cancelAll();
      } catch (_) {}
    }
  }

  Future<void> _showLocalNotification(RemoteMessage message) async {
    if (!await _isIntendedRecipient(message.data)) return;
    onNotificationReceived?.call();
    final notification = message.notification;
    if (notification == null) return;
    final AndroidNotification? android = message.notification?.android;
    await _localNotifications.show(
      id: message.hashCode,
      title: notification.title,
      body: notification.body,
      notificationDetails: NotificationDetails(
        android: AndroidNotificationDetails(
          'ptt_channel',
          'الإشعارات',
          channelDescription: 'قناة الإشعارات العامة للتطبيق',
          importance: Importance.max,
          priority: Priority.high,
          playSound: true,
          icon: android?.smallIcon,
        ),
        iOS: const DarwinNotificationDetails(
          presentAlert: true,
          presentBanner: true,
          presentList: true,
          presentSound: true,
        ),
      ),
      payload: message.data.isNotEmpty ? jsonEncode(message.data) : null,
    );
  }
}

@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  if (!kFirebaseConfigured) return;
  try {
    await Firebase.initializeApp(
      options: DefaultFirebaseOptions.currentPlatform,
    );
  } catch (e) {
    debugPrint('Firebase background init failed: $e');
  }
  debugPrint('Background message: ${message.messageId}');
}
