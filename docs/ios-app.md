# The platform as an iPhone / iPad app

## Option 1 — install from Safari (available now)

The site is an installable web app (PWA). It opens full screen like an app, with its own icon, and no App Store is needed.

1. Open the site in **Safari** on the iPhone or iPad (it must be Safari, not Chrome).
2. Tap **Share** (the square with an arrow).
3. Tap **Add to Home Screen**, then **Add**.
4. Open **Moderation** from the home screen and sign in.

The sign-in page shows these steps automatically on an iPhone or iPad. On Android, Chrome offers *Install app* in the menu.

What it does: full-screen display, app icon, safe-area spacing around the notch, and a friendly "You are offline" page if the connection drops. It deliberately **stores nothing offline** — no papers, marks or pages are cached on the device.

Not included: push notifications on the lock screen (reminders stay inside the app, under the bell).

## Option 2 — an App Store app (later, only if wanted)

A store version needs things that cannot be done from this project alone:

| Need | Why |
|---|---|
| A Mac with Xcode | To build and sign an iOS app |
| Apple Developer Program membership (annual fee) | To publish; also needed for TestFlight |
| App Store review | Apple rejects apps that are only a website in a frame (guideline 4.2) unless they add genuine native value |

Sensible route if CPUT wants it: wrap the site with **Capacitor** and add native features that justify an app — **push notifications** for the reminders and "your turn" alerts, **document sharing** (open a PDF report in Files/Mail), and **Face ID sign-in**. For CPUT staff only, an **Apple Business Manager "unlisted app"** or **TestFlight** avoids a public listing.

A fully native SwiftUI app is a separate project: it would need a documented API for the platform (today the site renders its own pages) and is not recommended unless there is a clear reason.

## Decisions for Option 2

1. Public App Store, unlisted (Business Manager) or TestFlight only?
2. Which Apple developer account will own it?
3. Are push notifications and Face ID wanted? (They need server work: push tokens, a notification service.)
