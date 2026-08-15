import { NextRequest, NextResponse } from "next/server";

export const runtime = "nodejs";
export const dynamic = "force-dynamic";

const phpBackendUrl = (process.env.PHP_BACKEND_URL ?? "http://127.0.0.1:8000").replace(/\/$/, "");

type RegisterResponse = {
  status: boolean;
  message: string;
  redirect?: string;
};

function getSetCookieHeaders(headers: Headers): string[] {
  const enhancedHeaders = headers as Headers & { getSetCookie?: () => string[] };
  if (typeof enhancedHeaders.getSetCookie === "function") {
    return enhancedHeaders.getSetCookie();
  }

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
    .replace(/\s*\n\s*/g, "\n")
    .trim();
}

function extractBackendError(html: string) {
  const match = html.match(/<div[^>]*class=["'][^"']*alert-error[^"']*["'][^>]*>([\s\S]*?)<\/div>/i);
  return match ? decodeHtml(match[1]) : null;
}

function jsonResponse(payload: RegisterResponse, status: number, cookies: string[] = []) {
  const response = NextResponse.json(payload, {
    status,
    headers: {
      "Cache-Control": "private, no-store, no-cache, must-revalidate, max-age=0",
    },
  });
  cookies.forEach((cookie) => response.headers.append("set-cookie", cookie));
  return response;
}

export async function POST(request: NextRequest) {
  const contentType = request.headers.get("content-type") ?? "";
  if (!contentType.includes("application/x-www-form-urlencoded")) {
    return jsonResponse({ status: false, message: "Formato de formulário inválido." }, 415);
  }

  const incomingForm = new URLSearchParams(await request.text());
  const nome = (incomingForm.get("nome") ?? "").trim();
  const email = (incomingForm.get("email") ?? "").trim();
  const telefone = (incomingForm.get("telefone") ?? "").trim();
  const senha = incomingForm.get("senha") ?? "";

  if (!nome || !email || !telefone || !senha) {
    return jsonResponse({ status: false, message: "Por favor, preencha todos os campos." }, 400);
  }

  try {
    const backendResponse = await fetch(`${phpBackendUrl}/registro`, {
      method: "POST",
      headers: {
        Accept: "text/html,application/xhtml+xml",
        "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
        ...(request.headers.get("cookie") ? { cookie: request.headers.get("cookie") as string } : {}),
      },
      body: new URLSearchParams({ nome, email, telefone, senha }).toString(),
      cache: "no-store",
      redirect: "manual",
      signal: AbortSignal.timeout(25_000),
    });

    const cookies = getSetCookieHeaders(backendResponse.headers);
    const location = backendResponse.headers.get("location");

    if (backendResponse.status >= 300 && backendResponse.status < 400 && location) {
      const redirectUrl = new URL(location, `${phpBackendUrl}/`);
      const redirectPath = `${redirectUrl.pathname}${redirectUrl.search}${redirectUrl.hash}`;
      const registrationCompleted = redirectUrl.pathname === "/login"
        && redirectUrl.searchParams.get("success") === "cadastro_realizado";

      return jsonResponse(
        {
          status: true,
          message: registrationCompleted
            ? "Cadastro realizado com sucesso. Agora você já pode entrar."
            : "Redirecionando...",
          redirect: redirectPath,
        },
        200,
        cookies,
      );
    }

    const html = await backendResponse.text();
    const backendError = extractBackendError(html);
    return jsonResponse(
      {
        status: false,
        message: backendError ?? "Não foi possível concluir o cadastro. Tente novamente.",
      },
      backendResponse.ok ? 422 : 502,
      cookies,
    );
  } catch (error) {
    console.error("Falha ao encaminhar cadastro para o backend PHP.", error);
    return jsonResponse({ status: false, message: "Erro de comunicação. Tente novamente." }, 502);
  }
}
