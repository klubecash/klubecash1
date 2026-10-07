import type { Metadata } from "next";
import { redirect } from "next/navigation";
import RegisterExperience from "@/components/RegisterExperience";
import { getHomeContext } from "@/lib/home-context";

export const dynamic = "force-dynamic";

export const metadata: Metadata = {
  title: "Criar Conta - KlubeCash",
  description: "Crie sua conta gratuita e comece a ganhar giftback com a KlubeCash.",
};

type SearchParams = Record<string, string | string[] | undefined>;

function firstValue(value: string | string[] | undefined) {
  return Array.isArray(value) ? value[0] : value;
}

export default async function RegisterPage({
  searchParams,
}: {
  searchParams: Promise<SearchParams>;
}) {
  const params = await searchParams;
  const context = await getHomeContext();

  if (context.authenticated && context.user) {
    redirect(context.user.dashboardUrl);
  }

  return (
    <RegisterExperience
      initialError={firstValue(params.error) ?? null}
      initialSuccess={firstValue(params.success) ?? null}
    />
  );
}
