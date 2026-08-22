import type { ApiResponse } from "@/types/store";

export class StoreClientApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly requestId?: string,
  ) {
    super(message);
  }
}

export async function storeFetch<T>(
  resource: string,
  init?: RequestInit,
): Promise<T> {
  const [resourceName, ...queryParts] = resource.split("&");
  const params = new URLSearchParams(queryParts.join("&"));
  const query = params.toString();
  const response = await fetch(
    `/api/store/v2/${resourceName.replace(/^\/+/, "")}${query ? `?${query}` : ""}`,
    {
      cache: "no-store",
      ...init,
      headers: {
        Accept: "application/json",
        ...(init?.body instanceof FormData
          ? {}
          : { "Content-Type": "application/json" }),
        ...init?.headers,
      },
    },
  );
  let payload: ApiResponse<T>;
  try {
    payload = (await response.json()) as ApiResponse<T>;
  } catch {
    throw new StoreClientApiError(
      "O servidor retornou uma resposta inválida.",
      response.status,
    );
  }
  if (response.status === 401 && typeof window !== "undefined") {
    const redirect = encodeURIComponent(
      `${window.location.pathname}${window.location.search}`,
    );
    window.location.assign(
      new URL(
        `/login?redirect=${redirect}&reason=session-expired`,
        window.location.origin,
      ).toString(),
    );
  }
  if (!response.ok || payload.status !== "success") {
    throw new StoreClientApiError(
      payload.message ?? "Não foi possível concluir a solicitação.",
      response.status,
      payload.requestId,
    );
  }
  return payload.data as T;
}
