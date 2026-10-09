import type { Metadata } from "next";
import { redirect } from "next/navigation";
import { AppShell } from "@/components/AppShell";
import { getCurrentUser } from "@/lib/auth";
import { prisma } from "@/lib/db";
import { NewAssessmentForm } from "./NewAssessmentForm";

export const metadata: Metadata = { title: "New assessment" };
export const dynamic = "force-dynamic";

export default async function NewAssessmentPage() {
  const user = await getCurrentUser();
  if (!user) redirect("/login");
  if (user.role !== "EXAMINER") redirect("/");
  const [subjects, internals, externals] = await Promise.all([
    prisma.subject.findMany({ orderBy: { code: "asc" }, select: { id: true, code: true, name: true } }),
    prisma.user.findMany({ where: { role: "INTERNAL_MODERATOR", active: true, NOT: { id: user.id } }, orderBy: { name: "asc" }, select: { id: true, name: true } }),
    prisma.user.findMany({ where: { role: "EXTERNAL_MODERATOR", active: true }, orderBy: { name: "asc" }, select: { id: true, name: true } }),
  ]);
  return (
    <AppShell>
      <NewAssessmentForm subjects={subjects} internals={internals} externals={externals} />
    </AppShell>
  );
}
