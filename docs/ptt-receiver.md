# Automatic PTT reception

## Behavior

An authenticated user with `ptt.use` explicitly enables reception. New voice messages addressed to that user are played sequentially without opening individual messages. This is automatic playback of the recorded PTT messages after upload, not automatic joining of the separate LiveKit live-broadcast rooms.

- The first feed request establishes a cursor without replaying historical messages.
- Each subsequent request uses recipient delivery IDs and drains pages in order.
- Both feed and audio endpoints check current recipient membership, active channel and mute state. API middleware also checks the current PTT permission.
- Played status is posted after successful audio completion, never when playback starts. Interrupted/unplayable messages remain available in the inbox.
- The receiver pauses during manual playback, recording and LiveKit use, and stops on logout or permission revocation.
- Browser WebM/Ogg recordings are converted to AAC in a private, content-addressed cache under `storage/ptt_delivery`; originals remain intact. Conversion is bounded to 12 seconds, uses an argument-array subprocess and cannot fetch remote input protocols.
- The existing manual inbox and notification delivery remain supported for older clients.

## Platforms

### Web

The shared layout offers an explicit reception toggle. Browser audio permission requires a user gesture. An exclusive browser lock prevents duplicate reception across tabs for the same user; embedded work windows share the parent receiver. The page must remain open. Normal polling is every 3 seconds; browser background throttling can increase latency. Closed browsers are not a background audio receiver.

### Android

The Flutter toggle starts a non-exported native media-playback foreground service with a visible persistent notification and a stop action. Credentials are held in memory. Playback requests transient audio focus, queues messages sequentially, and releases resources on interruption/stop. No microphone is captured by the receiver.

The service continues outside the PTT screen and while the app is backgrounded, subject to Android/network/battery restrictions. It does not auto-restart after force-stop or device reboot. Device sleep and manufacturer battery management can delay the polling transport; this implementation does not promise zero-latency delivery during Doze. An installed updated client and physical-device locked-screen verification are required before general rollout.

### iOS 16+

The native receiver uses Apple's PushToTalk framework and one aggregate system channel for the user's authorized work channels. Server-side recipient filtering still determines every message. An ephemeral system PTT token is registered separately from ordinary Firebase tokens. Background reception uses APNs `pushtotalk`, priority 10, expiration 0 and `<bundle-id>.voip-ptt`; foreground delivery also polls the authenticated feed. Playback starts only after the framework activates the audio session.

The enabled session is stored in a dedicated Keychain item with `AfterFirstUnlockThisDeviceOnly` protection for system channel restoration. Leaving the channel or logging out clears it and unregisters the device. APNs expiration 0 deliberately avoids delayed automatic playback of stale pushes; missed messages remain in the inbox.

## Deployment and Apple setup

1. Deploy server source and run `scripts/migrate_ptt_receiver.php` through the application bootstrap. This migration only adds `ptt_receiver_devices`; it does not modify existing voice messages.
2. Ensure `/usr/bin/ffmpeg` and PHP cURL HTTP/2 are available, and the PHP application user can create/write `storage/ptt_delivery` outside the public web directory.
3. In Apple Developer, select identifier `com.toporganic.topOrganicApp` and enable Push to Talk. Regenerate matching development/distribution provisioning profiles. Existing profiles inspected on 2026-11-02 did not contain this entitlement.
4. In Apple Developer → Certificates, Identifiers & Profiles → Keys, locate or create an APNs-enabled key authorized for this app/environment. An App Store Connect API upload key is not a substitute.
5. Enter Team ID, APNs Key ID, bundle ID and the private `.p8` contents in the restricted system administration push-settings form. A blank private-key field preserves the existing key; it is never rendered back into HTML. Do not commit keys or paste them into chat. The UI's configured flag indicates presence, not a successful device-delivery test.
6. Build/install updated mobile apps. No store upload is performed by this change. The iOS build needs the regenerated provisioning profiles; the unsigned simulator build cannot validate APNs reception.
7. Test on one authorized Android phone and one iPhone using a dedicated test channel and consenting test users: foreground, another app, screen locked, calls/audio interruptions, mute, permission removal, logout, offline/reconnect and bursts of messages. Verify no unrelated account hears a message. Do not use employee channels for synthetic tests.

## Verification performed

- `php tests/PttDeliveryTest.php`: recipient isolation, membership/mute revocation, send permission, 110-message pagination, no historical autoplay, generated-key APNs signature verification, and route registration/middleware.
- `php tests/PttAudioCompatibilityTest.php`: isolated synthetic WebM/Ogg → decodable AAC, original preservation, cache reuse and malformed audio rejection. Passes on the production host's PHP 8.3/ffmpeg without employee data or notifications.
- `node tests/PttWebReceiverTest.js`: sequential playback, completion-only acknowledgment, pause, revocation and stale-download cancellation.
- Changed PHP/JavaScript syntax checks, Dart analysis, Android debug APK build, full unsigned iOS simulator build and final Swift/AppDelegate type checks.
- No real PTT messages/pushes were sent to employees during verification. Locked-screen delivery on physical phones remains unverified.

At deployment, Firebase and LiveKit were configured; PTT-specific APNs credentials were absent. Background iOS reception is therefore not operational until Apple setup and a signed client update are completed.

References: [Apple PushToTalk](https://developer.apple.com/documentation/pushtotalk/creating-a-push-to-talk-app), [Apple framework walkthrough](https://developer.apple.com/videos/play/wwdc2022/10117/), [Android background media playback](https://developer.android.com/media/media3/session/background-playback).
