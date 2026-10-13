import { ReactNode } from "react";
import { choiceLabels } from "./model";

export function StatusTag({ value }: { value: unknown }) {
  const text = String(value ?? "—");
  const color = ["CRITICAL", "OPEN", "FAILED"].includes(text)
    ? "border-red-200 bg-red-50 text-red-800"
    : ["HIGH", "INVESTIGATING", "PROCESSING"].includes(text)
      ? "border-amber-200 bg-amber-50 text-amber-800"
      : ["COMPLETED", "RESOLVED", "ACTIVE"].includes(text)
        ? "border-green-200 bg-green-50 text-green-800"
        : "border-slate-200 bg-slate-50 text-slate-700";

  return <span className={`rounded-full border px-2 py-0.5 text-xs font-bold ${color}`}>{choiceLabels[text] ?? text}</span>;
}

export function Modal({ title, children, close }: { title: string; children: ReactNode; close: () => void }) {
  return <div className="app-modal-overlay" onClick={close}>
    <section role="dialog" aria-modal="true" aria-label={title} className="app-modal" onClick={event => event.stopPropagation()}>
      <div className="app-modal-head"><h2>{title}</h2><button onClick={close} aria-label="Fechar janela">✕</button></div>
      {children}
    </section>
  </div>;
}
