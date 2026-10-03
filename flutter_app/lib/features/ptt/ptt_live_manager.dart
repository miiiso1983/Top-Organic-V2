import 'package:flutter/material.dart';
import 'package:livekit_client/livekit_client.dart' as lk;

typedef PttLiveParticipantCallback = void Function(int count);

/// مدير اتصال البث المباشر عبر LiveKit في PTT.
class PttLiveManager {
  lk.Room? _room;
  lk.CancelListenFunc? _cancelSub;
  bool _publishing = false;
  bool _talking = false;
  bool _connecting = false;

  bool get isConnected =>
      _room?.connectionState == lk.ConnectionState.connected;
  bool get isConnecting => _connecting;
  bool get isPublishing => _publishing;
  bool get isTalking => _talking;
  int get participantCount => (_room?.remoteParticipants.length ?? 0) + 1;

  VoidCallback? onConnected;
  VoidCallback? onDisconnected;
  PttLiveParticipantCallback? onParticipantChanged;

  Future<void> connect({
    required String url,
    required String token,
    required bool publishing,
  }) async {
    if (_connecting || isConnected) return;
    _connecting = true;
    _publishing = publishing;
    try {
      await _room?.dispose();
      _room = lk.Room(
        roomOptions: const lk.RoomOptions(
          adaptiveStream: false,
          dynacast: false,
          defaultAudioCaptureOptions: lk.AudioCaptureOptions(
            echoCancellation: true,
            noiseSuppression: true,
          ),
        ),
      );
      _cancelSub?.call();
      _cancelSub = _room!.events.listen(_onEvent);
      await _room!.connect(url, token);
      if (publishing) {
        // أنشر الميكروفون لكن اجعله مكتوماً (وضع Walkie-Talkie).
        await _room!.localParticipant?.setMicrophoneEnabled(true);
        await _room!.localParticipant?.setMicrophoneEnabled(false);
      }
      onConnected?.call();
      onParticipantChanged?.call(participantCount);
    } finally {
      _connecting = false;
    }
  }

  Future<void> disconnect() async {
    _connecting = false;
    _talking = false;
    _cancelSub?.call();
    _cancelSub = null;
    await _room?.dispose();
    _room = null;
    onDisconnected?.call();
  }

  Future<void> startTalking() async {
    if (!_publishing || _talking || !isConnected) return;
    _talking = true;
    await _room?.localParticipant?.setMicrophoneEnabled(true);
  }

  Future<void> stopTalking() async {
    if (!_publishing || !_talking || !isConnected) return;
    _talking = false;
    await _room?.localParticipant?.setMicrophoneEnabled(false);
  }

  void _onEvent(lk.RoomEvent event) {
    if (event is lk.ParticipantConnectedEvent ||
        event is lk.ParticipantDisconnectedEvent) {
      onParticipantChanged?.call(participantCount);
    } else if (event is lk.RoomDisconnectedEvent) {
      disconnect();
    }
  }
}
