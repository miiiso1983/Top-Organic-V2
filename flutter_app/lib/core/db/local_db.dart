import 'package:sqflite/sqflite.dart';
import 'package:path/path.dart';

/// قاعدة بيانات محلية (Sqflite) للتخزين المؤقت والعمل دون اتصال
/// - cache: تخزين آخر نسخة من بيانات كل شاشة (JSON) لعرضها أوف لاين
/// - sync_queue: عمليات محلية بانتظار الإرسال للخادم عند توفر الاتصال
class LocalDb {
  LocalDb._();
  static final LocalDb instance = LocalDb._();
  Database? _db;

  Future<Database> get database async {
    _db ??= await _open();
    return _db!;
  }

  Future<Database> _open() async {
    final path = join(await getDatabasesPath(), 'top_organic.db');
    return openDatabase(
      path,
      version: 1,
      onCreate: (db, version) async {
        await db.execute('''
          CREATE TABLE cache (
            cache_key TEXT PRIMARY KEY,
            payload TEXT NOT NULL,
            updated_at INTEGER NOT NULL
          )
        ''');
        await db.execute('''
          CREATE TABLE sync_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            method TEXT NOT NULL,
            path TEXT NOT NULL,
            payload TEXT NOT NULL,
            entity TEXT NOT NULL,
            created_at INTEGER NOT NULL,
            synced INTEGER NOT NULL DEFAULT 0
          )
        ''');
      },
    );
  }

  // ---------- Cache ----------

  Future<void> putCache(String key, String jsonPayload) async {
    final db = await database;
    await db.insert('cache', {
      'cache_key': key,
      'payload': jsonPayload,
      'updated_at': DateTime.now().millisecondsSinceEpoch,
    }, conflictAlgorithm: ConflictAlgorithm.replace);
  }

  Future<String?> getCache(String key) async {
    final db = await database;
    final rows = await db.query(
      'cache',
      where: 'cache_key = ?',
      whereArgs: [key],
      limit: 1,
    );
    if (rows.isEmpty) return null;
    return rows.first['payload'] as String;
  }

  // ---------- Sync Queue ----------

  Future<int> enqueue({
    required String method,
    required String path,
    required String payloadJson,
    required String entity,
  }) async {
    final db = await database;
    return db.insert('sync_queue', {
      'method': method,
      'path': path,
      'payload': payloadJson,
      'entity': entity,
      'created_at': DateTime.now().millisecondsSinceEpoch,
      'synced': 0,
    });
  }

  Future<List<Map<String, Object?>>> pendingOperations() async {
    final db = await database;
    return db.query('sync_queue', where: 'synced = 0', orderBy: 'id ASC');
  }

  Future<void> markSynced(int id) async {
    final db = await database;
    await db.update(
      'sync_queue',
      {'synced': 1},
      where: 'id = ?',
      whereArgs: [id],
    );
  }

  Future<int> pendingCount() async {
    final db = await database;
    final result = await db.rawQuery(
      'SELECT COUNT(*) AS c FROM sync_queue WHERE synced = 0',
    );
    return Sqflite.firstIntValue(result) ?? 0;
  }
}
