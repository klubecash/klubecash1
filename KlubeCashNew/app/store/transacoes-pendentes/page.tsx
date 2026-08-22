import { redirect } from "next/navigation";

export default function RetiredPendingTransactionsPage() {
  redirect("/store/dashboard?notice=commission-flow-retired");
}
