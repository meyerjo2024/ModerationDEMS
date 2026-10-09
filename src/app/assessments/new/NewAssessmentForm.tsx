"use client";
import { motion } from "framer-motion";
import { ArrowRight } from "lucide-react";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { Button } from "@/components/ui/Button";
import { Notice } from "@/components/ui/Notice";
import { api } from "@/lib/client";

type Opt = { id: string; name: string };

export function NewAssessmentForm({ subjects, internals, externals }: { subjects: (Opt & { code: string })[]; internals: Opt[]; externals: Opt[] }) {
  const router = useRouter();
  const [subjectId, setSubjectId] = useState("");
  const [number, setNumber] = useState("");
  const [internalModeratorId, setInternal] = useState("");
  const [externalModeratorId, setExternal] = useState("");
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setError("");
    try {
      const { id } = await api<{ id: string }>("POST", "/api/assessments", { subjectId, number, internalModeratorId, externalModeratorId: externalModeratorId || null });
      router.push(`/assessments/${id}`);
    } catch (err) {
      setError((err as Error).message);
      setBusy(false);
    }
  };

  return (
    <motion.form onSubmit={submit} initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} className="card mx-auto max-w-xl space-y-6 p-8">
      <div>
        <p className="kicker">Phase 1 · Pre-assessment</p>
        <h1 className="mt-1 text-3xl font-semibold">New assessment</h1>
        <p className="mt-1 text-sm text-slate-500 dark:text-zinc-400">Choose the subject and assessment, then assign who will moderate it.</p>
      </div>

      {subjects.length === 0 && <Notice tone="warn" title="No subjects yet">Ask your Head of Department to add subjects in Admin.</Notice>}

      <div className="grid gap-5 sm:grid-cols-2">
        <div>
          <label className="label" htmlFor="subject">Subject code</label>
          <select id="subject" required className="field" value={subjectId} onChange={(e) => setSubjectId(e.target.value)}>
            <option value="">Select…</option>
            {subjects.map((s) => (
              <option key={s.id} value={s.id}>{s.code} — {s.name}</option>
            ))}
          </select>
        </div>
        <div>
          <label className="label" htmlFor="number">Assessment number</label>
          <input id="number" required className="field" placeholder="e.g. Test 1, Exam" maxLength={40} value={number} onChange={(e) => setNumber(e.target.value)} />
        </div>
      </div>

      <div>
        <label className="label" htmlFor="internal">Internal moderator</label>
        <select id="internal" required className="field" value={internalModeratorId} onChange={(e) => setInternal(e.target.value)}>
          <option value="">Select…</option>
          {internals.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
        </select>
      </div>
      <div>
        <label className="label" htmlFor="external">External moderator <span className="normal-case tracking-normal text-slate-400">(optional)</span></label>
        <select id="external" className="field" value={externalModeratorId} onChange={(e) => setExternal(e.target.value)}>
          <option value="">Not required</option>
          {externals.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
        </select>
        <p className="mt-1.5 text-xs text-slate-400">An external moderator only sees the record after the internal moderator’s final sign-off.</p>
      </div>

      {error && <Notice tone="error">{error}</Notice>}
      <Button type="submit" loading={busy} className="w-full">Continue <ArrowRight className="h-4 w-4" /></Button>
    </motion.form>
  );
}
