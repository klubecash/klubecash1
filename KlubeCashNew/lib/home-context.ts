import { cache } from "react";
import { headers } from "next/headers";
import type { HomeContext } from "@/types/home";

const phpBackendUrl = (process.env.PHP_BACKEND_URL ?? "http://127.0.0.1:8000").replace(/\/$/, "");

export const anonymousHomeContext = (): HomeContext => ({
  authenticated: false,
  user: null,
  partnerStores: [],
  links: {
    login: "/login",
    register: "/registro",
    storeRegister: "/lojas/cadastro",
    logout: "/logout",
  },
  currentYear: new Date().getFullYear(),
});

function isHomeContext(value: unknown): value is HomeContext {
  if (!value || typeof value !== "object") {
    return false;
  }

  const candidate = value as Partial<HomeContext>;
  return (
    typeof candidate.authenticated === "boolean" &&
    Array.isArray(candidate.partnerStores) &&
    Boolean(candidate.links) &&
    typeof candidate.currentYear === "number"
  );
}

export const getHomeContext = cache(async (): Promise<HomeContext> => {
  const requestHeaders = await headers();
  const cookie = requestHeaders.get("cookie") ?? "";

  try {
    const response = await fetch(`${phpBackendUrl}/api/homepage-context`, {
      cache: "no-store",
      headers: cookie ? { cookie } : undefined,
      signal: AbortSignal.timeout(10_000),
    });

    if (!response.ok) {
      throw new Error(`Homepage context respondeu com HTTP ${response.status}.`);
    }

    const context: unknown = await response.json();
    if (!isHomeContext(context)) {
      throw new Error("Homepage context retornou um contrato inválido.");
    }

    return context;
  } catch (error) {
    console.error("Não foi possível carregar o contexto PHP da homepage.", error);
    return anonymousHomeContext();
  }
});
