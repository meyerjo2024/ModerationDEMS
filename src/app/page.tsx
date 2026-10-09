import { redirect } from "next/navigation";
import { AppShell } from "@/components/AppShell";
import { Dashboard } from "@/components/Dashboard";
import { getCurrentUser } from "@/lib/auth";
import { needsMyAction } from "@/lib/rbac";
import { listAssessments } from "@/lib/services/queries";
import { toJson } from "@/lib/types";

export const dynamic = "force-dynamic";

export default async function HomePage() {
  const user = await getCurrentUser();
  if (!user) redirect("/login");
  const rows = await listAssessments(user);
  const items = rows.map(({ examinerId, internalModeratorId, externalModeratorId, ...r }) => ({
    ...r,
    myAction: needsMyAction(user, { examinerId, internalModeratorId, externalModeratorId, status: r.status, subject: r.subject }),
    hasExternal: !!externalModeratorId,
  }));
  return (
    <AppShell wide>
      <Dashboard user={{ name: user.name, role: user.role }} items={toJson(items)} />
    </AppShell>
  );
}
