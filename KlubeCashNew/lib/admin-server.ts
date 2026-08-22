import { cache } from "react";
import { cookies } from "next/headers";
import type { AdminContext, ApiResponse } from "@/types/admin";

const backend = (process.env.PHP_BACKEND_URL ?? "http://127.0.0.1:8000").replace(/\/$/, "");

export class AdminApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly requestId?: string,
  ) {
    super(message);
  }
}

export const getAdminContext = cache(async (): Promise<AdminContext> => {
  const cookieStore = await cookies();
  const response = await fetch(`${backend}/api/v2/admin/context`, {
    cache: "no-store",
    headers: cookieStore.size ? { cookie: cookieStore.toString() } : undefined,
    signal: AbortSignal.timeout(15_000),
  });
  let payload: ApiResponse<AdminContext>;
  try {
    payload = (await response.json()) as ApiResponse<AdminContext>;
  } catch {
    throw new AdminApiError("O backend retornou uma resposta inválida.", response.status);
  }
  if (!response.ok || payload.status !== "success" || !payload.data) {
    throw new AdminApiError(payload.message ?? "Não foi possível carregar o Admin.", response.status, payload.requestId);
  }
  return payload.data;
});
