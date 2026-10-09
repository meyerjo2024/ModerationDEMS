import { CheckCircle2, MessageSquareReply } from "lucide-react";
import { cn } from "@/lib/cn";
import { fmtDateTime } from "@/lib/format";
import { QuestionTable } from "./QuestionTable";
import { StatsGrid } from "./StatsGrid";
import { all, qrows, type Detail } from "./types";
import { FileChip } from "@/components/ui/FileDrop";
import { fmtBytes } from "@/lib/format";

const STAGE = { PRE_MODERATION: "Gate 1 · Pre-moderation", FINAL_INTERNAL: "Gate 2 · Final (internal)", FINAL_EXTERNAL: "Gate 3 · External" } as const;

/** Read-only view of everything captured so far. */
export function RecordSummary({ a, canSeeMarks }: { a: Detail; canSeeMarks: boolean }) {
  const rows = qrows(a);
  const samples = all(a, "SAMPLE_SCRIPT");
  return (
    <div className="space-y-6">
      {rows.length > 0 && (
        <section className="card p-6 sm:p-8">
          <p className="kicker">Section 1</p>
          <h2 className="mb-4 mt-1 text-xl font-semibold">Types of questions</h2>
          <QuestionTable rows={rows} />
        </section>
      )}
      {a.candidateCount != null && (
        <section className="card p-6 sm:p-8">
          <p className="kicker">Section 2</p>
          <h2 className="mb-4 mt-1 text-xl font-semibold">Performance statistics</h2>
          <StatsGrid stats={a} distribution={a.distribution} />
          {a.marksSource && <p className="mt-3 text-xs text-slate-400">Source: {canSeeMarks ? a.marksSource : a.marksSource.split(" › ").slice(1).join(" › ") || "Entered manually"}</p>}
          {a.examinerCommentary && (
            <div className="card-soft mt-5 p-4">
              <p className="kicker">Examiner’s commentary</p>
              <p className="mt-1.5 whitespace-pre-wrap text-sm">{a.examinerCommentary}</p>
            </div>
          )}
          {samples.length > 0 && (
            <div className="mt-5 grid gap-2 sm:grid-cols-2">
              {samples.map((s) => <FileChip key={s.id} name={s.filename} meta={fmtBytes(s.size)} href={`/api/files/${s.id}`} />)}
            </div>
          )}
        </section>
      )}
      {a.records.length > 0 && (
        <section className="card p-6 sm:p-8">
          <p className="kicker">Moderation</p>
          <h2 className="mb-4 mt-1 text-xl font-semibold">Decisions & feedback</h2>
          <ul className="space-y-3">
            {a.records.map((r) => {
              const ok = r.decision === "APPROVED";
              return (
                <li key={r.id} className="card-soft flex gap-3 p-4">
                  {ok ? <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-emerald-500" /> : <MessageSquareReply className="mt-0.5 h-5 w-5 shrink-0 text-amber-500" />}
                  <div className="min-w-0 text-sm">
                    <p className="font-semibold">
                      {STAGE[r.stage]} <span className={cn("font-medium", ok ? "text-emerald-600 dark:text-emerald-400" : "text-amber-600 dark:text-amber-400")}>· {ok ? "Approved" : "Revision requested"}</span>
                    </p>
                    <p className="text-xs text-slate-500 dark:text-zinc-400">{r.reviewer.name} · {fmtDateTime(r.createdAt)}{r.scriptsSampled != null ? ` · ${r.scriptsSampled} scripts sampled` : ""}</p>
                    {r.comments && <p className="mt-1.5 whitespace-pre-wrap">{r.comments}</p>}
                  </div>
                </li>
              );
            })}
          </ul>
        </section>
      )}
    </div>
  );
}
