import { redirect } from "next/navigation";

export default function RetiredPaymentPage() {
  redirect("/store/dashboard?notice=commission-flow-retired");
}
