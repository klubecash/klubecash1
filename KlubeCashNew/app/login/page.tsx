import type { Metadata } from "next";
import { redirect } from "next/navigation";
import LoginExperience from "@/components/LoginExperience";
import { getHomeContext } from "@/lib/home-context";

export const dynamic = "force-dynamic";

export const metadata: Metadata = {
  title: "Entrar - Klube Cash",
  description: "Entre na sua conta Klube Cash.",
};

type SearchParams = Record<string, string | string[] | undefined>;

function firstValue(value: string | string[] | undefined) {
  return Array.isArray(value) ? value[0] : value;
}

function successMessage(value: string | undefined) {
  if (value === "cadastro_realizado") {
    return "Cadastro realizado com sucesso. Agora você já pode entrar.";
  }
  return value ?? null;
}

export default async function LoginPage({
  searchParams,
}: {
  searchParams: Promise<SearchParams>;
}) {
  const params = await searchParams;
  const forceLogin = firstValue(params.force_login) === "1";
  const context = await getHomeContext();

  if (context.authenticated && context.user && !forceLogin) {
    redirect(context.user.dashboardUrl);
  }

  return (
    <LoginExperience
      initialError={firstValue(params.error) ?? null}
      initialSuccess={successMessage(firstValue(params.success))}
      forceLogin={forceLogin}
    />
  );
}
