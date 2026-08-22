"use client";

import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { createContext, useContext, useState } from "react";
import type { StoreContext } from "@/types/store";

const Context = createContext<StoreContext | null>(null);

export function StoreProviders({
  context,
  children,
}: {
  context: StoreContext;
  children: React.ReactNode;
}) {
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            staleTime: 30_000,
            gcTime: 5 * 60_000,
            retry: 1,
            refetchOnWindowFocus: false,
          },
          mutations: { retry: 0 },
        },
      }),
  );
  return (
    <QueryClientProvider client={queryClient}>
      <Context.Provider value={context}>{children}</Context.Provider>
    </QueryClientProvider>
  );
}

export function useStoreContext() {
  const value = useContext(Context);
  if (!value) throw new Error("StoreContext indisponível.");
  return value;
}
