"use client";
import { AnimatePresence, motion } from "framer-motion";
import { CheckCircle2, MessageSquareReply } from "lucide-react";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { Button } from "@/components/ui/Button";
import { Notice } from "@/components/ui/Notice";
import { SignOff, type SignOffValue } from "@/components/ui/SignOff";
import { SlideOver } from "@/components/ui/SlideOver";
import { api } from "@/lib/client";
import { DocumentViewer } from "./DocumentViewer";
import { QuestionTable } from "./QuestionTable";
import { latest, qrows, type Detail } from "./types";

/** Gate 1 — internal moderator reviews the draft paper + memo. */
export function PreReviewPanel({ a }: { a: Detail }) {
  const router = useRouter();
  const [approving, setApproving] = useState(false);
  const [sheet, setSheet] = useState(false);
  const [comments, setComments] = useState("");
  const [consensus, setConsensus] = useState(false);
  const [sig, setSig] = useState<SignOffValue | null>(null);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  const act = async (body: object) => {
    setBusy(true);
    setError("");
    try {
      await api("POST", `/api/assessments/${a.id}/pre-review`, body);
      setSheet(false);
      router.refresh();
      window.scrollTo({ top: 0, behavior: "smooth" });
    } catch (e) {
      setError((e as Error).message);
      setBusy(false);
    }
  };

  return (
    <div className="space-y-6">
      <section className="card p-6 sm:p-8">
        <p className="kicker">Gate 1 · Review</p>
        <h2 className="mt-1 text-2xl font-semibold">Assessment paper & memorandum</h2>
        <p className="mb-5 mt-1 text-sm text-slate-500 dark:text-zinc-400">Check alignment with the level descriptors, then approve or send it back with feedback.</p>
        <DocumentViewer docs={[{ label: "Assessment paper", att: latest(a, "PAPER") }, { label: "Memorandum", att: latest(a, "MEMO") }]} />
      </section>

      <section className="card p-6 sm:p-8">
        <p className="kicker">Section 1</p>
        <h2 className="mb-4 mt-1 text-xl font-semibold">Examiner’s question types</h2>
        <QuestionTable rows={qrows(a)} />
      </section>

      <section className="card p-6 sm:p-8">
        <p className="kicker">Decision</p>
        <div className="mt-3 flex flex-wrap gap-3">
          <Button variant="secondary" onClick={() => setSheet(true)}><MessageSquareReply className="h-4 w-4" /> Request revision</Button>
          <Button onClick={() => setApproving((v) => !v)}><CheckCircle2 className="h-4 w-4" /> Approve…</Button>
        </div>

        <AnimatePresence initial={false}>
          {approving && (
            <motion.div initial={{ opacity: 0, height: 0 }} animate={{ opacity: 1, height: "auto" }} exit={{ opacity: 0, height: 0 }} className="overflow-hidden">
              <div className="mt-6 space-y-5 border-t border-slate-200/60 pt-6 dark:border-white/10">
                <div>
                  <label className="label" htmlFor="pre-comments">Comments <span className="normal-case tracking-normal text-slate-400">(optional)</span></label>
                  <textarea id="pre-comments" rows={3} className="field" value={comments} onChange={(e) => setComments(e.target.value)} />
                </div>
                <label className="flex cursor-pointer items-center gap-3 text-sm font-medium">
                  <input type="checkbox" className="h-5 w-5 rounded-md accent-[#0b4ea2]" checked={consensus} onChange={(e) => setConsensus(e.target.checked)} />
                  Consensus reached with the examiner
                </label>
                <SignOff statement="By signing, I confirm I have reviewed the paper and memorandum and approve them for assessment." onChange={setSig} />
                {error && <Notice tone="error">{error}</Notice>}
                <div className="flex justify-end">
                  <Button loading={busy} disabled={!consensus || !sig} onClick={() => act({ decision: "APPROVED", consensusReached: consensus, comments: comments || undefined, signature: sig })}>
                    Sign & approve
                  </Button>
                </div>
              </div>
            </motion.div>
          )}
        </AnimatePresence>
      </section>

      <SlideOver open={sheet} onClose={() => setSheet(false)} title="Request revision" subtitle="Your feedback goes straight to the examiner, who can resubmit after changes.">
        <div className="space-y-4">
          <div>
            <label className="label" htmlFor="rev-comments">Feedback</label>
            <textarea id="rev-comments" rows={9} autoFocus className="field" placeholder="Be specific — e.g. “Question 3 sits above level 7 …”" value={comments} onChange={(e) => setComments(e.target.value)} />
          </div>
          {error && <Notice tone="error">{error}</Notice>}
          <Button className="w-full" loading={busy} disabled={comments.trim().length < 5} onClick={() => act({ decision: "REVISION_REQUESTED", comments })}>
            Send back to examiner
          </Button>
        </div>
      </SlideOver>
    </div>
  );
}
