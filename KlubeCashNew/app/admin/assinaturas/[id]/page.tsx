import { notFound } from "next/navigation";
import { SubscriptionDetailsPage } from "@/components/admin/AdminPages";
export default async function Page({ params }: { params: Promise<{ id: string }> }) { const { id } = await params; const value = Number(id); if (!Number.isInteger(value) || value <= 0) notFound(); return <SubscriptionDetailsPage id={value} />; }
