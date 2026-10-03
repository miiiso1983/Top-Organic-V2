import 'package:workmanager/workmanager.dart';
import '../network/api_client.dart';
import 'sync_manager.dart';

const String kSyncTaskName = 'top_organic_background_sync';

/// نقطة الدخول لمهمة المزامنة في الخلفية (تُستدعى بواسطة WorkManager)
@pragma('vm:entry-point')
void backgroundSyncDispatcher() {
  Workmanager().executeTask((task, inputData) async {
    if (task == kSyncTaskName) {
      final api = ApiClient();
      final sync = SyncManager(api);
      await sync.syncNow();
    }
    return Future.value(true);
  });
}

/// تسجيل مهمة دورية للمزامنة في الخلفية (كل 15 دقيقة كحد أدنى في أندرويد)
Future<void> registerBackgroundSync() async {
  await Workmanager().initialize(backgroundSyncDispatcher);
  await Workmanager().registerPeriodicTask(
    kSyncTaskName,
    kSyncTaskName,
    frequency: const Duration(minutes: 15),
    constraints: Constraints(networkType: NetworkType.connected),
    existingWorkPolicy: ExistingPeriodicWorkPolicy.keep,
  );
}
