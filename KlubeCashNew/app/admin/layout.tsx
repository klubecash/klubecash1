import { redirect } from "next/navigation";
import { AdminApiError, getAdminContext } from "@/lib/admin-server";
import { AdminProviders } from "@/components/admin/AdminProviders";
import { AdminShell } from "@/components/admin/AdminShell";
import "./admin.css";

export const dynamic = "force-dynamic";

export default async function AdminLayout({ children }: { children: React.ReactNode }) {
  let context;
  try {
    context = await getAdminContext();
  } catch (error) {
    if (error instanceof AdminApiError && error.status === 401) redirect("/login?redirect=%2Fadmin%2Fdashboard");
    if (error instanceof AdminApiError && error.status === 403) redirect("/?error=admin-access-denied");
    throw error;
  }
  return <AdminProviders context={context}><AdminShell>{children}</AdminShell></AdminProviders>;
}
