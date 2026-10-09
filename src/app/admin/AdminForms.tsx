"use client";
import { BookPlus, UserPlus } from "lucide-react";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { Button } from "@/components/ui/Button";
import { Notice } from "@/components/ui/Notice";
import { api } from "@/lib/client";
import { ROLE_LABEL } from "@/lib/workflow";

function useSubmit(path: string, onDone: () => void) {
  const router = useRouter();
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState<{ tone: "error" | "success"; text: string } | null>(null);
  const submit = async (e: React.FormEvent, body: object) => {
    e.preventDefault();
    setBusy(true);
    setMsg(null);
    try {
      await api("POST", path, body);
      setMsg({ tone: "success", text: "Saved." });
      onDone();
      router.refresh();
    } catch (err) {
      setMsg({ tone: "error", text: (err as Error).message });
    } finally {
      setBusy(false);
    }
  };
  return { busy, msg, submit };
}

export function AdminForms({ hods, defaultHod }: { hods: { id: string; name: string }[]; defaultHod: string }) {
  const [u, setU] = useState({ name: "", email: "", role: "EXAMINER", department: "", password: "" });
  const [s, setS] = useState({ code: "", name: "", department: "", hodId: defaultHod });
  const user = useSubmit("/api/admin/users", () => setU({ ...u, name: "", email: "", password: "" }));
  const subject = useSubmit("/api/admin/subjects", () => setS({ ...s, code: "", name: "" }));

  return (
    <div className="grid gap-6 lg:grid-cols-2">
      <form onSubmit={(e) => user.submit(e, { ...u, department: u.department || undefined })} className="card space-y-4 p-6">
        <p className="kicker flex items-center gap-1.5"><UserPlus className="h-3.5 w-3.5" /> Add user</p>
        <div className="grid gap-4 sm:grid-cols-2">
          <div><label className="label">Full name</label><input required className="field" value={u.name} onChange={(e) => setU({ ...u, name: e.target.value })} /></div>
          <div><label className="label">E-mail</label><input required type="email" className="field" value={u.email} onChange={(e) => setU({ ...u, email: e.target.value })} /></div>
          <div>
            <label className="label">Role</label>
            <select className="field" value={u.role} onChange={(e) => setU({ ...u, role: e.target.value })}>
              {Object.entries(ROLE_LABEL).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
            </select>
          </div>
          <div><label className="label">Department</label><input className="field" value={u.department} onChange={(e) => setU({ ...u, department: e.target.value })} /></div>
        </div>
        <div><label className="label">Temporary password</label><input required minLength={10} type="password" autoComplete="new-password" className="field" value={u.password} onChange={(e) => setU({ ...u, password: e.target.value })} /><p className="mt-1 text-xs text-slate-400">At least 10 characters. Share it securely.</p></div>
        {user.msg && <Notice tone={user.msg.tone}>{user.msg.text}</Notice>}
        <Button type="submit" loading={user.busy}>Create user</Button>
      </form>

      <form onSubmit={(e) => subject.submit(e, { ...s, department: s.department || undefined })} className="card space-y-4 p-6">
        <p className="kicker flex items-center gap-1.5"><BookPlus className="h-3.5 w-3.5" /> Add subject</p>
        <div className="grid gap-4 sm:grid-cols-2">
          <div><label className="label">Subject code</label><input required className="field uppercase" placeholder="CSC101" value={s.code} onChange={(e) => setS({ ...s, code: e.target.value })} /></div>
          <div><label className="label">Subject name</label><input required className="field" value={s.name} onChange={(e) => setS({ ...s, name: e.target.value })} /></div>
          <div><label className="label">Department</label><input className="field" value={s.department} onChange={(e) => setS({ ...s, department: e.target.value })} /></div>
          <div>
            <label className="label">Head of Department</label>
            <select className="field" value={s.hodId} onChange={(e) => setS({ ...s, hodId: e.target.value })}>
              {hods.map((h) => <option key={h.id} value={h.id}>{h.name}</option>)}
            </select>
          </div>
        </div>
        {subject.msg && <Notice tone={subject.msg.tone}>{subject.msg.text}</Notice>}
        <Button type="submit" loading={subject.busy}>Create subject</Button>
      </form>
    </div>
  );
}
