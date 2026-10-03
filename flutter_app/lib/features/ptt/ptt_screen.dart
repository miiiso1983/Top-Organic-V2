import 'dart:async';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:record/record.dart';
import 'package:audioplayers/audioplayers.dart';
import 'package:dio/dio.dart';
import 'package:path/path.dart' as p;
import '../../core/config.dart';
import '../../core/data/cached_repository.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';
import '../../core/network/api_client.dart';
import '../../shared/utils/timezone_helper.dart';
import 'ptt_live_manager.dart';

class PttScreen extends StatefulWidget {
  const PttScreen({super.key});

  @override
  State<PttScreen> createState() => _PttScreenState();
}

class _PttScreenState extends State<PttScreen> {
  bool _loading = true,
      _loadingChannel = false,
      _recording = false,
      _uploading = false;
  String? _error;
  List<dynamic> _channels = [], _messages = [], _priorities = [];
  Map<String, dynamic>? _channel;
  int? _selectedChannelId, _playingId;
  bool _canSend = false;
  bool _liveConfigured = false;
  bool _canLivePublish = false;
  Map<String, dynamic>? _activeLive;
  int _maxDuration = 60;
  String _priority = 'normal';
  DateTime? _recordStart;
  Timer? _recordTimer;
  AudioRecorder? _recorder;
  AudioPlayer? _player;
  final PttLiveManager _liveManager = PttLiveManager();
  String _liveStatus = '';
  int _liveParticipantCount = 0;
  bool _liveConnecting = false;

  /// ترجمة نص حسب اللغة الحالية، قابلة للاستخدام خارج build() أيضاً
  String t(String key) =>
      AppStrings.t(key, context.read<LocaleProvider>().locale.languageCode);

  @override
  void initState() {
    super.initState();
    _liveStatus = t('ptt_live_status_disconnected');
    _liveManager.onConnected = () =>
        _setLiveStatus(t('ptt_live_status_connected'), 1);
    _liveManager.onDisconnected = () =>
        _setLiveStatus(t('ptt_live_status_disconnected'), 0);
    _liveManager.onParticipantChanged = (c) => _setLiveCount(c);
    _loadPtt();
  }

  void _setLiveStatus(String status, int count) {
    if (!mounted) return;
    setState(() {
      _liveStatus = status;
      _liveParticipantCount = count;
      _liveConnecting = false;
    });
  }

  void _setLiveCount(int count) {
    if (!mounted) return;
    setState(() => _liveParticipantCount = count);
  }

  @override
  void dispose() {
    _recordTimer?.cancel();
    _recorder?.dispose();
    _player?.dispose();
    _liveManager.disconnect();
    super.dispose();
  }

  Future<void> _loadPtt() async {
    final repo = context.read<CachedRepository>();
    setState(() => _loading = true);
    try {
      final res = await repo.getJson(
        '/ptt/channels',
        'ptt_index',
        ttlSeconds: 0,
      );
      if (!mounted) return;
      setState(() => _channels = (res.data['data'] as List?) ?? []);
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = t('ptt_channels_load_error'));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _selectChannel(dynamic id) async {
    final channelId = id is int ? id : int.tryParse(id.toString());
    if (channelId == null) return;
    final repo = context.read<CachedRepository>();
    setState(() {
      _selectedChannelId = channelId;
      _loadingChannel = true;
      _error = null;
    });
    try {
      final res = await repo.getJson(
        '/ptt/channels/$channelId/messages',
        'ptt_channel_$channelId',
        ttlSeconds: 0,
      );
      if (!mounted) return;
      final data = res.data['data'] as Map<String, dynamic>? ?? {};
      setState(() {
        _channel = data['channel'] as Map<String, dynamic>?;
        _messages = (data['messages'] as List?) ?? [];
        _canSend = data['canSend'] == true;
        _priorities = (data['priorities'] as List?) ?? [];
        _maxDuration = (data['maxDuration'] as int?) ?? 60;
        _priority = _priorities.isNotEmpty
            ? _priorities.first.toString()
            : 'normal';
        _liveConfigured = data['liveConfigured'] == true;
        _canLivePublish = data['canLivePublish'] == true;
        _activeLive = data['activeLive'] as Map<String, dynamic>?;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = t('ptt_channel_load_error'));
    } finally {
      if (mounted) setState(() => _loadingChannel = false);
    }
  }

  void _back() async {
    await _stopLive();
    if (mounted)
      setState(() {
        _selectedChannelId = null;
        _channel = null;
        _messages = [];
        _activeLive = null;
      });
  }

  Future<void> _play(dynamic m) async {
    final id = m['id'],
        url = m['audio_url']?.toString() ?? m['file_path']?.toString();
    if (url == null || url.isEmpty) return;
    final api = context.read<ApiClient>();
    await _player?.dispose();
    final player = AudioPlayer();
    setState(() {
      _player = player;
      _playingId = id;
    });
    player.onPlayerComplete.listen((_) {
      if (mounted) setState(() => _playingId = null);
    });
    try {
      await player.play(UrlSource('${AppConfig.baseUrl}$url'));
      await api.post('/ptt/messages/$id/played');
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = t('ptt_play_error'));
    }
  }

  Future<void> _startRecord() async {
    if (!_canSend || _uploading) return;
    if (!(await Permission.microphone.request()).isGranted) return;
    final recorder = AudioRecorder();
    final path = p.join(
      Directory.systemTemp.path,
      'ptt_${BaghdadTime.now().millisecondsSinceEpoch}.m4a',
    );
    try {
      await recorder.start(
        const RecordConfig(encoder: AudioEncoder.aacLc),
        path: path,
      );
      _recordTimer = Timer(Duration(seconds: _maxDuration), _stopRecord);
      setState(() {
        _recorder = recorder;
        _recording = true;
        _recordStart = BaghdadTime.now();
      });
    } catch (e) {
      await recorder.dispose();
      if (!mounted) return;
      setState(() => _error = t('ptt_record_start_error'));
    }
  }

  Future<void> _stopRecord() async {
    if (!_recording || _recorder == null) return;
    final recorder = _recorder!, start = _recordStart;
    String? path;
    try {
      path = await recorder.stop();
    } catch (e) {
      if (mounted) setState(() => _error = t('ptt_record_stop_error'));
    }
    _recordTimer?.cancel();
    _recordTimer = null;
    if (mounted)
      setState(() {
        _recording = false;
        _recorder = null;
        _recordStart = null;
      });
    await recorder.dispose();
    if (path != null && _selectedChannelId != null) await _upload(path, start);
  }

  Future<void> _upload(String path, DateTime? start) async {
    final duration = start != null
        ? BaghdadTime.now().difference(start).inSeconds
        : 0;
    final api = context.read<ApiClient>();
    setState(() => _uploading = true);
    try {
      final formData = FormData.fromMap({
        'channel_id': _selectedChannelId,
        'priority': _priority,
        'duration_seconds': duration,
        'audio': await MultipartFile.fromFile(path, filename: 'audio.m4a'),
      });
      await api.post('/ptt/send', data: formData);
      if (!mounted) return;
      if (_selectedChannelId != null) await _selectChannel(_selectedChannelId);
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = t('ptt_send_error'));
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
    try {
      final f = File(path);
      if (await f.exists()) await f.delete();
    } catch (_) {}
  }

  Future<void> _joinLive({required bool publish}) async {
    if (_selectedChannelId == null || !_liveConfigured) return;
    if (!(await Permission.microphone.request()).isGranted) {
      if (mounted) setState(() => _error = t('ptt_mic_permission_required'));
      return;
    }
    if (!mounted) return;
    setState(() => _liveConnecting = true);
    final api = context.read<ApiClient>();
    try {
      final res = await api.post(
        '/ptt/channels/$_selectedChannelId/live/token',
        data: {'publish': publish ? 1 : 0},
      );
      if (!mounted) return;
      final data = (res.data as Map)['data'] as Map<String, dynamic>?;
      if (data == null) throw Exception(t('ptt_no_livekit_token'));
      var url = data['url']?.toString() ?? '';
      if (url.startsWith('https://'))
        url = url.replaceFirst('https://', 'wss://');
      if (url.startsWith('http://')) url = url.replaceFirst('http://', 'ws://');
      await _liveManager.connect(
        url: url,
        token: data['token']!.toString(),
        publishing: publish,
      );
      if (!mounted) return;
      setState(() => _liveConnecting = false);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _liveConnecting = false;
        _error = '${t('ptt_live_failed_prefix')}$e';
      });
    }
  }

  Future<void> _stopLive() async {
    await _liveManager.disconnect();
    if (_selectedChannelId != null && mounted) {
      try {
        final api = context.read<ApiClient>();
        await api.post('/ptt/channels/$_selectedChannelId/live/stop');
      } catch (_) {}
    }
    if (mounted) _setLiveStatus(t('ptt_live_status_disconnected'), 0);
  }

  @override
  Widget build(BuildContext context) {
    context.watch<LocaleProvider>();
    return Scaffold(
      appBar: AppBar(
        title: Text(
          _selectedChannelId == null
              ? t('ptt_title')
              : (_channel?['name']?.toString() ??
                    t('ptt_channel_fallback_title')),
        ),
        leading: _selectedChannelId == null
            ? null
            : IconButton(icon: const Icon(Icons.arrow_back), onPressed: _back),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null && _channels.isEmpty && _messages.isEmpty
          ? _errorWidget()
          : _selectedChannelId == null
          ? _channelsList()
          : _channelDetail(),
    );
  }

  Widget _errorWidget() => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Text(
        _error!,
        textAlign: TextAlign.center,
        style: const TextStyle(color: Colors.red),
      ),
    ),
  );

  Widget _channelsList() {
    if (_channels.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Text(
            t('ptt_no_channels_available'),
            textAlign: TextAlign.center,
            style: const TextStyle(color: Colors.grey, fontSize: 16),
          ),
        ),
      );
    }
    return ListView.builder(
      itemCount: _channels.length,
      itemBuilder: (context, i) {
        final c = _channels[i];
        final unread = (c['unread_count'] as int?) ?? 0;
        return ListTile(
          leading: const Icon(Icons.chat_bubble_outline),
          title: Text(c['name']?.toString() ?? '—'),
          subtitle: Text(c['description']?.toString() ?? ''),
          trailing: unread > 0
              ? Chip(label: Text('$unread'), padding: EdgeInsets.zero)
              : null,
          onTap: () => _selectChannel(c['id']),
        );
      },
    );
  }

  Widget _channelDetail() {
    if (_loadingChannel)
      return const Center(child: CircularProgressIndicator());
    return Column(
      children: [
        if (_priorities.length > 1)
          Padding(
            padding: const EdgeInsets.all(8),
            child: InputDecorator(
              decoration: InputDecoration(labelText: t('ptt_priority_label')),
              child: DropdownButtonHideUnderline(
                child: DropdownButton<String>(
                  value: _priority,
                  isExpanded: true,
                  items: _priorities
                      .map(
                        (p) => DropdownMenuItem(
                          value: p.toString(),
                          child: Text(p.toString()),
                        ),
                      )
                      .toList(),
                  onChanged: (v) => setState(() => _priority = v ?? 'normal'),
                ),
              ),
            ),
          ),
        if (_liveConfigured) _livePanel(),
        Expanded(
          child: _messages.isEmpty
              ? _emptyMessages()
              : ListView.builder(
                  reverse: true,
                  itemCount: _messages.length,
                  itemBuilder: (context, i) => _messageTile(_messages[i]),
                ),
        ),
        _talkBar(),
      ],
    );
  }

  Widget _emptyMessages() => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Text(
        _canSend ? t('ptt_empty_can_send') : t('ptt_empty_cannot_send'),
        textAlign: TextAlign.center,
        style: const TextStyle(color: Colors.grey, fontSize: 15),
      ),
    ),
  );

  Widget _livePanel() {
    final isLive = _liveManager.isConnected;
    final active = _activeLive != null;
    return Card(
      margin: const EdgeInsets.all(8),
      color: active ? Colors.red.shade50 : null,
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Icon(
                  Icons.broadcast_on_home,
                  color: active ? Colors.red : Colors.grey,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    '${t('ptt_live_label_prefix')}$_liveStatus ($_liveParticipantCount${t('ptt_live_participant_suffix')}',
                    style: TextStyle(
                      color: active ? Colors.red : Colors.grey.shade700,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
                if (active)
                  Container(
                    width: 8,
                    height: 8,
                    decoration: const BoxDecoration(
                      color: Colors.red,
                      shape: BoxShape.circle,
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 8),
            if (isLive)
              Row(
                children: [
                  Expanded(
                    child: _liveManager.isPublishing
                        ? Listener(
                            onPointerDown: (_) => _liveManager.startTalking(),
                            onPointerUp: (_) => _liveManager.stopTalking(),
                            onPointerCancel: (_) => _liveManager.stopTalking(),
                            child: Container(
                              height: 64,
                              decoration: BoxDecoration(
                                color: _liveManager.isTalking
                                    ? Colors.red
                                    : Colors.orange,
                                borderRadius: BorderRadius.circular(12),
                              ),
                              alignment: Alignment.center,
                              child: Text(
                                _liveManager.isTalking
                                    ? t('ptt_talking_now')
                                    : t('ptt_hold_to_broadcast'),
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 16,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ),
                          )
                        : const SizedBox.shrink(),
                  ),
                  const SizedBox(width: 8),
                  ElevatedButton.icon(
                    onPressed: _liveConnecting ? null : _stopLive,
                    icon: const Icon(Icons.stop),
                    label: Text(t('ptt_stop_button')),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.grey.shade700,
                    ),
                  ),
                ],
              )
            else
              Row(
                children: [
                  Expanded(
                    child: ElevatedButton.icon(
                      onPressed: _liveConnecting
                          ? null
                          : () => _joinLive(publish: false),
                      icon: const Icon(Icons.headphones),
                      label: Text(t('ptt_live_listen_button')),
                    ),
                  ),
                  const SizedBox(width: 8),
                  if (_canLivePublish)
                    Expanded(
                      child: ElevatedButton.icon(
                        onPressed: _liveConnecting
                            ? null
                            : () => _joinLive(publish: true),
                        icon: const Icon(Icons.mic),
                        label: Text(t('ptt_live_start_button')),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.red,
                        ),
                      ),
                    ),
                ],
              ),
          ],
        ),
      ),
    );
  }

  Widget _talkBar() {
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Listener(
          onPointerDown: (_) {
            if (_canSend) _startRecord();
          },
          onPointerUp: (_) => _stopRecord(),
          onPointerCancel: (_) => _stopRecord(),
          child: Container(
            height: 72,
            decoration: BoxDecoration(
              color: _recording
                  ? Colors.red
                  : _canSend
                  ? Colors.green
                  : Colors.grey,
              borderRadius: BorderRadius.circular(16),
            ),
            alignment: Alignment.center,
            child: _uploading
                ? const SizedBox(
                    width: 24,
                    height: 24,
                    child: CircularProgressIndicator(
                      color: Colors.white,
                      strokeWidth: 2,
                    ),
                  )
                : Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        _recording ? Icons.mic : Icons.mic_none,
                        color: Colors.white,
                        size: 32,
                      ),
                      const SizedBox(width: 8),
                      Text(
                        _recording
                            ? t('ptt_talking')
                            : (_canSend
                                  ? t('ptt_hold_to_talk')
                                  : t('ptt_no_send_permission')),
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ],
                  ),
          ),
        ),
      ),
    );
  }

  Widget _messageTile(dynamic m) {
    final id = m['id'],
        sender =
            m['sender']?['name']?.toString() ??
            m['sender_name']?.toString() ??
            '—';
    final priority = m['priority']?.toString() ?? 'normal';
    final color = priority == 'emergency'
        ? Colors.red
        : priority == 'high'
        ? Colors.orange
        : Colors.green;
    return ListTile(
      leading: CircleAvatar(
        backgroundColor: color,
        child: const Icon(Icons.volume_up, color: Colors.white),
      ),
      title: Text(sender),
      subtitle: Text('$priority • ${m['created_at']?.toString() ?? ''}'),
      trailing: _playingId == id
          ? const SizedBox(
              width: 20,
              height: 20,
              child: CircularProgressIndicator(strokeWidth: 2),
            )
          : IconButton(
              icon: const Icon(Icons.play_arrow),
              onPressed: () => _play(m),
            ),
    );
  }
}
