/* eslint-disable no-console */
import { PrismaClient } from "@prisma/client";
import bcrypt from "bcryptjs";

const prisma = new PrismaClient();

async function upsertUser(name: string, email: string, role: "EXAMINER" | "INTERNAL_MODERATOR" | "EXTERNAL_MODERATOR" | "HOD", password: string, department?: string) {
  const passwordHash = await bcrypt.hash(password, 12);
  return prisma.user.upsert({
    where: { email },
    update: {},
    create: { name, email, role, passwordHash, department },
  });
}

async function main() {
  const email = (process.env.SEED_ADMIN_EMAIL ?? "hod@example.edu").toLowerCase();
  const password = process.env.SEED_ADMIN_PASSWORD;
  if (!password || password.length < 10) {
    throw new Error("Set SEED_ADMIN_PASSWORD (min 10 characters) before seeding.");
  }
  const hod = await upsertUser(process.env.SEED_ADMIN_NAME ?? "Head of Department", email, "HOD", password, "Administration");
  console.log(`✔ HOD account ready: ${hod.email}`);

  if (process.env.SEED_DEMO_DATA !== "true") return;

  const demoPw = password;
  const [examiner, internal, external] = await Promise.all([
    upsertUser("Dr Amara Okafor", "examiner@example.edu", "EXAMINER", demoPw, "Computer Science"),
    upsertUser("Prof Liam van der Merwe", "moderator@example.edu", "INTERNAL_MODERATOR", demoPw, "Computer Science"),
    upsertUser("Dr Priya Naidoo", "external@example.edu", "EXTERNAL_MODERATOR", demoPw, "External"),
  ]);
  await prisma.subject.upsert({ where: { code: "CSC101" }, update: {}, create: { code: "CSC101", name: "Introduction to Programming", department: "Computer Science", hodId: hod.id } });
  await prisma.subject.upsert({ where: { code: "INF202" }, update: {}, create: { code: "INF202", name: "Information Systems II", department: "Computer Science", hodId: hod.id } });
  console.log(`✔ Demo users (password = SEED_ADMIN_PASSWORD): ${[examiner, internal, external].map((u) => u.email).join(", ")}`);
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(() => prisma.$disconnect());
