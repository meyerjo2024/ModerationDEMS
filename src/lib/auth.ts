import { cookies } from "next/headers";
import bcrypt from "bcryptjs";
import type { Role, User } from "@prisma/client";
import { prisma } from "./db";
import { forbidden, unauthorized } from "./errors";
import { SESSION_COOKIE, SESSION_TTL_SECONDS, signSession, verifySession } from "./session";

export type SessionUser = Pick<User, "id" | "name" | "email" | "role" | "department">;

export const hashPassword = (pw: string) => bcrypt.hash(pw, 12);
export const verifyPassword = (pw: string, hash: string) => bcrypt.compare(pw, hash);

export async function startSession(user: { id: string; role: Role }) {
  const token = await signSession({ uid: user.id, role: user.role });
  cookies().set(SESSION_COOKIE, token, {
    httpOnly: true,
    sameSite: "lax",
    secure: process.env.NODE_ENV === "production",
    path: "/",
    maxAge: SESSION_TTL_SECONDS,
  });
}

export function endSession() {
  cookies().delete(SESSION_COOKIE);
}

/** Current user (re-read from the DB so deactivation / role changes apply immediately). */
export async function getCurrentUser(): Promise<SessionUser | null> {
  const payload = await verifySession(cookies().get(SESSION_COOKIE)?.value);
  if (!payload) return null;
  const user = await prisma.user.findUnique({
    where: { id: payload.uid },
    select: { id: true, name: true, email: true, role: true, department: true, active: true },
  });
  if (!user || !user.active) return null;
  const { active: _a, ...rest } = user;
  return rest;
}

/** Role-based access control guard for API routes and server actions. */
export async function requireUser(...roles: Role[]): Promise<SessionUser> {
  const user = await getCurrentUser();
  if (!user) throw unauthorized();
  if (roles.length && !roles.includes(user.role)) throw forbidden();
  return user;
}

// ── tiny in-memory login throttle (per instance) ─────────────────────────────
const attempts = new Map<string, { n: number; first: number }>();
const WINDOW_MS = 15 * 60 * 1000;
const MAX_ATTEMPTS = 8;

export function loginThrottled(key: string): boolean {
  const a = attempts.get(key);
  if (!a) return false;
  if (Date.now() - a.first > WINDOW_MS) {
    attempts.delete(key);
    return false;
  }
  return a.n >= MAX_ATTEMPTS;
}
export function recordLoginFailure(key: string) {
  const a = attempts.get(key);
  if (!a || Date.now() - a.first > WINDOW_MS) attempts.set(key, { n: 1, first: Date.now() });
  else a.n += 1;
}
export function clearLoginFailures(key: string) {
  attempts.delete(key);
}
