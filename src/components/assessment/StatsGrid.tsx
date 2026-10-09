import { cn } from "@/lib/cn";
import { fmtPct } from "@/lib/format";

export interface Stats {
  candidateCount: number | null;
  passCount?: number | null;
  passRate: number | null;
  classAverage: number | null;
  highestMark: number | null;
  lowestMark: number | null;
  invalidEntries?: number | null;
}

/** KPI tiles + decile distribution, shared by the examiner preview and moderator review. */
export function StatsGrid({ stats, distribution }: { stats: Stats; distribution?: number[] | null }) {
  const tiles = [
    { k: "Candidates", v: stats.candidateCount ?? "—" },
    { k: "Pass rate", v: fmtPct(stats.passRate), sub: stats.passCount != null ? `${stats.passCount} passed (≥ 50%)` : undefined },
    { k: "Class average", v: fmtPct(stats.classAverage) },
    { k: "Highest", v: fmtPct(stats.highestMark) },
    { k: "Lowest", v: fmtPct(stats.lowestMark) },
  ];
  const max = Math.max(1, ...(distribution ?? [0]));
  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        {tiles.map((t) => (
          <div key={t.k} className="card-soft p-4">
            <p className="kicker">{t.k}</p>
            <p className="mt-1.5 text-2xl font-semibold tabular-nums">{t.v}</p>
            {t.sub && <p className="mt-0.5 text-xs text-slate-500 dark:text-zinc-400">{t.sub}</p>}
          </div>
        ))}
      </div>
      {distribution && (
        <div className="card-soft p-4">
          <p className="kicker mb-3">Mark distribution</p>
          <div className="flex h-28 items-end gap-1.5" role="img" aria-label="Number of candidates per 10% band">
            {distribution.map((n, i) => (
              <div key={i} className="flex h-full flex-1 flex-col items-center justify-end gap-1">
                <span className="text-[11px] tabular-nums text-slate-500">{n || ""}</span>
                <div
                  className={cn("w-full rounded-t-md transition-all", i >= 5 ? "bg-accent/80 dark:bg-accent-dark/80" : "bg-slate-300 dark:bg-zinc-600")}
                  style={{ height: `${Math.max((n / max) * 100, n ? 6 : 1.5)}%` }}
                />
              </div>
            ))}
          </div>
          <div className="mt-1.5 flex gap-1.5 text-[10px] text-slate-400">
            {distribution.map((_, i) => (
              <span key={i} className="flex-1 text-center">{i * 10}</span>
            ))}
          </div>
        </div>
      )}
      {!!stats.invalidEntries && (
        <p className="text-xs text-amber-700 dark:text-amber-300">
          {stats.invalidEntries} non-numeric or out-of-range entr{stats.invalidEntries === 1 ? "y was" : "ies were"} excluded (e.g. “ABS”).
        </p>
      )}
    </div>
  );
}
