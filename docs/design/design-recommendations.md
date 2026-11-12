# Design recommendations — draft only (the app has not been changed)

Drafts are in `docs/design/drafts/` (HTML + PNG screenshots). Sample names and subjects are placeholders.

## What looks "AI-generated" today, and why
| Today | Why it reads as generic | Replace with |
|---|---|---|
| Gradient washes on every card, glowing/pulsing status dots | Decoration with no meaning | Flat white surfaces; one small solid dot per status |
| Many accent colours (blue, teal, amber, rose, emerald, all saturated) | No hierarchy | One brand accent (the shield's blue `#0A5A94`); red/amber/green **only** for status text and dots |
| Cards inside cards, heavy borders and shadows | Visual noise | Hairline borders (`rgba(0,0,0,.08)`), grouped list rows instead of a card per item |
| Open Sans at many sizes, uppercase "kicker" labels everywhere | Template feel | System font stack (SF Pro on Apple devices, Inter elsewhere), 4 sizes, tight tracking on headings |
| Hero banner "Welcome back" | Marketing, not a work tool | Plain page title + one-line status |
| Emoji-style icons, `✔` marks | Inconsistent | One outline icon set (SF-Symbols-like), used sparingly |

## Principles (Apple Human Interface style)
1. **Content first.** Neutral canvas (`#F5F5F7` light, `#1C1C1E` dark); the work is the colour.
2. **One accent.** CPUT shield blue for actions, links and selection. Status colours only mean status.
3. **Grouped lists, not card grids.** Rows with a chevron (iOS Settings style) scan faster for moderation queues.
4. **Quiet motion.** 150 ms fades; remove pulsing indicators.
5. **Type.** `-apple-system, "SF Pro Text", Inter` · 34/30/17/15 px · weight 600 for headings, 400 body · tabular numbers for counts.
6. **Radius & spacing.** 18 px cards, 12 px inputs, 980 px (pill) primary buttons, 8-pt spacing grid.
7. **Real dark mode.** Own palette (draft B-dark), not inverted light.
8. **Accessible.** 4.5:1 contrast, 44 px touch targets on phone, visible focus ring.

## Two directions (screenshots in `docs/design/drafts/`)
- **A — "Light, top bar"** (`A-login`, `A-dashboard`, `A-overview`, `A-dashboard-phone`): translucent sticky top bar, segmented control for filters, a single grouped list. Closest to apple.com / iOS. Best on phones.
- **B — "Sidebar"** (`B-dashboard-light`, `B-dashboard-dark`): macOS/iPad-style sidebar with Administration grouped separately, KPI strip, grouped lists. Best for the HOD/admin on a laptop; more screen real estate for the Overview tables.

**Recommendation:** B on screens wider than 1024 px, collapsing to A's top bar on phones (one codebase). Dark mode from B.

## Logo
`staff logo1.jpg` (shield crest) is used in every draft. A transparent-background version (`drafts/logo.png`) was made so it sits cleanly on grey and dark surfaces. Recommend: crest at 30–34 px beside the word "Moderation" in the bar, 96 px on the sign-in page, in the PDF report header, PWA/app icon (needs a square version with padding) and e-mails. The old `cput-logo.jpg` wordmark can stay on the official PDF only if the form requires it.

## Rollout plan (if you approve)
1. Tokens: replace the Tailwind `@theme` colours/fonts/radii in `resources/css/app.css` (one file; most components inherit).
2. Layout: new shell (sidebar ≥1024 px, top bar below) in `components/app-layout.blade.php`; logo swap.
3. Components: status badge → dot + text; deadline chip; list-row component replacing dashboard cards.
4. Screens in order: login/register → dashboard → assessment page → Overview → Admin → My subjects.
5. Update the PDF report header, e-mails and the staff presentation screenshots.
6. Re-run the 48 tests and the Playwright screenshots; verify phone + dark.
Effort: about two working sessions; no database or logic changes.

## Decisions needed from you
- Direction A, B, or B-with-A-on-phones (recommended)?
- Approve the shield as the single logo everywhere, including the PDF header?
- Keep the Open Sans brand font, or switch to the system/Inter stack (recommended)?
