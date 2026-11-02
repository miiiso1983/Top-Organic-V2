package com.toporganic.app

import android.content.Intent
import android.os.Build
import io.flutter.embedding.android.FlutterFragmentActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterFragmentActivity() {
    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "toporganic/ptt_receiver").setMethodCallHandler { call, result ->
            when (call.method) {
                "enable" -> {
                    val intent = Intent(this, PttReceiverService::class.java)
                        .putExtra("base", call.argument<String>("base"))
                        .putExtra("token", call.argument<String>("token"))
                    try {
                        if (Build.VERSION.SDK_INT >= 26) startForegroundService(intent) else startService(intent)
                        result.success(true)
                    } catch (e: Exception) { result.error("ptt_start", "تعذر بدء الاستقبال في الخلفية", null) }
                }
                "disable" -> { stopService(Intent(this, PttReceiverService::class.java)); result.success(true) }
                "pause" -> {
                    if (PttReceiverService.running) startService(Intent(this, PttReceiverService::class.java).setAction("PAUSE").putExtra("paused", call.argument<Boolean>("paused") ?: false))
                    result.success(true)
                }
                "state" -> result.success(mapOf("enabled" to PttReceiverService.running, "speaker" to PttReceiverService.speaker, "error" to PttReceiverService.problem))
                else -> result.notImplemented()
            }
        }
    }
}
