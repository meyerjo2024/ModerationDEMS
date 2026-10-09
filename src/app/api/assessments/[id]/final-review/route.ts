import { requireUser } from "@/lib/auth";
import { parseJson, route } from "@/lib/api";
import { ctxFrom } from "@/lib/ctx";
import { finalModerationReview, finalReviewSchema } from "@/lib/services/assessments";

export const maxDuration = 60;

export const POST = route<{ id: string }>(async (req, { params }) => {
  const user = await requireUser("INTERNAL_MODERATOR", "EXTERNAL_MODERATOR");
  return finalModerationReview(ctxFrom(req, user), params.id, await parseJson(req, finalReviewSchema));
});
