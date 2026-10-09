import type { Prisma } from "@prisma/client";
import { prisma } from "../db";
import type { SessionUser } from "../auth";
import { calculateStatistics } from "../calc";
import { canView } from "../rbac";

/** Assessments the user may see (HODs see everything in their subjects). */
export async function listAssessments(user: SessionUser) {
  const where: Prisma.AssessmentWhereInput = {
    OR: [
      { examinerId: user.id },
      { internalModeratorId: user.id },
      { externalModeratorId: user.id, status: { in: ["PENDING_EXTERNAL_MODERATION", "COMPLETED"] } },
      { subject: { hodId: user.id } },
    ],
  };
  return prisma.assessment.findMany({
    where,
    orderBy: { updatedAt: "desc" },
    select: {
      id: true, number: true, status: true, updatedAt: true, revision: true,
      examinerId: true, internalModeratorId: true, externalModeratorId: true,
      passRate: true, candidateCount: true, classAverage: true,
      subject: { select: { id: true, code: true, name: true, hodId: true } },
      examiner: { select: { name: true } },
      internalModerator: { select: { name: true } },
    },
  });
}

/** Full detail for the record page. Signature images are included for display. */
export async function getAssessmentDetail(user: SessionUser, id: string) {
  const a = await prisma.assessment.findUnique({
    where: { id },
    include: {
      subject: { include: { hod: { select: { name: true } } } },
      examiner: { select: { id: true, name: true } },
      internalModerator: { select: { id: true, name: true } },
      externalModerator: { select: { id: true, name: true } },
      attachments: { orderBy: { createdAt: "desc" }, select: { id: true, kind: true, filename: true, size: true, mimeType: true, createdAt: true, sha256: true } },
      records: { orderBy: { createdAt: "asc" }, include: { reviewer: { select: { name: true } } } },
      signatures: { orderBy: { signedAt: "asc" }, include: { user: { select: { name: true, role: true } } } },
      auditLogs: { orderBy: [{ createdAt: "asc" }, { id: "asc" }], include: { user: { select: { name: true, role: true } } } },
    },
  });
  if (!a || !canView(user, a)) return null;
  const { scores, ...rest } = a; // raw scores stay server-side; only the banded distribution is exposed
  const distribution = scores && a.totalMarks ? calculateStatistics(scores as number[], { totalMarks: a.totalMarks }).distribution : null;
  return { ...rest, distribution };
}
