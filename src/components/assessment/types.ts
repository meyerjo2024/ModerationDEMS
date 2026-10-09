import type { getAssessmentDetail } from "@/lib/services/queries";
import type { Jsonify } from "@/lib/types";

export type Detail = Jsonify<NonNullable<Awaited<ReturnType<typeof getAssessmentDetail>>>>;
export type Att = Detail["attachments"][number];
export type Viewer = { id: string; name: string; role: string };

export interface QRow {
  type: string;
  weighting: number;
  heqfLevel: number;
  aligned: boolean;
  comment: string;
}

/** Newest attachment of a kind (list is already newest-first). */
export const latest = (a: Detail, kind: Att["kind"]) => a.attachments.find((x) => x.kind === kind);
export const all = (a: Detail, kind: Att["kind"]) => a.attachments.filter((x) => x.kind === kind);

/** Section 1 rows (stored as JSON, validated on write by the API). */
export const qrows = (a: Detail): QRow[] => (a.questionTypes as unknown as QRow[] | null) ?? [];
