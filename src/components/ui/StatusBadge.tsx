import type { AssessmentStatus } from "@prisma/client";
import { cn } from "@/lib/cn";
import { STATUS_LABEL } from "@/lib/workflow";

/** Tailwind needs literal class names, hence the explicit table. */
export const STATUS_TONE: Record<AssessmentStatus, { chip: string; dot: string; wash: string; ring: string }> = {
  DRAFT: { chip: "bg-slate-500/10 text-slate-600 dark:text-zinc-300", dot: "bg-slate-400 text-slate-400", wash: "from-slate-400/15", ring: "ring-slate-400/30" },
  PENDING_PRE_MODERATION: { chip: "bg-amber-500/10 text-amber-700 dark:text-amber-300", dot: "bg-amber-500 text-amber-500", wash: "from-amber-400/20", ring: "ring-amber-400/30" },
  REVISION_REQUESTED: { chip: "bg-rose-500/10 text-rose-700 dark:text-rose-300", dot: "bg-rose-500 text-rose-500", wash: "from-rose-400/20", ring: "ring-rose-400/30" },
  READY_FOR_POST_MODERATION: { chip: "bg-sky-500/10 text-sky-700 dark:text-sky-300", dot: "bg-sky-500 text-sky-500", wash: "from-sky-400/20", ring: "ring-sky-400/30" },
  PENDING_FINAL_MODERATION: { chip: "bg-violet-500/10 text-violet-700 dark:text-violet-300", dot: "bg-violet-500 text-violet-500", wash: "from-violet-400/20", ring: "ring-violet-400/30" },
  PENDING_EXTERNAL_MODERATION: { chip: "bg-indigo-500/10 text-indigo-700 dark:text-indigo-300", dot: "bg-indigo-500 text-indigo-500", wash: "from-indigo-400/20", ring: "ring-indigo-400/30" },
  COMPLETED: { chip: "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300", dot: "bg-emerald-500 text-emerald-500", wash: "from-emerald-400/20", ring: "ring-emerald-400/30" },
};

/** Minimalist status pill with a softly pulsing indicator for in-flight states. */
export function StatusBadge({ status, className }: { status: AssessmentStatus; className?: string }) {
  const t = STATUS_TONE[status];
  const live = status !== "COMPLETED" && status !== "DRAFT";
  return (
    <span className={cn("inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold", t.chip, className)}>
      <span className={cn("h-1.5 w-1.5 rounded-full", t.dot, live && "animate-pulseRing")} />
      {STATUS_LABEL[status]}
    </span>
  );
}
