"use client";
import { Fingerprint, ShieldCheck } from "lucide-react";
import { useState } from "react";
import { SignaturePad } from "./SignaturePad";

export interface SignOffValue {
  image: string;
  password: string;
}

/** Pen signature + password re-confirmation ("authenticated signature token"). */
export function SignOff({ statement, onChange }: { statement: string; onChange: (v: SignOffValue | null) => void }) {
  const [image, setImage] = useState<string | null>(null);
  const [password, setPassword] = useState("");
  const emit = (i: string | null, p: string) => onChange(i && p ? { image: i, password: p } : null);

  return (
    <div className="space-y-4">
      <p className="flex items-start gap-2 text-sm text-slate-600 dark:text-zinc-300">
        <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" />
        {statement}
      </p>
      <SignaturePad
        onChange={(png) => {
          setImage(png);
          emit(png, password);
        }}
      />
      <div>
        <label className="label" htmlFor="sign-password">
          Confirm with your password
        </label>
        <div className="relative">
          <Fingerprint className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
          <input
            id="sign-password"
            type="password"
            autoComplete="current-password"
            className="field pl-10"
            placeholder="Your account password"
            value={password}
            onChange={(e) => {
              setPassword(e.target.value);
              emit(image, e.target.value);
            }}
          />
        </div>
        <p className="mt-1.5 text-xs text-slate-400">Your signature is timestamped and bound to your authenticated account.</p>
      </div>
    </div>
  );
}
