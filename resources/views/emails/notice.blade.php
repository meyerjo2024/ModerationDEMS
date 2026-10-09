<div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Inter,sans-serif;background:#f5f5f7;padding:32px">
  <div style="max-width:520px;margin:auto;background:#fff;border-radius:20px;padding:32px;border:1px solid #e5e7eb">
    <div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#6b7280">Moderation DEMS</div>
    <h1 style="font-size:22px;margin:8px 0 12px;color:#111827">{{ $heading }}</h1>
    <p style="font-size:15px;line-height:1.55;color:#374151">{{ $body }}</p>
    @if ($url)
      <p style="margin-top:24px"><a href="{{ $url }}" style="background:#f58220;color:#fff;text-decoration:none;padding:11px 20px;border-radius:999px;font-size:14px;font-weight:600">{{ $cta }}</a></p>
    @endif
  </div>
</div>
