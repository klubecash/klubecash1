import type { Metadata } from "next";
import { redirect } from "next/navigation";
import { getHomeContext } from "@/lib/home-context";
import WhatsAppAuthorization from "@/components/WhatsAppAuthorization";

export const dynamic = "force-dynamic";

export const metadata: Metadata = {
  title: "Autorizar WhatsApp - Klube Cash",
  description: "Autorize com seguranca o acesso lojista pelo WhatsApp.",
  robots: { index: false, follow: false },
  referrer: "no-referrer",
};

function validToken(value: string | string[] | undefined) {
  const token = Array.isArray(value) ? value[0] : value;
  return token && /^[A-Za-z0-9_-]{32,128}$/.test(token) ? token : null;
}

export default async function WhatsAppAuthPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  const params = await searchParams;
  const token = validToken(params.token);
  if (!token) redirect("/login?error=" + encodeURIComponent("Link de autorizacao invalido."));

  const context = await getHomeContext();
  const returnTo = `/whatsapp/autenticar?token=${encodeURIComponent(token)}`;
  if (!context.authenticated || !context.user) {
    redirect(`/login?returnTo=${encodeURIComponent(returnTo)}`);
  }

  return <WhatsAppAuthorization token={token} />;
}
