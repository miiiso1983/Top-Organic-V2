#!/bin/sh
#
# Xcode Cloud post-clone hook for a Flutter app.
# Xcode Cloud clones the repo and runs xcodebuild on Runner.xcworkspace,
# but it does not include the Flutter SDK. This script installs Flutter,
# generates the iOS artifacts, and runs pod install so the archive step succeeds.
#
# Place at: flutter_app/ios/ci_scripts/ci_post_clone.sh
# Xcode Cloud auto-runs this script when it lives next to the Xcode project.

set -e

# Pin the Flutter version to match your local/project version.
# Set FLUTTER_VERSION in Xcode Cloud workflow environment variables (e.g. 3.44.2).
FLUTTER_VERSION="${FLUTTER_VERSION:-3.44.2}"

# Path to the Flutter project inside the repo (repo root is $CI_PRIMARY_REPOSITORY_PATH).
# For this repo, the Flutter app is under flutter_app/.
FLUTTER_PROJECT_PATH="${FLUTTER_PROJECT_PATH:-flutter_app}"

echo "=== Installing Flutter $FLUTTER_VERSION ==="
if [ ! -d "$HOME/flutter/bin" ]; then
  git clone https://github.com/flutter/flutter.git --depth 1 -b "$FLUTTER_VERSION" "$HOME/flutter"
fi
export PATH="$HOME/flutter/bin:$PATH"
flutter --version

echo "=== Preparing Flutter iOS build ==="
cd "$CI_PRIMARY_REPOSITORY_PATH/$FLUTTER_PROJECT_PATH"

flutter precache --ios
flutter pub get

echo "=== Generating iOS build artifacts (no codesign) ==="
flutter build ios --release --no-codesign

echo "=== Flutter setup complete ==="
