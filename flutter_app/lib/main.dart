import 'dart:async';
import 'dart:developer' as developer;
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:provider/provider.dart';
import 'package:go_router/go_router.dart';
import 'core/theme.dart';
import 'core/config.dart';
import 'core/network/api_client.dart';
import 'core/storage/auth_storage.dart';
import 'core/data/cached_repository.dart';
import 'core/sync/sync_manager.dart';
import 'core/sync/background_sync.dart';
import 'core/services/push_notification_service.dart';
import 'core/locale/locale_provider.dart';
import 'features/auth/auth_provider.dart';
import 'features/notifications/notifications_provider.dart';
import 'app/router.dart';

void main() {
  runZonedGuarded(
    () async {
      WidgetsFlutterBinding.ensureInitialized();

      FlutterError.onError = (FlutterErrorDetails details) {
        FlutterError.presentError(details);
        developer.log(
          'FLUTTER ERROR: ${details.exception}',
          error: details.exception,
          stackTrace: details.stack,
          name: 'TopOrganicApp',
        );
      };

      ErrorWidget.builder = (FlutterErrorDetails details) {
        return Material(
          child: Container(
            padding: const EdgeInsets.all(24),
            color: Colors.white,
            child: Center(
              child: Text(
                'حدث خطأ في التطبيق:\n${details.exception}',
                textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.red, fontSize: 16),
                textDirection: TextDirection.rtl,
              ),
            ),
          ),
        );
      };

      runApp(const TopOrganicApp());
      // تسجيل مهمة المزامنة الدورية في الخلفية (يتجاهل الأخطاء على المنصات غير المدعومة كالويب/سطح المكتب)
      registerBackgroundSync().catchError((_) {});
    },
    (error, stack) {
      developer.log(
        'ZONE ERROR: $error',
        error: error,
        stackTrace: stack,
        name: 'TopOrganicApp',
      );
    },
  );
}

class TopOrganicApp extends StatefulWidget {
  const TopOrganicApp({super.key});

  @override
  State<TopOrganicApp> createState() => _TopOrganicAppState();
}

class _TopOrganicAppState extends State<TopOrganicApp> {
  late final AuthStorage _authStorage;
  late final ApiClient _apiClient;
  late final CachedRepository _repository;
  late final SyncManager _syncManager;
  late final AuthProvider _authProvider;
  late final NotificationsProvider _notificationsProvider;
  late final LocaleProvider _localeProvider;
  late final GoRouter _router;

  @override
  void initState() {
    super.initState();
    _authStorage = AuthStorage();
    _apiClient = ApiClient(authStorage: _authStorage);
    _repository = CachedRepository(_apiClient);
    _syncManager = SyncManager(_apiClient);
    _authProvider = AuthProvider(_apiClient, _authStorage);
    _notificationsProvider = NotificationsProvider();
    _localeProvider = LocaleProvider();
    _localeProvider.load();
    _apiClient.onUnauthorized = () => _authProvider.logout();
    _authProvider.onLogin = () => _notificationsProvider.refresh(_apiClient);
    _router = buildRouter(_authProvider);
    _authProvider.addListener(() {
      PushNotificationService().setSessionReady(
        _authProvider.state == AuthState.authenticated,
      );
    });

    PushNotificationService().onNotificationReceived = () =>
        _notificationsProvider.bump();
    PushNotificationService().initialize(_apiClient, router: _router);

    _authProvider
        .bootstrap()
        .then((_) {
          PushNotificationService().sendCurrentToken();
          _notificationsProvider.startAutoRefresh(_apiClient);
        })
        .catchError((e, s) {
          developer.log(
            'BOOTSTRAP ERROR: $e',
            error: e,
            stackTrace: s,
            name: 'TopOrganicApp',
          );
        });
    try {
      _syncManager.start();
    } catch (e, s) {
      developer.log(
        'SYNC START ERROR: $e',
        error: e,
        stackTrace: s,
        name: 'TopOrganicApp',
      );
    }
  }

  @override
  void dispose() {
    _syncManager.dispose();
    _notificationsProvider.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        Provider<ApiClient>.value(value: _apiClient),
        Provider<CachedRepository>.value(value: _repository),
        Provider<SyncManager>.value(value: _syncManager),
        ChangeNotifierProvider<AuthProvider>.value(value: _authProvider),
        ChangeNotifierProvider<NotificationsProvider>.value(
          value: _notificationsProvider,
        ),
        ChangeNotifierProvider<LocaleProvider>.value(value: _localeProvider),
      ],
      child: Consumer<LocaleProvider>(
        builder: (context, localeProvider, _) => MaterialApp.router(
          title: AppConfig.appName,
          debugShowCheckedModeBanner: false,
          theme: AppTheme.light(),
          locale: localeProvider.locale,
          supportedLocales: const [Locale('ar'), Locale('en')],
          localizationsDelegates: const [
            GlobalMaterialLocalizations.delegate,
            GlobalWidgetsLocalizations.delegate,
            GlobalCupertinoLocalizations.delegate,
          ],
          builder: (context, child) => Directionality(
            textDirection: localeProvider.textDirection,
            child: child!,
          ),
          routerConfig: _router,
        ),
      ),
    );
  }
}
