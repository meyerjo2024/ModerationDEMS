import { z } from "zod";
import { hashPassword, requireUser } from "@/lib/auth";
import { clientIp, parseJson, route } from "@/lib/api";
import { audit } from "@/lib/audit";
import { prisma } from "@/lib/db";
import { conflict } from "@/lib/errors";

const schema = z.object({
  name: z.string().trim().min(2).max(120),
  email: z.string().trim().toLowerCase().email(),
  role: z.enum(["EXAMINER", "INTERNAL_MODERATOR", "EXTERNAL_MODERATOR", "HOD"]),
  department: z.string().trim().max(120).optional(),
  password: z.string().min(10, "Use at least 10 characters.").max(128),
});

export const POST = route(async (req) => {
  const admin = await requireUser("HOD");
  const input = await parseJson(req, schema);
  if (await prisma.user.findUnique({ where: { email: input.email } })) throw conflict("A user with that e-mail already exists.");
  const user = await prisma.user.create({
    data: { ...input, department: input.department || null, passwordHash: await hashPassword(input.password) },
    select: { id: true, name: true, email: true, role: true },
  });
  await audit({ userId: admin.id, action: "USER_CREATED", details: { email: user.email, role: user.role }, ip: clientIp(req) });
  return { user };
});
