import { redirect } from "next/navigation";
import { getStoreContext, StoreApiError } from "@/lib/store-api";
import { StoreProviders } from "@/components/store/StoreProviders";
import { StoreShell } from "@/components/store/StoreShell";
import "./store.css";

export const dynamic = "force-dynamic";

export default async function StoreLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  let context;
  try {
    context = await getStoreContext();
  } catch (error) {
    if (error instanceof StoreApiError && error.status === 401) {
      redirect("/login?redirect=%2Fstore%2Fdashboard");
    }
    throw error;
  }
  return (
    <StoreProviders context={context}>
      <StoreShell>{children}</StoreShell>
    </StoreProviders>
  );
}
