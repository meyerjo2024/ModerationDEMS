# Deployment guide

Three ways to run Moderation DEMS. All use PostgreSQL; Supabase is the recommended host for the database.

| Where | Use for | App | Database |
|---|---|---|---|
| **Laravel Herd** | Daily development on a Mac/Windows PC | Herd serves `http://moderation-dems.test` | Herd Pro Postgres, or Supabase |
| **Render (Docker)** | Demo / production on the web | Docker web service (free plan works) | Supabase |
| Any Docker host | Self-hosting | `Dockerfile` in the repo | Any PostgreSQL |

---

## 1. Local with Laravel Herd

1. Park the project folder in Herd (or *Sites → Add*). It is served at `http://moderation-dems.test`.
2. Create the database. Herd Pro: **Services → PostgreSQL → create database `dems`**. No Herd Pro? use Supabase (section 2) and skip this.
3. In a terminal inside the project:

```bash
cp .env.example .env
composer install
npm install && npm run build      # use `npm run dev` while editing views
php artisan key:generate
```

4. Edit `.env`:

```dotenv
APP_URL=http://moderation-dems.test
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=dems
DB_USERNAME=root          # whatever Herd shows for the Postgres service
DB_PASSWORD=
DEMS_SEED_PASSWORD=choose-a-password-10+chars
DEMS_DEMO_DATA=true       # demo accounts on the login page (development only)
```

5. Create tables and accounts:

```bash
php artisan migrate
php artisan db:seed
```

6. Open `http://moderation-dems.test`. Demo logins: `hod@`, `examiner@`, `moderator@`, `external@example.edu` (all use `DEMS_SEED_PASSWORD`).

> **zsh tip:** don't paste lines with `# comments (like this)` into a macOS terminal — zsh treats `( )` specially. Paste only the commands.

---

## 2. Supabase (database)

1. [supabase.com](https://supabase.com) → **New project**; save the **database password**.
2. Click **Connect** → choose **Session pooler** → set **Type** to **URI** → copy the string:

```
postgresql://postgres.<PROJECT-ID>:[YOUR-PASSWORD]@aws-0-<REGION>.pooler.supabase.com:5432/postgres
```

3. Replace `[YOUR-PASSWORD]` (brackets included) with your password. Use **letters and numbers only** — characters such as `@ # / : ? % &` must otherwise be URL-encoded.
4. Use it as `DB_URL` (see below). Keep `DB_CONNECTION=pgsql` and `DB_SSLMODE=require`.

Why the *Session pooler* on port **5432**? The direct host (`db.<id>.supabase.co`) is IPv6-only and Render cannot reach it; the session pooler is IPv4 and supports normal prepared statements. (If you ever use the *Transaction* pooler, port 6543, also set `DB_EMULATE_PREPARES=true`.)

Row Level Security is enabled on every table by a migration, so Supabase's public API key cannot read any data; the app connects as the owner through this connection string.

---

## 3. Render (free, Docker)

Render has no PHP runtime, so the app runs as a **Docker** service built from the repo's `Dockerfile`. The container applies the migrations and seeds the first HOD on every start.

### 3a. Prepare the variables

Template: [`.env.render.example`](../.env.render.example).

| Variable | Value |
|---|---|
| `APP_KEY` | `echo "base64:$(openssl rand -base64 32)"` — paste the whole result |
| `APP_URL` | `https://<your-service>.onrender.com` |
| `DB_URL` | the Supabase Session pooler string (step 2) |
| `DEMS_SEED_EMAIL` / `DEMS_SEED_PASSWORD` | first login (HOD) — password 10+ characters |
| `DEMS_DEMO_DATA` | `true` for demo accounts on the login page; **remove for real use** |
| `INSTITUTION_NAME` | shown on the login page and the PDF |
| fixed values | `APP_ENV=production`, `APP_DEBUG=false`, `PORT=8080`, `LOG_CHANNEL=stderr`, `DB_CONNECTION=pgsql`, `DB_SSLMODE=require`, `SESSION_DRIVER=database`, `SESSION_SECURE_COOKIE=true`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=log` |

### 3b. Create the service

**Option A – Blueprint** (reads `render.yaml`, free plan): *New + → Blueprint →* repository `meyerjo2024/ModerationDEMS` → **select the branch that contains `render.yaml` with `plan: free`** (see troubleshooting) → fill the prompted variables → **Apply**.

**Option B – Web Service** (use this if Blueprints ask for a card):
1. *New + → Web Service →* connect the repository and the correct branch.
2. **Language: Docker**, **Instance type: Free**, **Health Check Path:** `/health`.
3. *Environment → Add from .env* → paste the variables from 3a → **Create**.

### 3c. Verify

- Build log shows Docker steps (`composer install`, `npm run build`) — **not** `prisma` or `next build`.
- Start log shows:

```
──── DEMS database check ────
driver=pgsql host=aws-0-….pooler.supabase.com port=5432 …
RESULT: CONNECTED OK
… Running migrations … create_dems_tables … DONE
```

- `https://<service>.onrender.com/health` → `{"ok":true}`; then sign in.

Free instances sleep after ~15 minutes idle; the first request afterwards takes about a minute.

---

## E-mail (optional)

Not needed for a demo. With the default `MAIL_MAILER=log` nothing is sent; the signed PDF is available on the record. To e-mail the HOD, set an SMTP transport:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=…
MAIL_PASSWORD=…
MAIL_FROM_ADDRESS=no-reply@your-institution.edu
```

## File storage

Uploaded files and generated PDFs are stored in the database (`DEMS_STORAGE=db`), so they survive redeploys on free hosting. `DEMS_STORAGE=disk` + `DEMS_DISK=<disk>` switches to a Laravel filesystem disk.

---

## Troubleshooting (problems seen in real deployments)

| Symptom | Cause | Fix |
|---|---|---|
| Render log shows `npm install`, `prisma generate`, `next build`, "DATABASE_URL is not set" | The service is building the **old Next.js code** on `main` | Deploy the branch that contains the Laravel app, or merge it into `main`; use a **Docker** service |
| "Payment Information Required" on a Blueprint | The Blueprint was read from a branch whose `render.yaml` still has paid plans | Select the branch with `plan: free`, or use **Option B** |
| `Waiting on default database connection…` then exit | Wrong/missing `DB_URL` | Read the `DEMS database check` block above it; fix per the rows below |
| `DB_URL set: NO` or `host=127.0.0.1` | `DB_URL` not saved | Add it under *Environment* |
| `could not translate host name` | Placeholder or direct-connection host | Copy the **Session pooler** string from Supabase |
| `Tenant or user not found` | Wrong project ID or region in the string | Re-copy the whole string from Supabase |
| `password authentication failed` | Wrong or URL-unsafe password | Reset it (letters + numbers), rebuild the string, paste fresh |
| `ECIRCUITBREAKER too many authentication failures` | Supabase blocked the server after repeated bad logins | Suspend the service, fix the password, wait 10–15 min, redeploy |
| `Unsupported cipher or incorrect key length` | `APP_KEY` missing or malformed | `echo "base64:$(openssl rand -base64 32)"` |
| `No open ports detected` | The app never started (usually the database error above) | Fix the database first; `PORT` must be `8080` |
| Login page has no demo box | `DEMS_DEMO_DATA` is not `true` (or seeding ran before it was set) | Set it and redeploy |
| `psql: … socket /tmp/.s.PGSQL.5432` on your Mac | You ran `psql` without a connection string | Quote the full URL: `psql "postgresql://…" -c "select 1"` (straight quotes!) |

**Security reminders:** never paste connection strings, passwords or screenshots of the *Environment* tab (the eye icon reveals values) into chats or tickets; if you did, reset the password. Switch demo data off for real use.
