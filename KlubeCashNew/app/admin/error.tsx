"use client";
import { ErrorState } from "@/components/admin/AdminStates";
export default function AdminError({ error, reset }: { error: Error & { digest?: string }; reset: () => void }) { return <div className="admin-page"><ErrorState error={error} retry={reset} /></div>; }
