"use client";
import { Eraser } from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";
import { cn } from "@/lib/cn";

/**
 * Minimal pen-signature canvas (mouse, touch and Apple Pencil via pointer events).
 * Emits a transparent PNG data URL once something substantial has been drawn, else null.
 */
export function SignaturePad({ onChange, height = 150, className }: { onChange: (png: string | null) => void; height?: number; className?: string }) {
  const ref = useRef<HTMLCanvasElement>(null);
  const drawing = useRef(false);
  const last = useRef<{ x: number; y: number } | null>(null);
  const length = useRef(0);
  const [empty, setEmpty] = useState(true);

  const setup = useCallback(() => {
    const c = ref.current;
    if (!c) return;
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const { width } = c.getBoundingClientRect();
    c.width = Math.round(width * dpr);
    c.height = Math.round(height * dpr);
    const ctx = c.getContext("2d")!;
    ctx.scale(dpr, dpr);
    ctx.lineCap = "round";
    ctx.lineJoin = "round";
    ctx.lineWidth = 2.4;
    ctx.strokeStyle = "#1d1d1f";
  }, [height]);

  useEffect(() => {
    setup();
    onChange(null);
    // re-sizing wipes the canvas, so only set up once
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const pos = (e: React.PointerEvent) => {
    const r = ref.current!.getBoundingClientRect();
    return { x: e.clientX - r.left, y: e.clientY - r.top };
  };

  const down = (e: React.PointerEvent<HTMLCanvasElement>) => {
    e.currentTarget.setPointerCapture(e.pointerId);
    drawing.current = true;
    last.current = pos(e);
    const ctx = ref.current!.getContext("2d")!;
    ctx.beginPath();
    ctx.arc(last.current.x, last.current.y, 0.6, 0, Math.PI * 2);
    ctx.stroke();
  };
  const move = (e: React.PointerEvent<HTMLCanvasElement>) => {
    if (!drawing.current || !last.current) return;
    const p = pos(e);
    const ctx = ref.current!.getContext("2d")!;
    const mid = { x: (last.current.x + p.x) / 2, y: (last.current.y + p.y) / 2 };
    ctx.beginPath();
    ctx.moveTo(last.current.x, last.current.y);
    ctx.quadraticCurveTo(last.current.x, last.current.y, mid.x, mid.y);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    length.current += Math.hypot(p.x - last.current.x, p.y - last.current.y);
    last.current = p;
  };
  const up = () => {
    if (!drawing.current) return;
    drawing.current = false;
    last.current = null;
    if (length.current > 40) {
      setEmpty(false);
      onChange(ref.current!.toDataURL("image/png"));
    }
  };

  const clear = () => {
    const c = ref.current!;
    c.getContext("2d")!.clearRect(0, 0, c.width, c.height);
    length.current = 0;
    setEmpty(true);
    onChange(null);
  };

  return (
    <div className={cn("relative", className)}>
      <div className="relative overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-inner dark:border-white/15">
        <canvas
          ref={ref}
          style={{ height, touchAction: "none" }}
          className="block w-full cursor-crosshair"
          onPointerDown={down}
          onPointerMove={move}
          onPointerUp={up}
          onPointerCancel={up}
          aria-label="Signature pad — draw your signature"
        />
        {empty && (
          <span className="pointer-events-none absolute inset-0 flex items-center justify-center text-sm text-slate-300">Sign here</span>
        )}
        <span className="pointer-events-none absolute inset-x-6 bottom-9 border-b border-dashed border-slate-200" />
      </div>
      <button type="button" onClick={clear} className="absolute right-3 top-3 inline-flex items-center gap-1 rounded-full bg-slate-900/5 px-2.5 py-1 text-xs font-medium text-slate-500 hover:bg-slate-900/10">
        <Eraser className="h-3.5 w-3.5" /> Clear
      </button>
    </div>
  );
}
