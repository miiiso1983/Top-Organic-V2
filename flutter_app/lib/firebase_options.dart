import 'package:firebase_core/firebase_core.dart' show FirebaseOptions;
import 'package:flutter/foundation.dart'
    show defaultTargetPlatform, kIsWeb, TargetPlatform;

/// قيم Firebase — تم إنشاؤها من ملفات GoogleService-Info.plist و google-services.json.
const bool kFirebaseConfigured = true;

class DefaultFirebaseOptions {
  static FirebaseOptions get currentPlatform {
    if (kIsWeb) return web;
    switch (defaultTargetPlatform) {
      case TargetPlatform.android:
        return android;
      case TargetPlatform.iOS:
        return ios;
      default:
        return android;
    }
  }

  static const FirebaseOptions web = FirebaseOptions(
    apiKey: 'YOUR_API_KEY',
    appId: 'YOUR_WEB_APP_ID',
    messagingSenderId: 'YOUR_SENDER_ID',
    projectId: 'YOUR_PROJECT_ID',
    authDomain: 'YOUR_AUTH_DOMAIN',
    storageBucket: 'YOUR_STORAGE_BUCKET',
  );

  static const FirebaseOptions android = FirebaseOptions(
    apiKey: 'AIzaSyC1w_EtkqJBTPlERLxWaOx94-bqcRcAPIg',
    appId: '1:84882961745:android:94ae388e86f6c81bd5cf70',
    messagingSenderId: '84882961745',
    projectId: 'top-organic-97642',
    storageBucket: 'top-organic-97642.firebasestorage.app',
  );

  static const FirebaseOptions ios = FirebaseOptions(
    apiKey: 'AIzaSyAah-lVEvvmiZAVqLZb2sT1t2vSlU5-pwA',
    appId: '1:84882961745:ios:422b92327c7ced37d5cf70',
    messagingSenderId: '84882961745',
    projectId: 'top-organic-97642',
    storageBucket: 'top-organic-97642.firebasestorage.app',
    iosBundleId: 'com.toporganic.topOrganicApp',
  );
}
