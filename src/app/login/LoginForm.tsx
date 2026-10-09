"use client";
import { motion } from "framer-motion";
import { ArrowRight, ClipboardCheck, FileSignature, History, ShieldCheck, Sparkles } from "lucide-react";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useState } from "react";
import { Button } from "@/components/ui/Button";
import { Notice } from "@/components/ui/Notice";
import { api } from "@/lib/client";

type Demo = { accounts: { role: string; email: string }[]; password: string } | null;

const FEATURES = [
  { Icon: ClipboardCheck, title: "Four-gate moderation", text: "Pre-moderation, final review and optional external sign-off." },
  { Icon: FileSignature, title: "Authenticated signatures", text: "Pen signature plus password, timestamped to your account." },
  { Icon: History, title: "Audit-ready reports", text: "A tamper-evident trail and a signed PDF for the HOD." },
];

function Form({ demo, institution }: { demo: Demo; institution: string }) {
  const router = useRouter();
  const next = useSearchParams().get("next");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setError("");
    try {
      await api("POST", "/api/auth/login", { email, password });
      router.replace(next && next.startsWith("/") && !next.startsWith("//") ? next : "/");
      router.refresh();
    } catch (err) {
      setError((err as Error).message);
      setBusy(false);
    }
  };

  return (
    <main className="grid min-h-screen lg:grid-cols-[1.1fr_1fr]">
      {/* Brand panel */}
      <section className="relative hidden overflow-hidden bg-navy p-12 text-white lg:flex lg:flex-col lg:justify-between">
        <div className="pointer-events-none absolute -right-32 -top-32 h-[28rem] w-[28rem] rounded-full bg-brand/25 blur-3xl" />
        <div className="pointer-events-none absolute -bottom-40 -left-24 h-[30rem] w-[30rem] rounded-full bg-accent/50 blur-3xl" />
        <div className="pointer-events-none absolute inset-0 opacity-[.07]" style={{ backgroundImage: "radial-gradient(#fff 1px, transparent 1px)", backgroundSize: "22px 22px" }} />
        <div className="relative flex items-center gap-3">
          <span className="grid h-10 w-10 place-items-center rounded-xl bg-brand shadow-lift"><ShieldCheck className="h-5 w-5" /></span>
          <div className="leading-tight">
            <p className="font-semibold">{institution}</p>
            <p className="text-xs uppercase tracking-[.16em] text-white/60">Moderation DEMS</p>
          </div>
        </div>
        <div className="relative max-w-lg">
          <p className="mb-4 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-white/90 backdrop-blur"><Sparkles className="h-3.5 w-3.5 text-brand" /> Quality assurance, digitised</p>
          <h1 className="text-5xl font-semibold leading-[1.05] tracking-tight">Assessment moderation, <span className="text-brand">without the paperwork.</span></h1>
          <p className="mt-5 text-lg text-white/70">From draft paper to signed, archived report — one secure workflow for examiners, moderators and heads of department.</p>
          <ul className="mt-10 space-y-5">
            {FEATURES.map(({ Icon, title, text }) => (
              <li key={title} className="flex gap-4">
                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white/10 backdrop-blur"><Icon className="h-5 w-5 text-brand" /></span>
                <span><span className="block font-semibold">{title}</span><span className="text-sm text-white/65">{text}</span></span>
              </li>
            ))}
          </ul>
        </div>
        <p className="relative text-xs text-white/40">© {new Date().getFullYear()} {institution}</p>
      </section>

      {/* Form panel */}
      <section className="flex items-center justify-center px-5 py-12">
        <motion.div initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} transition={{ type: "spring", stiffness: 260, damping: 28 }} className="w-full max-w-md">
          <div className="mb-8 flex items-center gap-3 lg:hidden">
            <span className="grid h-10 w-10 place-items-center rounded-xl bg-brand text-white"><ShieldCheck className="h-5 w-5" /></span>
            <p className="font-semibold">Moderation DEMS</p>
          </div>
          <h2 className="text-3xl font-semibold">Welcome back</h2>
          <p className="mt-1 text-slate-500 dark:text-zinc-400">Sign in with your institutional account.</p>

          <form onSubmit={submit} className="card mt-8 space-y-4 p-6 sm:p-8">
            <div>
              <label className="label" htmlFor="email">E-mail</label>
              <input id="email" type="email" autoComplete="username" required className="field" placeholder="you@institution.edu" value={email} onChange={(e) => setEmail(e.target.value)} />
            </div>
            <div>
              <label className="label" htmlFor="password">Password</label>
              <input id="password" type="password" autoComplete="current-password" required className="field" value={password} onChange={(e) => setPassword(e.target.value)} />
            </div>
            {error && <Notice tone="error">{error}</Notice>}
            <Button type="submit" loading={busy} className="w-full !bg-brand !text-white">Sign in <ArrowRight className="h-4 w-4" /></Button>
          </form>

          {demo && (
            <div className="mt-6 rounded-3xl border border-brand/30 bg-brand-soft/70 p-5 dark:bg-brand/10">
              <p className="kicker !text-brand">Demo accounts · click to fill</p>
              <ul className="mt-3 space-y-1.5">
                {demo.accounts.map((a) => (
                  <li key={a.email}>
                    <button type="button" onClick={() => { setEmail(a.email); setPassword(demo.password); }} className="flex w-full items-center justify-between gap-3 rounded-xl px-3 py-2 text-left text-sm transition hover:bg-white/80 dark:hover:bg-white/10">
                      <span className="font-medium">{a.role}</span>
                      <span className="truncate text-slate-500 dark:text-zinc-400">{a.email}</span>
                    </button>
                  </li>
                ))}
              </ul>
              <p className="mt-3 border-t border-brand/20 pt-3 text-xs text-slate-600 dark:text-zinc-300">
                Password for all demo accounts: <code className="rounded bg-white/80 px-1.5 py-0.5 font-mono font-semibold dark:bg-white/10">{demo.password}</code>
              </p>
            </div>
          )}
        </motion.div>
      </section>
    </main>
  );
}

export function LoginForm(props: { demo: Demo; institution: string }) {
  return (
    <Suspense>
      <Form {...props} />
    </Suspense>
  );
}
