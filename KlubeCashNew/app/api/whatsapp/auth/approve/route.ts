import { NextRequest, NextResponse } from "next/server";

export const runtime = "nodejs";
export const dynamic = "force-dynamic";

const phpBackendUrl = (process.env.PHP_BACKEND_URL ?? "http://127.0.0.1:8000").replace(/\/$/, "");

function setCookies(source: Headers, target: NextResponse) {
  const enhanced = source as Headers & { getSetCookie?: () => string[] };
  const values = enhanced.getSetCookie?.() ?? (source.get("set-cookie") ? [source.get("set-cookie") as string] : []);
  values.forEach((cookie) => target.headers.append("set-cookie", cookie));
}

async function proxy(request: NextRequest) {
  const backendUrl = new URL("/api/v2/whatsapp/auth/approve", `${phpBackendUrl}/`);
  if (request.method === "GET") {
    const token = request.nextUrl.searchParams.get("token");
    if (token) backendUrl.searchParams.set("token", token);
  }
  const headers = new Headers({ accept: "application/json", "x-request-id": request.headers.get("x-request-id") ?? crypto.randomUUID() });
  ["cookie", "content-type", "x-csrf-token"].forEach((name) => {
    const value = request.headers.get(name);
    if (value) headers.set(name, value);
  });

  try {
    const backend = await fetch(backendUrl, {
      method: request.method,
      headers,
      body: request.method === "POST" ? await request.arrayBuffer() : undefined,
      cache: "no-store",
      redirect: "manual",
      signal: AbortSignal.timeout(20_000),
    });
    const response = new NextResponse(backend.body, {
      status: backend.status,
      headers: {
        "content-type": backend.headers.get("content-type") ?? "application/json; charset=UTF-8",
        "cache-control": "private, no-store, no-cache, must-revalidate, max-age=0",
      },
    });
    setCookies(backend.headers, response);
    return response;
  } catch {
    return NextResponse.json(
      { status: "error", message: "Nao foi possivel comunicar com o servidor. Tente novamente.", requestId: headers.get("x-request-id") },
      { status: 504 },
    );
  }
}

export const GET = proxy;
export const POST = proxy;
