# Moderation DEMS

Academic **Digital Examination & Moderation System** — replaces the manual, document-centric moderation
cycle with a role-based, audited, web-hosted workflow that ends in a signed PDF report for the Head of
Department. Design brief: [`docs/`](docs).

**Stack:** Laravel 13 (PHP 8.3) · Blade + Alpine.js · Tailwind CSS 4 · PostgreSQL (Supabase) ·
PhpSpreadsheet (marks) · Dompdf (report) — runs under **Laravel Herd** locally and as a Docker service on Render.

## Workflow

| # | Phase / Gate | Actor | Status after |
|---|---|---|---|
| 1 | Pre-assessment: Section 1 (question types, HEQF), paper + memo, signature | Examiner | `Pending Pre-Moderation Review` |
| 2 | **Gate 1** pre-moderation: approve (consensus + signature) or request revision | Internal moderator | `Ready for Post-Moderation` / `Revision Requested` |
| 3 | Post-assessment: marks workbook → automatic statistics, commentary, Section 2 signature | Examiner | `Pending Final Moderation Review` |
| 4 | **Gate 2** final review: statistics, sample scripts, quality checks, signature | Internal moderator | `Completed`, or `Pending External Moderation` if an external moderator is assigned |
| 4b | **Gate 3** external review (optional) | External moderator | `Completed` |
| 5 | PDF generated, archived, e-mailed to HOD | System | `Completed` |

Gate numbering: Gate 1 = internal pre-approval, Gate 2 = internal final consensus, Gate 3 = optional external
moderator. The HOD receives the archived report and can monitor every record in their subjects; they are not a signing gate.
Returning a record at Gate 2/3 sends it back to the examiner; sign-offs restart from Gate 2.

## Security & audit model

- Roles: `EXAMINER`, `INTERNAL_MODERATOR`, `EXTERNAL_MODERATOR`, `HOD`. Routes are role-gated **and** every action checks the user's assignment on that record (`App\Services\Workflow`). Records a user may not see return 404.
- Signatures = pen drawing **+ password re-entry**, stored with a SHA-256 hash of exactly what was attested, timestamp, IP and user agent.
- The state machine locks the row (`SELECT … FOR UPDATE`), so a record can't be advanced twice.
- Statistics are always **recomputed server-side** from the source marks; the browser never supplies them.
- The audit log is hash-chained per record (tamper-evident); the PDF shows the chain head.
- Student marks are stored as anonymous numbers; the raw workbook is downloadable by the examiner only.
- External moderators only see a record once it reaches them.
- Sessions in the database, login throttling, CSRF on every mutation, upload validation by extension **and** magic bytes.
- Migration `…_enable_row_level_security` turns on RLS (no policies) so Supabase's public API key cannot read the data; the app connects as the table owner and is unaffected.

## Calculation rules (`app/Services/StatisticsCalculator.php`)

Candidates = non-blank numeric rows · Pass = mark ≥ 50 % of total marks · highest / lowest / class average as % ·
non-numeric entries (e.g. `ABS`) are excluded and reported, never silently counted.

## Local development with Laravel Herd

1. Put this folder in a Herd-parked directory (or *Add site*): it is served at `http://moderation-dems.test`.
2. Herd Pro → **Services → PostgreSQL** → create a database named `dems`. (No Herd Pro? Use Supabase — see below.)
3. In a terminal inside the project:

```bash
cp .env.example .env
composer install
npm install && npm run build        # or `npm run dev` while developing
php artisan key:generate
php artisan migrate
```

4. Open `.env` and set `DEMS_SEED_PASSWORD` (10+ characters). For demo accounts also set `DEMS_DEMO_DATA=true`. Then:

```bash
php artisan db:seed
```

5. Visit **http://moderation-dems.test** (set `APP_URL` to the same). Sign in as `DEMS_SEED_EMAIL` (default `hod@example.edu`).
   With demo data, the login page lists `hod@`, `examiner@`, `moderator@`, `external@example.edu` — click one to fill it in.

Tests: `php artisan test` (SQLite in memory by default; `DB_CONNECTION=pgsql DB_DATABASE=… php artisan test` for PostgreSQL).

## Database: Supabase

In Supabase → **Project Settings → Database → Connection string**, copy the **Session pooler** string (port 5432 — it works over IPv4
and supports prepared statements) and put it in `.env`:

```
DB_CONNECTION=pgsql
DB_URL="postgresql://postgres.PROJECTREF:PASSWORD@aws-0-REGION.pooler.supabase.com:5432/postgres"
DB_SSLMODE=require
```

URL-encode special characters in the password. If you use the *transaction* pooler (port 6543) also set `DB_EMULATE_PREPARES=true`.
Then `php artisan migrate && php artisan db:seed`. Files are stored in the same database by default (`DEMS_STORAGE=db`);
set `DEMS_STORAGE=disk` and `DEMS_DISK=<a filesystem disk>` to use another disk.

## Deploy to Render

Render has no native PHP runtime, so the app ships as a Docker service (`Dockerfile`, `render.yaml`).

1. Create a Supabase project (see above).
2. Render → **New → Blueprint** → select this repo. Fill in: `APP_KEY` (`php artisan key:generate --show`), `APP_URL`, `DB_URL`, `DEMS_SEED_EMAIL`, `DEMS_SEED_PASSWORD`, `INSTITUTION_NAME` and the `MAIL_*` SMTP settings.
3. On every start the container runs the migrations and the (idempotent) HOD seed. Check `/health`, sign in, then use **Admin** to create users and subjects.

With `MAIL_MAILER=log` nothing is delivered; the record page then says the HOD e-mail was **not** delivered and the audit trail records it.

## Known limits

- The Docker/Render setup has not been exercised on Render itself (it was written and the app tested outside Docker).
- PDF preview is inline for PDFs; Word files are download-only.
- Marks upload supports `.xlsx` / `.csv` (header in row 1), not legacy `.xls`.
- Login throttling uses the cache (`file` by default) — use a shared cache store if you run several instances.
