import { z } from "zod";
import type { Assessment, AssessmentStatus, AttachmentKind, Prisma, SignatureSection } from "@prisma/client";
import { prisma } from "../db";
import type { SessionUser } from "../auth";
import { badRequest, conflict, forbidden, notFound } from "../errors";
import { audit } from "../audit";
import { calculateStatistics, CalcError, parseManualScores, type CalcResult } from "../calc";
import { QUALITY_CHECKS } from "../checklist";
import { hashContent, sha256 } from "../hash";
import { inspectWorkbook, readColumn } from "../excel";
import { createNotifications, emailUsers } from "../notify";
import { sendMail, mailHtml } from "../mail";
import { canView } from "../rbac";
import { confirmSigner, recordSignature, signatureInput, type RequestMeta } from "../signatures";
import { getFile, putFile } from "../storage";
import { validateUpload } from "../uploads";
import { currentAttachment, generateReportPdf } from "./report";

export interface Ctx {
  user: SessionUser;
  meta: RequestMeta;
}

// ── schemas ─────────────────────────────────────────────────────────────────
export const questionRow = z.object({
  type: z.string().trim().min(1, "Give each question type a name.").max(80),
  weighting: z.number({ invalid_type_error: "Weighting must be a number." }).min(0).max(100),
  heqfLevel: z.number().int().min(5, "HEQF levels run from 5 to 10.").max(10, "HEQF levels run from 5 to 10."),
  aligned: z.boolean(),
  comment: z.string().max(500).optional().default(""),
});
export const questionTypesSchema = z.array(questionRow).min(1, "Add at least one question type.").max(15);
export type QuestionRow = z.infer<typeof questionRow>;

export const createSchema = z.object({
  subjectId: z.string().min(1),
  number: z.string().trim().min(1, "Enter the assessment number.").max(40),
  internalModeratorId: z.string().min(1, "Choose an internal moderator."),
  externalModeratorId: z.string().min(1).nullish(),
});

export const submitPreSchema = z.object({ questionTypes: questionTypesSchema, signature: signatureInput });

export const preReviewSchema = z.discriminatedUnion("decision", [
  z.object({ decision: z.literal("REVISION_REQUESTED"), comments: z.string().trim().min(5, "Tell the examiner what needs to change.").max(4000) }),
  z.object({
    decision: z.literal("APPROVED"),
    consensusReached: z.literal(true, { errorMap: () => ({ message: "Tick “Consensus reached” to approve." }) }),
    comments: z.string().trim().max(4000).optional(),
    signature: signatureInput,
  }),
]);

export const marksSourceSchema = z.discriminatedUnion("type", [
  z.object({ type: z.literal("excel"), attachmentId: z.string().min(1), sheet: z.string().min(1), column: z.number().int().min(1).max(500) }),
  z.object({ type: z.literal("manual"), text: z.string().min(1).max(200_000) }),
]);
export type MarksSource = z.infer<typeof marksSourceSchema>;

export const calculateSchema = z.object({ source: marksSourceSchema, totalMarks: z.number().positive().max(100000).default(100) });

export const submitPostSchema = z.object({
  source: marksSourceSchema,
  totalMarks: z.number().positive().max(100000),
  commentary: z.string().trim().min(10, "Add a few words on student performance.").max(6000),
  signature: signatureInput,
});

const checklistAnswer = z.object({
  id: z.string(),
  answer: z.enum(["YES", "NO", "NA"]),
  comment: z.string().max(500).optional().default(""),
});
export const finalReviewSchema = z.discriminatedUnion("decision", [
  z.object({ decision: z.literal("REVISION_REQUESTED"), comments: z.string().trim().min(5, "Tell the examiner what needs to change.").max(4000) }),
  z.object({
    decision: z.literal("APPROVED"),
    consensusReached: z.literal(true, { errorMap: () => ({ message: "Tick “Consensus reached” to approve." }) }),
    checklist: z.array(checklistAnswer),
    scriptsSampled: z.number().int().min(0).max(10000),
    comments: z.string().trim().max(4000).optional(),
    signature: signatureInput,
  }),
]);

// ── helpers ─────────────────────────────────────────────────────────────────
const include = { subject: true } satisfies Prisma.AssessmentInclude;

export async function loadAssessment(user: SessionUser, id: string) {
  const a = await prisma.assessment.findUnique({ where: { id }, include });
  if (!a || !canView(user, a)) throw notFound("Assessment not found.");
  return a;
}

function expectStatus(a: Pick<Assessment, "status">, ...ok: AssessmentStatus[]) {
  if (!ok.includes(a.status)) throw conflict("This record has moved on and can no longer be changed that way. Please refresh.");
}

/** Optimistic state-machine step: fails if another request already moved the record. */
async function transition(
  tx: Prisma.TransactionClient,
  id: string,
  from: AssessmentStatus[],
  data: Prisma.AssessmentUpdateManyMutationInput,
) {
  const { count } = await tx.assessment.updateMany({ where: { id, status: { in: from } }, data });
  if (count !== 1) throw conflict("This record was just updated by someone else. Please refresh.");
}

const isExaminerOf = (u: SessionUser, a: Assessment) => u.id === a.examinerId;

function assertExaminer(ctx: Ctx, a: Assessment) {
  if (!isExaminerOf(ctx.user, a)) throw forbidden("Only the examiner for this assessment can do that.");
}

// ── create ──────────────────────────────────────────────────────────────────
export async function createAssessment(ctx: Ctx, input: z.infer<typeof createSchema>) {
  if (ctx.user.role !== "EXAMINER") throw forbidden("Only examiners can start an assessment.");
  const ext = input.externalModeratorId || null;
  if (input.internalModeratorId === ctx.user.id) throw badRequest("You cannot moderate your own assessment.");
  if (ext && (ext === ctx.user.id || ext === input.internalModeratorId)) throw badRequest("The external moderator must be a different person.");

  const [subject, internal, external] = await Promise.all([
    prisma.subject.findUnique({ where: { id: input.subjectId } }),
    prisma.user.findFirst({ where: { id: input.internalModeratorId, role: "INTERNAL_MODERATOR", active: true } }),
    ext ? prisma.user.findFirst({ where: { id: ext, role: "EXTERNAL_MODERATOR", active: true } }) : null,
  ]);
  if (!subject) throw badRequest("Unknown subject.");
  if (!internal) throw badRequest("Choose a valid internal moderator.");
  if (ext && !external) throw badRequest("Choose a valid external moderator.");
  if (await prisma.assessment.findUnique({ where: { subjectId_number: { subjectId: subject.id, number: input.number } } })) {
    throw conflict(`${subject.code} already has an assessment called “${input.number}”.`);
  }

  return prisma.$transaction(async (tx) => {
    const a = await tx.assessment.create({
      data: {
        subjectId: subject.id,
        number: input.number,
        examinerId: ctx.user.id,
        internalModeratorId: internal.id,
        externalModeratorId: external?.id ?? null,
      },
    });
    await audit({ assessmentId: a.id, userId: ctx.user.id, action: "ASSESSMENT_CREATED", details: { subject: subject.code, number: a.number }, ip: ctx.meta.ip }, tx);
    return a;
  });
}

// ── Phase 1: Section 1 ──────────────────────────────────────────────────────
export async function saveSection1(ctx: Ctx, id: string, questionTypes: QuestionRow[]) {
  const a = await loadAssessment(ctx.user, id);
  assertExaminer(ctx, a);
  expectStatus(a, "DRAFT", "REVISION_REQUESTED");
  await prisma.assessment.update({ where: { id }, data: { questionTypes } });
  return { ok: true };
}

export async function uploadAttachment(ctx: Ctx, id: string, kind: AttachmentKind, filename: string, data: Buffer) {
  const a = await loadAssessment(ctx.user, id);
  assertExaminer(ctx, a);
  const slots: Partial<Record<AttachmentKind, AssessmentStatus[]>> = {
    PAPER: ["DRAFT", "REVISION_REQUESTED"],
    MEMO: ["DRAFT", "REVISION_REQUESTED"],
    MARKS: ["READY_FOR_POST_MODERATION"],
    SAMPLE_SCRIPT: ["READY_FOR_POST_MODERATION"],
  };
  const allowed = slots[kind];
  if (!allowed) throw badRequest("That kind of file cannot be uploaded.");
  if (!allowed.includes(a.status)) throw conflict("Files can't be added at this stage.");
  const { mimeType, filename: safe } = validateUpload(kind, filename, data);
  const storageKey = await putFile(data, mimeType);
  return prisma.$transaction(async (tx) => {
    const att = await tx.attachment.create({
      data: { assessmentId: id, kind, filename: safe, mimeType, size: data.length, sha256: sha256(data), storageKey, uploadedById: ctx.user.id },
      select: { id: true, kind: true, filename: true, size: true, createdAt: true },
    });
    await audit({ assessmentId: id, userId: ctx.user.id, action: "FILE_UPLOADED", details: { kind, filename: safe, sha256: sha256(data) }, ip: ctx.meta.ip }, tx);
    return att;
  });
}

export async function submitPreAssessment(ctx: Ctx, id: string, input: z.infer<typeof submitPreSchema>) {
  const a = await loadAssessment(ctx.user, id);
  assertExaminer(ctx, a);
  expectStatus(a, "DRAFT", "REVISION_REQUESTED");

  const total = input.questionTypes.reduce((s, q) => s + q.weighting, 0);
  if (Math.abs(total - 100) > 0.01) throw badRequest(`Question weightings must add up to 100% (currently ${+total.toFixed(2)}%).`);
  const [paper, memo] = await Promise.all([currentAttachment(prisma, id, "PAPER"), currentAttachment(prisma, id, "MEMO")]);
  if (!paper) throw badRequest("Upload the draft assessment paper first.");
  if (!memo) throw badRequest("Upload the memorandum first.");
  await confirmSigner(ctx.user, input.signature);

  const revision = a.revision + 1;
  const contentHash = hashContent({ section: 1, id, revision, questionTypes: input.questionTypes, paper: paper.sha256, memo: memo.sha256 });
  await prisma.$transaction(async (tx) => {
    await transition(tx, id, ["DRAFT", "REVISION_REQUESTED"], { status: "PENDING_PRE_MODERATION", questionTypes: input.questionTypes, revision });
    await recordSignature(tx, { assessmentId: id, userId: ctx.user.id, section: "EXAMINER_SECTION_1", image: input.signature.image, contentHash, meta: ctx.meta });
    await audit({ assessmentId: id, userId: ctx.user.id, action: "SECTION1_SUBMITTED", details: { revision, contentHash }, ip: ctx.meta.ip }, tx);
    await createNotifications(tx, [a.internalModeratorId], id, `${a.subject.code} ${a.number} is ready for your pre-moderation review.`);
  });
  void emailUsers([a.internalModeratorId], id, `Pre-moderation requested: ${a.subject.code} ${a.number}`, `${ctx.user.name} has submitted ${a.subject.code} ${a.number} for your pre-moderation review.`);
  return { status: "PENDING_PRE_MODERATION" as const };
}

// ── Phase 2: Gate 1 ─────────────────────────────────────────────────────────
export async function preModerationReview(ctx: Ctx, id: string, input: z.infer<typeof preReviewSchema>) {
  const a = await loadAssessment(ctx.user, id);
  if (a.internalModeratorId !== ctx.user.id) throw forbidden("Only the assigned internal moderator can review this.");
  expectStatus(a, "PENDING_PRE_MODERATION");

  if (input.decision === "REVISION_REQUESTED") {
    await prisma.$transaction(async (tx) => {
      await transition(tx, id, ["PENDING_PRE_MODERATION"], { status: "REVISION_REQUESTED" });
      await tx.moderationRecord.create({ data: { assessmentId: id, stage: "PRE_MODERATION", reviewerId: ctx.user.id, decision: "REVISION_REQUESTED", comments: input.comments } });
      await audit({ assessmentId: id, userId: ctx.user.id, action: "PRE_REVIEW_REVISION", details: { comments: input.comments }, ip: ctx.meta.ip }, tx);
      await createNotifications(tx, [a.examinerId], id, `Revision requested on ${a.subject.code} ${a.number}.`);
    });
    void emailUsers([a.examinerId], id, `Revision requested: ${a.subject.code} ${a.number}`, `${ctx.user.name} asked for changes: ${input.comments}`);
    return { status: "REVISION_REQUESTED" as const };
  }

  await confirmSigner(ctx.user, input.signature);
  const [paper, memo] = await Promise.all([currentAttachment(prisma, id, "PAPER"), currentAttachment(prisma, id, "MEMO")]);
  const contentHash = hashContent({ gate: 1, id, revision: a.revision, paper: paper?.sha256, memo: memo?.sha256, questionTypes: a.questionTypes, comments: input.comments ?? "" });
  await prisma.$transaction(async (tx) => {
    await transition(tx, id, ["PENDING_PRE_MODERATION"], { status: "READY_FOR_POST_MODERATION" });
    await tx.moderationRecord.create({ data: { assessmentId: id, stage: "PRE_MODERATION", reviewerId: ctx.user.id, decision: "APPROVED", consensusReached: true, comments: input.comments || null } });
    await recordSignature(tx, { assessmentId: id, userId: ctx.user.id, section: "PRE_MODERATOR", image: input.signature.image, contentHash, meta: ctx.meta });
    await audit({ assessmentId: id, userId: ctx.user.id, action: "PRE_REVIEW_APPROVED", details: { contentHash }, ip: ctx.meta.ip }, tx);
    await createNotifications(tx, [a.examinerId], id, `${a.subject.code} ${a.number} passed pre-moderation. You can run the assessment.`);
  });
  void emailUsers([a.examinerId], id, `Approved: ${a.subject.code} ${a.number}`, `${ctx.user.name} approved the assessment. After marking, return to upload results (Section 2).`);
  return { status: "READY_FOR_POST_MODERATION" as const };
}

// ── Phase 3: data harvest ───────────────────────────────────────────────────
async function resolveMarks(
  ctx: Ctx,
  id: string,
  source: MarksSource,
  totalMarks: number,
): Promise<{ result: CalcResult; description: string; fingerprint: string }> {
  try {
    if (source.type === "manual") {
      const values = parseManualScores(source.text);
      return { result: calculateStatistics(values, { totalMarks }), description: "Entered manually", fingerprint: sha256(source.text) };
    }
    const att = await prisma.attachment.findFirst({ where: { id: source.attachmentId, assessmentId: id, kind: "MARKS" } });
    if (!att) throw badRequest("That marks file was not found on this assessment.");
    const buf = await getFile(att.storageKey);
    const { header, values } = await readColumn(buf, att.filename, source.sheet, source.column);
    return {
      result: calculateStatistics(values, { totalMarks }),
      description: `${att.filename} › ${source.sheet} › ${header}`,
      fingerprint: `${att.sha256}:${source.sheet}:${source.column}`,
    };
  } catch (e) {
    if (e instanceof CalcError) throw badRequest(e.message);
    throw e;
  }
}

export async function listMarksColumns(ctx: Ctx, id: string, attachmentId: string) {
  const a = await loadAssessment(ctx.user, id);
  assertExaminer(ctx, a);
  const att = await prisma.attachment.findFirst({ where: { id: attachmentId, assessmentId: id, kind: "MARKS" } });
  if (!att) throw notFound("Marks file not found.");
  return { sheets: await inspectWorkbook(await getFile(att.storageKey), att.filename) };
}

/** Stateless preview: nothing is saved until the examiner signs. Raw scores are not returned. */
export async function previewStatistics(ctx: Ctx, id: string, input: z.infer<typeof calculateSchema>) {
  const a = await loadAssessment(ctx.user, id);
  assertExaminer(ctx, a);
  const { result, description } = await resolveMarks(ctx, id, input.source, input.totalMarks);
  const { scores: _s, ...stats } = result;
  return { stats, source: description };
}

export async function submitPostAssessment(ctx: Ctx, id: string, input: z.infer<typeof submitPostSchema>) {
  const a = await loadAssessment(ctx.user, id);
  assertExaminer(ctx, a);
  expectStatus(a, "READY_FOR_POST_MODERATION");
  // Statistics are always recomputed server-side from the source — never trusted from the client.
  const { result, description, fingerprint } = await resolveMarks(ctx, id, input.source, input.totalMarks);
  await confirmSigner(ctx.user, input.signature);

  const stats = {
    candidateCount: result.candidateCount, passCount: result.passCount, passRate: result.passRate,
    highestMark: result.highestMark, lowestMark: result.lowestMark, classAverage: result.classAverage,
  };
  const contentHash = hashContent({ section: 2, id, stats, totalMarks: result.totalMarks, source: fingerprint, commentary: input.commentary });
  await prisma.$transaction(async (tx) => {
    await transition(tx, id, ["READY_FOR_POST_MODERATION"], {
      status: "PENDING_FINAL_MODERATION",
      ...stats,
      totalMarks: result.totalMarks,
      scores: result.scores,
      invalidEntries: result.invalidEntries,
      marksSource: description,
      examinerCommentary: input.commentary,
    });
    await recordSignature(tx, { assessmentId: id, userId: ctx.user.id, section: "EXAMINER_SECTION_2", image: input.signature.image, contentHash, meta: ctx.meta });
    await audit({ assessmentId: id, userId: ctx.user.id, action: "SECTION2_SUBMITTED", details: { ...stats, source: description, contentHash }, ip: ctx.meta.ip }, tx);
    await createNotifications(tx, [a.internalModeratorId], id, `${a.subject.code} ${a.number} results are ready for final moderation.`);
  });
  void emailUsers([a.internalModeratorId], id, `Final moderation requested: ${a.subject.code} ${a.number}`, `${ctx.user.name} has submitted results and commentary for final moderation.`);
  return { status: "PENDING_FINAL_MODERATION" as const };
}

// ── Phase 4: Gate 2 / Gate 3 ────────────────────────────────────────────────
export async function finalModerationReview(ctx: Ctx, id: string, input: z.infer<typeof finalReviewSchema>) {
  const a = await loadAssessment(ctx.user, id);
  expectStatus(a, "PENDING_FINAL_MODERATION", "PENDING_EXTERNAL_MODERATION");
  const external = a.status === "PENDING_EXTERNAL_MODERATION";
  if (external ? a.externalModeratorId !== ctx.user.id : a.internalModeratorId !== ctx.user.id) {
    throw forbidden(external ? "Only the assigned external moderator can review this." : "Only the assigned internal moderator can review this.");
  }
  const stage = external ? "FINAL_EXTERNAL" : "FINAL_INTERNAL";
  const section: SignatureSection = external ? "EXTERNAL_MODERATOR" : "FINAL_INTERNAL_MODERATOR";
  const from: AssessmentStatus[] = [a.status];

  if (input.decision === "REVISION_REQUESTED") {
    await prisma.$transaction(async (tx) => {
      await transition(tx, id, from, { status: "READY_FOR_POST_MODERATION" });
      await tx.moderationRecord.create({ data: { assessmentId: id, stage, reviewerId: ctx.user.id, decision: "REVISION_REQUESTED", comments: input.comments } });
      await audit({ assessmentId: id, userId: ctx.user.id, action: external ? "EXTERNAL_REVIEW_RETURNED" : "FINAL_REVIEW_RETURNED", details: { comments: input.comments }, ip: ctx.meta.ip }, tx);
      await createNotifications(tx, [a.examinerId, a.internalModeratorId], id, `${a.subject.code} ${a.number} was returned for Section 2 corrections.`);
    });
    void emailUsers([a.examinerId], id, `Returned for corrections: ${a.subject.code} ${a.number}`, `${ctx.user.name} returned the results: ${input.comments}`);
    return { status: "READY_FOR_POST_MODERATION" as const };
  }

  // Quality-check answers must be complete; "No" needs an explanation.
  const byId = new Map(input.checklist.map((c) => [c.id, c]));
  const checklist = QUALITY_CHECKS.map((q) => {
    const c = byId.get(q.id);
    if (!c) throw badRequest("Answer every quality-check question.");
    if (c.answer === "NO" && !c.comment.trim()) throw badRequest(`Explain your “No” answer for: ${q.question}`);
    return { id: q.id, question: q.question, answer: c.answer, comment: c.comment.trim() };
  });
  await confirmSigner(ctx.user, input.signature);

  const contentHash = hashContent({
    gate: external ? 3 : 2, id,
    stats: [a.candidateCount, a.passRate, a.classAverage, a.highestMark, a.lowestMark],
    commentary: a.examinerCommentary, checklist, scriptsSampled: input.scriptsSampled, comments: input.comments ?? "",
  });

  const hasExternal = !!a.externalModeratorId;
  const completes = external || !hasExternal;
  const nextStatus: AssessmentStatus = completes ? "COMPLETED" : "PENDING_EXTERNAL_MODERATION";

  const pdf = await prisma.$transaction(
    async (tx) => {
      await transition(tx, id, from, completes ? { status: nextStatus, completedAt: new Date() } : { status: nextStatus });
      await tx.moderationRecord.create({
        data: { assessmentId: id, stage, reviewerId: ctx.user.id, decision: "APPROVED", consensusReached: true, comments: input.comments || null, checklist, scriptsSampled: input.scriptsSampled },
      });
      await recordSignature(tx, { assessmentId: id, userId: ctx.user.id, section, image: input.signature.image, contentHash, meta: ctx.meta });
      await audit({ assessmentId: id, userId: ctx.user.id, action: external ? "EXTERNAL_REVIEW_APPROVED" : "FINAL_REVIEW_APPROVED", details: { contentHash }, ip: ctx.meta.ip }, tx);

      if (!completes) {
        await createNotifications(tx, [a.externalModeratorId!], id, `${a.subject.code} ${a.number} is ready for your external moderation.`);
        return null;
      }
      // Phase 5 — generated inside the transaction so a failure rolls the approval back cleanly.
      const buf = await generateReportPdf(tx, id);
      const filename = `Moderation-Report_${a.subject.code}_${a.number}`.replace(/[^\w.-]+/g, "_") + ".pdf";
      const storageKey = await putFile(buf, "application/pdf");
      await tx.attachment.create({
        data: { assessmentId: id, kind: "FINAL_REPORT", filename, mimeType: "application/pdf", size: buf.length, sha256: sha256(buf), storageKey, uploadedById: ctx.user.id },
      });
      await audit({ assessmentId: id, userId: ctx.user.id, action: "REPORT_GENERATED", details: { filename, sha256: sha256(buf) }, ip: ctx.meta.ip }, tx);
      await createNotifications(tx, [a.examinerId, a.internalModeratorId, ...(a.externalModeratorId ? [a.externalModeratorId] : []), a.subject.hodId], id, `${a.subject.code} ${a.number} moderation is complete.`);
      return { buf, filename };
    },
    { timeout: 30_000, maxWait: 10_000 },
  );

  if (!pdf) {
    void emailUsers([a.externalModeratorId!], id, `External moderation requested: ${a.subject.code} ${a.number}`, `${ctx.user.name} has completed internal moderation. Your review is requested.`);
    return { status: nextStatus };
  }
  await emailReportToHod(a.id, a.subject.hodId, `${a.subject.code} ${a.number}`, pdf, ctx);
  return { status: nextStatus };
}

async function emailReportToHod(id: string, hodId: string, label: string, pdf: { buf: Buffer; filename: string }, ctx: Ctx) {
  try {
    const hod = await prisma.user.findUnique({ where: { id: hodId }, select: { email: true, name: true } });
    if (!hod) return;
    const { delivered } = await sendMail({
      to: hod.email,
      subject: `Moderation complete: ${label}`,
      text: `Dear ${hod.name},\n\nModeration of ${label} is complete. The signed report is attached.`,
      html: mailHtml(`Moderation complete: ${label}`, `Dear ${hod.name}, moderation of ${label} is complete. The signed, read-only report is attached.`),
      attachments: [{ filename: pdf.filename, content: pdf.buf, contentType: "application/pdf" }],
    });
    await audit({ assessmentId: id, userId: ctx.user.id, action: delivered ? "REPORT_EMAILED" : "REPORT_EMAIL_FAILED", details: delivered ? { to: hod.email } : { reason: "SMTP not configured" }, ip: ctx.meta.ip });
  } catch (e) {
    console.error("[report] e-mail to HOD failed", e);
    await audit({ assessmentId: id, userId: ctx.user.id, action: "REPORT_EMAIL_FAILED", details: { reason: e instanceof Error ? e.message : "unknown" }, ip: ctx.meta.ip });
  }
}
