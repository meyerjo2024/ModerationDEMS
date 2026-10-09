import { requireUser } from "@/lib/auth";
import { route } from "@/lib/api";
import { badRequest } from "@/lib/errors";
import { ctxFrom } from "@/lib/ctx";
import { listMarksColumns } from "@/lib/services/assessments";

/** Lists sheets / columns of an uploaded marks workbook (the "pick T1" dropdown). */
export const dynamic = "force-dynamic";

export const GET = route<{ id: string }>(async (req, { params }) => {
  const user = await requireUser("EXAMINER");
  const attachmentId = new URL(req.url).searchParams.get("attachmentId");
  if (!attachmentId) throw badRequest("attachmentId is required.");
  return listMarksColumns(ctxFrom(req, user), params.id, attachmentId);
});
