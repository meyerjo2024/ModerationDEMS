import { Circle, ShieldCheck } from "lucide-react";
import { fmtDateTime } from "@/lib/format";
import { AUDIT_LABEL, ROLE_LABEL } from "@/lib/workflow";
import type { Detail } from "./types";

const SECTION_LABEL: Record<string, string> = {
  EXAMINER_SECTION_1: "Examiner · Section 1",
  PRE_MODERATOR: "Gate 1 · Pre-moderation",
  EXAMINER_SECTION_2: "Examiner · Section 2",
  FINAL_INTERNAL_MODERATOR: "Gate 2 · Final (internal)",
  EXTERNAL_MODERATOR: "Gate 3 · External",
};

export function People({ a }: { a: Detail }) {
  const rows: [string, string | undefined][] = [
    ["Examiner", a.examiner.name],
    ["Internal moderator", a.internalModerator.name],
    ["External moderator", a.externalModerator?.name],
    ["Head of Department", a.subject.hod.name],
  ];
  return (
    <section className="card p-6">
      <p className="kicker mb-3">People</p>
      <dl className="space-y-2.5 text-sm">
        {rows.map(([k, v]) => (
          <div key={k} className="flex justify-between gap-3">
            <dt className="text-slate-500 dark:text-zinc-400">{k}</dt>
            <dd className={v ? "text-right font-medium" : "text-slate-400"}>{v ?? "Not assigned"}</dd>
          </div>
        ))}
      </dl>
    </section>
  );
}

export function Signatures({ a }: { a: Detail }) {
  const latest = Object.values(Object.fromEntries(a.signatures.map((s) => [s.section, s])));
  if (!latest.length) return null;
  return (
    <section className="card p-6">
      <p className="kicker mb-3 flex items-center gap-1.5"><ShieldCheck className="h-3.5 w-3.5 text-emerald-500" /> Signatures</p>
      <ul className="space-y-3">
        {latest.map((s) => (
          <li key={s.id} className="card-soft p-3">
            <p className="text-[11px] font-semibold uppercase tracking-wider text-accent dark:text-accent-dark">{SECTION_LABEL[s.section]}</p>
            <div className="my-1.5 rounded-lg bg-white p-1.5">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={s.imageData} alt={`Signature of ${s.user.name}`} className="h-12 w-auto max-w-full object-contain" />
            </div>
            <p className="text-sm font-semibold">{s.user.name}</p>
            <p className="text-xs text-slate-500 dark:text-zinc-400">{ROLE_LABEL[s.user.role]} · {fmtDateTime(s.signedAt)}</p>
          </li>
        ))}
      </ul>
    </section>
  );
}

export function Timeline({ a }: { a: Detail }) {
  const items = a.auditLogs.filter((l) => l.action !== "FILE_VIEWED");
  return (
    <section className="card p-6">
      <p className="kicker mb-4">Audit trail</p>
      <ol className="relative space-y-4 border-l border-slate-200 pl-5 dark:border-white/10">
        {items.map((l) => {
          const d = (l.details ?? {}) as Record<string, unknown>;
          const extra = typeof d.filename === "string" ? d.filename : null;
          return (
            <li key={l.id} className="relative">
              <Circle className="absolute -left-[27px] top-1 h-3 w-3 fill-accent text-accent dark:fill-accent-dark dark:text-accent-dark" />
              <p className="text-sm font-medium">{AUDIT_LABEL[l.action] ?? l.action}</p>
              {extra && <p className="truncate text-xs text-slate-500">{extra}</p>}
              <p className="text-xs text-slate-400">{l.user?.name ?? "System"} · {fmtDateTime(l.createdAt)}</p>
            </li>
          );
        })}
      </ol>
      <p className="mt-4 text-[11px] leading-snug text-slate-400">Entries are hash-chained: each one cryptographically covers the one before it.</p>
    </section>
  );
}
