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

## What is already wired on the server
- `POST /devices` stores the phone's push token for the signed-in person (`device_tokens` table); `DELETE /devices` removes it.
- The site detects the app and registers for push automatically after sign-in (`resources/js/app.js`).
- **Sending** pushes is the next server task: an APNs key (`.p8`) from your Apple account and a small sender that turns the existing in-app reminders and "your turn" notifications into pushes (see `docs/ios-appstore.md`).

## Updating
The app loads the site, so web changes reach the app immediately. A new App Store build is only needed when the native shell changes (icon, plugins, capabilities).
