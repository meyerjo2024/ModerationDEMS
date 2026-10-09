const dt = new Intl.DateTimeFormat("en-GB", { day: "numeric", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", timeZone: "UTC" });
const d = new Intl.DateTimeFormat("en-GB", { day: "numeric", month: "short", year: "numeric", timeZone: "UTC" });

/** Deterministic (UTC) formatting so server and client renders always match. */
export const fmtDateTime = (s: string | Date) => `${dt.format(new Date(s))} UTC`;
export const fmtDate = (s: string | Date) => d.format(new Date(s));
export const fmtBytes = (n: number) => (n < 1024 ? `${n} B` : n < 1048576 ? `${(n / 1024).toFixed(0)} KB` : `${(n / 1048576).toFixed(1)} MB`);
export const fmtPct = (n: number | null | undefined) => (n == null ? "—" : `${n.toFixed(1)}%`);
