import { requireUser } from "@/lib/auth";
import { parseJson, route } from "@/lib/api";
import { ctxFrom } from "@/lib/ctx";
import { submitPostAssessment, submitPostSchema } from "@/lib/services/assessments";

export const POST = route<{ id: string }>(async (req, { params }) => {
  const user = await requireUser("EXAMINER");
  return submitPostAssessment(ctxFrom(req, user), params.id, await parseJson(req, submitPostSchema));
});
