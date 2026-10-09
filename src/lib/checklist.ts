export type Answer = "YES" | "NO" | "NA";

export const QUALITY_CHECKS = [
  { id: "accuracy", question: "Marking is accurate and in line with the memorandum." },
  { id: "totals", question: "Marks have been totalled and transcribed correctly." },
  { id: "fairness", question: "Marking was fair and free from bias across candidates." },
  { id: "consistency", question: "Marking was applied consistently across the sample (high, average and low scripts)." },
  { id: "alternatives", question: "Valid alternative answers were credited appropriately." },
  { id: "alignment", question: "Outcomes align with the intended learning outcomes and HEQF level descriptors." },
  { id: "statistics", question: "The pass rate and mark distribution are reasonable, and any anomalies are explained." },
  { id: "commentary", question: "The examiner's commentary on student performance is accurate and sufficient." },
] as const;

export const CHECK_IDS: string[] = QUALITY_CHECKS.map((q) => q.id);
