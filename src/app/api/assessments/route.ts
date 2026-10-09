import { requireUser } from "@/lib/auth";
import { parseJson, route } from "@/lib/api";
import { ctxFrom } from "@/lib/ctx";
import { createAssessment, createSchema } from "@/lib/services/assessments";
import { listAssessments } from "@/lib/services/queries";

export const dynamic = "force-dynamic";

export const GET = route(async () => ({ assessments: await listAssessments(await requireUser()) }));

export const POST = route(async (req) => {
  const user = await requireUser("EXAMINER");
  const a = await createAssessment(ctxFrom(req, user), await parseJson(req, createSchema));
  return { id: a.id };
});
