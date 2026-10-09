import type { Prisma, PrismaClient } from "@prisma/client";
import { prisma } from "./db";
import { hashContent } from "./hash";

type Db = PrismaClient | Prisma.TransactionClient;

export interface AuditInput {
  assessmentId?: string | null;
  userId?: string | null;
  action: string;
  details?: Prisma.InputJsonValue;
  ip?: string | null;
}

/**
 * Appends an audit entry. Entries tied to an assessment are hash-chained
 * (each hash covers the previous one) so edits are detectable after the fact.
 */
export async function audit(input: AuditInput, db: Db = prisma) {
  const createdAt = new Date();
  let prevHash: string | null = null;
  if (input.assessmentId) {
    const last = await db.auditLog.findFirst({
      where: { assessmentId: input.assessmentId },
      orderBy: [{ createdAt: "desc" }, { id: "desc" }],
      select: { hash: true },
    });
    prevHash = last?.hash ?? null;
  }
  const hash = hashContent({
    prevHash,
    assessmentId: input.assessmentId ?? null,
    userId: input.userId ?? null,
    action: input.action,
    details: input.details ?? null,
    createdAt: createdAt.toISOString(),
  });
  return db.auditLog.create({
    data: {
      assessmentId: input.assessmentId ?? null,
      userId: input.userId ?? null,
      action: input.action,
      details: input.details ?? undefined,
      ipAddress: input.ip ?? null,
      prevHash,
      hash,
      createdAt,
    },
  });
}

/** Re-computes the chain for an assessment; returns false if any link is broken. */
export async function verifyAuditChain(assessmentId: string): Promise<boolean> {
  const rows = await prisma.auditLog.findMany({
    where: { assessmentId },
    orderBy: [{ createdAt: "asc" }, { id: "asc" }],
  });
  let prev: string | null = null;
  for (const r of rows) {
    const expected = hashContent({
      prevHash: prev,
      assessmentId: r.assessmentId,
      userId: r.userId,
      action: r.action,
      details: r.details ?? null,
      createdAt: r.createdAt.toISOString(),
    });
    if (r.prevHash !== prev || r.hash !== expected) return false;
    prev = r.hash;
  }
  return true;
}
