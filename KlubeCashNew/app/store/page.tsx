import { redirect } from "next/navigation";
import { getStoreContext } from "@/lib/store-api";

export default async function StorePage() {
  const context = await getStoreContext();
  redirect(context.canViewNetwork ? "/store/rede" : "/store/dashboard");
}
