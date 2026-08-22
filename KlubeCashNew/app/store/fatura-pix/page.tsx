import { redirect } from "next/navigation";
export default function InvoicePixPage() {
  redirect("/store/meu-plano?notice=pix-not-available");
}
