import { z } from "zod";
import { requireUser } from "@/lib/auth";
import { clientIp, parseJson, route } from "@/lib/api";
import { audit } from "@/lib/audit";
import { prisma } from "@/lib/db";
import { badRequest, conflict } from "@/lib/errors";

const schema = z.object({
  code: z.string().trim().toUpperCase().min(2).max(20),
  name: z.string().trim().min(2).max(160),
  department: z.string().trim().max(120).optional(),
  hodId: z.string().min(1),
});

export const POST = route(async (req) => {
  const admin = await requireUser("HOD");
  const input = await parseJson(req, schema);
  if (!(await prisma.user.findFirst({ where: { id: input.hodId, role: "HOD", active: true } }))) throw badRequest("Choose a valid Head of Department.");
  if (await prisma.subject.findUnique({ where: { code: input.code } })) throw conflict("That subject code already exists.");
  const subject = await prisma.subject.create({ data: { ...input, department: input.department || null } });
  await audit({ userId: admin.id, action: "SUBJECT_CREATED", details: { code: subject.code }, ip: clientIp(req) });
  return { subject };
});
