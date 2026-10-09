"use client";
import { motion } from "framer-motion";
import { ShieldCheck } from "lucide-react";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useState } from "react";
import { Button } from "@/components/ui/Button";
import { Notice } from "@/components/ui/Notice";
import { api } from "@/lib/client";

function Form() {
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
    <motion.div initial={{ opacity: 0, y: 16, scale: 0.98 }} animate={{ opacity: 1, y: 0, scale: 1 }} transition={{ type: "spring", stiffness: 260, damping: 28 }} className="card w-full max-w-sm p-8">
      <span className="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-gradient-to-br from-[#2997ff] to-[#0057b8] text-white shadow-lift">
        <ShieldCheck className="h-7 w-7" />
      </span>
      <h1 className="mt-5 text-center text-2xl font-semibold">Moderation DEMS</h1>
      <p className="mt-1 text-center text-sm text-slate-500 dark:text-zinc-400">Sign in to manage assessments and moderation.</p>
      <form onSubmit={submit} className="mt-7 space-y-4">
        <div>
          <label className="label" htmlFor="email">E-mail</label>
          <input id="email" type="email" autoComplete="username" required className="field" placeholder="you@institution.edu" value={email} onChange={(e) => setEmail(e.target.value)} />
        </div>
        <div>
          <label className="label" htmlFor="password">Password</label>
          <input id="password" type="password" autoComplete="current-password" required className="field" value={password} onChange={(e) => setPassword(e.target.value)} />
        </div>
        {error && <Notice tone="error">{error}</Notice>}
        <Button type="submit" loading={busy} className="w-full">Sign in</Button>
      </form>
    </motion.div>
  );
}

export function LoginForm() {
  return (
    <Suspense>
      <Form />
    </Suspense>
  );
}
