import type { AttachmentKind, Prisma, PrismaClient } from "@prisma/client";
import { calculateStatistics } from "../calc";
import { CHECK_IDS } from "../checklist";
import { ROLE_LABEL } from "../workflow";
import { renderReportPdf, type ReportData } from "../pdf";
import { KIND_LABEL } from "../uploads";

type Db = PrismaClient | Prisma.TransactionClient;

const STAGE_LABEL = {
  PRE_MODERATION: "Gate 1 · Internal pre-moderation",
  FINAL_INTERNAL: "Gate 2 · Internal final moderation",
  FINAL_EXTERNAL: "Gate 3 · External moderation",
} as const;

const SECTION_LABEL = {
  EXAMINER_SECTION_1: "Examiner · Section 1",
  PRE_MODERATOR: "Internal moderator · Pre-moderation",
  EXAMINER_SECTION_2: "Examiner · Section 2",
  FINAL_INTERNAL_MODERATOR: "Internal moderator · Final",
  EXTERNAL_MODERATOR: "External moderator",
} as const;

const SECTION_ORDER = Object.keys(SECTION_LABEL);

/** Latest current attachment of each kind (older versions are retained for audit). */
export async function currentAttachment(db: Db, assessmentId: string, kind: AttachmentKind) {
  return db.attachment.findFirst({ where: { assessmentId, kind }, orderBy: { createdAt: "desc" } });
}

export async function buildReportData(db: Db, assessmentId: string): Promise<ReportData> {
  const a = await db.assessment.findUniqueOrThrow({
    where: { id: assessmentId },
    include: {
      subject: { include: { hod: { select: { name: true } } } },
      examiner: { select: { name: true } },
      internalModerator: { select: { name: true } },
      externalModerator: { select: { name: true } },
      records: { include: { reviewer: { select: { name: true } } }, orderBy: { createdAt: "asc" } },
      signatures: { include: { user: { select: { name: true, role: true } } }, orderBy: { signedAt: "asc" } },
    },
  });

  const docs = [];
  for (const kind of ["PAPER", "MEMO", "MARKS"] as const) {
    const att = await currentAttachment(db, assessmentId, kind);
    if (att) docs.push({ label: KIND_LABEL[kind], filename: att.filename, sha256: att.sha256, uploadedAt: att.createdAt });
  }

  // Only the latest *approved* review per stage, and the latest signature per section, count.
  const reviews = (["PRE_MODERATION", "FINAL_INTERNAL", "FINAL_EXTERNAL"] as const).flatMap((stage) => {
    const r = [...a.records].reverse().find((x) => x.stage === stage && x.decision === "APPROVED");
    if (!r) return [];
    const list = (r.checklist as { id: string; question: string; answer: string; comment?: string }[] | null) ?? [];
    return [
      {
        stageLabel: STAGE_LABEL[stage],
        reviewer: r.reviewer.name,
        decision: "Approved",
        consensusReached: r.consensusReached,
        comments: r.comments,
        scriptsSampled: r.scriptsSampled,
        checklist: list.filter((c) => CHECK_IDS.includes(c.id)),
        date: r.createdAt,
      },
    ];
  });

  const signatures = SECTION_ORDER.flatMap((section) => {
    const s = [...a.signatures].reverse().find((x) => x.section === section);
    if (!s) return [];
    return [
      {
        label: SECTION_LABEL[section as keyof typeof SECTION_LABEL],
        name: s.user.name,
        role: ROLE_LABEL[s.user.role],
        signedAt: s.signedAt,
        contentHash: s.contentHash,
        image: Buffer.from(s.imageData.split(",")[1] ?? "", "base64"),
      },
    ];
  });

  let stats: ReportData["stats"];
  if (a.scores && a.totalMarks) {
    const c = calculateStatistics(a.scores as number[], { totalMarks: a.totalMarks });
    stats = {
      totalMarks: c.totalMarks,
      passMark: c.passMark,
      candidateCount: a.candidateCount ?? c.candidateCount,
      passCount: a.passCount ?? c.passCount,
      passRate: a.passRate ?? c.passRate,
      highestMark: a.highestMark ?? c.highestMark,
      lowestMark: a.lowestMark ?? c.lowestMark,
      classAverage: a.classAverage ?? c.classAverage,
      invalidEntries: a.invalidEntries ?? 0,
      distribution: c.distribution,
      source: a.marksSource,
    };
  }

  const auditCount = await db.auditLog.count({ where: { assessmentId } });
  const head = await db.auditLog.findFirst({ where: { assessmentId }, orderBy: [{ createdAt: "desc" }, { id: "desc" }], select: { hash: true } });

  return {
    institution: process.env.INSTITUTION_NAME ?? "Your Institution",
    reportId: a.id,
    generatedAt: new Date(),
    subject: a.subject,
    assessmentNumber: a.number,
    examiner: a.examiner.name,
    internalModerator: a.internalModerator.name,
    externalModerator: a.externalModerator?.name ?? null,
    hod: a.subject.hod.name,
    questionTypes: (a.questionTypes as ReportData["questionTypes"] | null) ?? [],
    documents: docs,
    stats,
    commentary: a.examinerCommentary,
    reviews,
    signatures,
    audit: { count: auditCount, headHash: head?.hash ?? null },
  };
}

export async function generateReportPdf(db: Db, assessmentId: string) {
  return Buffer.from(await renderReportPdf(await buildReportData(db, assessmentId)));
}
