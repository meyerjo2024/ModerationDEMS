import { PDFDocument, PDFFont, PDFPage, StandardFonts, rgb, type RGB } from "pdf-lib";

/** Everything the report needs — plain data, no DB access (easy to test). */
export interface ReportData {
  institution: string;
  reportId: string;
  generatedAt: Date;
  subject: { code: string; name: string; department?: string | null };
  assessmentNumber: string;
  examiner: string;
  internalModerator: string;
  externalModerator?: string | null;
  hod: string;
  questionTypes: { type: string; weighting: number; heqfLevel: number; aligned: boolean; comment?: string }[];
  documents: { label: string; filename: string; sha256: string; uploadedAt: Date }[];
  stats?: {
    totalMarks: number;
    passMark: number;
    candidateCount: number;
    passCount: number;
    passRate: number;
    highestMark: number;
    lowestMark: number;
    classAverage: number;
    invalidEntries: number;
    distribution: number[];
    source?: string | null;
  };
  commentary?: string | null;
  reviews: {
    stageLabel: string;
    reviewer: string;
    decision: string;
    consensusReached: boolean;
    comments?: string | null;
    scriptsSampled?: number | null;
    checklist?: { question: string; answer: string; comment?: string }[];
    date: Date;
  }[];
  signatures: {
    label: string;
    name: string;
    role: string;
    signedAt: Date;
    contentHash: string;
    image: Uint8Array;
  }[];
  audit: { count: number; headHash: string | null };
}

const INK = rgb(0.114, 0.114, 0.122);
const MUTED = rgb(0.43, 0.43, 0.45);
const HAIR = rgb(0.82, 0.82, 0.84);
const SOFT = rgb(0.96, 0.96, 0.97);
const ACCENT = rgb(0, 0.443, 0.89);
const GREEN = rgb(0.13, 0.63, 0.29);
const RED = rgb(0.84, 0.18, 0.16);

const W = 595.28;
const H = 841.89;
const M = 56;
const CW = W - M * 2;

class Writer {
  pdf!: PDFDocument;
  regular!: PDFFont;
  bold!: PDFFont;
  page!: PDFPage;
  y = 0;
  private chars!: Set<number>;

  static async create(title: string) {
    const w = new Writer();
    w.pdf = await PDFDocument.create();
    w.pdf.setTitle(title);
    w.pdf.setProducer("Moderation DEMS");
    w.pdf.setCreator("Moderation DEMS");
    w.regular = await w.pdf.embedFont(StandardFonts.Helvetica);
    w.bold = await w.pdf.embedFont(StandardFonts.HelveticaBold);
    w.chars = new Set(w.regular.getCharacterSet());
    w.newPage();
    return w;
  }

  /** Standard PDF fonts only cover WinAnsi — replace anything else rather than crash. */
  clean(s: string): string {
    let out = "";
    for (const ch of s.normalize("NFC")) {
      const cp = ch.codePointAt(0)!;
      if (ch === "\t") out += "  ";
      else if (this.chars.has(cp)) out += ch;
      else if (cp === 0x2011) out += "-";
      else out += "?";
    }
    return out;
  }

  newPage() {
    this.page = this.pdf.addPage([W, H]);
    this.y = H - M;
    if (this.pdf.getPageCount() > 1) {
      this.page.drawText("Academic Moderation Report", { x: M, y: H - 34, size: 8, font: this.regular, color: MUTED });
      this.page.drawLine({ start: { x: M, y: H - 42 }, end: { x: W - M, y: H - 42 }, thickness: 0.4, color: HAIR });
      this.y = H - M - 12;
    }
  }

  ensure(h: number) {
    if (this.y - h < M + 24) this.newPage();
  }

  gap(n: number) {
    this.y -= n;
  }

  width(text: string, size: number, bold = false) {
    return (bold ? this.bold : this.regular).widthOfTextAtSize(text, size);
  }

  wrap(text: string, size: number, maxW: number, bold = false): string[] {
    const font = bold ? this.bold : this.regular;
    const lines: string[] = [];
    for (const para of this.clean(text).split(/\r?\n/)) {
      if (!para.trim()) {
        lines.push("");
        continue;
      }
      let line = "";
      for (const word of para.split(/\s+/)) {
        let w = word;
        // hard-break words wider than the column (hashes, URLs)
        while (font.widthOfTextAtSize(w, size) > maxW) {
          let cut = w.length - 1;
          while (cut > 1 && font.widthOfTextAtSize(w.slice(0, cut), size) > maxW) cut--;
          if (line) {
            lines.push(line);
            line = "";
          }
          lines.push(w.slice(0, cut));
          w = w.slice(cut);
        }
        const test = line ? `${line} ${w}` : w;
        if (font.widthOfTextAtSize(test, size) <= maxW) line = test;
        else {
          lines.push(line);
          line = w;
        }
      }
      if (line) lines.push(line);
    }
    return lines;
  }

  text(text: string, o: { size?: number; bold?: boolean; color?: RGB; x?: number; maxW?: number; lead?: number } = {}) {
    const size = o.size ?? 10;
    const lead = o.lead ?? size * 1.45;
    const x = o.x ?? M;
    for (const line of this.wrap(text, size, o.maxW ?? CW - (x - M), o.bold)) {
      this.ensure(lead);
      this.y -= lead;
      if (line) this.page.drawText(line, { x, y: this.y + size * 0.28, size, font: o.bold ? this.bold : this.regular, color: o.color ?? INK });
    }
  }

  h2(text: string, kicker?: string) {
    this.ensure(60);
    this.gap(18);
    if (kicker) this.text(kicker.toUpperCase(), { size: 7.5, bold: true, color: ACCENT });
    this.text(text, { size: 15, bold: true, lead: 20 });
    this.gap(2);
    this.page.drawLine({ start: { x: M, y: this.y }, end: { x: W - M, y: this.y }, thickness: 0.5, color: HAIR });
    this.gap(6);
  }

  /** Label / value grid (2 columns of pairs). */
  facts(rows: [string, string][]) {
    const colW = CW / 2;
    for (let i = 0; i < rows.length; i += 2) {
      const pair = rows.slice(i, i + 2);
      const heights = pair.map(([, v]) => 12 + this.wrap(v, 10, colW - 12).length * 14.5);
      const h = Math.max(...heights);
      this.ensure(h);
      pair.forEach(([k, v], j) => {
        const x = M + j * colW;
        this.page.drawText(this.clean(k.toUpperCase()), { x, y: this.y - 10, size: 7, font: this.bold, color: MUTED });
        this.wrap(v, 10, colW - 12).forEach((ln, n) =>
          this.page.drawText(ln, { x, y: this.y - 24 - n * 14.5 + 3, size: 10, font: this.regular, color: INK }),
        );
      });
      this.y -= h + 2;
    }
  }

  table(cols: { label: string; w: number; align?: "right" | "center" }[], rows: string[][]) {
    const pad = 6;
    const total = cols.reduce((a, c) => a + c.w, 0);
    const scale = CW / total;
    const widths = cols.map((c) => c.w * scale);
    const drawHead = () => {
      this.ensure(24);
      this.page.drawRectangle({ x: M, y: this.y - 20, width: CW, height: 20, color: SOFT });
      let x = M;
      cols.forEach((c, i) => {
        this.cell(this.clean(c.label), x, this.y - 13.5, widths[i], 7.5, true, MUTED, c.align, pad);
        x += widths[i];
      });
      this.y -= 20;
    };
    drawHead();
    for (const row of rows) {
      const lines = row.map((cell, i) => this.wrap(cell, 9, widths[i] - pad * 2));
      const h = Math.max(...lines.map((l) => l.length)) * 12.5 + 10;
      if (this.y - h < M + 24) {
        this.newPage();
        drawHead();
      }
      let x = M;
      lines.forEach((ls, i) => {
        ls.forEach((ln, n) => this.cell(ln, x, this.y - 14 - n * 12.5, widths[i], 9, false, INK, cols[i].align, pad));
        x += widths[i];
      });
      this.y -= h;
      this.page.drawLine({ start: { x: M, y: this.y }, end: { x: W - M, y: this.y }, thickness: 0.4, color: HAIR });
    }
    this.gap(6);
  }

  private cell(text: string, x: number, y: number, w: number, size: number, bold: boolean, color: RGB, align: string | undefined, pad: number) {
    const font = bold ? this.bold : this.regular;
    const tw = font.widthOfTextAtSize(text, size);
    const tx = align === "right" ? x + w - pad - tw : align === "center" ? x + (w - tw) / 2 : x + pad;
    this.page.drawText(text, { x: tx, y, size, font, color });
  }
}

const fmtDate = (d: Date) =>
  `${d.toISOString().slice(0, 10)} ${d.toISOString().slice(11, 16)} UTC`;
const pct = (n: number) => `${n.toFixed(1)}%`;

export async function renderReportPdf(d: ReportData): Promise<Uint8Array> {
  const w = await Writer.create(`Moderation Report — ${d.subject.code} ${d.assessmentNumber}`);

  // ── Cover / title block ───────────────────────────────────────────────────
  w.text(d.institution.toUpperCase(), { size: 8, bold: true, color: MUTED });
  w.gap(10);
  w.text("Academic Moderation Report", { size: 26, bold: true, lead: 30 });
  w.text(`${d.subject.code} · ${d.subject.name} · ${d.assessmentNumber}`, { size: 12, color: MUTED, lead: 18 });
  w.gap(6);
  w.page.drawRectangle({ x: M, y: w.y - 4, width: 44, height: 3, color: ACCENT });
  w.gap(14);

  w.facts([
    ["Subject", `${d.subject.code} — ${d.subject.name}`],
    ["Assessment", d.assessmentNumber],
    ["Examiner", d.examiner],
    ["Internal moderator", d.internalModerator],
    ["External moderator", d.externalModerator ?? "Not required"],
    ["Head of Department", d.hod],
  ]);

  // ── Section 1 ─────────────────────────────────────────────────────────────
  w.h2("Types of questions", "Section 1 · Pre-assessment");
  w.table(
    [
      { label: "Question type", w: 150 },
      { label: "Weighting", w: 62, align: "right" },
      { label: "HEQF level", w: 62, align: "center" },
      { label: "Aligned", w: 52, align: "center" },
      { label: "Comment", w: 170 },
    ],
    d.questionTypes.map((q) => [q.type, `${q.weighting}%`, String(q.heqfLevel), q.aligned ? "Yes" : "No", q.comment ?? ""]),
  );
  w.text(`Total weighting: ${d.questionTypes.reduce((a, q) => a + q.weighting, 0)}%`, { size: 9, color: MUTED });

  if (d.documents.length) {
    w.h2("Documents reviewed", "Evidence");
    w.table(
      [
        { label: "Document", w: 90 },
        { label: "File", w: 150 },
        { label: "Uploaded (UTC)", w: 95 },
        { label: "SHA-256 fingerprint", w: 150 },
      ],
      d.documents.map((x) => [x.label, x.filename, fmtDate(x.uploadedAt), x.sha256.slice(0, 32) + "…"]),
    );
  }

  // ── Section 2 ─────────────────────────────────────────────────────────────
  if (d.stats) {
    const s = d.stats;
    w.ensure(330); // keep tiles + chart together
    w.h2("Performance statistics", "Section 2 · Post-assessment");
    // KPI tiles
    const tiles: [string, string][] = [
      ["Candidates", String(s.candidateCount)],
      ["Pass rate", pct(s.passRate)],
      ["Class average", pct(s.classAverage)],
      ["Highest", pct(s.highestMark)],
      ["Lowest", pct(s.lowestMark)],
    ];
    w.ensure(64);
    const gap = 8;
    const tw = (CW - gap * (tiles.length - 1)) / tiles.length;
    tiles.forEach(([k, v], i) => {
      const x = M + i * (tw + gap);
      w.page.drawRectangle({ x, y: w.y - 52, width: tw, height: 52, color: SOFT, borderColor: HAIR, borderWidth: 0.4 });
      w.page.drawText(w.clean(k.toUpperCase()), { x: x + 10, y: w.y - 16, size: 6.5, font: w.bold, color: MUTED });
      w.page.drawText(w.clean(v), { x: x + 10, y: w.y - 40, size: 17, font: w.bold, color: INK });
    });
    w.gap(60);
    w.text(
      `${s.passCount} of ${s.candidateCount} candidates achieved ${s.passMark}% or higher. Marks are expressed as percentages of ${s.totalMarks} total marks.` +
        (s.invalidEntries ? ` ${s.invalidEntries} non-numeric / out-of-range entr${s.invalidEntries === 1 ? "y was" : "ies were"} excluded.` : ""),
      { size: 9, color: MUTED },
    );
    if (s.source) w.text(`Source: ${s.source}`, { size: 9, color: MUTED });

    // Distribution chart (vector)
    w.gap(10);
    w.ensure(130);
    const chartH = 80;
    const max = Math.max(1, ...s.distribution);
    const bw = CW / 10;
    const base = w.y - chartH - 14;
    s.distribution.forEach((n, i) => {
      const h = (n / max) * chartH;
      const x = M + i * bw + 6;
      w.page.drawRectangle({ x, y: base, width: bw - 12, height: Math.max(h, 0.8), color: i >= 5 ? ACCENT : rgb(0.6, 0.6, 0.64) });
      if (n) w.page.drawText(String(n), { x: x + (bw - 12) / 2 - w.width(String(n), 8) / 2, y: base + h + 3, size: 8, font: w.regular, color: INK });
      const lbl = i === 9 ? "90–100" : `${i * 10}–${i * 10 + 9}`;
      const label = w.clean(lbl);
      w.page.drawText(label, { x: x + (bw - 12) / 2 - w.width(label, 7) / 2, y: base - 11, size: 7, font: w.regular, color: MUTED });
    });
    w.page.drawLine({ start: { x: M, y: base }, end: { x: W - M, y: base }, thickness: 0.5, color: HAIR });
    w.y = base - 24;
    w.text("Mark distribution (% of total, number of candidates per band)", { size: 8, color: MUTED });

    if (d.commentary) {
      w.h2("Examiner commentary", "Section 2");
      w.text(d.commentary, { size: 10 });
    }
  }

  // ── Moderation records ────────────────────────────────────────────────────
  for (const r of d.reviews) {
    w.h2(r.stageLabel, "Moderation");
    w.facts([
      ["Reviewer", r.reviewer],
      ["Outcome", r.decision],
      ["Consensus reached", r.consensusReached ? "Yes" : "No"],
      ["Date", fmtDate(r.date)],
      ...(r.scriptsSampled != null ? ([["Scripts sampled", String(r.scriptsSampled)]] as [string, string][]) : []),
    ]);
    if (r.checklist?.length) {
      w.gap(4);
      w.table(
        [
          { label: "Quality check", w: 280 },
          { label: "Answer", w: 50, align: "center" },
          { label: "Comment", w: 160 },
        ],
        r.checklist.map((c) => [c.question, c.answer === "NA" ? "N/A" : c.answer === "YES" ? "Yes" : "No", c.comment ?? ""]),
      );
    }
    if (r.comments) {
      w.text("Comments", { size: 8, bold: true, color: MUTED });
      w.text(r.comments, { size: 10 });
    }
  }

  // ── Signatures ────────────────────────────────────────────────────────────
  w.ensure(240); // heading + intro + first signature row stay together
  w.h2("Signatures", "Authenticated sign-off");
  w.text(
    "Each signature was drawn by the signatory while signed in to their own account and confirmed with their password. The hash identifies the exact content attested to.",
    { size: 8.5, color: MUTED },
  );
  w.gap(8);
  const colW = (CW - 16) / 2;
  for (let i = 0; i < d.signatures.length; i += 2) {
    w.ensure(120);
    const top = w.y;
    for (const [j, sig] of d.signatures.slice(i, i + 2).entries()) {
      const x = M + j * (colW + 16);
      w.page.drawRectangle({ x, y: top - 112, width: colW, height: 112, borderColor: HAIR, borderWidth: 0.5, color: rgb(1, 1, 1) });
      w.page.drawText(w.clean(sig.label.toUpperCase()), { x: x + 12, y: top - 16, size: 7, font: w.bold, color: ACCENT });
      try {
        const img = await w.pdf.embedPng(sig.image);
        const scale = Math.min(150 / img.width, 44 / img.height);
        w.page.drawImage(img, { x: x + 12, y: top - 68, width: img.width * scale, height: img.height * scale });
      } catch {
        w.page.drawText("[signature unavailable]", { x: x + 12, y: top - 50, size: 8, font: w.regular, color: RED });
      }
      w.page.drawLine({ start: { x: x + 12, y: top - 72 }, end: { x: x + colW - 12, y: top - 72 }, thickness: 0.4, color: HAIR });
      w.page.drawText(w.clean(sig.name), { x: x + 12, y: top - 84, size: 10, font: w.bold, color: INK });
      w.page.drawText(w.clean(`${sig.role} · ${fmtDate(sig.signedAt)}`), { x: x + 12, y: top - 96, size: 7.5, font: w.regular, color: MUTED });
      w.page.drawText(w.clean(`#${sig.contentHash.slice(0, 24)}`), { x: x + 12, y: top - 107, size: 6.5, font: w.regular, color: MUTED });
    }
    w.y = top - 124;
  }

  // ── Compliance footer block ───────────────────────────────────────────────
  w.ensure(70);
  w.gap(10);
  w.page.drawRectangle({ x: M, y: w.y - 52, width: CW, height: 52, color: SOFT });
  w.page.drawCircle({ x: M + 16, y: w.y - 16, size: 4, color: GREEN });
  w.page.drawText("Audit-compliant record", { x: M + 28, y: w.y - 19, size: 9, font: w.bold, color: INK });
  w.page.drawText(
    w.clean(`${d.audit.count} audit entries · chain head ${d.audit.headHash ? d.audit.headHash.slice(0, 32) + "…" : "n/a"}`),
    { x: M + 28, y: w.y - 33, size: 7.5, font: w.regular, color: MUTED },
  );
  w.page.drawText(w.clean(`Report ${d.reportId} · generated ${fmtDate(d.generatedAt)}`), {
    x: M + 28,
    y: w.y - 44,
    size: 7.5,
    font: w.regular,
    color: MUTED,
  });

  // Page numbers
  const pages = w.pdf.getPages();
  pages.forEach((p, i) => {
    const t = `${d.subject.code} ${d.assessmentNumber} · Page ${i + 1} of ${pages.length}`;
    p.drawText(w.clean(t), { x: W - M - w.width(w.clean(t), 7.5), y: 28, size: 7.5, font: w.regular, color: MUTED });
    p.drawText(w.clean(d.institution), { x: M, y: 28, size: 7.5, font: w.regular, color: MUTED });
  });

  return w.pdf.save();
}
