import 'dart:io';
import 'package:camera/camera.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_mlkit_face_detection/google_mlkit_face_detection.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:provider/provider.dart';
import '../../core/locale/locale_provider.dart';
import '../../core/locale/app_strings.dart';

/// شاشة تحدي الحيوية: تطلب من المستخدم فعلاً (غمض/ابتسم/حرك الرأس)
/// وتلتقط صورة بعد التحقق من الحركة لمنع خداع الصور المطبوعة أو الشاشات.
class LivenessCameraScreen extends StatefulWidget {
  const LivenessCameraScreen({super.key});

  @override
  State<LivenessCameraScreen> createState() => _LivenessCameraScreenState();
}

class _LivenessCameraScreenState extends State<LivenessCameraScreen> {
  CameraController? _controller;
  int _cameraIndex = -1;
  List<CameraDescription> _cameras = [];
  final FaceDetector _faceDetector = FaceDetector(
    options: FaceDetectorOptions(
      enableClassification: true,
      enableTracking: true,
      performanceMode: FaceDetectorMode.fast,
    ),
  );

  bool _isDetecting = false;
  bool _completed = false;
  String _challenge = '';
  String _instruction = '';
  String? _initError;

  // حالات التحدي
  bool _eyesWereClosed = false;
  bool _smileWasDetected = false;
  bool _turnStartDetected = false;

  static const Map<DeviceOrientation, int> _orientations = {
    DeviceOrientation.portraitUp: 0,
    DeviceOrientation.landscapeLeft: 90,
    DeviceOrientation.portraitDown: 180,
    DeviceOrientation.landscapeRight: 270,
  };

  @override
  void initState() {
    super.initState();
    _pickChallenge();
    _initCamera();
  }

  void _pickChallenge() {
    final lang = context.read<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    final challenges = ['blink', 'smile', 'turn_left', 'turn_right'];
    _challenge =
        challenges[DateTime.now().millisecondsSinceEpoch % challenges.length];
    final instructions = {
      'blink': t('hr_liveness_instruction_blink'),
      'smile': t('hr_liveness_instruction_smile'),
      'turn_left': t('hr_liveness_instruction_turn_left'),
      'turn_right': t('hr_liveness_instruction_turn_right'),
    };
    _instruction =
        instructions[_challenge] ?? t('hr_liveness_instruction_default');
  }

  Future<void> _initCamera() async {
    final lang = context.read<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    setState(() => _initError = null);
    try {
      // طلب إذن الكاميرا من داخل الشاشة نفسها كضمان إضافي —
      // بدون الإذن تفشل التهيئة وتبقى الشاشة سوداء
      var status = await Permission.camera.status;
      if (!status.isGranted) {
        status = await Permission.camera.request();
      }
      if (!status.isGranted) {
        if (!mounted) return;
        setState(() => _initError = t('hr_liveness_camera_permission_denied'));
        return;
      }

      _cameras = await availableCameras();
      if (_cameras.isEmpty) {
        if (!mounted) return;
        setState(() => _initError = t('hr_liveness_no_camera_found'));
        return;
      }
      for (var i = 0; i < _cameras.length; i++) {
        if (_cameras[i].lensDirection == CameraLensDirection.front) {
          _cameraIndex = i;
          break;
        }
      }
      if (_cameraIndex == -1) _cameraIndex = 0;

      final camera = _cameras[_cameraIndex];
      _controller = CameraController(
        camera,
        ResolutionPreset.medium,
        enableAudio: false,
        imageFormatGroup: Platform.isAndroid
            ? ImageFormatGroup.nv21
            : ImageFormatGroup.bgra8888,
      );
      await _controller!.initialize().timeout(const Duration(seconds: 12));
      if (!mounted) return;
      setState(() {});
      await _controller!.startImageStream(_processImage);
    } catch (e) {
      debugPrint('Camera init error: $e');
      if (!mounted) return;
      setState(() => _initError = _friendlyCameraError(e));
    }
  }

  String _friendlyCameraError(Object e) {
    final lang = context.read<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    final msg = e.toString().toLowerCase();
    if (msg.contains('accessdenied') || msg.contains('permission')) {
      return t('hr_liveness_access_denied');
    }
    if (msg.contains('timeout')) {
      return t('hr_liveness_timeout');
    }
    return t('hr_liveness_generic_error');
  }

  Future<void> _processImage(CameraImage image) async {
    if (_isDetecting || _completed || _controller == null) return;
    _isDetecting = true;
    try {
      final inputImage = _inputImageFromCameraImage(image);
      if (inputImage == null) return;
      final faces = await _faceDetector.processImage(inputImage);
      if (faces.isEmpty) return;
      _checkChallenge(faces.first);
    } catch (e) {
      debugPrint('Liveness error: $e');
    } finally {
      _isDetecting = false;
    }
  }

  InputImage? _inputImageFromCameraImage(CameraImage image) {
    final camera = _cameras[_cameraIndex];
    final sensorOrientation = camera.sensorOrientation;
    InputImageRotation? rotation;
    if (Platform.isIOS) {
      rotation = InputImageRotationValue.fromRawValue(sensorOrientation);
    } else if (Platform.isAndroid) {
      var rotationCompensation =
          _orientations[_controller!.value.deviceOrientation];
      if (rotationCompensation == null) return null;
      if (camera.lensDirection == CameraLensDirection.front) {
        rotationCompensation = (sensorOrientation + rotationCompensation) % 360;
      } else {
        rotationCompensation =
            (sensorOrientation - rotationCompensation + 360) % 360;
      }
      rotation = InputImageRotationValue.fromRawValue(rotationCompensation);
    }
    if (rotation == null) return null;

    final format = InputImageFormatValue.fromRawValue(image.format.raw);
    if (format == null ||
        (Platform.isAndroid && format != InputImageFormat.nv21) ||
        (Platform.isIOS && format != InputImageFormat.bgra8888)) {
      return null;
    }
    if (image.planes.isEmpty) return null;
    final plane = image.planes.first;

    return InputImage.fromBytes(
      bytes: plane.bytes,
      metadata: InputImageMetadata(
        size: Size(image.width.toDouble(), image.height.toDouble()),
        rotation: rotation,
        format: format,
        bytesPerRow: plane.bytesPerRow,
      ),
    );
  }

  void _checkChallenge(Face face) {
    switch (_challenge) {
      case 'blink':
        final left = face.leftEyeOpenProbability ?? 1.0;
        final right = face.rightEyeOpenProbability ?? 1.0;
        if (left < 0.3 && right < 0.3) _eyesWereClosed = true;
        if (_eyesWereClosed && left > 0.7 && right > 0.7) {
          _completeChallenge();
        }
        break;
      case 'smile':
        if (!_smileWasDetected && (face.smilingProbability ?? 0) > 0.7) {
          _smileWasDetected = true;
        }
        if (_smileWasDetected && (face.smilingProbability ?? 0) < 0.5) {
          _completeChallenge();
        }
        break;
      case 'turn_left':
        final y = face.headEulerAngleY ?? 0;
        if (!_turnStartDetected && y.abs() < 10) _turnStartDetected = true;
        if (_turnStartDetected && y > 25) _completeChallenge();
        break;
      case 'turn_right':
        final y = face.headEulerAngleY ?? 0;
        if (!_turnStartDetected && y.abs() < 10) _turnStartDetected = true;
        if (_turnStartDetected && y < -25) _completeChallenge();
        break;
    }
  }

  Future<void> _completeChallenge() async {
    if (_completed) return;
    _completed = true;
    final lang = context.read<LocaleProvider>().locale.languageCode;
    setState(
      () => _instruction = AppStrings.t('hr_liveness_verified_capturing', lang),
    );
    try {
      await _controller?.stopImageStream();
      final file = await _controller?.takePicture();
      if (file != null && mounted) {
        Navigator.pop(context, File(file.path));
      }
    } catch (e) {
      debugPrint('Capture error: $e');
    }
  }

  @override
  void dispose() {
    try {
      _controller?.stopImageStream();
    } catch (_) {}
    _controller?.dispose();
    _faceDetector.close();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<LocaleProvider>().locale.languageCode;
    String t(String key) => AppStrings.t(key, lang);
    final initialized = _controller?.value.isInitialized ?? false;
    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        fit: StackFit.expand,
        children: [
          if (initialized)
            CameraPreview(_controller!)
          else if (_initError != null)
            Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(
                      Icons.videocam_off,
                      color: Colors.white,
                      size: 48,
                    ),
                    const SizedBox(height: 16),
                    Text(
                      _initError!,
                      textAlign: TextAlign.center,
                      textDirection: TextDirection.rtl,
                      style: const TextStyle(color: Colors.white, fontSize: 16),
                    ),
                    const SizedBox(height: 20),
                    ElevatedButton.icon(
                      onPressed: _initCamera,
                      icon: const Icon(Icons.refresh),
                      label: Text(t('retry')),
                    ),
                    const SizedBox(height: 8),
                    TextButton(
                      onPressed: () => Navigator.pop(context),
                      child: Text(
                        t('hr_liveness_close'),
                        style: const TextStyle(color: Colors.white70),
                      ),
                    ),
                  ],
                ),
              ),
            )
          else
            const Center(child: CircularProgressIndicator(color: Colors.white)),
          Center(
            child: Container(
              width: 260,
              height: 320,
              decoration: BoxDecoration(
                border: Border.all(
                  color: _completed ? Colors.green : Colors.white,
                  width: 3,
                ),
                borderRadius: BorderRadius.circular(160),
              ),
            ),
          ),
          Positioned(
            top: 60,
            left: 16,
            right: 16,
            child: Center(
              child: Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 20,
                  vertical: 12,
                ),
                decoration: BoxDecoration(
                  color: Colors.black54,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  _instruction,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ),
          ),
          Positioned(
            top: 60,
            left: 8,
            child: IconButton(
              onPressed: () => Navigator.pop(context),
              icon: const Icon(Icons.close, color: Colors.white, size: 28),
            ),
          ),
        ],
      ),
    );
  }
}
