import type { Prisma, PrismaClient } from "@prisma/client";
import { prisma } from "./db";
import { mailHtml, sendMail } from "./mail";

type Db = PrismaClient | Prisma.TransactionClient;

export const appUrl = () => (process.env.APP_URL ?? "http://localhost:3000").replace(/\/$/, "");

/** In-app notification rows (transactional). */
export async function createNotifications(db: Db, userIds: string[], assessmentId: string, message: string) {
  const ids = [...new Set(userIds)];
  if (!ids.length) return;
  await db.notification.createMany({ data: ids.map((userId) => ({ userId, assessmentId, message })) });
}

/** Best-effort e-mail to users; never throws (delivery problems must not roll back workflow). */
export async function emailUsers(userIds: string[], assessmentId: string, subject: string, message: string) {
  try {
    const users = await prisma.user.findMany({ where: { id: { in: [...new Set(userIds)] }, active: true }, select: { email: true } });
    if (!users.length) return;
    const url = `${appUrl()}/assessments/${assessmentId}`;
    await sendMail({
      to: users.map((u) => u.email),
      subject,
      text: `${message}\n\nOpen the record: ${url}`,
      html: mailHtml(subject, message, "Open record", url),
    });
  } catch (e) {
    console.error("[notify] e-mail failed", e);
  }
}
