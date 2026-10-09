// End-to-end smoke test: drives the full 5-phase lifecycle against a running server.
//   BASE_URL=http://localhost:3000 PASSWORD=... node scripts/smoke.mjs   (needs SEED_DEMO_DATA=true)
import ExcelJS from "exceljs";
import zlib from "node:zlib";
import assert from "node:assert/strict";

const BASE = process.env.BASE_URL ?? "http://localhost:3000";
const PASSWORD = process.env.PASSWORD ?? "demo-password-123";

// A valid PNG with noisy pixels (so it passes the "not empty" check), no canvas needed.
function pngSignature() {
  const w = 120, h = 40;
  const raw = Buffer.alloc((w * 4 + 1) * h);
  for (let y = 0; y < h; y++) for (let x = 0; x < w * 4; x++) raw[y * (w * 4 + 1) + 1 + x] = Math.floor(Math.random() * 256);
  const crcTable = Array.from({ length: 256 }, (_, n) => { let c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1; return c >>> 0; });
  const crc = (b) => { let c = 0xffffffff; for (const x of b) c = crcTable[(c ^ x) & 255] ^ (c >>> 8); return (c ^ 0xffffffff) >>> 0; };
  const chunk = (t, d) => { const len = Buffer.alloc(4); len.writeUInt32BE(d.length); const td = Buffer.concat([Buffer.from(t), d]); const c = Buffer.alloc(4); c.writeUInt32BE(crc(td)); return Buffer.concat([len, td, c]); };
  const ihdr = Buffer.alloc(13); ihdr.writeUInt32BE(w, 0); ihdr.writeUInt32BE(h, 4); ihdr[8] = 8; ihdr[9] = 6;
  const png = Buffer.concat([Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]), chunk("IHDR", ihdr), chunk("IDAT", zlib.deflateSync(raw)), chunk("IEND", Buffer.alloc(0))]);
  return "data:image/png;base64," + png.toString("base64");
}
const SIG = pngSignature();

class Client {
  jar = "";
  constructor(name) { this.name = name; }
  async req(method, path, body, raw) {
    const res = await fetch(BASE + path, {
      method,
      headers: { ...(body && !raw ? { "Content-Type": "application/json" } : {}), ...(this.jar ? { cookie: this.jar } : {}) },
      body: raw ? body : body ? JSON.stringify(body) : undefined,
      redirect: "manual",
    });
    const set = res.headers.getSetCookie?.() ?? [];
    for (const c of set) { const [kv] = c.split(";"); if (kv.endsWith("=") ) this.jar = ""; else this.jar = kv; }
    const ct = res.headers.get("content-type") ?? "";
    const data = ct.includes("json") ? await res.json() : Buffer.from(await res.arrayBuffer());
    return { status: res.status, data };
  }
  async ok(method, path, body, raw) {
    const r = await this.req(method, path, body, raw);
    assert.ok(r.status < 300, `${this.name} ${method} ${path} → ${r.status} ${JSON.stringify(r.data)}`);
    return r.data;
  }
  async login(email) { await this.ok("POST", "/api/auth/login", { email, password: PASSWORD }); return this; }
}
const step = (s) => console.log("•", s);
const sig = { image: SIG, password: PASSWORD };

const examiner = await new Client("examiner").login("examiner@example.edu");
const internal = await new Client("internal").login("moderator@example.edu");
const external = await new Client("external").login("external@example.edu");
const hod = await new Client("hod").login("hod@example.edu");

// directory
const { users, subjects } = await (async () => {
  const { PrismaClient } = await import("@prisma/client");
  const p = new PrismaClient();
  const out = { users: await p.user.findMany(), subjects: await p.subject.findMany() };
  await p.$disconnect(); return out;
})();
const uid = (e) => users.find((u) => u.email === e).id;

step("auth rules");
assert.equal((await new Client("anon").req("GET", "/api/assessments")).status, 401);
assert.equal((await internal.req("POST", "/api/assessments", { subjectId: subjects[0].id, number: "x", internalModeratorId: uid("moderator@example.edu") })).status, 403, "moderators cannot create");
assert.equal((await new Client("bad").req("POST", "/api/auth/login", { email: "examiner@example.edu", password: "wrong" })).status, 401);

step("Phase 1: create + Section 1");
const num = "Test " + Date.now().toString().slice(-6);
const { id } = await examiner.ok("POST", "/api/assessments", { subjectId: subjects[0].id, number: num, internalModeratorId: uid("moderator@example.edu"), externalModeratorId: uid("external@example.edu") });
assert.equal((await external.req("GET", "/api/files/none")).status, 404);
const rows = [
  { type: "Multiple choice", weighting: 30, heqfLevel: 6, aligned: true, comment: "" },
  { type: "Essay", weighting: 70, heqfLevel: 7, aligned: true, comment: "Level 7 synthesis" },
];
let r = await examiner.req("POST", `/api/assessments/${id}/submit-pre`, { questionTypes: rows, signature: sig });
assert.equal(r.status, 400, "needs documents first"); 
const pdf = Buffer.from("%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
const upload = async (c, kind, name, buf) => { const f = new FormData(); f.set("kind", kind); f.set("file", new Blob([buf]), name); return c.ok("POST", `/api/assessments/${id}/attachments`, f, true); };
await upload(examiner, "PAPER", "paper.pdf", pdf); await upload(examiner, "MEMO", "memo.pdf", pdf);
assert.equal((await examiner.req("POST", `/api/assessments/${id}/attachments`, (() => { const f = new FormData(); f.set("kind", "PAPER"); f.set("file", new Blob([Buffer.from("MZ not a pdf")]), "evil.pdf"); return f; })(), true)).status, 400, "magic bytes enforced");
r = await examiner.req("POST", `/api/assessments/${id}/submit-pre`, { questionTypes: [{ ...rows[0], weighting: 40 }, rows[1]], signature: sig });
assert.equal(r.status, 400, "weightings must total 100");
r = await examiner.req("POST", `/api/assessments/${id}/submit-pre`, { questionTypes: rows, signature: { image: SIG, password: "nope" } });
assert.equal(r.status, 403, "signature needs password");
await examiner.ok("POST", `/api/assessments/${id}/submit-pre`, { questionTypes: rows, signature: sig });
assert.equal((await examiner.req("POST", `/api/assessments/${id}/submit-pre`, { questionTypes: rows, signature: sig })).status, 409, "no double submit");

step("Phase 2: Gate 1 revision loop then approve");
assert.equal((await external.req("POST", `/api/assessments/${id}/pre-review`, { decision: "REVISION_REQUESTED", comments: "nope nope" })).status, 403);
r = await internal.req("POST", `/api/assessments/${id}/pre-review`, { decision: "APPROVED", consensusReached: false, signature: sig });
assert.equal(r.status, 400, "consensus required");
await internal.ok("POST", `/api/assessments/${id}/pre-review`, { decision: "REVISION_REQUESTED", comments: "Question 3 is above level 7." });
await examiner.ok("POST", `/api/assessments/${id}/submit-pre`, { questionTypes: rows, signature: sig });
await internal.ok("POST", `/api/assessments/${id}/pre-review`, { decision: "APPROVED", consensusReached: true, comments: "Good.", signature: sig });

step("Phase 3: marks workbook → statistics");
const wb = new ExcelJS.Workbook(); const ws = wb.addWorksheet("Marks");
ws.addRow(["StudentNo", "T1", "T2"]);
const t1 = [80, 50, 49, "", "ABS", 30, 100, 62, 71, 45]; t1.forEach((m, i) => ws.addRow([`s${i}`, m, 10]));
const xlsx = Buffer.from(await wb.xlsx.writeBuffer());
const att = await upload(examiner, "MARKS", "marks.xlsx", xlsx);
const cols = await examiner.ok("GET", `/api/assessments/${id}/marks?attachmentId=${att.attachment.id}`);
const t1col = cols.sheets[0].columns.find((c) => c.header === "T1");
assert.ok(t1col, "T1 column listed");
const prev = await examiner.ok("POST", `/api/assessments/${id}/calculate`, { source: { type: "excel", attachmentId: att.attachment.id, sheet: "Marks", column: t1col.index }, totalMarks: 100 });
assert.equal(prev.stats.candidateCount, 8); assert.equal(prev.stats.invalidEntries, 1);
assert.equal(prev.stats.passCount, 5); assert.equal(prev.stats.passRate, 62.5); assert.equal(prev.stats.highestMark, 100); assert.equal(prev.stats.lowestMark, 30);
assert.equal(prev.stats.classAverage, 60.88);
await examiner.ok("POST", `/api/assessments/${id}/submit-post`, { source: { type: "excel", attachmentId: att.attachment.id, sheet: "Marks", column: t1col.index }, totalMarks: 100, commentary: "Essay question 2 was poorly answered overall.", signature: sig });

step("Phase 4: Gate 2 (internal) → Gate 3 (external) → completion");
const checklist = (ans = "YES") => ["accuracy","totals","fairness","consistency","alternatives","alignment","statistics","commentary"].map((id) => ({ id, answer: ans, comment: "" }));
assert.equal((await internal.req("POST", `/api/assessments/${id}/final-review`, { decision: "APPROVED", consensusReached: true, checklist: checklist("NO"), scriptsSampled: 5, signature: sig })).status, 400, "NO needs comment");
const out2 = await internal.ok("POST", `/api/assessments/${id}/final-review`, { decision: "APPROVED", consensusReached: true, checklist: checklist(), scriptsSampled: 5, comments: "Marking is consistent.", signature: sig });
assert.equal(out2.status, "PENDING_EXTERNAL_MODERATION");
const out3 = await external.ok("POST", `/api/assessments/${id}/final-review`, { decision: "APPROVED", consensusReached: true, checklist: checklist(), scriptsSampled: 3, signature: sig });
assert.equal(out3.status, "COMPLETED");

step("Phase 5: archived PDF");
const list = await hod.ok("GET", "/api/assessments");
assert.ok(list.assessments.find((a) => a.id === id && a.status === "COMPLETED"), "HOD sees completed record");
const { PrismaClient } = await import("@prisma/client"); const p = new PrismaClient();
const rep = await p.attachment.findFirst({ where: { assessmentId: id, kind: "FINAL_REPORT" } });
assert.ok(rep, "report stored");
const dl = await hod.req("GET", `/api/files/${rep.id}`);
assert.equal(dl.status, 200); assert.equal(dl.data.subarray(0, 4).toString(), "%PDF");
await (await import("node:fs/promises")).writeFile(process.env.PDF_OUT ?? "/tmp/dems-report.pdf", dl.data);
const { verifyAuditChain } = {}; // chain verified in-process below
const logs = await p.auditLog.findMany({ where: { assessmentId: id }, orderBy: [{ createdAt: "asc" }, { id: "asc" }] });
assert.ok(logs.length >= 12); for (let i = 1; i < logs.length; i++) assert.equal(logs[i].prevHash, logs[i - 1].hash, "audit chain linked");
assert.equal((await examiner.req("POST", `/api/assessments/${id}/submit-pre`, { questionTypes: rows, signature: sig })).status, 409, "completed is final");
await p.$disconnect();
console.log("\n✔ full lifecycle passed — report:", process.env.PDF_OUT ?? "/tmp/dems-report.pdf");
