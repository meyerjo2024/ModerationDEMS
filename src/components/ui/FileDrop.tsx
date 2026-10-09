"use client";
import { FileText, Loader2, UploadCloud } from "lucide-react";
import { useRef, useState } from "react";
import { cn } from "@/lib/cn";

/** Drag-and-drop / click upload target. */
export function FileDrop({ label, accept, hint, onFile, busy, disabled }: { label: string; accept: string; hint: string; onFile: (f: File) => void; busy?: boolean; disabled?: boolean }) {
  const input = useRef<HTMLInputElement>(null);
  const [over, setOver] = useState(false);
  return (
    <button
      type="button"
      disabled={disabled || busy}
      onClick={() => input.current?.click()}
      onDragOver={(e) => {
        e.preventDefault();
        setOver(true);
      }}
      onDragLeave={() => setOver(false)}
      onDrop={(e) => {
        e.preventDefault();
        setOver(false);
        const f = e.dataTransfer.files?.[0];
        if (f) onFile(f);
      }}
      className={cn(
        "flex w-full items-center gap-3 rounded-2xl border border-dashed px-4 py-3.5 text-left transition",
        over ? "border-accent bg-accent/5" : "border-slate-300/80 hover:border-accent/60 hover:bg-slate-50 dark:border-white/15 dark:hover:bg-white/5",
        (disabled || busy) && "opacity-60",
      )}
    >
      <span className="grid h-10 w-10 place-items-center rounded-xl bg-accent/10 text-accent dark:text-accent-dark">
        {busy ? <Loader2 className="h-5 w-5 animate-spin" /> : <UploadCloud className="h-5 w-5" />}
      </span>
      <span className="min-w-0">
        <span className="block text-sm font-medium">{label}</span>
        <span className="block truncate text-xs text-slate-500 dark:text-zinc-400">{hint}</span>
      </span>
      <input
        ref={input}
        type="file"
        accept={accept}
        hidden
        onChange={(e) => {
          const f = e.target.files?.[0];
          if (f) onFile(f);
          e.target.value = "";
        }}
      />
    </button>
  );
}

export function FileChip({ name, meta, href }: { name: string; meta?: string; href?: string }) {
  const body = (
    <>
      <FileText className="h-4 w-4 shrink-0 text-slate-400" />
      <span className="truncate font-medium">{name}</span>
      {meta && <span className="shrink-0 text-xs text-slate-400">{meta}</span>}
    </>
  );
  const cls = "flex items-center gap-2 rounded-xl bg-slate-900/[.04] px-3 py-2 text-sm dark:bg-white/5";
  return href ? (
    <a href={href} className={cn(cls, "hover:bg-slate-900/[.07] dark:hover:bg-white/10")}>
      {body}
    </a>
  ) : (
    <div className={cls}>{body}</div>
  );
}
