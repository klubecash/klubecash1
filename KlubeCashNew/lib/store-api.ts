import { cache } from "react";
import { cookies } from "next/headers";
import type { ApiResponse, StoreContext } from "@/types/store";

const phpBackendUrl = (
  process.env.PHP_BACKEND_URL ?? "http://127.0.0.1:8000"
).replace(/\/$/, "");

export class StoreApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly requestId?: string,
  ) {
    super(message);
  }
}

export const getStoreContext = cache(async (): Promise<StoreContext> => {
  const cookieStore = await cookies();
  const cookie = cookieStore.toString();
  const response = await fetch(
    `${phpBackendUrl}/api/v2/store/context`,
    {
      cache: "no-store",
      headers: cookie ? { cookie } : undefined,
      signal: AbortSignal.timeout(15_000),
    },
  );
  let payload: ApiResponse<StoreContext> | null = null;
  try {
    payload = (await response.json()) as ApiResponse<StoreContext>;
  } catch {
    throw new StoreApiError(
      "O backend retornou uma resposta inválida.",
      response.status,
    );
  }
  if (!response.ok || payload.status !== "success" || !payload.data) {
    throw new StoreApiError(
      payload.message ?? "Não foi possível carregar a loja.",
      response.status,
      payload.requestId,
    );
  }
  return payload.data;
});
