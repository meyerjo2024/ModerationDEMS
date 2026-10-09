"use client";
import { AnimatePresence, motion } from "framer-motion";
import { MessageSquareWarning, Plus, Save, Send, Trash2 } from "lucide-react";
import { useRouter } from "next/navigation";
import { useMemo, useState } from "react";
import { Button } from "@/components/ui/Button";
import { FileChip, FileDrop } from "@/components/ui/FileDrop";
import { Notice } from "@/components/ui/Notice";
import { SignOff, type SignOffValue } from "@/components/ui/SignOff";
import { api } from "@/lib/client";
import { cn } from "@/lib/cn";
import { fmtBytes, fmtDateTime } from "@/lib/format";
import { latest, qrows, type Detail, type QRow } from "./types";

const SUGGESTIONS = ["Multiple choice", "Short answer", "Essay", "Case study", "Calculation", "Practical / coding"];
const blank = (): QRow => ({ type: "", weighting: 0, heqfLevel: 6, aligned: true, comment: "" });

/** Phase 1 — Section 1: types of questions, documents, signature. */
export function Section1Form({ a }: { a: Detail }) {
  const router = useRouter();
  const [rows, setRows] = useState<QRow[]>(() => (qrows(a).length ? qrows(a) : [blank()]));
  const [sig, setSig] = useState<SignOffValue | null>(null);
  const [error, setError] = useState("");
  const [saved, setSaved] = useState("");
  const [busy, setBusy] = useState<"save" | "submit" | "PAPER" | "MEMO" | null>(null);

  const total = useMemo(() => +rows.reduce((s, r) => s + (Number(r.weighting) || 0), 0).toFixed(2), [rows]);
  const paper = latest(a, "PAPER");
  const memo = latest(a, "MEMO");
  const feedback = [...a.records].reverse().find((r) => r.stage === "PRE_MODERATION" && r.decision === "REVISION_REQUESTED");

  const set = (i: number, patch: Partial<QRow>) => setRows((rs) => rs.map((r, n) => (n === i ? { ...r, ...patch } : r)));
  const payload = () => rows.map((r) => ({ ...r, weighting: Number(r.weighting) || 0, heqfLevel: Number(r.heqfLevel) }));

  const run = async (kind: typeof busy, fn: () => Promise<void>) => {
    setBusy(kind);
    setError("");
    setSaved("");
    try {
      await fn();
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(null);
    }
  };

  const upload = (kind: "PAPER" | "MEMO", file: File) =>
    run(kind, async () => {
      const f = new FormData();
      f.set("kind", kind);
      f.set("file", file);
      await api("POST", `/api/assessments/${a.id}/attachments`, f);
      router.refresh();
    });

  const saveDraft = () =>
    run("save", async () => {
      await api("PUT", `/api/assessments/${a.id}/section1`, { questionTypes: payload() });
      setSaved("Draft saved.");
    });

  const submit = () =>
    run("submit", async () => {
      if (!sig) throw new Error("Draw your signature and enter your password to sign.");
      await api("POST", `/api/assessments/${a.id}/submit-pre`, { questionTypes: payload(), signature: sig });
      router.refresh();
      window.scrollTo({ top: 0, behavior: "smooth" });
    });

  return (
    <div className="space-y-6">
      {feedback && (
        <Notice tone="warn" title={`Revision requested by ${feedback.reviewer.name}`}>
          <p className="whitespace-pre-wrap">{feedback.comments}</p>
          <p className="mt-1 text-xs opacity-70">{fmtDateTime(feedback.createdAt)}</p>
        </Notice>
      )}

      <section className="card p-6 sm:p-8">
        <p className="kicker">Section 1</p>
        <h2 className="mt-1 text-2xl font-semibold">Types of questions</h2>
        <p className="mt-1 text-sm text-slate-500 dark:text-zinc-400">Assign each question type its weighting and check alignment with the HEQF level descriptors.</p>

        <div className="mt-6 space-y-3">
          <AnimatePresence initial={false}>
            {rows.map((r, i) => (
              <motion.div key={i} layout initial={{ opacity: 0, height: 0 }} animate={{ opacity: 1, height: "auto" }} exit={{ opacity: 0, height: 0 }} className="overflow-hidden">
                <div className="card-soft grid gap-3 p-4 sm:grid-cols-[1.6fr_.8fr_.8fr_auto] sm:items-end">
                  <div>
                    <label className="label">Question type</label>
                    <input list="qtypes" className="field" placeholder="e.g. Essay" value={r.type} maxLength={80} onChange={(e) => set(i, { type: e.target.value })} />
                  </div>
                  <div>
                    <label className="label">Weighting %</label>
                    <input type="number" min={0} max={100} step="any" inputMode="decimal" className="field tabular-nums" value={r.weighting || ""} onChange={(e) => set(i, { weighting: e.target.value === "" ? 0 : Number(e.target.value) })} />
                  </div>
                  <div>
                    <label className="label">HEQF level</label>
                    <select className="field" value={r.heqfLevel} onChange={(e) => set(i, { heqfLevel: Number(e.target.value) })}>
                      {[5, 6, 7, 8, 9, 10].map((l) => <option key={l} value={l}>Level {l}</option>)}
                    </select>
                  </div>
                  <div className="flex items-center justify-between gap-3 sm:justify-end">
                    <button type="button" role="switch" aria-checked={r.aligned} onClick={() => set(i, { aligned: !r.aligned })} className="flex items-center gap-2 text-xs font-medium text-slate-600 dark:text-zinc-300">
                      <span className={cn("relative h-6 w-10 rounded-full transition-colors", r.aligned ? "bg-emerald-500" : "bg-slate-300 dark:bg-zinc-600")}>
                        <span className={cn("absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all", r.aligned ? "left-[18px]" : "left-0.5")} />
                      </span>
                      Aligned
                    </button>
                    <button type="button" aria-label="Remove row" disabled={rows.length === 1} onClick={() => setRows((rs) => rs.filter((_, n) => n !== i))} className="rounded-full p-2 text-slate-400 hover:bg-rose-500/10 hover:text-rose-500 disabled:opacity-30">
                      <Trash2 className="h-4 w-4" />
                    </button>
                  </div>
                  <div className="sm:col-span-4">
                    <input className="field !py-2 text-sm" placeholder="Alignment comment (optional)" maxLength={500} value={r.comment} onChange={(e) => set(i, { comment: e.target.value })} />
                  </div>
                </div>
              </motion.div>
            ))}
          </AnimatePresence>
          <datalist id="qtypes">{SUGGESTIONS.map((s) => <option key={s} value={s} />)}</datalist>
        </div>

        <div className="mt-4 flex flex-wrap items-center justify-between gap-4">
          <Button variant="secondary" type="button" onClick={() => setRows((rs) => [...rs, blank()])} disabled={rows.length >= 15}>
            <Plus className="h-4 w-4" /> Add question type
          </Button>
          <div className="flex items-center gap-3" aria-live="polite">
            <div className="h-2 w-32 overflow-hidden rounded-full bg-slate-200 dark:bg-white/10">
              <motion.div className={cn("h-full rounded-full", total === 100 ? "bg-emerald-500" : total > 100 ? "bg-rose-500" : "bg-accent")} animate={{ width: `${Math.min(total, 100)}%` }} />
            </div>
            <span className={cn("text-sm font-semibold tabular-nums", total === 100 ? "text-emerald-600 dark:text-emerald-400" : total > 100 ? "text-rose-600" : "text-slate-600 dark:text-zinc-300")}>{total}% / 100%</span>
          </div>
        </div>
      </section>

      <section className="card p-6 sm:p-8">
        <p className="kicker">Documents</p>
        <h2 className="mt-1 text-2xl font-semibold">Assessment paper & memorandum</h2>
        <div className="mt-5 grid gap-5 sm:grid-cols-2">
          {([["PAPER", "Draft assessment paper", paper], ["MEMO", "Memorandum", memo]] as const).map(([kind, label, att]) => (
            <div key={kind} className="space-y-2">
              <p className="label !mb-0">{label}</p>
              {att && <FileChip name={att.filename} meta={`${fmtBytes(att.size)} · ${fmtDateTime(att.createdAt)}`} href={`/api/files/${att.id}`} />}
              <FileDrop label={att ? "Replace file" : `Upload ${label.toLowerCase()}`} hint="PDF or Word · up to 15 MB" accept=".pdf,.doc,.docx" busy={busy === kind} onFile={(f) => upload(kind, f)} />
            </div>
          ))}
        </div>
      </section>

      <section className="card p-6 sm:p-8">
        <p className="kicker">Sign-off</p>
        <h2 className="mt-1 text-2xl font-semibold">Sign & submit for pre-moderation</h2>
        <div className="mt-5">
          <SignOff statement="By signing, I confirm the question types, weightings and HEQF alignment above are accurate and the attached paper and memorandum are final for moderation." onChange={setSig} />
        </div>
        {error && <Notice tone="error" className="mt-5">{error}</Notice>}
        {saved && <Notice tone="success" className="mt-5">{saved}</Notice>}
        <div className="mt-6 flex flex-wrap justify-end gap-3">
          <Button variant="secondary" onClick={saveDraft} loading={busy === "save"}><Save className="h-4 w-4" /> Save draft</Button>
          <Button onClick={submit} loading={busy === "submit"} disabled={!sig || total !== 100 || !paper || !memo}>
            <Send className="h-4 w-4" /> Sign & submit
          </Button>
        </div>
        {(total !== 100 || !paper || !memo) && (
          <p className="mt-3 flex items-center justify-end gap-1.5 text-xs text-slate-400">
            <MessageSquareWarning className="h-3.5 w-3.5" />
            {total !== 100 ? "Weightings must total 100%. " : ""}{!paper || !memo ? "Upload both documents to continue." : ""}
          </p>
        )}
      </section>
    </div>
  );
}
