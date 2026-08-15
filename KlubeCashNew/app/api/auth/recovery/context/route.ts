import { NextRequest, NextResponse } from "next/server";

export const runtime = "nodejs";
export const dynamic = "force-dynamic";

const phpBackendUrl = (process.env.PHP_BACKEND_URL ?? "http://127.0.0.1:8000").replace(/\/$/, "");

type RecoveryContext = {
  csrfToken: string;
  validToken: boolean;
  maskedEmail: string | null;
  expirationHours: number;
  error: string | null;
  success: string | null;
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
    .replace(/\s+/g, " ")
    .trim();
}

function extract(html: string, pattern: RegExp) {
  const match = html.match(pattern);
  return match ? decodeHtml(match[1]) : null;
}

export async function GET(request: NextRequest) {
  const phpUrl = new URL("/recuperar-senha", `${phpBackendUrl}/`);
  const token = request.nextUrl.searchParams.get("token");
  const requestSent = request.nextUrl.searchParams.get("enviado");
  if (token) phpUrl.searchParams.set("token", token);
  if (requestSent === "1") phpUrl.searchParams.set("enviado", "1");

  try {
    const backendResponse = await fetch(phpUrl, {
      cache: "no-store",
      redirect: "manual",
      headers: {
        Accept: "text/html,application/xhtml+xml",
        ...(request.headers.get("cookie") ? { cookie: request.headers.get("cookie") as string } : {}),
      },
      signal: AbortSignal.timeout(20_000),
    });

    if (!backendResponse.ok) {
      return NextResponse.json(
        { message: "Não foi possível preparar a recuperação de senha." },
        { status: 502, headers: { "Cache-Control": "private, no-store" } },
      );
    }

    const html = await backendResponse.text();
    const csrfToken = html.match(/name=["']csrf_token["'][^>]*value=["']([a-f0-9]{64})["']/i)?.[1] ?? "";
    if (!csrfToken) {
      return NextResponse.json(
        { message: "Não foi possível iniciar a sessão segura de recuperação." },
        { status: 502, headers: { "Cache-Control": "private, no-store" } },
      );
    }

    const expirationMatch = html.match(/expira em\s+(\d+)\s+horas?/i);
    const payload: RecoveryContext = {
      csrfToken,
      validToken: html.includes('id="reset-form"'),
      maskedEmail: extract(html, /<div[^>]*class=["']user-email["'][^>]*>([\s\S]*?)<\/div>/i),
      expirationHours: expirationMatch ? Math.max(1, Number.parseInt(expirationMatch[1], 10)) : 2,
      error: extract(html, /<div[^>]*class=["'][^"']*alert-error[^"']*server-feedback[^"']*["'][^>]*>([\s\S]*?)<\/div>/i),
      success: extract(html, /<div[^>]*class=["'][^"']*alert-success[^"']*server-feedback[^"']*["'][^>]*>([\s\S]*?)<\/div>/i),
    };

    const response = NextResponse.json(payload, {
      headers: { "Cache-Control": "private, no-store, no-cache, must-revalidate, max-age=0" },
    });
    getSetCookieHeaders(backendResponse.headers).forEach((cookie) => response.headers.append("set-cookie", cookie));
    return response;
  } catch (error) {
    console.error("Falha ao carregar o contexto de recuperação no backend PHP.", error);
    return NextResponse.json(
      { message: "Erro de comunicação. Tente novamente." },
      { status: 502, headers: { "Cache-Control": "private, no-store" } },
    );
  }
}
