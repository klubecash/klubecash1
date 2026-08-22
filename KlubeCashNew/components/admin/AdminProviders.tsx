"use client";

import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { createContext, useContext, useState } from "react";
import type { AdminContext } from "@/types/admin";

const Context = createContext<AdminContext | null>(null);

export function AdminProviders({ context, children }: { context: AdminContext; children: React.ReactNode }) {
  const [client] = useState(() => new QueryClient({
    defaultOptions: {
      queries: { staleTime: 20_000, gcTime: 5 * 60_000, retry: 1, refetchOnWindowFocus: false },
      mutations: { retry: 0 },
    },
  }));
  return <QueryClientProvider client={client}><Context.Provider value={context}>{children}</Context.Provider></QueryClientProvider>;
}

export function useAdminContext() {
  const context = useContext(Context);
  if (!context) throw new Error("AdminContext indisponível.");
  return context;
}
