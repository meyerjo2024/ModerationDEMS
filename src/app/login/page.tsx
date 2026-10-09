import type { Metadata } from "next";
import { LoginForm } from "./LoginForm";

export const metadata: Metadata = { title: "Sign in" };
export const dynamic = "force-dynamic";

const DEMO = [
  { role: "Head of Department", email: "hod@example.edu" },
  { role: "Examiner", email: "examiner@example.edu" },
  { role: "Internal moderator", email: "moderator@example.edu" },
  { role: "External moderator", email: "external@example.edu" },
];

export default function LoginPage() {
  // Demo accounts are only advertised when the database was seeded with SEED_DEMO_DATA=true.
  const demo = process.env.SEED_DEMO_DATA === "true" ? { accounts: DEMO, password: process.env.SEED_ADMIN_PASSWORD ?? "" } : null;
  return <LoginForm demo={demo} institution={process.env.INSTITUTION_NAME ?? "Your Institution"} />;
}
