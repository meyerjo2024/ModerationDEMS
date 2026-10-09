"use client";
import { FileSpreadsheet, Loader2, PenLine, Send, Table2 } from "lucide-react";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/Button";
import { FileChip, FileDrop } from "@/components/ui/FileDrop";
import { Notice } from "@/components/ui/Notice";
import { PillTabs } from "@/components/ui/PillTabs";
import { SignOff, type SignOffValue } from "@/components/ui/SignOff";
import { api } from "@/lib/client";
import { fmtBytes, fmtDateTime } from "@/lib/format";
import { StatsGrid, type Stats } from "./StatsGrid";
import { all, latest, type Detail } from "./types";

type Sheet = { name: string; columns: { index: number; header: string; nonBlank: number }[] };
type Preview = { stats: Stats & { distribution: number[]; totalMarks: number }; source: string };

/** Phase 3 — harvest marks, run the calculation engine, add commentary, sign Section 2. */
export function Section2Form({ a }: { a: Detail }) {
  const router = useRouter();
  const [mode, setMode] = useState<"excel" | "manual">("excel");
  const [attId, setAttId] = useState(latest(a, "MARKS")?.id ?? "");
  const [sheets, setSheets] = useState<Sheet[]>([]);
  const [sheet, setSheet] = useState("");
  const [column, setColumn] = useState(0);
  const [manual, setManual] = useState("");
  const [total, setTotal] = useState("100");
  const [preview, setPreview] = useState<Preview | null>(null);
  const [calcError, setCalcError] = useState("");
  const [calculating, setCalculating] = useState(false);
  const [commentary, setCommentary] = useState("");
  const [sig, setSig] = useState<SignOffValue | null>(null);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState<string | null>(null);

  const feedback = [...a.records].reverse().find((r) => r.stage !== "PRE_MODERATION" && r.decision === "REVISION_REQUESTED");
  const samples = all(a, "SAMPLE_SCRIPT");
  const marks = latest(a, "MARKS");
  const totalNum = Number(total);

  // Load sheets/columns whenever the workbook changes.
  useEffect(() => {
    if (!attId) return;
    let live = true;
    api<{ sheets: Sheet[] }>("GET", `/api/assessments/${a.id}/marks?attachmentId=${attId}`)
      .then((r) => {
        if (!live) return;
        setSheets(r.sheets);
        setSheet(r.sheets[0]?.name ?? "");
        setColumn(0);
        setPreview(null);
      })
      .catch((e) => live && setCalcError((e as Error).message));
    return () => {
      live = false;
    };
  }, [attId, a.id]);

  const source =
    mode === "excel"
      ? attId && sheet && column
        ? ({ type: "excel", attachmentId: attId, sheet, column } as const)
        : null
      : manual.trim()
        ? ({ type: "manual", text: manual } as const)
        : null;
  const sourceKey = JSON.stringify(source);

  // Live calculation preview (server-side engine; debounced).
  useEffect(() => {
    setCalcError("");
    if (!source || !(totalNum > 0)) {
      setPreview(null);
      return;
    }
    let live = true;
    setCalculating(true);
    const t = setTimeout(() => {
      api<Preview>("POST", `/api/assessments/${a.id}/calculate`, { source, totalMarks: totalNum })
        .then((p) => live && setPreview(p))
        .catch((e) => {
          if (!live) return;
          setPreview(null);
          setCalcError((e as Error).message);
        })
        .finally(() => live && setCalculating(false));
    }, 350);
    return () => {
      live = false;
      clearTimeout(t);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [sourceKey, totalNum, a.id]);

  const upload = async (kind: "MARKS" | "SAMPLE_SCRIPT", file: File) => {
    setBusy(kind);
    setError("");
    try {
      const f = new FormData();
      f.set("kind", kind);
      f.set("file", file);
      const { attachment } = await api<{ attachment: { id: string } }>("POST", `/api/assessments/${a.id}/attachments`, f);
      if (kind === "MARKS") setAttId(attachment.id);
      router.refresh();
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(null);
    }
  };

  const submit = async () => {
    if (!source || !sig) return;
    setBusy("submit");
    setError("");
    try {
      await api("POST", `/api/assessments/${a.id}/submit-post`, { source, totalMarks: totalNum, commentary, signature: sig });
      router.refresh();
      window.scrollTo({ top: 0, behavior: "smooth" });
    } catch (e) {
      setError((e as Error).message);
      setBusy(null);
    }
  };

  const cols = sheets.find((s) => s.name === sheet)?.columns ?? [];
  const ready = !!preview && commentary.trim().length >= 10 && !!sig;

  return (
    <div className="space-y-6">
      {feedback && (
        <Notice tone="warn" title={`Returned by ${feedback.reviewer.name}`}>
          <p className="whitespace-pre-wrap">{feedback.comments}</p>
          <p className="mt-1 text-xs opacity-70">{fmtDateTime(feedback.createdAt)}</p>
        </Notice>
      )}

      <section className="card p-6 sm:p-8">
        <p className="kicker">Section 2 · Step 1</p>
        <h2 className="mt-1 text-2xl font-semibold">Student marks</h2>
        <p className="mt-1 text-sm text-slate-500 dark:text-zinc-400">Upload the marks workbook and pick the assessment column (e.g. T1), or enter marks by hand. Statistics are calculated automatically.</p>

        <div className="mt-5">
          <PillTabs value={mode} onChange={setMode} tabs={[{ value: "excel", label: "Excel / CSV" }, { value: "manual", label: "Enter manually" }]} />
        </div>

        {mode === "excel" ? (
          <div className="mt-5 space-y-4">
            {marks && <FileChip name={marks.filename} meta={`${fmtBytes(marks.size)} · ${fmtDateTime(marks.createdAt)}`} />}
            <FileDrop label={marks ? "Replace marks workbook" : "Upload marks workbook"} hint=".xlsx or .csv · header in row 1 · one column per assessment" accept=".xlsx,.csv" busy={busy === "MARKS"} onFile={(f) => upload("MARKS", f)} />
            {sheets.length > 0 && (
              <div className="grid gap-4 sm:grid-cols-2">
                {sheets.length > 1 && (
                  <div>
                    <label className="label" htmlFor="sheet">Sheet</label>
                    <select id="sheet" className="field" value={sheet} onChange={(e) => { setSheet(e.target.value); setColumn(0); }}>
                      {sheets.map((s) => <option key={s.name}>{s.name}</option>)}
                    </select>
                  </div>
                )}
                <div>
                  <label className="label" htmlFor="column"><Table2 className="mr-1 inline h-3.5 w-3.5" />Assessment column</label>
                  <select id="column" className="field" value={column} onChange={(e) => setColumn(Number(e.target.value))}>
                    <option value={0}>Select a column…</option>
                    {cols.map((c) => <option key={c.index} value={c.index}>{c.header} · {c.nonBlank} entries</option>)}
                  </select>
                </div>
              </div>
            )}
          </div>
        ) : (
          <div className="mt-5">
            <label className="label" htmlFor="manual">Marks (one per line)</label>
            <textarea id="manual" rows={6} className="field font-mono text-sm" placeholder={"72\n48.5\n55\n…"} value={manual} onChange={(e) => setManual(e.target.value)} />
            <p className="mt-1.5 text-xs text-slate-400">Anonymous marks only — no names or student numbers.</p>
          </div>
        )}

        <div className="mt-5 max-w-[220px]">
          <label className="label" htmlFor="total">Total marks available</label>
          <input id="total" type="number" min={1} inputMode="decimal" className="field tabular-nums" value={total} onChange={(e) => setTotal(e.target.value)} />
        </div>
      </section>

      <section className="card p-6 sm:p-8">
        <div className="flex items-center justify-between">
          <div>
            <p className="kicker">Section 2 · Step 2</p>
            <h2 className="mt-1 text-2xl font-semibold">Calculated results</h2>
          </div>
          {calculating && <Loader2 className="h-5 w-5 animate-spin text-slate-400" />}
        </div>
        <div className="mt-5">
          {preview ? (
            <>
              <StatsGrid stats={preview.stats} distribution={preview.stats.distribution} />
              <p className="mt-3 flex items-center gap-1.5 text-xs text-slate-400"><FileSpreadsheet className="h-3.5 w-3.5" /> Source: {preview.source}</p>
            </>
          ) : calcError ? (
            <Notice tone="error">{calcError}</Notice>
          ) : (
            <p className="rounded-2xl border border-dashed border-slate-300/80 px-4 py-10 text-center text-sm text-slate-400 dark:border-white/15">Choose a marks column to see the statistics.</p>
          )}
        </div>
      </section>

      <section className="card p-6 sm:p-8">
        <p className="kicker">Section 2 · Step 3</p>
        <h2 className="mt-1 text-2xl font-semibold">Sample scripts <span className="text-base font-normal text-slate-400">(optional)</span></h2>
        <p className="mb-4 mt-1 text-sm text-slate-500 dark:text-zinc-400">Attach marked scripts (high, average and low) for the moderator to review.</p>
        {samples.length > 0 && (
          <div className="mb-3 grid gap-2 sm:grid-cols-2">
            {samples.map((s) => <FileChip key={s.id} name={s.filename} meta={fmtBytes(s.size)} href={`/api/files/${s.id}`} />)}
          </div>
        )}
        <FileDrop label="Add a sample script" hint="PDF, PNG or JPG · up to 15 MB each" accept=".pdf,.png,.jpg,.jpeg" busy={busy === "SAMPLE_SCRIPT"} onFile={(f) => upload("SAMPLE_SCRIPT", f)} />
      </section>

      <section className="card p-6 sm:p-8">
        <p className="kicker">Section 2 · Step 4</p>
        <h2 className="mt-1 text-2xl font-semibold">Commentary & signature</h2>
        <div className="mt-5">
          <label className="label" htmlFor="commentary">Comments on student performance</label>
          <textarea id="commentary" rows={5} className="field" placeholder="e.g. Most candidates struggled with the essay question on …" maxLength={6000} value={commentary} onChange={(e) => setCommentary(e.target.value)} />
        </div>
        <div className="mt-6">
          <SignOff statement="By signing Section 2, I confirm the marks are complete and the statistics above reflect the final marked results." onChange={setSig} />
        </div>
        {error && <Notice tone="error" className="mt-5">{error}</Notice>}
        <div className="mt-6 flex justify-end">
          <Button onClick={submit} loading={busy === "submit"} disabled={!ready}>
            <Send className="h-4 w-4" /> Sign & submit for final moderation
          </Button>
        </div>
        {!ready && (
          <p className="mt-3 flex items-center justify-end gap-1.5 text-xs text-slate-400">
            <PenLine className="h-3.5 w-3.5" /> Needs calculated results, commentary and your signature.
          </p>
        )}
      </section>
    </div>
  );
}
