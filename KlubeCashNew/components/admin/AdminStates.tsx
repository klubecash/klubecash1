"use client";

import { AlertTriangle, Inbox, LoaderCircle, X } from "lucide-react";
import { useEffect, useRef } from "react";
import type { Pagination } from "@/types/admin";

export function LoadingState({ label = "Carregando dados administrativos..." }: { label?: string }) {
  return <div className="admin-panel admin-empty"><div><LoaderCircle className="spin" size={32} /><h3>{label}</h3></div></div>;
}
export function ErrorState({ error, retry }: { error: unknown; retry?: () => void }) {
  const message = error instanceof Error ? error.message : "Erro inesperado.";
  return <div className="admin-panel admin-empty"><div><AlertTriangle size={34} /><h3>Não foi possível carregar</h3><p>{message}</p>{retry && <button className="admin-button" onClick={retry}>Tentar novamente</button>}</div></div>;
}
export function EmptyState({ title = "Nenhum registro encontrado", message = "Ajuste os filtros ou volte mais tarde." }: { title?: string; message?: string }) {
  return <div className="admin-empty"><div><Inbox size={34} /><h3>{title}</h3><p>{message}</p></div></div>;
}
export function Status({ value }: { value: string }) { return <span className={`admin-status ${value}`}>{value.replaceAll("_", " ")}</span>; }
export function PaginationBar({ value, onPage }: { value: Pagination; onPage: (page: number) => void }) {
  return <div className="admin-pagination"><span>{value.totalItems} registros · página {value.page} de {value.totalPages}</span><button disabled={value.page <= 1} onClick={() => onPage(value.page - 1)}>‹</button><button disabled={value.page >= value.totalPages} onClick={() => onPage(value.page + 1)}>›</button></div>;
}
export function Modal({ title, subtitle, onClose, children }: { title: string; subtitle?: string; onClose: () => void; children: React.ReactNode }) {
  const dialog = useRef<HTMLDivElement>(null);
  const onCloseRef = useRef(onClose);

  useEffect(() => {
    onCloseRef.current = onClose;
  }, [onClose]);

  useEffect(() => {
    const previousFocus = document.activeElement as HTMLElement | null;
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    const focusable = () => Array.from(dialog.current?.querySelectorAll<HTMLElement>(
      'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])',
    ) ?? []);
    (focusable()[0] ?? dialog.current)?.focus();

    const handleKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        event.preventDefault();
        onCloseRef.current();
        return;
      }
      if (event.key !== "Tab") return;
      const items = focusable();
      if (items.length === 0) {
        event.preventDefault();
        dialog.current?.focus();
        return;
      }
      const first = items[0];
      const last = items[items.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    };

    document.addEventListener("keydown", handleKey);
    return () => {
      document.removeEventListener("keydown", handleKey);
      document.body.style.overflow = previousOverflow;
      previousFocus?.focus();
    };
  }, []);

  return (
    <div
      className="admin-modal-backdrop"
      role="presentation"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) onClose();
      }}
    >
      <div
        ref={dialog}
        className="admin-modal"
        role="dialog"
        aria-modal="true"
        aria-label={title}
        tabIndex={-1}
      >
        <div className="admin-modal-head">
          <div>
            <h3>{title}</h3>
            {subtitle && <p>{subtitle}</p>}
          </div>
          <button className="admin-modal-close" onClick={onClose} aria-label="Fechar">
            <X size={18} />
          </button>
        </div>
        {children}
      </div>
    </div>
  );
}
