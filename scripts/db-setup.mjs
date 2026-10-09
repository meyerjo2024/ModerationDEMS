// Runs during `npm run build` on Render (RENDER=true) or when DEMS_AUTO_MIGRATE=true:
// applies pending migrations and seeds the HOD account. Both steps are idempotent.
import { execSync } from "node:child_process";

if (process.env.RENDER !== "true" && process.env.DEMS_AUTO_MIGRATE !== "true") {
  console.log("[db-setup] skipped (not on Render; set DEMS_AUTO_MIGRATE=true to force)");
  process.exit(0);
}
if (!process.env.DATABASE_URL) {
  console.error("[db-setup] DATABASE_URL is not set. Add your Supabase connection strings (DATABASE_URL and DIRECT_URL) to this service's environment.");
  process.exit(1);
}
// Migrations need a direct connection; fall back to DATABASE_URL for plain Postgres.
process.env.DIRECT_URL ||= process.env.DATABASE_URL;
const run = (cmd) => execSync(cmd, { stdio: "inherit" });
console.log("[db-setup] applying migrations…");
run("npx prisma migrate deploy");
console.log("[db-setup] seeding…");
run("npx tsx prisma/seed.ts");
