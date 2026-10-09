import { requireUser } from "@/lib/auth";
import { route } from "@/lib/api";
import { audit } from "@/lib/audit";
import { prisma } from "@/lib/db";
import { forbidden, notFound } from "@/lib/errors";
import { canView } from "@/lib/rbac";
import { getFile } from "@/lib/storage";
import { clientIp } from "@/lib/api";

export const dynamic = "force-dynamic";

export const runtime = "nodejs";

/** Authorised download / inline preview of an attachment. */
export const GET = route<{ id: string }>(async (req, { params }) => {
  const user = await requireUser();
  const att = await prisma.attachment.findUnique({ where: { id: params.id }, include: { assessment: { include: { subject: true } } } });
  if (!att || !canView(user, att.assessment)) throw notFound("File not found.");

  // The raw marks workbook may carry student identifiers — examiner only.
  if (att.kind === "MARKS" && att.assessment.examinerId !== user.id) throw forbidden();

  const url = new URL(req.url);
  const inline = url.searchParams.get("inline") === "1" && att.mimeType === "application/pdf";
  const data = await getFile(att.storageKey);

  if (att.assessment.examinerId !== user.id && att.kind !== "FINAL_REPORT") {
    await audit({ assessmentId: att.assessmentId, userId: user.id, action: "FILE_VIEWED", details: { filename: att.filename, kind: att.kind }, ip: clientIp(req) });
  }
  const safeName = encodeURIComponent(att.filename);
  return new Response(new Uint8Array(data), {
    headers: {
      "Content-Type": att.mimeType,
      "Content-Length": String(data.length),
      "Content-Disposition": `${inline ? "inline" : "attachment"}; filename*=UTF-8''${safeName}`,
      // `sandbox` would stop the browser's built-in PDF viewer, so it only applies to downloads.
      ...(inline ? {} : { "Content-Security-Policy": "sandbox" }),
      "X-Content-Type-Options": "nosniff",
      "Cache-Control": "private, no-store",
    },
  });
});
