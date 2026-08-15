import { NextRequest, NextResponse } from "next/server";

export const runtime = "nodejs";
export const dynamic = "force-dynamic";

const phpBackendUrl = (process.env.PHP_BACKEND_URL ?? "http://127.0.0.1:8000").replace(/\/$/, "");

type RecoveryResponse = {
  status: boolean;
  message: string;
  redirect?: string;
};

function getSetCookieHeaders(headers: Headers): string[] {
  const enhancedHeaders = headers as Headers & { getSetCookie?: () => string[] };
  if (typeof enhancedHeaders.getSetCookie === "function") return enhancedHeaders.getSetCookie();
  const cookie = headers.get("set-cookie");
  return cookie ? [cookie] : [];
}

function decodeHtml(value: string) {
  const entities: Record<string, string> = {
    "&amp;": "&",
    "&lt;": "<",
    "&gt;": ">",
    "&quot;": '"',
    "&#039;": "'",
    "&nbsp;": " ",
  };
  return value
    .replace(/<br\s*\/?\s*>/gi, "\n")
    .replace(/<[^>]+>/g, "")
    .replace(/&(amp|lt|gt|quot|#039|nbsp);/g, (entity) => entities[entity] ?? entity)
    .replace(/^[⚠️✅ℹ️\s]+/u, "")
    .replace(/\s*\n\s*/g, "\n")
    .trim();
}

function extractBackendError(html: string) {
  const match = html.match(/<div[^>]*class=["'][^"']*alert-error[^"']*server-feedback[^"']*["'][^>]*>([\s\S]*?)<\/div>/i);
  return match ? decodeHtml(match[1]) : null;
}

function jsonResponse(payload: RecoveryResponse, status: number, cookies: string[] = []) {
  const response = NextResponse.json(payload, {
    status,
    headers: { "Cache-Control": "private, no-store, no-cache, must-revalidate, max-age=0" },
  });
  cookies.forEach((cookie) => response.headers.append("set-cookie", cookie));
  return response;
}

export async function POST(request: NextRequest) {
  const contentType = request.headers.get("content-type") ?? "";
  if (!contentType.includes("application/x-www-form-urlencoded")) {
    return jsonResponse({ status: false, message: "Formato de formulário inválido." }, 415);
  }

  const form = new URLSearchParams(await request.text());
  const action = form.get("action");
  const csrfToken = form.get("csrf_token") ?? "";
  if ((action !== "request" && action !== "reset") || !csrfToken) {
    return jsonResponse({ status: false, message: "Solicitação inválida." }, 400);
  }

  const phpUrl = new URL("/recuperar-senha", `${phpBackendUrl}/`);
  const outgoingForm = new URLSearchParams({ action, csrf_token: csrfToken });

  if (action === "request") {
    outgoingForm.set("email", (form.get("email") ?? "").trim());
  } else {
    const token = form.get("token") ?? "";
    phpUrl.searchParams.set("token", token);
    outgoingForm.set("token", token);
    outgoingForm.set("password", form.get("password") ?? "");
    outgoingForm.set("confirm_password", form.get("confirm_password") ?? "");
  }

  try {
    const backendResponse = await fetch(phpUrl, {
      method: "POST",
      cache: "no-store",
      redirect: "manual",
      headers: {
        Accept: "text/html,application/xhtml+xml",
        "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
        ...(request.headers.get("cookie") ? { cookie: request.headers.get("cookie") as string } : {}),
      },
      body: outgoingForm.toString(),
      signal: AbortSignal.timeout(30_000),
    });

    const cookies = getSetCookieHeaders(backendResponse.headers);
    const location = backendResponse.headers.get("location");
    if (backendResponse.status >= 300 && backendResponse.status < 400 && location) {
      const redirectUrl = new URL(location, `${phpBackendUrl}/`);
      const redirectPath = `${redirectUrl.pathname}${redirectUrl.search}${redirectUrl.hash}`;
      const resetMessage = redirectUrl.pathname === "/login"
        ? redirectUrl.searchParams.get("success")
        : null;
      return jsonResponse(
        {
          status: true,
          message: resetMessage
            ?? "Se existir uma conta com este e-mail, enviaremos as instruções. Verifique também as pastas Spam e Promoções.",
          redirect: redirectPath,
        },
        200,
        cookies,
      );
    }

    const html = await backendResponse.text();
    const error = extractBackendError(html)
      ?? (backendResponse.status === 403 ? "Sua sessão expirou. Atualize a página e tente novamente." : null)
      ?? "Não foi possível concluir a solicitação. Tente novamente.";
    return jsonResponse({ status: false, message: error }, backendResponse.ok ? 422 : backendResponse.status, cookies);
  } catch (error) {
    console.error("Falha ao encaminhar recuperação de senha para o backend PHP.", error);
    return jsonResponse({ status: false, message: "Erro de comunicação. Tente novamente." }, 502);
  }
}
