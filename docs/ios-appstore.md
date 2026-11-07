# iOS app and the App Store — plan and checklist

*Status: server-side prerequisites are built and tested; the native project (`mobile/`) is a scaffold that must be built on a Mac. Nothing has been submitted.*

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
- [x] Installable web app basics (manifest, icons, safe areas) that the shell reuses.
- [x] HTTPS only (App Transport Security satisfied).

## 4. To build next

1. **Push sender** (server): APNs HTTP/2 with the `.p8` key (env: `APNS_KEY_ID`, `APNS_TEAM_ID`, `APNS_BUNDLE_ID`, `APNS_KEY` ), sending when the existing in-app notifications are created (revision requested, your turn, deadline reminders). Remove invalid tokens on APNs "unregistered" answers. *Needs the key from Apple; testable only on a real device.*
2. Optional native extras: Face ID unlock, "open PDF report" in Files/Mail via the share sheet.
3. Build, sign and test the shell on a Mac (see `mobile/README.md`).

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

## 7. Decisions needed

1. Who owns the Apple Developer account (CPUT organisation) and who has a Mac for builds?
2. Public, unlisted or Business Manager distribution?
3. Bundle identifier to register.
4. Push notifications: yes (recommended)? Face ID unlock: yes/no?
5. Who finalises the privacy notice (region, retention period, contact)?
6. A staging site with fictional data for the App Review account.

## 8. Rough sequence

1. Decisions and accounts (Apple enrolment can take days to weeks for organisations)
2. Mac build of the shell, TestFlight to a few staff
3. Push sender and Face ID (if wanted), test on devices
4. Finalise privacy/support pages, review account and screenshots
5. Submit; respond to review feedback; release
