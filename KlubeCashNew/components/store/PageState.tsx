import { AlertTriangle, Inbox, LoaderCircle } from "lucide-react";

export function LoadingState({
  label = "Carregando informações...",
}: {
  label?: string;
}) {
  return (
    <div className="store-empty">
      <LoaderCircle size={34} className="spin" />
      <h3>{label}</h3>
    </div>
  );
}

export function ErrorState({
  message,
  retry,
}: {
  message: string;
  retry?: () => void;
}) {
  return (
    <div className="store-empty">
      <AlertTriangle size={36} />
      <h3>Não foi possível carregar</h3>
      <p>{message}</p>
      {retry && (
        <button className="store-button" onClick={retry}>
          Tentar novamente
        </button>
      )}
    </div>
  );
}

export function EmptyState({
  title,
  message,
}: {
  title: string;
  message: string;
}) {
  return (
    <div className="store-empty">
      <Inbox size={38} />
      <h3>{title}</h3>
      <p>{message}</p>
    </div>
  );
}
