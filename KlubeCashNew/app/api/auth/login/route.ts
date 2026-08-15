import { NextRequest, NextResponse } from "next/server";

export const runtime = "nodejs";
export const dynamic = "force-dynamic";

const phpBackendUrl = (process.env.PHP_BACKEND_URL ?? "http://127.0.0.1:8000").replace(/\/$/, "");

type LoginResponse = {
  status: boolean;
  message?: string;
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

export async function POST(request: NextRequest) {
  const contentType = request.headers.get("content-type") ?? "";
  if (!contentType.includes("application/x-www-form-urlencoded")) {
    return NextResponse.json<LoginResponse>(
      { status: false, message: "Formato de formulário inválido." },
      { status: 415 },
    );
  }

  const incomingForm = new URLSearchParams(await request.text());
  const email = (incomingForm.get("email") ?? "").trim();
  const password = incomingForm.get("password") ?? "";

  if (!email || !password) {
    return NextResponse.json<LoginResponse>(
      { status: false, message: "Por favor, preencha todos os campos." },
      { status: 400 },
    );
  }

  const phpLoginUrl = new URL("/login", `${phpBackendUrl}/`);
  if (request.nextUrl.searchParams.has("force_login")) {
    phpLoginUrl.searchParams.set("force_login", "1");
  }

  try {
    const backendResponse = await fetch(phpLoginUrl, {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
        ...(request.headers.get("cookie") ? { cookie: request.headers.get("cookie") as string } : {}),
      },
      body: new URLSearchParams({ email, password }).toString(),
      cache: "no-store",
      redirect: "manual",
      signal: AbortSignal.timeout(20_000),
    });

    const responseText = await backendResponse.text();
    let payload: LoginResponse;
    try {
      payload = JSON.parse(responseText) as LoginResponse;
    } catch {
      return NextResponse.json<LoginResponse>(
        { status: false, message: "Não foi possível concluir o login. Tente novamente." },
        { status: 502 },
      );
    }

    const response = NextResponse.json(payload, {
      status: backendResponse.status,
      headers: {
        "Cache-Control": "private, no-store, no-cache, must-revalidate, max-age=0",
      },
    });

    getSetCookieHeaders(backendResponse.headers).forEach((cookie) => {
      response.headers.append("set-cookie", cookie);
    });

    return response;
  } catch (error) {
    console.error("Falha ao encaminhar login para o backend PHP.", error);
    return NextResponse.json<LoginResponse>(
      { status: false, message: "Erro de comunicação. Tente novamente." },
      { status: 502 },
    );
  }
}
