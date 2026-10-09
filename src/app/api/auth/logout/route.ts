import { endSession } from "@/lib/auth";
import { route } from "@/lib/api";

export const POST = route(async () => {
  endSession();
  return { ok: true };
});
