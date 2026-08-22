import type { ApiResponse } from "@/types/admin";

export class AdminClientError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly requestId?: string,
    public readonly errors?: Record<string, string[]>,
  ) {
    super(message);
  }
}

export async function adminFetch<T>(resource: string, init?: RequestInit): Promise<T> {
  const response = await fetch(`/api/admin/${resource.replace(/^\/+/, "")}`, {
    cache: "no-store",
    ...init,
    headers: {
      Accept: "application/json",
      ...(init?.body ? { "Content-Type": "application/json" } : {}),
      ...init?.headers,
    },
  });
  let payload: ApiResponse<T>;
  try {
    payload = (await response.json()) as ApiResponse<T>;
  } catch {
    throw new AdminClientError("O servidor retornou uma resposta inválida.", response.status);
  }
  if (response.status === 401 && typeof window !== "undefined") {
    const redirect = encodeURIComponent(`${window.location.pathname}${window.location.search}`);
    window.location.replace(`/login?redirect=${redirect}&reason=session-expired`);
  }
  if (!response.ok || payload.status !== "success") {
    throw new AdminClientError(payload.message ?? "Não foi possível concluir a solicitação.", response.status, payload.requestId, payload.errors);
  }
  return payload.data as T;
}

export function mutationHeaders(csrfToken: string, idempotent = false): HeadersInit {
  return {
    "x-csrf-token": csrfToken,
    ...(idempotent ? { "x-idempotency-key": crypto.randomUUID() } : {}),
  };
}
