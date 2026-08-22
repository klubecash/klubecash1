import { redirect } from "next/navigation";

export default function RetiredPaymentHistoryPage() {
  redirect("/store/dashboard?notice=financial-history-retired");
}
