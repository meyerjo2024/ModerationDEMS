# iOS app and the App Store — plan and checklist

*Status: server side (push sender, device registration, delete-account, privacy/support pages) and the Face ID lock in the site are built and tested; the native project (`mobile/`) is a scaffold that must be built on a Mac. Nothing has been submitted.*

## Decisions made

| Question | Decision |
|---|---|
| Who owns the Apple Developer account? | **CPUT** (organisation); the developer is added to it as a team member |
| Distribution | **Apple Business Manager — custom app** (staff only) |
| Push notifications | **Yes** |
| Face ID | **Yes** (app lock) |
| Privacy wording | To be finalised later by the owner |

### What Business Manager distribution needs
- CPUT's Apple Developer **organisation** account and CPUT's **Apple Business Manager** account (both need CPUT's D-U-N-S number and are normally linked by the University's IT/legal contact).
- In App Store Connect, the app's **Pricing and Availability → Distribution method = Custom App**, then enter CPUT's Business Manager organisation; the University's ABM administrator then assigns the app to devices/users (managed distribution) or via redemption codes.
- Custom apps still go through App Review (so the review account and no-placeholder rules below still apply) but are not listed publicly.
- The **developer** needs an App Store Connect role (App Manager or Developer) and a Certificates/Identifiers role, granted by CPUT's Account Holder.

### Builds without owning a Mac
If no Mac is available, builds can run on a hosted Mac: **Xcode Cloud** (included with the developer account, builds from the GitHub repository), **GitHub Actions macOS runners**, **Codemagic**, or a rented cloud Mac. The first build, signing and TestFlight upload are easiest on a real Mac or Xcode Cloud.

## 1. Approach

A **native shell around the live site** (Capacitor). One code base, instant web updates, and the native parts Apple expects of an app: push notifications, native launch and status bar, installed icon. It is *not* a bare web page in a frame, which Apple rejects (guideline 4.2): push notifications for reminders and "your turn" alerts are the genuine native value, and Face ID unlock / opening PDFs in Files or Mail can be added.

Alternatives considered: a fully native SwiftUI app (needs a separate API, much more work, not recommended now) and the installable web app (already available, see `docs/ios-app.md`).

## 2. What you need (outside this repository)

| Item | Note |
|---|---|
| Apple Developer Program account | Annual fee; an *organisation* account (D-U-N-S number) is normal for a university. Needed for TestFlight and the store |
| A Mac with Xcode | To build, sign and upload |
| Bundle ID | e.g. `za.ac.cput.moderation` — register in the developer account and match `mobile/capacitor.config.ts` |
| APNs authentication key (`.p8`) | Created in the developer account; used by the server to send pushes |
| Distribution choice | Public App Store, **unlisted** (link only) or **Apple Business Manager** (staff only) — see §6 |

## 3. Built in this project (server and site)

- [x] **Delete my account** inside the app and site (Apple guideline 5.1.1(v)): *your name → Delete my account*, password confirmed, refused while named on unfinished assessments or as the last HOD; the person is anonymised (“Former user”), signed records stay.
- [x] **Privacy notice page** `/privacy` and **Support page** `/support`, public, linked from the sign-in page. *Drafts: the University must review the wording and fill in region, retention and contact.* Set `DEMS_SUPPORT_EMAIL` and `DEMS_SUPPORT_PHONE`.
- [x] **Device registration** for push: `POST /devices` / `DELETE /devices`, table `device_tokens`; the site registers automatically inside the app.
- [x] **Push sender** (`PushSender`): APNs over HTTP/2 with a `.p8` key; sends after the notification is saved; never breaks the workflow if Apple is unreachable.
- [x] **Face ID lock** switch on the Account page (inside the app only).
- [x] Installable web app basics (manifest, icons, safe areas) that the shell reuses.
- [x] HTTPS only (App Transport Security satisfied).

## 4. To build next

1. ~~Push sender~~ **built**: every in-app notification is pushed to the person's iPhones (`PushSender`, tested with a faked APNs and a verified ES256 token); invalid tokens are removed. Needs the `.p8` key from Apple and a real device to prove end to end.
2. ~~Face ID lock~~ **built** in the site (works inside the app; tested against a stand-in for the native plugin). Needs `NSFaceIDUsageDescription` in Info.plist.
3. Optional: "open PDF report" in Files/Mail via the share sheet.
4. Build, sign and test the shell on a Mac or hosted Mac (see `mobile/README.md`).

## 5. App Store submission checklist

**Account and app record**
- [ ] Developer account (organisation) and App Store Connect app record, name, bundle ID, primary language
- [ ] Category: Education (or Productivity); age rating questionnaire
- [ ] **Privacy policy URL** = `https://<site>/privacy` (finalised text) and **Support URL** = `https://<site>/support`
- [ ] **App Privacy "nutrition label"**: data collected = name, e-mail, user content (documents, comments), identifiers (device token for push), diagnostics none; **not used for tracking**; linked to the user for app functionality
- [ ] Export compliance: the app uses only standard HTTPS → answer "exempt" (set `ITSAppUsesNonExemptEncryption` = NO)

**Review requirements**
- [ ] A **review account** with working credentials and a few sample assessments (Apple reviewers cannot register): create a dedicated reviewer examiner/moderator account and say in the *App Review Information* notes how to use it. (The reviewer needs no real data: create clearly fictional sample records on a staging copy.)
- [ ] Notes explaining that accounts are created by staff registration with HOD approval, and that the app is for CPUT staff
- [ ] In-app account deletion present (done) and reachable
- [ ] No placeholder text, no "demo" labels, no broken links (Support/Privacy links work)
- [ ] Sign in with Apple is **not** required (no third-party/social login is used)

**Assets**
- [ ] 1024×1024 app icon (no transparency), launch screen
- [ ] Screenshots for required iPhone sizes (6.9" and 6.5") and iPad if the iPad build is shipped; description, keywords, promotional text
- [ ] TestFlight: internal testers first (up to 100), then external beta

**Release**
- [ ] Archive in Xcode → upload → TestFlight → Submit for Review (typically 1–3 days; allow for one rejection round)

## 6. Distribution options for a university

| Option | Audience | Notes |
|---|---|---|
| Public App Store | Anyone can find it | Needs full store listing; may attract sign-up attempts from the public (the app requires HOD approval, so they cannot enter) |
| **Unlisted app distribution** | Only people with the link | Same review, no public search listing — fits a staff-only tool |
| Apple Business Manager (custom app) | Specific organisations | Staff only; managed distribution |
| TestFlight only | Up to 10,000 external testers, builds expire after 90 days | Good for a pilot |

Recommendation: **TestFlight pilot first, then unlisted App Store distribution.**

## 7. Still open

1. Bundle identifier to register (default `za.ac.cput.moderation`).
2. Who at CPUT is the Apple **Account Holder** and ABM administrator, and can they grant the developer access?
3. A Mac, Xcode Cloud or a hosted Mac for builds.
4. A staging site with fictional data for the App Review account.
5. Privacy notice wording (region, retention period, contact): to be finalised later.

## 8. Rough sequence

1. Decisions and accounts (Apple enrolment can take days to weeks for organisations)
2. Mac build of the shell, TestFlight to a few staff
3. Push sender and Face ID (if wanted), test on devices
4. Finalise privacy/support pages, review account and screenshots
5. Submit; respond to review feedback; release
