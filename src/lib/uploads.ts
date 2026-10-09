import type { AttachmentKind } from "@prisma/client";
import { badRequest } from "./errors";

export const MAX_UPLOAD_BYTES = Number(process.env.MAX_UPLOAD_MB ?? 15) * 1024 * 1024;

type Sig = { ext: string[]; mime: string; test: (b: Buffer) => boolean };

const startsWith = (b: Buffer, ...bytes: number[]) => bytes.every((x, i) => b[i] === x);
const SIGS: Record<string, Sig> = {
  pdf: { ext: ["pdf"], mime: "application/pdf", test: (b) => startsWith(b, 0x25, 0x50, 0x44, 0x46) },
  docx: {
    ext: ["docx"],
    mime: "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    test: (b) => startsWith(b, 0x50, 0x4b, 0x03, 0x04),
  },
  doc: { ext: ["doc"], mime: "application/msword", test: (b) => startsWith(b, 0xd0, 0xcf, 0x11, 0xe0) },
  xlsx: {
    ext: ["xlsx"],
    mime: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    test: (b) => startsWith(b, 0x50, 0x4b, 0x03, 0x04),
  },
  csv: { ext: ["csv"], mime: "text/csv", test: (b) => !b.subarray(0, 4096).includes(0) },
  png: { ext: ["png"], mime: "image/png", test: (b) => startsWith(b, 0x89, 0x50, 0x4e, 0x47) },
  jpg: { ext: ["jpg", "jpeg"], mime: "image/jpeg", test: (b) => startsWith(b, 0xff, 0xd8, 0xff) },
};

const ALLOWED: Record<AttachmentKind, string[]> = {
  PAPER: ["pdf", "docx", "doc"],
  MEMO: ["pdf", "docx", "doc"],
  MARKS: ["xlsx", "csv"],
  SAMPLE_SCRIPT: ["pdf", "png", "jpg"],
  FINAL_REPORT: ["pdf"],
};

/** Validates extension against the slot and checks the file's magic bytes. */
export function validateUpload(kind: AttachmentKind, filename: string, data: Buffer): { mimeType: string; filename: string } {
  if (data.length === 0) throw badRequest("That file is empty.");
  if (data.length > MAX_UPLOAD_BYTES) throw badRequest(`Files are limited to ${Math.round(MAX_UPLOAD_BYTES / 1048576)} MB.`);
  const ext = filename.split(".").pop()?.toLowerCase() ?? "";
  const key = ALLOWED[kind].find((k) => SIGS[k].ext.includes(ext));
  if (!key) throw badRequest(`Allowed file types here: ${ALLOWED[kind].map((k) => "." + SIGS[k].ext[0]).join(", ")}.`);
  if (!SIGS[key].test(data)) throw badRequest("The file contents do not match its extension.");
  const safe = filename.replace(/[^\w.\- ()]+/g, "_").slice(-120);
  return { mimeType: SIGS[key].mime, filename: safe };
}

export const KIND_LABEL: Record<AttachmentKind, string> = {
  PAPER: "Assessment paper",
  MEMO: "Memorandum",
  MARKS: "Student marks",
  SAMPLE_SCRIPT: "Sample script",
  FINAL_REPORT: "Moderation report",
};
