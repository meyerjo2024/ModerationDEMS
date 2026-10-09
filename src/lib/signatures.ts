import { z } from "zod";
import type { Prisma, SignatureSection } from "@prisma/client";
import { prisma } from "./db";
import { badRequest, forbidden } from "./errors";
import { clearLoginFailures, loginThrottled, recordLoginFailure, verifyPassword, type SessionUser } from "./auth";

/** A pen signature plus password re-confirmation (authenticated signature token). */
export const signatureInput = z.object({
  image: z.string().max(400_000),
  password: z.string().min(1, "Enter your password to sign."),
});
export type SignatureInput = z.infer<typeof signatureInput>;

export interface RequestMeta {
  ip: string | null;
  userAgent: string | null;
}

const PNG_PREFIX = "data:image/png;base64,";

export function decodeSignature(dataUrl: string): Buffer {
  if (!dataUrl.startsWith(PNG_PREFIX)) throw badRequest("Please draw your signature in the signature pad.");
  const buf = Buffer.from(dataUrl.slice(PNG_PREFIX.length), "base64");
  const isPng = buf.length > 8 && buf[0] === 0x89 && buf[1] === 0x50 && buf[2] === 0x4e && buf[3] === 0x47;
  if (!isPng) throw badRequest("The signature image is not valid.");
  if (buf.length < 600) throw badRequest("Your signature looks empty. Please sign again.");
  return buf;
}

/** Re-authenticates the signer. Must be called before a signature is recorded. */
export async function confirmSigner(user: SessionUser, sig: SignatureInput) {
  decodeSignature(sig.image);
  const key = `sign:${user.id}`;
  if (loginThrottled(key)) throw forbidden("Too many incorrect passwords. Please wait a few minutes and try again.");
  const row = await prisma.user.findUnique({ where: { id: user.id }, select: { passwordHash: true } });
  if (!row || !(await verifyPassword(sig.password, row.passwordHash))) {
    recordLoginFailure(key);
    throw forbidden("That password is not correct, so the signature was not applied.");
  }
  clearLoginFailures(key);
}

export async function recordSignature(
  tx: Prisma.TransactionClient,
  args: {
    assessmentId: string;
    userId: string;
    section: SignatureSection;
    image: string;
    contentHash: string;
    meta: RequestMeta;
  },
) {
  return tx.signature.create({
    data: {
      assessmentId: args.assessmentId,
      userId: args.userId,
      section: args.section,
      imageData: args.image,
      contentHash: args.contentHash,
      ipAddress: args.meta.ip,
      userAgent: args.meta.userAgent?.slice(0, 300) ?? null,
    },
  });
}
