package com.toporganic.app

import android.app.*
import android.content.*
import android.media.*
import android.os.*
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL
import java.util.concurrent.Executors

/** Explicitly enabled listening session. No microphone capture, no exported entry points. */
class PttReceiverService : Service() {
    companion object {
        @Volatile var running = false
        @Volatile var paused = false
        @Volatile var speaker = ""
        @Volatile var problem = ""
        const val CHANNEL = "ptt_receiver"
        const val ID = 7401
    }
    private val worker = Executors.newSingleThreadExecutor()
    private val main = Handler(Looper.getMainLooper())
    private var player: MediaPlayer? = null
    private var focus: AudioFocusRequest? = null
    private var active = false
    private var generation = 0
    private var playback = 0
    private var cursor: Long? = null
    private var token = ""
    private var base = ""
    private val pending = java.util.ArrayDeque<JSONObject>()
    private var playing = false
    private val poll = object : Runnable {
        override fun run() {
            if (!active) return
            val epoch = generation
            val auth = token
            val endpoint = base + "/ptt/delivery" + (cursor?.let { "?after=$it" } ?: "")
            worker.execute {
                try {
                    val json = JSONObject(request(endpoint, auth = auth)).getJSONObject("data")
                    main.post {
                        if (!active || epoch != generation) return@post
                        val rows = json.getJSONArray("messages")
                        for (i in 0 until rows.length()) pending.add(rows.getJSONObject(i))
                        cursor = json.getLong("cursor")
                        problem = ""
                        playNext()
                        main.postDelayed(this, if (json.optBoolean("has_more")) 100 else 3000)
                    }
                } catch (e: Exception) {
                    main.post {
                        if (!active || epoch != generation) return@post
                        if (e.message == "HTTP 401" || e.message == "HTTP 403") stopSelf()
                        else { problem = "تعذر الاتصال، جارٍ إعادة المحاولة"; main.postDelayed(this, 8000) }
                    }
                }
            }
        }
    }
    override fun onBind(intent: Intent?) = null
    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        if (intent?.action == "STOP") { stopSelf(); return START_NOT_STICKY }
        if (intent?.action == "PAUSE") {
            paused = intent.getBooleanExtra("paused", false)
            if (paused && playing) { player?.stop(); finish(false) }
            if (!paused) playNext()
            return START_NOT_STICKY
        }
        val newBase = intent?.getStringExtra("base") ?: return START_NOT_STICKY
        val newToken = intent.getStringExtra("token") ?: return START_NOT_STICKY
        if (!newBase.startsWith("https://") || newToken.isEmpty()) { stopSelf(); return START_NOT_STICKY }
        val nm = getSystemService(NotificationManager::class.java)
        if (Build.VERSION.SDK_INT >= 26) nm.createNotificationChannel(NotificationChannel(CHANNEL, "استقبال PTT", NotificationManager.IMPORTANCE_LOW))
        val stop = PendingIntent.getService(this, 0, Intent(this, PttReceiverService::class.java).setAction("STOP"), PendingIntent.FLAG_IMMUTABLE)
        val open = PendingIntent.getActivity(this, 1, Intent(this, MainActivity::class.java), PendingIntent.FLAG_IMMUTABLE)
        val builder = if (Build.VERSION.SDK_INT >= 26) Notification.Builder(this, CHANNEL) else Notification.Builder(this)
        startForeground(ID, builder.setSmallIcon(applicationInfo.icon).setContentTitle("استقبال PTT مفعّل")
            .setContentText("تشغيل الرسائل الصوتية الواردة تلقائياً").setContentIntent(open).setOngoing(true)
            .addAction(Notification.Action.Builder(null, "إيقاف", stop).build()).build())
        if (active && newToken == token && newBase == base) return START_NOT_STICKY
        generation++; playback++; main.removeCallbacks(poll); player?.release(); player = null; pending.clear(); playing = false
        if (Build.VERSION.SDK_INT >= 26) focus?.let { getSystemService(AudioManager::class.java).abandonAudioFocusRequest(it) }; focus = null
        token = newToken; base = newBase.trimEnd('/'); cursor = null; active = true; running = true; paused = false; problem = ""
        main.post(poll)
        return START_NOT_STICKY
    }
    private fun request(url: String, method: String = "GET", auth: String): String {
        val c = URL(url).openConnection() as HttpURLConnection
        c.instanceFollowRedirects = false; c.connectTimeout = 10000; c.readTimeout = 15000
        c.requestMethod = method; c.setRequestProperty("Authorization", "Bearer $auth")
        try {
            if (c.responseCode !in 200..299) throw IllegalStateException("HTTP ${c.responseCode}")
            return c.inputStream.bufferedReader().use { it.readText() }
        } finally { c.disconnect() }
    }
    private fun playNext() {
        if (!active || playing || paused || pending.isEmpty()) return
        val message = pending.removeFirst(); val epoch = generation; val id = message.getLong("id")
        val auth = token; val host = base
        val playbackId = ++playback
        playing = true; speaker = message.optString("sender_name")
        // Download authenticated audio first: membership/mute changes are checked before playback.
        worker.execute {
            var bytes: ByteArray? = null
            try {
                val c = URL("$host/ptt/messages/$id/audio").openConnection() as HttpURLConnection
                c.instanceFollowRedirects = false; c.connectTimeout = 10000; c.readTimeout = 20000
                c.setRequestProperty("Authorization", "Bearer $auth")
                try { if (c.responseCode == 200) bytes = c.inputStream.use { input ->
                    val output = java.io.ByteArrayOutputStream(); val buffer = ByteArray(8192)
                    var n = input.read(buffer)
                    while (n >= 0) { if (output.size() + n > 16 * 1024 * 1024) throw IllegalStateException("Audio too large"); output.write(buffer,0,n); n=input.read(buffer) }
                    output.toByteArray()
                } } finally { c.disconnect() }
            } catch (_: Exception) {}
            main.post {
                if (!active || epoch != generation || playbackId != playback) return@post
                if (paused) { finish(false); return@post }
                val audio = bytes
                if (audio == null || audio.isEmpty() || audio.size > 16 * 1024 * 1024) { finish(false); return@post }
                try {
                    val manager = getSystemService(AudioManager::class.java)
                    val attributes = AudioAttributes.Builder().setUsage(AudioAttributes.USAGE_MEDIA).setContentType(AudioAttributes.CONTENT_TYPE_SPEECH).build()
                    val listener = AudioManager.OnAudioFocusChangeListener { change -> if (change < 0 && playbackId == playback) { player?.stop(); finish(false) } }
                    val granted = if (Build.VERSION.SDK_INT >= 26) {
                        focus = AudioFocusRequest.Builder(AudioManager.AUDIOFOCUS_GAIN_TRANSIENT).setAudioAttributes(attributes).setOnAudioFocusChangeListener(listener).build()
                        manager.requestAudioFocus(focus!!)
                    } else { @Suppress("DEPRECATION") manager.requestAudioFocus(listener, AudioManager.STREAM_MUSIC, AudioManager.AUDIOFOCUS_GAIN_TRANSIENT) }
                    if (granted != AudioManager.AUDIOFOCUS_REQUEST_GRANTED) { finish(false); return@post }
                    val file = java.io.File.createTempFile("ptt_rx_", ".audio", cacheDir); file.writeBytes(audio)
                    val mp = MediaPlayer(); player = mp; mp.setAudioAttributes(attributes)
                    mp.setWakeMode(this, PowerManager.PARTIAL_WAKE_LOCK)
                    java.io.FileInputStream(file).use { mp.setDataSource(it.fd) }; file.delete()
                    mp.setOnPreparedListener { if (active && !paused && playbackId == playback) it.start() else finish(false) }
                    mp.setOnCompletionListener {
                        if (playbackId != playback) return@setOnCompletionListener
                        if (active && epoch == generation) worker.execute { try { request("$host/ptt/messages/$id/played", "POST", auth) } catch (_: Exception) {} }
                        finish(true)
                    }
                    mp.setOnErrorListener { _, _, _ -> if (playbackId == playback) finish(false); true }
                    mp.prepareAsync()
                } catch (_: Exception) { finish(false) }
            }
        }
    }
    private fun finish(completed: Boolean) {
        playback++
        player?.release(); player = null; playing = false; speaker = ""
        if (Build.VERSION.SDK_INT >= 26) focus?.let { getSystemService(AudioManager::class.java).abandonAudioFocusRequest(it) }
        focus = null
        if (active) main.post { playNext() }
    }
    override fun onDestroy() {
        active = false; running = false; paused = false; generation++; main.removeCallbacksAndMessages(null)
        player?.release(); player = null; pending.clear(); token = ""; speaker = ""; worker.shutdownNow()
        if (Build.VERSION.SDK_INT >= 26) focus?.let { getSystemService(AudioManager::class.java).abandonAudioFocusRequest(it) }
        super.onDestroy()
    }
}
