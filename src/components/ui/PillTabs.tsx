"use client";
import { motion } from "framer-motion";
import { useId } from "react";
import { cn } from "@/lib/cn";

export interface Tab<T extends string> {
  value: T;
  label: string;
  count?: number;
}

/** Segmented "pill" control with a gliding highlight (macOS style). */
export function PillTabs<T extends string>({ tabs, value, onChange, className }: { tabs: Tab<T>[]; value: T; onChange: (v: T) => void; className?: string }) {
  const id = useId();
  return (
    <div role="tablist" className={cn("inline-flex max-w-full gap-1 overflow-x-auto rounded-full bg-slate-900/5 p-1 dark:bg-white/10", className)}>
      {tabs.map((t) => {
        const active = t.value === value;
        return (
          <button
            key={t.value}
            role="tab"
            aria-selected={active}
            onClick={() => onChange(t.value)}
            className={cn(
              "relative whitespace-nowrap rounded-full px-3.5 py-1.5 text-[13px] font-medium transition-colors",
              active ? "text-slate-900 dark:text-white" : "text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-zinc-200",
            )}
          >
            {active && (
              <motion.span
                layoutId={`pill-${id}`}
                className="absolute inset-0 rounded-full bg-white shadow-sm dark:bg-white/15"
                transition={{ type: "spring", stiffness: 500, damping: 38 }}
              />
            )}
            <span className="relative z-10">
              {t.label}
              {t.count !== undefined && <span className="ml-1.5 text-xs tabular-nums opacity-60">{t.count}</span>}
            </span>
          </button>
        );
      })}
    </div>
  );
}
