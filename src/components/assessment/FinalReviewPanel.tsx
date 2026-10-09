"use client";
import { CheckCircle2, MessageSquareReply } from "lucide-react";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { Button } from "@/components/ui/Button";
import { Notice } from "@/components/ui/Notice";
import { SignOff, type SignOffValue } from "@/components/ui/SignOff";
import { SlideOver } from "@/components/ui/SlideOver";
import { api } from "@/lib/client";
import { cn } from "@/lib/cn";
import { QUALITY_CHECKS, type Answer } from "@/lib/checklist";
import { DocumentViewer } from "./DocumentViewer";
import { StatsGrid } from "./StatsGrid";
import { all, latest, type Detail } from "./types";

const OPTIONS: { v: Answer; label: string; on: string }[] = [
  { v: "YES", label: "Yes", on: "bg-emerald-500 text-white" },
  { v: "NO", label: "No", on: "bg-rose-500 text-white" },
  { v: "NA", label: "N/A", on: "bg-slate-500 text-white" },
];

/** Gate 2 (internal) and Gate 3 (external): statistics, sample scripts, quality checks, final signature. */
export function FinalReviewPanel({ a, stage }: { a: Detail; stage: "internal" | "external" }) {
  const router = useRouter();
  const [answers, setAnswers] = useState<Record<string, { answer?: Answer; comment: string }>>({});
  const [scripts, setScripts] = useState("");
  const [comments, setComments] = useState("");
  const [consensus, setConsensus] = useState(false);
  const [sig, setSig] = useState<SignOffValue | null>(null);
  const [sheet, setSheet] = useState(false);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  const scriptsDocs = all(a, "SAMPLE_SCRIPT").map((s, i) => ({ label: `Script ${i + 1}`, att: s }));
  const external = stage === "external";
  const complete = QUALITY_CHECKS.every((q) => answers[q.id]?.answer && (answers[q.id].answer !== "NO" || answers[q.id].comment.trim()));
  const willComplete = external || !a.externalModerator;

  const send = async (body: object) => {
    setBusy(true);
    setError("");
    try {
      await api("POST", `/api/assessments/${a.id}/final-review`, body);
      setSheet(false);
      router.refresh();
      window.scrollTo({ top: 0, behavior: "smooth" });
    } catch (e) {
      setError((e as Error).message);
      setBusy(false);
    }
  };

  const approve = () =>
    send({
      decision: "APPROVED",
      consensusReached: consensus,
      scriptsSampled: Number(scripts),
      comments: comments || undefined,
      checklist: QUALITY_CHECKS.map((q) => ({ id: q.id, answer: answers[q.id].answer, comment: answers[q.id].comment })),
      signature: sig,
    });

  const prior = [...a.records].reverse().find((r) => r.stage === "FINAL_INTERNAL" && r.decision === "APPROVED");

  return (
    <div className="space-y-6">
      <section className="card p-6 sm:p-8">
        <p className="kicker">{external ? "Gate 3 · External moderation" : "Gate 2 · Final review"}</p>
        <h2 className="mt-1 text-2xl font-semibold">Results & examiner commentary</h2>
        <div className="mt-5"><StatsGrid stats={a} distribution={a.distribution} /></div>
        {a.marksSource && <p className="mt-3 text-xs text-slate-400">Source: {a.marksSource}</p>}
        <div className="card-soft mt-5 p-4">
          <p className="kicker">Examiner’s commentary</p>
          <p className="mt-1.5 whitespace-pre-wrap text-sm">{a.examinerCommentary}</p>
        </div>
        {external && prior && (
          <div className="card-soft mt-4 p-4">
            <p className="kicker">Internal moderator’s sign-off · {prior.reviewer.name}</p>
            <p className="mt-1.5 text-sm">{prior.comments || "Approved — no additional comments."} <span className="text-slate-400">({prior.scriptsSampled} scripts sampled)</span></p>
          </div>
        )}
      </section>

      <section className="card p-6 sm:p-8">
        <p className="kicker">Evidence</p>
        <h2 className="mb-4 mt-1 text-xl font-semibold">Sample scripts & assessment documents</h2>
        <DocumentViewer docs={[...scriptsDocs, { label: "Assessment paper", att: latest(a, "PAPER") }, { label: "Memorandum", att: latest(a, "MEMO") }]} />
      </section>

      <section className="card p-6 sm:p-8">
        <p className="kicker">Quality check</p>
        <h2 className="mt-1 text-xl font-semibold">Marking accuracy, fairness & consistency</h2>
        <ul className="mt-5 divide-y divide-slate-200/60 dark:divide-white/10">
          {QUALITY_CHECKS.map((q) => {
            const cur = answers[q.id];
            return (
              <li key={q.id} className="space-y-2.5 py-4 first:pt-0">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <p className="max-w-lg text-sm font-medium">{q.question}</p>
                  <div role="radiogroup" aria-label={q.question} className="inline-flex rounded-full bg-slate-900/5 p-1 dark:bg-white/10">
                    {OPTIONS.map((o) => (
                      <button
                        key={o.v}
                        role="radio"
                        aria-checked={cur?.answer === o.v}
                        type="button"
                        onClick={() => setAnswers((s) => ({ ...s, [q.id]: { comment: s[q.id]?.comment ?? "", answer: o.v } }))}
                        className={cn("rounded-full px-3.5 py-1 text-[13px] font-semibold transition", cur?.answer === o.v ? o.on : "text-slate-500 hover:text-slate-800 dark:text-zinc-400")}
                      >
                        {o.label}
                      </button>
                    ))}
                  </div>
                </div>
                {cur?.answer === "NO" && (
                  <input className="field !py-2 text-sm" placeholder="Explain your concern (required)" maxLength={500} value={cur.comment} onChange={(e) => setAnswers((s) => ({ ...s, [q.id]: { ...s[q.id], comment: e.target.value } }))} />
                )}
              </li>
            );
          })}
        </ul>

        <div className="mt-4 grid gap-5 sm:grid-cols-2">
          <div>
            <label className="label" htmlFor="scripts">Scripts sampled</label>
            <input id="scripts" type="number" min={0} className="field tabular-nums" value={scripts} onChange={(e) => setScripts(e.target.value)} />
          </div>
        </div>
        <div className="mt-5">
          <label className="label" htmlFor="final-comments">Comments <span className="normal-case tracking-normal text-slate-400">(optional)</span></label>
          <textarea id="final-comments" rows={3} className="field" value={comments} onChange={(e) => setComments(e.target.value)} />
        </div>
      </section>

      <section className="card p-6 sm:p-8">
        <p className="kicker">Sign-off</p>
        <h2 className="mt-1 text-xl font-semibold">{willComplete ? "Final signature & completion" : "Final signature"}</h2>
        <label className="mt-5 flex cursor-pointer items-center gap-3 text-sm font-medium">
          <input type="checkbox" className="h-5 w-5 rounded-md accent-[#0b4ea2]" checked={consensus} onChange={(e) => setConsensus(e.target.checked)} />
          Consensus reached
        </label>
        <div className="mt-5">
          <SignOff statement="By signing, I confirm I have reviewed the statistics and sample scripts and that the quality checks above are my honest assessment." onChange={setSig} />
        </div>
        {willComplete && <Notice tone="info" className="mt-5">Approving completes moderation: the PDF report is generated, archived and e-mailed to the Head of Department.</Notice>}
        {!willComplete && <Notice tone="info" className="mt-5">After your approval the record is forwarded to the external moderator.</Notice>}
        {error && <Notice tone="error" className="mt-4">{error}</Notice>}
        <div className="mt-6 flex flex-wrap justify-end gap-3">
          <Button variant="secondary" onClick={() => setSheet(true)}><MessageSquareReply className="h-4 w-4" /> Return to examiner</Button>
          <Button onClick={approve} loading={busy} disabled={!complete || !consensus || !sig || scripts === ""}>
            <CheckCircle2 className="h-4 w-4" /> Sign & approve
          </Button>
        </div>
      </section>

      <SlideOver open={sheet} onClose={() => setSheet(false)} title="Return to examiner" subtitle="The examiner will correct Section 2 and resubmit; sign-offs restart from Gate 2.">
        <div className="space-y-4">
          <div>
            <label className="label" htmlFor="ret">Feedback</label>
            <textarea id="ret" rows={9} autoFocus className="field" value={comments} onChange={(e) => setComments(e.target.value)} />
          </div>
          {error && <Notice tone="error">{error}</Notice>}
          <Button className="w-full" loading={busy} disabled={comments.trim().length < 5} onClick={() => send({ decision: "REVISION_REQUESTED", comments })}>
            Send back
          </Button>
        </div>
      </SlideOver>
    </div>
  );
}
