#!/bin/bash
# تشغيل تطبيق الموبايل على محاكي iOS (iOS Simulator) — يعمل فقط على macOS.
#
# الاستخدام:
#   chmod +x flutter_app/run_ios_simulator.sh
#   ./flutter_app/run_ios_simulator.sh            # يشغّل على أول محاكي iOS مفتوح/افتراضي
#   ./flutter_app/run_ios_simulator.sh <device-id> # لتحديد محاكي معيّن (من نتيجة flutter devices)
#
# يقوم هذا السكربت بكل الخطوات دفعة واحدة:
#   1) سحب آخر تحديثات الكود من main
#   2) تثبيت حزم Dart
#   3) تثبيت/تحديث مكتبات CocoaPods الخاصة بـ iOS
#   4) فتح تطبيق Simulator
#   5) تشغيل التطبيق على المحاكي

set -e

DEVICE_ID="$1"

if [[ "$(uname)" != "Darwin" ]]; then
    echo "خطأ: محاكي iOS يعمل فقط على macOS. هذا السكربت لا يمكن تشغيله على نظامك الحالي." >&2
    exit 1
fi

if ! xcode-select -p >/dev/null 2>&1; then
    echo "خطأ: أدوات Xcode غير مثبّتة. نفّذ أولاً: xcode-select --install" >&2
    exit 1
fi

if ! command -v flutter >/dev/null 2>&1; then
    echo "خطأ: أمر flutter غير موجود في PATH. تأكد من تثبيت Flutter SDK." >&2
    exit 1
fi

if ! command -v pod >/dev/null 2>&1; then
    echo "خطأ: CocoaPods غير مثبّت. نفّذ أولاً: sudo gem install cocoapods" >&2
    exit 1
fi

# الانتقال لجذر المشروع (المجلد الأب لمجلد flutter_app الذي يحوي هذا السكربت)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

echo "==> (1/5) سحب آخر تحديثات الكود..."
cd "$REPO_ROOT"
git pull origin main

echo "==> (2/5) تثبيت حزم Dart..."
cd "$SCRIPT_DIR"
flutter pub get

echo "==> (3/5) تثبيت/تحديث مكتبات CocoaPods..."
cd "$SCRIPT_DIR/ios"
pod install --repo-update
cd "$SCRIPT_DIR"

echo "==> (4/5) فتح تطبيق Simulator..."
open -a Simulator
# مهلة بسيطة لإعطاء المحاكي فرصة للإقلاع قبل محاولة Flutter الاتصال به
sleep 5

echo "==> (5/5) تشغيل التطبيق على المحاكي..."
if [[ -n "$DEVICE_ID" ]]; then
    flutter run -d "$DEVICE_ID"
else
    flutter run
fi
