# Moderation DEMS

Academic **Digital Examination & Moderation System** — replaces the manual, document-centric
moderation cycle with a role-based, audited, web-hosted workflow that ends in a signed PDF report
for the Head of Department. Design brief: [`docs/`](docs).

Stack: Next.js 14 (App Router) · React · Tailwind CSS · Framer Motion · Lucide · Prisma · PostgreSQL ·
pdf-lib · ExcelJS · Nodemailer. One deployable web service + one database.

## Workflow

| # | Phase / Gate | Actor | Status after |
|---|---|---|---|
| 1 | Pre-assessment: Section 1 (question types, HEQF), paper + memo, signature | Examiner | `Pending Pre-Moderation Review` |
| 2 | **Gate 1** pre-moderation: approve (consensus + signature) or request revision | Internal moderator | `Ready for Post-Moderation` / `Revision Requested` |
| 3 | Post-assessment: marks workbook → automatic statistics, commentary, Section 2 signature | Examiner | `Pending Final Moderation Review` |
| 4 | **Gate 2** final review: statistics, sample scripts, quality checks, signature | Internal moderator | `Completed`, or `Pending External Moderation` if an external moderator is assigned |
| 4b | **Gate 3** external review (optional) | External moderator | `Completed` |
| 5 | PDF generated, archived, e-mailed to HOD | System | `Completed` |

Gate numbering (resolving the open questions in the proposal): Gate 1 = internal pre-approval,
Gate 2 = internal final consensus, Gate 3 = optional external moderator. The HOD is the recipient of
the archived report (and can monitor every record in their subjects), not a signing gate.

Returning a record at Gate 2/3 sends it back to the examiner (`Ready for Post-Moderation`); sign-offs restart from Gate 2.

## Security & audit model

- Roles: `EXAMINER`, `INTERNAL_MODERATOR`, `EXTERNAL_MODERATOR`, `HOD`. Every endpoint checks role **and** per-record assignment; the state machine uses optimistic status guards so a record can't be advanced twice.
- Signatures = pen drawing **+ password re-entry**, stored with a SHA-256 hash of exactly what was attested, timestamp, IP and user agent.
- Statistics are always **recomputed server-side** from the source marks; the client never supplies them.
- Audit log is hash-chained per record (tamper-evident). The PDF shows the chain head.
- Student marks are stored as anonymous numbers; the raw workbook is downloadable by the examiner only.
- External moderators only see a record once it reaches them.
- Sessions: signed httpOnly cookie, 8 h; login throttling; same-origin check on mutations.

## Calculation rules (`src/lib/calc.ts`)

Candidates = non-blank numeric rows · Pass = mark ≥ 50 % of total marks · highest / lowest / class average as % ·
non-numeric entries (e.g. `ABS`) are excluded and reported, never silently counted.

## Database: Supabase

Any PostgreSQL works; Supabase is the recommended host. In Supabase → **Project Settings → Database → Connection string** copy:

- `DATABASE_URL` — **Transaction pooler** (port 6543), append `?pgbouncer=true&connection_limit=1`
- `DIRECT_URL` — **Session pooler** (port 5432), used only for migrations (the `db.<ref>.supabase.co` host is IPv6-only, which Render can't reach)

Migration `0002_enable_rls` turns on Row Level Security (no policies) for every table so Supabase's public REST/anon key cannot read your data; the app itself connects as the owner via Prisma and is unaffected.
Files are stored in the same database by default; Supabase Storage / S3 can be used instead via `STORAGE_DRIVER=s3` (Supabase Storage exposes an S3-compatible endpoint).

## Local development (with Laravel Herd)

Herd runs PHP sites; this app is Node, so Herd supplies the **database** (and optionally a local domain), while Next.js runs with `npm run dev`.

```bash
cp .env.example .env
npm install
npx prisma migrate deploy
npm run db:seed
npm run dev
```

Pick one database in `.env`:
- **Herd Pro Postgres service:** create a database named `dems`, then set `DATABASE_URL` and `DIRECT_URL` to the same `postgresql://…@127.0.0.1:5432/dems?schema=public` URL shown in Herd.
- **Supabase directly:** use the two Supabase strings above (handy for sharing one dev DB).

Set `SEED_ADMIN_PASSWORD` (10+ characters) and, for demo accounts, `SEED_DEMO_DATA="true"`. Open http://localhost:3000, or add a Herd proxy to `http://localhost:3000` if you want a `.test` domain.

Tests: `npm test` (calculation engine) · `node --env-file=.env scripts/smoke.mjs` (full lifecycle against a running server).

## Deploy to Render (with Supabase)

1. Create a Supabase project and note the two connection strings above.
2. Render → **New → Blueprint** (or your existing web service) → set `DATABASE_URL`, `DIRECT_URL`, `AUTH_SECRET`, `APP_URL`, `SEED_ADMIN_EMAIL`, `SEED_ADMIN_PASSWORD`, `INSTITUTION_NAME`, SMTP settings.
3. Every build runs `prisma migrate deploy` and seeds the HOD account (idempotent; see `scripts/db-setup.mjs`). Sign in, then use **Admin** to create users and subjects.

Without `SMTP_HOST`, e-mails are logged and the audit trail records `Report e-mail failed`.

## Known limits

- PDF preview is inline for PDFs; Word files are download-only.
- Marks upload supports `.xlsx` / `.csv` (header in row 1), not legacy `.xls`.
- The PDF uses standard Helvetica: characters outside Western-European (WinAnsi) print as `?`.
- Login throttling is per instance (in-memory); use a shared store if you scale beyond one instance.
