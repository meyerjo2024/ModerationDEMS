import { createHash } from "node:crypto";

export const sha256 = (data: Buffer | string) => createHash("sha256").update(data).digest("hex");

/** Deterministic JSON (sorted keys) so equal content always hashes equally. */
export function stableStringify(v: unknown): string {
  if (v === null || typeof v !== "object") return JSON.stringify(v) ?? "null";
  if (Array.isArray(v)) return `[${v.map(stableStringify).join(",")}]`;
  const o = v as Record<string, unknown>;
  return `{${Object.keys(o)
    .filter((k) => o[k] !== undefined)
    .sort()
    .map((k) => `${JSON.stringify(k)}:${stableStringify(o[k])}`)
    .join(",")}}`;
}

export const hashContent = (v: unknown) => sha256(stableStringify(v));
