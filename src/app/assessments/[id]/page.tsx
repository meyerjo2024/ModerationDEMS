import { ArrowLeft, Download, Hourglass, PartyPopper } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";
import { notFound, redirect } from "next/navigation";
import { AppShell } from "@/components/AppShell";
import { FinalReviewPanel } from "@/components/assessment/FinalReviewPanel";
import { PreReviewPanel } from "@/components/assessment/PreReviewPanel";
import { RecordSummary } from "@/components/assessment/RecordSummary";
import { Section1Form } from "@/components/assessment/Section1Form";
import { Section2Form } from "@/components/assessment/Section2Form";
import { People, Signatures, Timeline } from "@/components/assessment/Sidebar";
import { latest } from "@/components/assessment/types";
import { Stepper } from "@/components/ui/Stepper";
import { StatusBadge } from "@/components/ui/StatusBadge";
import { getCurrentUser } from "@/lib/auth";
import { getAssessmentDetail } from "@/lib/services/queries";
import { toJson } from "@/lib/types";
import { ACTOR_FOR_STATUS, currentGateIndex } from "@/lib/workflow";

export const dynamic = "force-dynamic";
export const metadata: Metadata = { title: "Assessment" };

const NEXT_STEP: Record<string, string> = {
  DRAFT: "complete Section 1 and submit for pre-moderation",
  REVISION_REQUESTED: "revise Section 1 and resubmit",
  PENDING_PRE_MODERATION: "review the draft paper and memorandum (Gate 1)",
  READY_FOR_POST_MODERATION: "capture results once marking is complete (Section 2)",
  PENDING_FINAL_MODERATION: "complete the final moderation review (Gate 2)",
  PENDING_EXTERNAL_MODERATION: "complete the external moderation (Gate 3)",
};

export default async function AssessmentPage({ params }: { params: { id: string } }) {
  const user = await getCurrentUser();
  if (!user) redirect("/login");
  const raw = await getAssessmentDetail(user, params.id);
  if (!raw) notFound();
  const a = toJson(raw);

  const isExaminer = a.examiner.id === user.id;
  const isInternal = a.internalModerator.id === user.id;
  const isExternal = a.externalModerator?.id === user.id;
  const actor = ACTOR_FOR_STATUS[a.status];
  const waitingOn = { examiner: a.examiner.name, internal: a.internalModerator.name, external: a.externalModerator?.name ?? "the external moderator", none: "" }[actor];

  let panel: React.ReactNode;
  if ((a.status === "DRAFT" || a.status === "REVISION_REQUESTED") && isExaminer) panel = <Section1Form a={a} />;
  else if (a.status === "PENDING_PRE_MODERATION" && isInternal) panel = <PreReviewPanel a={a} />;
  else if (a.status === "READY_FOR_POST_MODERATION" && isExaminer) panel = <Section2Form a={a} />;
  else if (a.status === "PENDING_FINAL_MODERATION" && isInternal) panel = <FinalReviewPanel a={a} stage="internal" />;
  else if (a.status === "PENDING_EXTERNAL_MODERATION" && isExternal) panel = <FinalReviewPanel a={a} stage="external" />;
  else {
    const report = latest(a, "FINAL_REPORT");
    const emailed = a.auditLogs.some((l) => l.action === "REPORT_EMAILED");
    panel = (
      <div className="space-y-6">
        {a.status === "COMPLETED" ? (
          <section className="card flex flex-wrap items-center justify-between gap-4 p-6 sm:p-8">
            <div className="flex items-center gap-4">
              <span className="grid h-12 w-12 place-items-center rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"><PartyPopper className="h-6 w-6" /></span>
              <div>
                <h2 className="text-xl font-semibold">Moderation complete</h2>
                <p className="text-sm text-slate-500 dark:text-zinc-400">The signed report is archived{emailed ? ` and was e-mailed to ${a.subject.hod.name}` : ". E-mail to the Head of Department has not been delivered — download the PDF below"}.</p>
              </div>
            </div>
            {report && <a href={`/api/files/${report.id}`} className="btn-primary"><Download className="h-4 w-4" /> Download PDF</a>}
          </section>
        ) : (
          <section className="card flex items-center gap-4 p-6 sm:p-8">
            <span className="grid h-12 w-12 place-items-center rounded-2xl bg-amber-500/10 text-amber-600"><Hourglass className="h-6 w-6" /></span>
            <div>
              <h2 className="text-xl font-semibold">Waiting on {waitingOn}</h2>
              <p className="text-sm text-slate-500 dark:text-zinc-400">Next step: {NEXT_STEP[a.status]}.</p>
            </div>
          </section>
        )}
        <RecordSummary a={a} canSeeMarks={isExaminer} />
      </div>
    );
  }

  return (
    <AppShell wide>
      <div className="space-y-8">
        <div>
          <Link href="/" className="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-zinc-200">
            <ArrowLeft className="h-4 w-4" /> Dashboard
          </Link>
          <div className="mt-3 flex flex-wrap items-start justify-between gap-4">
            <div>
              <h1 className="text-3xl font-semibold sm:text-4xl">{a.subject.code} · {a.number}</h1>
              <p className="mt-1 text-slate-500 dark:text-zinc-400">{a.subject.name}{a.revision > 1 ? ` · Revision ${a.revision}` : ""}</p>
            </div>
            <StatusBadge status={a.status} className="!px-4 !py-1.5 !text-sm" />
          </div>
        </div>

        <div className="card p-5 sm:p-6">
          <Stepper current={currentGateIndex(a.status, !!a.externalModerator)} hasExternal={!!a.externalModerator} />
        </div>

        <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
          <div className="min-w-0">{panel}</div>
          <aside className="space-y-6 lg:sticky lg:top-20">
            <People a={a} />
            <Signatures a={a} />
            <Timeline a={a} />
          </aside>
        </div>
      </div>
    </AppShell>
  );
}
