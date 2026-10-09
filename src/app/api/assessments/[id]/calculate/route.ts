import { requireUser } from "@/lib/auth";
import { parseJson, route } from "@/lib/api";
import { ctxFrom } from "@/lib/ctx";
import { calculateSchema, previewStatistics } from "@/lib/services/assessments";

export const POST = route<{ id: string }>(async (req, { params }) => {
  const user = await requireUser("EXAMINER");
  return previewStatistics(ctxFrom(req, user), params.id, await parseJson(req, calculateSchema));
});
