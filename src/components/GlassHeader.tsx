"use client";
import { Bell, CheckCheck, LogOut, ShieldCheck } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import type { Role } from "@prisma/client";
import { api } from "@/lib/client";
import { fmtDateTime } from "@/lib/format";
import { ROLE_LABEL } from "@/lib/workflow";

interface Note {
  id: string;
  message: string;
  read: boolean;
  createdAt: string;
  assessmentId: string | null;
}

export function GlassHeader({ user }: { user: { name: string; role: Role } }) {
  const router = useRouter();
  const [notes, setNotes] = useState<Note[]>([]);
  const [open, setOpen] = useState(false);
  const box = useRef<HTMLDivElement>(null);
  const unread = notes.filter((n) => !n.read).length;

  const load = () => api<{ notifications: Note[] }>("GET", "/api/notifications").then((r) => setNotes(r.notifications)).catch(() => {});
  useEffect(() => {
    load();
    const t = setInterval(load, 60_000);
    return () => clearInterval(t);
  }, []);
  useEffect(() => {
    const h = (e: MouseEvent) => box.current && !box.current.contains(e.target as Node) && setOpen(false);
    document.addEventListener("mousedown", h);
    return () => document.removeEventListener("mousedown", h);
  }, []);

  const signOut = async () => {
    await api("POST", "/api/auth/logout");
    router.replace("/login");
    router.refresh();
  };

  return (
    <header className="glass-header">
      <div className="mx-auto flex h-14 max-w-7xl items-center justify-between gap-3 px-4 sm:px-6">
        <div className="flex items-center gap-6">
          <Link href="/" className="flex items-center gap-2.5 font-semibold tracking-tight">
            <span className="grid h-7 w-7 place-items-center rounded-lg bg-brand text-white shadow-sm">
              <ShieldCheck className="h-4 w-4" />
            </span>
            <span className="hidden sm:inline">Moderation DEMS</span>
          </Link>
          <nav className="flex items-center gap-1 text-sm font-medium text-white/80">
            <Link href="/" className="whitespace-nowrap rounded-full px-3 py-1.5 hover:bg-white/10 hover:text-white">Dashboard</Link>
            {user.role === "EXAMINER" && (
              <Link href="/assessments/new" className="whitespace-nowrap rounded-full px-3 py-1.5 hover:bg-white/10 hover:text-white">New assessment</Link>
            )}
            {user.role === "HOD" && (
              <Link href="/admin" className="whitespace-nowrap rounded-full px-3 py-1.5 hover:bg-white/10 hover:text-white">Admin</Link>
            )}
          </nav>
        </div>

        <div className="flex items-center gap-2">
          <div className="relative" ref={box}>
            <button
              aria-label={`Notifications${unread ? ` (${unread} unread)` : ""}`}
              onClick={() => {
                setOpen((o) => !o);
                if (!open && unread) api("POST", "/api/notifications").then(() => setTimeout(load, 4000)).catch(() => {});
              }}
              className="relative rounded-full p-2 text-white/80 hover:bg-white/10"
            >
              <Bell className="h-[18px] w-[18px]" />
              {unread > 0 && <span className="absolute right-1 top-1 h-2.5 w-2.5 rounded-full bg-rose-500 ring-2 ring-navy" />}
            </button>
            {open && (
              <div className="card absolute right-0 mt-2 w-80 overflow-hidden text-slate-900 dark:text-zinc-100 !bg-white/95 p-1.5 shadow-lift dark:!bg-zinc-900/95">
                <p className="kicker px-3 pb-1 pt-2">Notifications</p>
                {notes.length === 0 ? (
                  <p className="flex items-center gap-2 px-3 py-6 text-sm text-slate-500"><CheckCheck className="h-4 w-4" /> You’re all caught up.</p>
                ) : (
                  <ul className="max-h-80 overflow-y-auto">
                    {notes.map((n) => (
                      <li key={n.id}>
                        <Link
                          href={n.assessmentId ? `/assessments/${n.assessmentId}` : "/"}
                          onClick={() => setOpen(false)}
                          className="block rounded-xl px-3 py-2.5 hover:bg-white/10 hover:text-white"
                        >
                          <p className={`text-sm ${n.read ? "text-slate-500 dark:text-zinc-400" : "font-medium"}`}>{n.message}</p>
                          <p className="mt-0.5 text-xs text-slate-400">{fmtDateTime(n.createdAt)}</p>
                        </Link>
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            )}
          </div>
          <div className="hidden text-right leading-tight sm:block">
            <p className="text-[13px] font-semibold">{user.name}</p>
            <p className="text-[11px] text-white/60">{ROLE_LABEL[user.role]}</p>
          </div>
          <button onClick={signOut} aria-label="Sign out" className="rounded-full p-2 text-white/80 hover:bg-white/10">
            <LogOut className="h-[18px] w-[18px]" />
          </button>
        </div>
      </div>
    </header>
  );
}
