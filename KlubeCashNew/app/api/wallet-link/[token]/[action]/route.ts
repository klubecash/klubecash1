import { NextRequest, NextResponse } from "next/server";

export const runtime = "nodejs";
export const dynamic = "force-dynamic";

const backend = (process.env.PHP_BACKEND_URL ?? "http://127.0.0.1:8000").replace(/\/$/, "");

async function proxy(request: NextRequest, context: { params: Promise<{ token: string; action: string }> }) {
  const { token, action } = await context.params;
  if (!/^[a-f0-9]{48}$/.test(token) || !["context", "wallet", "login", "request-code", "verify-code", "signup", "claim"].includes(action)) {
    return NextResponse.json({ status: "error", message: "Link não encontrado." }, { status: 404 });
  }
  const url = new URL("/api/v2/wallet-link", `${backend}/`);
  url.searchParams.set("token", token);
  url.searchParams.set("action", action);
  const headers = new Headers();
  for (const key of ["cookie", "content-type", "x-csrf-token", "accept"]) {
    const value = request.headers.get(key);
    if (value) headers.set(key, value);
  }
  try {
    const response = await fetch(url, {
      method: request.method,
      headers,
      body: request.method === "GET" ? undefined : await request.arrayBuffer(),
      cache: "no-store",
      redirect: "manual",
      signal: AbortSignal.timeout(30_000),
    });
    const result = new NextResponse(response.body, {
      status: response.status,
      headers: { "content-type": "application/json; charset=UTF-8", "cache-control": "private, no-store, no-cache, must-revalidate, max-age=0" },
    });
    const cookies = (response.headers as Headers & { getSetCookie?: () => string[] }).getSetCookie?.() ?? (response.headers.get("set-cookie") ? [response.headers.get("set-cookie") as string] : []);
    for (const cookie of cookies) result.headers.append("set-cookie", cookie);
    return result;
  } catch {
    return NextResponse.json({ status: "error", message: "Consulta indisponível. Tente novamente." }, { status: 503, headers: { "cache-control": "no-store" } });
  }
}

export const GET = proxy;
export const POST = proxy;
