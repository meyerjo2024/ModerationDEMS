import nodemailer from "nodemailer";

export interface MailInput {
  to: string | string[];
  subject: string;
  text: string;
  html?: string;
  attachments?: { filename: string; content: Buffer; contentType?: string }[];
}

let transport: nodemailer.Transporter | null | undefined;

function getTransport() {
  if (transport !== undefined) return transport;
  const host = process.env.SMTP_HOST;
  transport = host
    ? nodemailer.createTransport({
        host,
        port: Number(process.env.SMTP_PORT ?? 587),
        secure: process.env.SMTP_SECURE === "true",
        auth: process.env.SMTP_USER ? { user: process.env.SMTP_USER, pass: process.env.SMTP_PASS } : undefined,
      })
    : null;
  return transport;
}

export const mailEnabled = () => !!getTransport();

/** Sends an e-mail. With no SMTP configured it logs instead. Throws on delivery failure. */
export async function sendMail(input: MailInput): Promise<{ delivered: boolean }> {
  const t = getTransport();
  if (!t) {
    console.log(`[mail:disabled] to=${[input.to].flat().join(",")} subject="${input.subject}"`);
    return { delivered: false };
  }
  await t.sendMail({ from: process.env.MAIL_FROM ?? "DEMS <no-reply@localhost>", ...input });
  return { delivered: true };
}

export function mailHtml(title: string, body: string, ctaLabel?: string, ctaUrl?: string) {
  const esc = (s: string) => s.replace(/[&<>"]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" })[c]!);
  return `<div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Inter,sans-serif;background:#f5f5f7;padding:32px">
  <div style="max-width:520px;margin:auto;background:#fff;border-radius:20px;padding:32px;border:1px solid #e5e7eb">
    <div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#6b7280">Moderation DEMS</div>
    <h1 style="font-size:22px;margin:8px 0 12px;color:#111827">${esc(title)}</h1>
    <p style="font-size:15px;line-height:1.55;color:#374151">${esc(body)}</p>
    ${ctaUrl ? `<p style="margin-top:24px"><a href="${esc(ctaUrl)}" style="background:#0071e3;color:#fff;text-decoration:none;padding:11px 20px;border-radius:999px;font-size:14px;font-weight:600">${esc(ctaLabel ?? "Open")}</a></p>` : ""}
  </div></div>`;
}
