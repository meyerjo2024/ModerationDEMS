/**
 * Automated calculation engine for Section 2 (post-assessment statistics).
 *
 *  - Blank cells are ignored (not candidates).
 *  - Non-blank cells that are not valid marks (text such as "ABS", negatives,
 *    marks above the total) are excluded from the statistics and counted in
 *    `invalidEntries` so the examiner can see and correct them.
 *  - Marks are converted to percentages of `totalMarks`; a cell written as
 *    "62%" is treated as already being a percentage.
 *  - Pass = percentage >= passMark (default 50).
 */
export interface CalcResult {
  totalMarks: number;
  passMark: number;
  /** Valid, non-blank candidate rows. */
  candidateCount: number;
  passCount: number;
  /** Percentages, 0–100, 2 d.p. */
  passRate: number;
  highestMark: number;
  lowestMark: number;
  classAverage: number;
  /** Non-blank entries that could not be used as a mark. */
  invalidEntries: number;
  /** Valid scores as raw marks (anonymous). */
  scores: number[];
  /** Count of candidates per 10%-band (0–9 … 90–100). */
  distribution: number[];
}

export class CalcError extends Error {}

const r2 = (n: number) => Math.round((n + Number.EPSILON) * 100) / 100;

function isBlank(v: unknown): boolean {
  return v === null || v === undefined || (typeof v === "string" && v.trim() === "");
}

/** Returns a raw mark, or null if the cell is not a usable number. */
function toMark(v: unknown, totalMarks: number): number | null {
  if (typeof v === "number") return Number.isFinite(v) ? v : null;
  if (typeof v !== "string") return null;
  let s = v.trim();
  const isPct = s.endsWith("%");
  if (isPct) s = s.slice(0, -1).trim();
  // "45,5" → "45.5" (only when there is a single comma and no dot)
  if (/^-?\d+,\d+$/.test(s)) s = s.replace(",", ".");
  if (!/^-?\d+(\.\d+)?$/.test(s)) return null;
  const n = Number(s);
  return isPct ? (n / 100) * totalMarks : n;
}

export function calculateStatistics(
  raw: unknown[],
  opts: { totalMarks?: number; passMark?: number } = {},
): CalcResult {
  const totalMarks = opts.totalMarks ?? 100;
  const passMark = opts.passMark ?? 50;
  if (!(totalMarks > 0) || !Number.isFinite(totalMarks)) throw new CalcError("Total marks must be a positive number.");

  const scores: number[] = [];
  let invalid = 0;
  for (const cell of raw) {
    if (isBlank(cell)) continue;
    const m = toMark(cell, totalMarks);
    if (m === null || m < 0 || m > totalMarks + 1e-9) invalid++;
    else scores.push(r2(m));
  }
  if (scores.length === 0) throw new CalcError("No valid marks were found in the selected data.");

  const pct = scores.map((s) => (s / totalMarks) * 100);
  const passCount = scores.filter((s) => s * 100 >= passMark * totalMarks - 1e-9).length;
  const distribution = Array<number>(10).fill(0);
  for (const p of pct) distribution[Math.min(9, Math.floor(p / 10))]++;

  return {
    totalMarks,
    passMark,
    candidateCount: scores.length,
    passCount,
    passRate: r2((passCount / scores.length) * 100),
    highestMark: r2(Math.max(...pct)),
    lowestMark: r2(Math.min(...pct)),
    classAverage: r2(pct.reduce((a, b) => a + b, 0) / pct.length),
    invalidEntries: invalid,
    scores,
    distribution,
  };
}

/** Parses pasted marks (one per line / comma / semicolon / tab separated). */
export function parseManualScores(text: string): string[] {
  return text.split(/[\n\r\t;]+/).flatMap((line) => (/^\s*-?\d+,\d+\s*$/.test(line) ? [line] : line.split(",")));
}
