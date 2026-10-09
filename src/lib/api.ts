import { NextResponse } from "next/server";
import { ZodError, type ZodTypeAny, type z } from "zod";
import { ApiError, badRequest } from "./errors";

type Ctx<P> = { params: P };

/** Wraps a route handler: CSRF origin check for mutations, uniform JSON errors. */
export function route<P = Record<string, string>>(
  handler: (req: Request, ctx: Ctx<P>) => Promise<Response | unknown>,
) {
  return async (req: Request, ctx: Ctx<P>) => {
    try {
      if (!["GET", "HEAD", "OPTIONS"].includes(req.method)) assertSameOrigin(req);
      const out = await handler(req, ctx);
      if (out instanceof Response) return out;
      return NextResponse.json(out ?? { ok: true });
    } catch (e) {
      if (e instanceof ApiError) {
        return NextResponse.json({ error: e.message, details: e.details }, { status: e.status });
      }
      if (e instanceof ZodError) {
        const first = e.issues[0];
        const where = first?.path.length ? `${first.path.join(".")}: ` : "";
        return NextResponse.json(
          { error: `${where}${first?.message ?? "Invalid input"}`, details: e.issues },
          { status: 400 },
        );
      }
      console.error("[api] unhandled error", e);
      return NextResponse.json({ error: "Something went wrong on our side. Please try again." }, { status: 500 });
    }
  };
}

function assertSameOrigin(req: Request) {
  const origin = req.headers.get("origin");
  if (!origin) return; // non-browser clients / same-origin form posts without Origin
  const host = req.headers.get("x-forwarded-host") ?? req.headers.get("host");
  try {
    if (new URL(origin).host !== host) throw new ApiError(403, "Cross-origin request blocked.");
  } catch (e) {
    if (e instanceof ApiError) throw e;
    throw new ApiError(403, "Cross-origin request blocked.");
  }
}

export async function parseJson<S extends ZodTypeAny>(req: Request, schema: S): Promise<z.infer<S>> {
  let body: unknown;
  try {
    body = await req.json();
  } catch {
    throw badRequest("Request body must be valid JSON.");
  }
  return schema.parse(body);
}

export function clientIp(req: Request): string | null {
  return req.headers.get("x-forwarded-for")?.split(",")[0]?.trim() ?? null;
}
