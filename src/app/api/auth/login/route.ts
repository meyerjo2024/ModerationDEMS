import bcrypt from "bcryptjs";
import { z } from "zod";
import { audit } from "@/lib/audit";
import { clearLoginFailures, loginThrottled, recordLoginFailure, startSession, verifyPassword } from "@/lib/auth";
import { clientIp, parseJson, route } from "@/lib/api";
import { prisma } from "@/lib/db";
import { ApiError } from "@/lib/errors";

const schema = z.object({ email: z.string().trim().toLowerCase().email(), password: z.string().min(1) });

// Real bcrypt hash so unknown e-mails cost the same time as wrong passwords.
const DUMMY = bcrypt.hashSync("not-a-real-password", 12);

export const POST = route(async (req) => {
  const { email, password } = await parseJson(req, schema);
  const ip = clientIp(req) ?? "unknown";
  const key = `login:${ip}:${email}`;
  if (loginThrottled(key)) throw new ApiError(429, "Too many attempts. Please wait a few minutes and try again.");

  const user = await prisma.user.findUnique({ where: { email } });
  const ok = await verifyPassword(password, user?.passwordHash ?? DUMMY);
  if (!user || !user.active || !ok) {
    recordLoginFailure(key);
    await audit({ action: "LOGIN_FAILED", details: { email }, ip });
    throw new ApiError(401, "E-mail or password is incorrect.");
  }
  clearLoginFailures(key);
  await startSession(user);
  await audit({ userId: user.id, action: "LOGIN", ip });
  return { user: { id: user.id, name: user.name, role: user.role } };
});
