import type { SessionUser } from "./auth";
import { clientIp } from "./api";
import type { Ctx } from "./services/assessments";

export const ctxFrom = (req: Request, user: SessionUser): Ctx => ({
  user,
  meta: { ip: clientIp(req), userAgent: req.headers.get("user-agent") },
});
