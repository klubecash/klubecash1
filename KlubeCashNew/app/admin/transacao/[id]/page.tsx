import { notFound } from "next/navigation";
import { TransactionDetailsPage } from "@/components/admin/AdminPages";
export default async function Page({ params }: { params: Promise<{ id: string }> }) { const { id } = await params; const value = Number(id); if (!Number.isInteger(value) || value <= 0) notFound(); return <TransactionDetailsPage id={value} />; }
