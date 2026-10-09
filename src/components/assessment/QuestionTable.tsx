import { Check, X } from "lucide-react";
import type { QRow } from "./types";

export function QuestionTable({ rows }: { rows: QRow[] }) {
  const total = rows.reduce((s, r) => s + r.weighting, 0);
  return (
    <div className="overflow-hidden rounded-2xl border border-slate-200/60 dark:border-white/10">
      <table className="w-full text-left text-sm">
        <thead className="bg-slate-50/80 text-[11px] uppercase tracking-wider text-slate-500 dark:bg-white/5 dark:text-zinc-400">
          <tr>
            <th className="px-4 py-2.5 font-semibold">Question type</th>
            <th className="px-3 py-2.5 text-right font-semibold">Weight</th>
            <th className="px-3 py-2.5 text-center font-semibold">HEQF</th>
            <th className="px-3 py-2.5 text-center font-semibold">Aligned</th>
            <th className="hidden px-4 py-2.5 font-semibold sm:table-cell">Comment</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-200/60 dark:divide-white/10">
          {rows.map((r, i) => (
            <tr key={i}>
              <td className="px-4 py-2.5 font-medium">{r.type}</td>
              <td className="px-3 py-2.5 text-right tabular-nums">{r.weighting}%</td>
              <td className="px-3 py-2.5 text-center tabular-nums">{r.heqfLevel}</td>
              <td className="px-3 py-2.5">
                <span className="flex justify-center">{r.aligned ? <Check className="h-4 w-4 text-emerald-500" /> : <X className="h-4 w-4 text-rose-500" />}</span>
              </td>
              <td className="hidden px-4 py-2.5 text-slate-500 dark:text-zinc-400 sm:table-cell">{r.comment}</td>
            </tr>
          ))}
        </tbody>
        <tfoot>
          <tr className="bg-slate-50/60 text-xs font-semibold dark:bg-white/5">
            <td className="px-4 py-2">Total</td>
            <td className="px-3 py-2 text-right tabular-nums">{+total.toFixed(2)}%</td>
            <td colSpan={3} />
          </tr>
        </tfoot>
      </table>
    </div>
  );
}
