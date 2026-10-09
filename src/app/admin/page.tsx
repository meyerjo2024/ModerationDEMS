import type { Metadata } from "next";
import { redirect } from "next/navigation";
import { AppShell } from "@/components/AppShell";
import { getCurrentUser } from "@/lib/auth";
import { prisma } from "@/lib/db";
import { AdminForms } from "./AdminForms";
import { ROLE_LABEL } from "@/lib/workflow";

export const metadata: Metadata = { title: "Admin" };
export const dynamic = "force-dynamic";

export default async function AdminPage() {
  const user = await getCurrentUser();
  if (!user) redirect("/login");
  if (user.role !== "HOD") redirect("/");
  const [users, subjects] = await Promise.all([
    prisma.user.findMany({ orderBy: [{ role: "asc" }, { name: "asc" }], select: { id: true, name: true, email: true, role: true } }),
    prisma.subject.findMany({ orderBy: { code: "asc" }, include: { hod: { select: { name: true } } } }),
  ]);
  const hods = users.filter((u) => u.role === "HOD").map((u) => ({ id: u.id, name: u.name }));
  return (
    <AppShell>
      <div className="space-y-8">
        <div>
          <p className="kicker">Administration</p>
          <h1 className="mt-1 text-3xl font-semibold">People & subjects</h1>
        </div>
        <AdminForms hods={hods} defaultHod={user.id} />
        <div className="grid gap-6 lg:grid-cols-2">
          <section className="card p-6">
            <p className="kicker mb-3">Subjects · {subjects.length}</p>
            <ul className="divide-y divide-slate-200/60 text-sm dark:divide-white/10">
              {subjects.map((s) => (
                <li key={s.id} className="flex justify-between gap-3 py-2.5">
                  <span><span className="font-semibold">{s.code}</span> <span className="text-slate-500">{s.name}</span></span>
                  <span className="text-slate-400">{s.hod.name}</span>
                </li>
              ))}
              {subjects.length === 0 && <li className="py-6 text-slate-400">No subjects yet.</li>}
            </ul>
          </section>
          <section className="card p-6">
            <p className="kicker mb-3">Users · {users.length}</p>
            <ul className="divide-y divide-slate-200/60 text-sm dark:divide-white/10">
              {users.map((u) => (
                <li key={u.id} className="flex justify-between gap-3 py-2.5">
                  <span className="min-w-0"><span className="font-medium">{u.name}</span> <span className="truncate text-slate-400">{u.email}</span></span>
                  <span className="shrink-0 text-slate-500">{ROLE_LABEL[u.role]}</span>
                </li>
              ))}
            </ul>
          </section>
        </div>
      </div>
    </AppShell>
  );
}
