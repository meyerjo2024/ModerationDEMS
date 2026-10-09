import { requireUser } from "@/lib/auth";
import { route } from "@/lib/api";
import { prisma } from "@/lib/db";

export const dynamic = "force-dynamic";

export const GET = route(async () => {
  const user = await requireUser();
  const items = await prisma.notification.findMany({
    where: { userId: user.id },
    orderBy: { createdAt: "desc" },
    take: 20,
    select: { id: true, message: true, read: true, createdAt: true, assessmentId: true },
  });
  return { notifications: items, unread: items.filter((n) => !n.read).length };
});

/** Marks all of the signed-in user's notifications as read. */
export const POST = route(async () => {
  const user = await requireUser();
  await prisma.notification.updateMany({ where: { userId: user.id, read: false }, data: { read: true } });
  return { ok: true };
});
