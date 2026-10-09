"use client";
import { AnimatePresence, motion } from "framer-motion";
import { ArrowUpRight, FilePlus2, Search, Sparkles } from "lucide-react";
import Link from "next/link";
import { useMemo, useState } from "react";
import type { AssessmentStatus, Role } from "@prisma/client";
import { cn } from "@/lib/cn";
import { fmtDate, fmtPct } from "@/lib/format";
import { currentGateIndex, GATES, STATUS_LABEL } from "@/lib/workflow";
import { PillTabs } from "./ui/PillTabs";
import { STATUS_TONE, StatusBadge } from "./ui/StatusBadge";

export interface DashItem {
  id: string;
  number: string;
  status: AssessmentStatus;
  updatedAt: string;
  passRate: number | null;
  candidateCount: number | null;
  subject: { code: string; name: string };
  examiner: { name: string };
  internalModerator: { name: string };
  myAction: boolean;
  hasExternal: boolean;
}

const HEADLINE: AssessmentStatus[] = ["PENDING_PRE_MODERATION", "REVISION_REQUESTED", "READY_FOR_POST_MODERATION", "COMPLETED"];
type Filter = "ALL" | "ACTION" | AssessmentStatus;

export function Dashboard({ user, items }: { user: { name: string; role: Role }; items: DashItem[] }) {
  const [filter, setFilter] = useState<Filter>("ALL");
  const [q, setQ] = useState("");

  const counts = useMemo(() => {
    const c = {} as Record<AssessmentStatus, number>;
    for (const i of items) c[i.status] = (c[i.status] ?? 0) + 1;
    return c;
  }, [items]);
  const actionCount = items.filter((i) => i.myAction).length;

  const shown = items.filter(
    (i) =>
      (filter === "ALL" || (filter === "ACTION" ? i.myAction : i.status === filter)) &&
      `${i.subject.code} ${i.subject.name} ${i.number}`.toLowerCase().includes(q.toLowerCase()),
  );

  const first = user.name.split(" ").filter((p) => !/^(dr|prof|mr|mrs|ms)\.?$/i.test(p))[0] ?? user.name;

  return (
    <div className="space-y-8">
      <div className="relative overflow-hidden rounded-4xl bg-navy p-8 text-white shadow-lift sm:p-10">
        <div className="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-brand/30 blur-3xl" />
        <div className="pointer-events-none absolute inset-0 opacity-[.06]" style={{ backgroundImage: "radial-gradient(#fff 1px, transparent 1px)", backgroundSize: "22px 22px" }} />
        <div className="relative flex flex-wrap items-end justify-between gap-4">
          <div>
            <p className="text-[11px] font-semibold uppercase tracking-[.16em] text-brand">Overview</p>
            <h1 className="mt-1 text-3xl font-semibold sm:text-4xl">Welcome back, {first}.</h1>
            <p className="mt-1 text-white/70">
              {actionCount ? `${actionCount} record${actionCount === 1 ? "" : "s"} waiting on you.` : "Nothing is waiting on you right now."}
            </p>
          </div>
          {user.role === "EXAMINER" && (
            <Link href="/assessments/new" className="btn bg-brand text-white shadow-sm hover:brightness-110">
              <FilePlus2 className="h-4 w-4" /> New assessment
            </Link>
          )}
        </div>
      </div>

      {/* Status tiles */}
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        {HEADLINE.map((s, i) => (
          <motion.button
            key={s}
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: i * 0.05 }}
            whileHover={{ y: -2 }}
            onClick={() => setFilter(filter === s ? "ALL" : s)}
            className={cn("card relative overflow-hidden p-5 text-left transition-shadow hover:shadow-lift", filter === s && cn("ring-2", STATUS_TONE[s].ring))}
          >
            <span className={cn("pointer-events-none absolute inset-0 bg-gradient-to-br to-transparent", STATUS_TONE[s].wash)} />
            <span className="relative flex items-center gap-2 text-xs font-semibold text-slate-600 dark:text-zinc-300">
              <span className={cn("h-2 w-2 rounded-full", STATUS_TONE[s].dot, s !== "COMPLETED" && "animate-pulseRing")} />
              {STATUS_LABEL[s]}
            </span>
            <span className="relative mt-3 block text-4xl font-semibold tabular-nums">{counts[s] ?? 0}</span>
          </motion.button>
        ))}
      </div>

      {/* Filters */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <PillTabs<Filter>
          value={filter}
          onChange={setFilter}
          tabs={[
            { value: "ALL", label: "All", count: items.length },
            { value: "ACTION", label: "Needs my action", count: actionCount },
            { value: "DRAFT", label: "Drafts", count: counts.DRAFT ?? 0 },
            { value: "PENDING_FINAL_MODERATION", label: "Final review", count: (counts.PENDING_FINAL_MODERATION ?? 0) + (counts.PENDING_EXTERNAL_MODERATION ?? 0) },
          ]}
        />
        <div className="relative w-full sm:w-64">
          <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search subject or number" className="field !rounded-full pl-10" aria-label="Search" />
        </div>
      </div>

      {/* Wallet-style cards */}
      {shown.length === 0 ? (
        <div className="card grid place-items-center gap-3 px-6 py-16 text-center">
          <span className="grid h-12 w-12 place-items-center rounded-2xl bg-accent/10 text-accent dark:text-accent-dark"><Sparkles className="h-6 w-6" /></span>
          <p className="text-lg font-semibold">{items.length ? "No matching records" : "No assessments yet"}</p>
          <p className="max-w-sm text-sm text-slate-500 dark:text-zinc-400">
            {items.length ? "Try a different filter or search term." : user.role === "EXAMINER" ? "Start your first assessment to begin the moderation workflow." : "Records appear here once an examiner assigns them to you."}
          </p>
        </div>
      ) : (
        <motion.div layout className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          <AnimatePresence mode="popLayout">
            {shown.map((a, i) => (
              <WalletCard key={a.id} a={a} index={i} />
            ))}
          </AnimatePresence>
        </motion.div>
      )}
    </div>
  );
}

function WalletCard({ a, index }: { a: DashItem; index: number }) {
  const tone = STATUS_TONE[a.status];
  const step = currentGateIndex(a.status, a.hasExternal);
  return (
    <motion.div layout initial={{ opacity: 0, y: 16, scale: 0.98 }} animate={{ opacity: 1, y: 0, scale: 1 }} exit={{ opacity: 0, scale: 0.96 }} transition={{ delay: Math.min(index, 8) * 0.04, type: "spring", stiffness: 300, damping: 30 }} whileHover={{ y: -4 }}>
      <Link href={`/assessments/${a.id}`} className={cn("card group relative block overflow-hidden p-6 transition-shadow hover:shadow-lift", a.myAction && cn("ring-2", tone.ring))}>
        <span className={cn("pointer-events-none absolute inset-x-0 top-0 h-28 bg-gradient-to-b to-transparent", tone.wash)} />
        <div className="relative flex items-start justify-between gap-3">
          <StatusBadge status={a.status} />
          <ArrowUpRight className="h-5 w-5 text-slate-300 transition group-hover:text-accent" />
        </div>
        <div className="relative mt-5">
          <p className="text-3xl font-semibold tracking-tight">{a.subject.code}</p>
          <p className="mt-0.5 text-sm font-medium text-slate-700 dark:text-zinc-200">{a.number}</p>
          <p className="truncate text-sm text-slate-500 dark:text-zinc-400">{a.subject.name}</p>
        </div>
        {a.passRate != null && (
          <div className="relative mt-4 flex gap-6 text-sm">
            <span><span className="kicker block">Pass rate</span><span className="font-semibold tabular-nums">{fmtPct(a.passRate)}</span></span>
            <span><span className="kicker block">Candidates</span><span className="font-semibold tabular-nums">{a.candidateCount}</span></span>
          </div>
        )}
        <div className="relative mt-5 flex gap-1" aria-hidden>
          {GATES.map((g, i) => (
            <span key={g.key} className={cn("h-1 flex-1 rounded-full", i < step ? "bg-emerald-500/70" : i === step ? tone.dot.split(" ")[0] : "bg-slate-200 dark:bg-white/10")} />
          ))}
        </div>
        <div className="relative mt-4 flex items-center justify-between text-xs text-slate-500 dark:text-zinc-400">
          <span className="truncate">{a.examiner.name}</span>
          <span className="shrink-0">{a.myAction ? <span className="font-semibold text-accent dark:text-accent-dark">Your turn</span> : fmtDate(a.updatedAt)}</span>
        </div>
      </Link>
    </motion.div>
  );
}
