#!/bin/bash
# بناء تطبيق الموبايل كملف APK أندرويد للتجربة (يعمل على أي جهاز فيه
# Flutter SDK + Android SDK — macOS أو Linux، سواء كان نفس جهاز تطوير
# محاكي iOS أو غيره).
#
# الاستخدام:
#   chmod +x flutter_app/run_android_build.sh
#   ./flutter_app/run_android_build.sh            # يبني APK فقط
#   ./flutter_app/run_android_build.sh install     # يبني ثم يثبّته مباشرة
#                                                   # على جهاز/محاكي متصل
#
# ملاحظة: لمعظم الهواتف الحديثة استخدم app-arm64-v8a-release.apk،
# ولمحاكي أندرويد (الذي يعمل عادة بمعالج x86_64) استخدم
# app-x86_64-release.apk.

set -e

ACTION="$1"

if ! command -v flutter >/dev/null 2>&1; then
    echo "خطأ: أمر flutter غير موجود في PATH. تأكد من تثبيت Flutter SDK." >&2
    exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

echo "==> (1/4) سحب آخر تحديثات الكود..."
cd "$REPO_ROOT"
git pull origin main

echo "==> (2/4) تثبيت حزم Dart..."
cd "$SCRIPT_DIR"
flutter pub get

# توقيع البناء يتطلب android/key.properties (يشير إليه build.gradle.kts
# حتى لنسخة التجربة/التطوير). إن لم يكن موجوداً، نولّد مفتاح توقيع مؤقت
# للتجربة المحلية فقط — غير صالح لإصدار على المتجر.
KEY_PROPS="$SCRIPT_DIR/android/key.properties"
GENERATED_KEYSTORE=""
if [[ ! -f "$KEY_PROPS" ]]; then
    echo "==> لا يوجد مفتاح توقيع (key.properties) — سيتم توليد مفتاح تجربة مؤقت محلياً..."
    GENERATED_KEYSTORE="$SCRIPT_DIR/android/.local_test.keystore"
    keytool -genkeypair -v -keystore "$GENERATED_KEYSTORE" \
        -storepass test1234 -alias testkey -keypass test1234 \
        -keyalg RSA -keysize 2048 -validity 3650 \
        -dname "CN=Test, OU=Test, O=Test, L=Test, S=Test, C=US" >/dev/null
    cat > "$KEY_PROPS" <<EOF
storePassword=test1234
keyPassword=test1234
keyAlias=testkey
storeFile=$GENERATED_KEYSTORE
EOF
    echo "    (تذكير: هذا توقيع تجربة محلي فقط، غير صالح لنشر المتجر)"
fi

echo "==> (3/4) بناء APK (نسخة لكل معالج لتصغير الحجم)..."
flutter build apk --release --split-per-abi

OUT_DIR="$SCRIPT_DIR/build/app/outputs/flutter-apk"
echo "==> (4/4) تم البناء بنجاح:"
ls -lh "$OUT_DIR"/app-*-release.apk

if [[ "$ACTION" == "install" ]]; then
    echo "==> تثبيت على الجهاز/المحاكي المتصل..."
    flutter install
fi

echo ""
echo "لتثبيت الملف يدوياً على هاتف حقيقي: انقل app-arm64-v8a-release.apk للهاتف وافتحه."
echo "لتثبيته على محاكي أندرويد (x86_64): استخدم app-x86_64-release.apk."
