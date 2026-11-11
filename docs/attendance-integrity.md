# OS-reported mock location protection

Implemented for both mobile attendance entry points and the server. VPN usage is not inspected, reported or blocked. There are no new permissions, plugins, background location tracking or device/app attestations.

## Behavior

- Capture a fresh one-shot location before the attendance identity/camera flow and again immediately before submitting the punch. Each capture uses the existing Geolocator high-accuracy provider with a 12-second timeout.
- Reject `Position.isMocked == true`. The installed plugin maps Android Location.isMock/isFromMockProvider and iOS 15+ CLLocationSourceInformation.isSimulatedBySoftware. Never infer tampering from GPS permission denial or poor accuracy alone. Existing geofence/exemption rules remain in force.
- Preserve the dedicated screen's selfie and face-verification flow. No face-recognition settings or matching thresholds are changed.
- A locally blocked attempt sends a best-effort authenticated POST `/api/attendance/location-alert` with only check type and mock signal. It creates no punch and uploads no photo or coordinates. A reporting timeout/network failure does not permit the punch; offline alerts are not queued.
- The attendance endpoint also rejects an explicit JSON boolean `attendance_integrity.mock_location: true` before photo saving or punch writing. VPN-only payloads proceed to the usual attendance rules.
- Audit action `attendance_mock_location` is attached to the employee resolved from the authenticated account. Client-supplied employee IDs are ignored. Details state that this is a reported suspicion, not proof of misconduct. Identical reports within 60 seconds are collapsed; the general HTTP activity log retains each request. Logging failure never changes the denial decision.
- Both report and punch endpoints require `mobile.attendance`. The existing `admin.audit` permission protects the audit viewer and Excel export. Arabic label: "اشتباه بمحاكاة الموقع — رفض البصمة".
- The HR shortcut no longer queues new offline attendance with stale coordinates. Existing queued records are not deleted or modified.

## Rollout and limits

Server changes are backwards compatible. Old clients can omit the optional metadata; modified clients can lie about it. This is specifically OS-reported mock detection, not Google Play Integrity / Apple App Attest or proof of physical presence. Users need the updated mobile app for client-side detection and alerts. A future minimum-version policy requires a staged release, not simply trusting a client version string.

No real-device fake-location trial or store upload is implied by automated tests. iOS below 15 and spoofing not surfaced by the OS cannot be reliably detected by this signal.

## Verification

- PHP actual-controller tests with an in-memory database: mock denial for in/out before photo/punch writes, VPN-only acceptance, backward compatibility, fresh retry, audit employee binding, duplicate collapse and invalid report rejection.
- Current attendance-state regressions pass.
- 15 targeted Flutter unit/widget/selfie tests pass: Android/iOS mock flags, VPN never queried, no-GPS behavior, both screens and directions, reporting failure, retry, duplicate taps/disposal and selfie compression.
- Dart analyzer: zero errors in the four changed production Dart files.
- Android debug APK build succeeded with `flutter build apk --debug --no-pub`.
- iOS device debug build succeeded with `flutter build ios --debug --no-codesign --no-pub` (unsigned; not an App Store/TestFlight upload or physical-device validation).
- Backend deployed with pre-change file hashes, backups and PHP 8.3 lint. Unauthenticated alert requests return 401; login remains available. No production employee punch was created during verification.
