import type { Metadata } from "next";
import { redirect } from "next/navigation";
import RecoveryExperience from "@/components/RecoveryExperience";
import { getHomeContext } from "@/lib/home-context";

export const dynamic = "force-dynamic";

export const metadata: Metadata = {
  title: "Recuperar Senha - Klube Cash",
  description: "Recupere com segurança o acesso à sua conta Klube Cash.",
  robots: { index: false, follow: false, noarchive: true },
};

type SearchParams = Record<string, string | string[] | undefined>;

function firstValue(value: string | string[] | undefined) {
  return Array.isArray(value) ? value[0] : value;
}

export default async function RecoveryPage({
  searchParams,
}: {
  searchParams: Promise<SearchParams>;
}) {
  const params = await searchParams;
  const context = await getHomeContext();
  if (context.authenticated && context.user) redirect(context.user.dashboardUrl);

  return (
    <RecoveryExperience
      token={firstValue(params.token) ?? null}
      requestSent={firstValue(params.enviado) === "1"}
    />
  );
}
