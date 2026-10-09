"use client";
import { Download, FileText } from "lucide-react";
import { useState } from "react";
import { fmtBytes } from "@/lib/format";
import { PillTabs } from "@/components/ui/PillTabs";
import type { Att } from "./types";

/** Integrated previewer: PDFs render inline; Word files offer a download. */
export function DocumentViewer({ docs }: { docs: { label: string; att: Att | undefined }[] }) {
  const present = docs.filter((d) => d.att) as { label: string; att: Att }[];
  const [tab, setTab] = useState(present[0]?.label ?? "");
  if (!present.length) return <p className="text-sm text-slate-500">No documents were uploaded.</p>;
  const cur = present.find((d) => d.label === tab) ?? present[0];
  const isPdf = cur.att.mimeType === "application/pdf";

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <PillTabs value={cur.label} onChange={setTab} tabs={present.map((d) => ({ value: d.label, label: d.label }))} />
        <a href={`/api/files/${cur.att.id}`} className="btn-secondary !py-1.5 text-[13px]">
          <Download className="h-4 w-4" /> Download
        </a>
      </div>
      {isPdf ? (
        <iframe key={cur.att.id} title={cur.label} src={`/api/files/${cur.att.id}?inline=1#view=FitH`} className="h-[70vh] w-full rounded-2xl border border-slate-200/70 bg-white dark:border-white/10" />
      ) : (
        <div className="card-soft grid place-items-center gap-3 px-6 py-14 text-center">
          <FileText className="h-9 w-9 text-slate-400" />
          <div>
            <p className="font-medium">{cur.att.filename}</p>
            <p className="text-sm text-slate-500 dark:text-zinc-400">{fmtBytes(cur.att.size)} · Word documents can’t be previewed in the browser.</p>
          </div>
          <a href={`/api/files/${cur.att.id}`} className="btn-primary"><Download className="h-4 w-4" /> Download to review</a>
        </div>
      )}
    </div>
  );
}
