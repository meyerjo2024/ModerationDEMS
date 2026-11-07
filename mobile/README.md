# Moderation — iPhone / iPad app (Capacitor)

A native iOS shell around the live Moderation DEMS site. The site stays the single source of truth; the shell adds an app icon, splash screen, native status bar and **push notifications** (the reminders and "your turn" alerts).

> **Status:** project scaffold only. It has **not** been built or run: that needs a Mac with Xcode and an Apple Developer account. Follow the steps below on a Mac.

## Requirements
- A Mac with **Xcode 16 or later** and Node 20+
- An **Apple Developer Program** membership (needed for a device build, TestFlight and the App Store)
- The production site address (HTTPS)

## First build (on a Mac)
```bash
cd mobile
npm install
export DEMS_URL="https://your-production-site"      # the live site
npx cap add ios        # creates the ios/ Xcode project (once)
npx cap sync ios
npx cap open ios       # opens Xcode
```
In Xcode:
1. Select the **App** target → **Signing & Capabilities** → choose your Team and set the **Bundle Identifier** (it must match `appId` in `capacitor.config.ts`).
2. **+ Capability → Push Notifications** (and **Background Modes → Remote notifications**).
3. Replace the app icon with the 1024×1024 icon (`../public/icons/icon-512.png` is a starting point: export a 1024 px version).
4. Run on a real iPhone (push does not work in the simulator).

## Face ID
The site shows a **Face ID lock** switch on the Account page (only inside the app). It uses the `@aparajita/capacitor-biometric-auth` plugin (already in `package.json`). In Xcode add to **Info.plist**:

- `NSFaceIDUsageDescription` = `Unlock Moderation with Face ID.`

The lock screen appears when the app opens and after a minute in the background; it does not replace the password (the person stays signed in).

## What is already wired on the server
- `POST /devices` stores the phone's push token for the signed-in person (`device_tokens` table); `DELETE /devices` removes it.
- The site detects the app and registers for push automatically after sign-in (`resources/js/app.js`).
- **Sending** pushes is built (`app/Services/PushSender.php`): every in-app notification is also sent to the person's registered iPhones. Create an **APNs authentication key (.p8)** in the Apple Developer account and set these in Render's Environment tab: `APNS_KEY_ID`, `APNS_TEAM_ID`, `APNS_BUNDLE_ID`, `APNS_KEY` (the contents of the .p8 file; newlines may be typed as `\n`) and `APNS_PRODUCTION=true` (use `false` only for builds run straight from Xcode).

## Updating
The app loads the site, so web changes reach the app immediately. A new App Store build is only needed when the native shell changes (icon, plugins, capabilities).
