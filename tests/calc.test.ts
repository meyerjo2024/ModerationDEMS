import { describe, expect, it } from "vitest";
import { calculateStatistics, CalcError, parseManualScores } from "@/lib/calc";

describe("calculateStatistics", () => {
  it("counts non-blank rows, pass rate (>=50%), highest/lowest and average", () => {
    const r = calculateStatistics([80, 50, 49.9, null, "", "  ", 30, 100]);
    expect(r.candidateCount).toBe(5);
    expect(r.passCount).toBe(3); // 80, 50, 100 — exactly 50 passes
    expect(r.passRate).toBe(60);
    expect(r.highestMark).toBe(100);
    expect(r.lowestMark).toBe(30);
    expect(r.classAverage).toBe(61.98);
    expect(r.invalidEntries).toBe(0);
  });

  it("converts marks to percentages of the total", () => {
    const r = calculateStatistics([25, 30, 10, 45], { totalMarks: 50 });
    expect(r.passRate).toBe(75); // 25/50 = 50% passes
    expect(r.highestMark).toBe(90);
    expect(r.lowestMark).toBe(20);
    expect(r.classAverage).toBe(55); // (50+60+20+90)/4
  });

  it("flags non-numeric / out-of-range entries instead of silently using them", () => {
    const r = calculateStatistics([60, "ABS", "DNS", -5, 120, "70"]);
    expect(r.candidateCount).toBe(2);
    expect(r.invalidEntries).toBe(4);
  });

  it("understands percent strings and decimal commas", () => {
    const r = calculateStatistics(["62%", "45,5", 70], { totalMarks: 100 });
    expect(r.scores).toEqual([62, 45.5, 70]);
  });

  it("builds a 10-band distribution (100% falls in the last band)", () => {
    const r = calculateStatistics([0, 9, 10, 55, 99, 100]);
    expect(r.distribution).toEqual([2, 1, 0, 0, 0, 1, 0, 0, 0, 2]);
  });

  it("throws when there is nothing to calculate", () => {
    expect(() => calculateStatistics([null, "", "ABS"])).toThrow(CalcError);
    expect(() => calculateStatistics([50], { totalMarks: 0 })).toThrow(CalcError);
  });

  it("parses pasted marks", () => {
    expect(parseManualScores("55\n60, 70;80\t90")).toEqual(["55", "60", " 70", "80", "90"]);
    expect(parseManualScores("45,5\n50")).toEqual(["45,5", "50"]);
  });
});
