import { z } from "zod";
import { requireUser } from "@/lib/auth";
import { parseJson, route } from "@/lib/api";
import { ctxFrom } from "@/lib/ctx";
import { questionTypesSchema, saveSection1 } from "@/lib/services/assessments";

export const PUT = route<{ id: string }>(async (req, { params }) => {
  const user = await requireUser("EXAMINER");
  const { questionTypes } = await parseJson(req, z.object({ questionTypes: questionTypesSchema }));
  return saveSection1(ctxFrom(req, user), params.id, questionTypes);
});
