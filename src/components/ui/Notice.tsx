import { AlertCircle, CheckCircle2, Info } from "lucide-react";
import { cn } from "@/lib/cn";

const TONES = {
  error: { cls: "bg-rose-500/10 text-rose-700 dark:text-rose-300", Icon: AlertCircle },
  success: { cls: "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300", Icon: CheckCircle2 },
  info: { cls: "bg-sky-500/10 text-sky-700 dark:text-sky-300", Icon: Info },
  warn: { cls: "bg-amber-500/10 text-amber-800 dark:text-amber-300", Icon: AlertCircle },
} as const;

export function Notice({ tone = "info", title, children, className }: { tone?: keyof typeof TONES; title?: string; children?: React.ReactNode; className?: string }) {
  const { cls, Icon } = TONES[tone];
  return (
    <div role={tone === "error" ? "alert" : "status"} className={cn("flex gap-3 rounded-2xl px-4 py-3 text-sm", cls, className)}>
      <Icon className="mt-0.5 h-4 w-4 shrink-0" />
      <div>
        {title && <p className="font-semibold">{title}</p>}
        {children && <div className={title ? "mt-0.5 opacity-90" : ""}>{children}</div>}
      </div>
    </div>
  );
}
