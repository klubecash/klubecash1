"use client";

import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { createContext, useContext, useEffect, useState } from "react";
import type { StoreContext } from "@/types/store";

const Context = createContext<StoreContext | null>(null);
type ViewState = { branch: "network" | number; sellerId: string; startDate: string; endDate: string; status: string };
type ViewContext = ViewState & { setView: (change: Partial<ViewState>) => void; query: (includeStatus?: boolean) => string };
const View = createContext<ViewContext | null>(null);

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
  const [view, setViewState] = useState<ViewState>({ branch: context.store.id, sellerId: "", startDate: "", endDate: "", status: "" });
  const storageKey = `klubecash-view-${context.user.id ?? 0}-${context.networkId ?? context.store.id}`;
  useEffect(() => {
    const frame = requestAnimationFrame(() => {
      const stored = sessionStorage.getItem(storageKey);
      if (!stored) return;
      try {
        const candidate = JSON.parse(stored) as ViewState;
        const validBranch = candidate.branch === "network" ? context.canViewNetwork :
          candidate.branch === context.store.id || (context.canViewNetwork && context.stores?.some((store) => store.id === candidate.branch && store.networkId === context.networkId));
        if (validBranch) setViewState(candidate);
      } catch { /* A broken preference must not prevent access to the store. */ }
    });
    return () => cancelAnimationFrame(frame);
  }, [storageKey, context.canViewNetwork, context.stores, context.store.id, context.networkId]);
  const setView = (change: Partial<ViewState>) => {
    setViewState((current) => {
      const next = { ...current, ...change };
      sessionStorage.setItem(storageKey, JSON.stringify(next));
      return next;
    });
  };
  const query = (includeStatus = false) => {
    const params = new URLSearchParams();
    if (context.canViewNetwork && view.branch === "network") params.set("scope", "network");
    else if (context.canViewNetwork && view.branch !== context.store.id) {
      params.set("scope", "network"); params.set("storeId", String(view.branch));
    }
    if (view.sellerId) params.set("sellerId", view.sellerId);
    if (view.startDate) params.set("startDate", view.startDate);
    if (view.endDate) params.set("endDate", view.endDate);
    if (includeStatus && view.status) params.set("status", view.status);
    return params.toString() ? `&${params.toString()}` : "";
  };
  return (
    <QueryClientProvider client={queryClient}>
      <Context.Provider value={context}><View.Provider value={{ ...view, setView, query }}>{children}</View.Provider></Context.Provider>
    </QueryClientProvider>
  );
}

export function useStoreView() {
  const value = useContext(View);
  if (!value) throw new Error("Visão da loja indisponível.");
  return value;
}

export function useStoreContext() {
  const value = useContext(Context);
  if (!value) throw new Error("StoreContext indisponível.");
  return value;
}
