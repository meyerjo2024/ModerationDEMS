"use client";
import { AnimatePresence, motion } from "framer-motion";
import { X } from "lucide-react";
import { useEffect } from "react";

/** Right-hand sheet with a frosted backdrop. Esc / backdrop click closes. */
export function SlideOver({ open, onClose, title, subtitle, children }: { open: boolean; onClose: () => void; title: string; subtitle?: string; children: React.ReactNode }) {
  useEffect(() => {
    if (!open) return;
    const h = (e: KeyboardEvent) => e.key === "Escape" && onClose();
    window.addEventListener("keydown", h);
    return () => window.removeEventListener("keydown", h);
  }, [open, onClose]);

  return (
    <AnimatePresence>
      {open && (
        <div className="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label={title}>
          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} onClick={onClose} className="absolute inset-0 bg-slate-900/20 backdrop-blur-sm dark:bg-black/50" />
          <motion.aside
            initial={{ x: "100%" }}
            animate={{ x: 0 }}
            exit={{ x: "100%" }}
            transition={{ type: "spring", stiffness: 380, damping: 40 }}
            className="absolute inset-y-0 right-0 flex w-full max-w-md flex-col border-l border-slate-200/60 bg-white/85 shadow-lift backdrop-blur-2xl dark:border-white/10 dark:bg-zinc-900/85 sm:inset-y-3 sm:right-3 sm:rounded-3xl sm:border"
          >
            <header className="flex items-start justify-between gap-4 p-6 pb-3">
              <div>
                <h2 className="text-xl font-semibold">{title}</h2>
                {subtitle && <p className="mt-1 text-sm text-slate-500 dark:text-zinc-400">{subtitle}</p>}
              </div>
              <button onClick={onClose} aria-label="Close" className="rounded-full p-2 text-slate-500 hover:bg-slate-900/5 dark:hover:bg-white/10">
                <X className="h-5 w-5" />
              </button>
            </header>
            <div className="flex-1 overflow-y-auto p-6 pt-3">{children}</div>
          </motion.aside>
        </div>
      )}
    </AnimatePresence>
  );
}
