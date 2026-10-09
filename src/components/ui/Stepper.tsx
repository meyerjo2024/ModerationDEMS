"use client";
import { motion } from "framer-motion";
import { Check } from "lucide-react";
import { cn } from "@/lib/cn";
import { GATES } from "@/lib/workflow";

/** Horizontal journey indicator for the 4 gates (+ setup & archive). */
export function Stepper({ current, hasExternal }: { current: number; hasExternal: boolean }) {
  return (
    <ol className="flex items-start gap-1 overflow-x-auto pb-1" aria-label="Moderation progress">
      {GATES.map((g, i) => {
        const skipped = g.key === "gate3" && !hasExternal;
        const done = i < current || current > GATES.length - 1;
        const active = i === current;
        return (
          <li key={g.key} className={cn("flex min-w-[112px] flex-1 flex-col", skipped && "opacity-40")}>
            <div className="flex items-center">
              <motion.span
                initial={false}
                animate={{ scale: active ? 1.08 : 1 }}
                className={cn(
                  "grid h-7 w-7 shrink-0 place-items-center rounded-full text-xs font-semibold ring-4 transition-colors",
                  done && !skipped && "bg-emerald-500 text-white ring-emerald-500/15",
                  active && "bg-accent text-white ring-accent/20 dark:bg-accent-dark dark:text-black",
                  !done && !active && "bg-slate-200 text-slate-500 ring-transparent dark:bg-white/10 dark:text-zinc-400",
                )}
              >
                {done && !skipped ? <Check className="h-3.5 w-3.5" /> : i + 1}
              </motion.span>
              {i < GATES.length - 1 && <span className={cn("mx-1.5 h-0.5 flex-1 rounded-full", done ? "bg-emerald-500/60" : "bg-slate-200 dark:bg-white/10")} />}
            </div>
            <p className={cn("mt-2 text-[13px] font-semibold leading-tight", active ? "text-slate-900 dark:text-white" : "text-slate-600 dark:text-zinc-300")}>{g.title}</p>
            <p className="text-xs text-slate-400">{skipped ? "Not assigned" : g.sub}</p>
          </li>
        );
      })}
    </ol>
  );
}
