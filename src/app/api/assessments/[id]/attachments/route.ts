import { z } from "zod";
import { requireUser } from "@/lib/auth";
import { route } from "@/lib/api";
import { badRequest } from "@/lib/errors";
import { ctxFrom } from "@/lib/ctx";
import { MAX_UPLOAD_BYTES } from "@/lib/uploads";
import { uploadAttachment } from "@/lib/services/assessments";

export const runtime = "nodejs";

const kindSchema = z.enum(["PAPER", "MEMO", "MARKS", "SAMPLE_SCRIPT"]);

export const POST = route<{ id: string }>(async (req, { params }) => {
  const user = await requireUser("EXAMINER");
  const declared = Number(req.headers.get("content-length") ?? 0);
  if (declared > MAX_UPLOAD_BYTES + 64 * 1024) throw badRequest("That file is too large.");
  const form = await req.formData();
  const file = form.get("file");
  const kind = kindSchema.safeParse(form.get("kind"));
  if (!(file instanceof File) || !kind.success) throw badRequest("Attach a file and choose what it is.");
  const att = await uploadAttachment(ctxFrom(req, user), params.id, kind.data, file.name, Buffer.from(await file.arrayBuffer()));
  return { attachment: att };
});
