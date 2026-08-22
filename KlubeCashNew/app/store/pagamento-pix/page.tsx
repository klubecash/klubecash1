import { redirect } from "next/navigation";

export default function RetiredPixPage() {
  redirect("/store/dashboard?notice=pix-not-available");
}
