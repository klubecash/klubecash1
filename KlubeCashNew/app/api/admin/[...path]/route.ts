import { NextRequest, NextResponse } from "next/server";

export const runtime = "nodejs";
export const dynamic = "force-dynamic";

const backend = (process.env.PHP_BACKEND_URL ?? "http://127.0.0.1:8000").replace(/\/$/, "");

function setCookies(headers: Headers): string[] {
  const enhanced = headers as Headers & { getSetCookie?: () => string[] };
  return enhanced.getSetCookie?.() ?? (headers.get("set-cookie") ? [headers.get("set-cookie") as string] : []);
}

async function proxy(request: NextRequest, context: { params: Promise<{ path: string[] }> }) {
  const { path } = await context.params;
  if (!path.length || !path.every((segment) => /^[a-z0-9_-]+$/i.test(segment))) {
    return NextResponse.json({ status: "error", message: "Rota não permitida.", requestId: crypto.randomUUID(), generatedAt: new Date().toISOString() }, { status: 404 });
  }
  const url = new URL(`/api/v2/admin/${path.join("/")}`, `${backend}/`);
  request.nextUrl.searchParams.forEach((value, key) => url.searchParams.append(key, value));
  const headers = new Headers();
  ["accept", "content-type", "cookie", "x-csrf-token", "x-idempotency-key", "x-request-id"].forEach((key) => {
    const value = request.headers.get(key);
    if (value) headers.set(key, value);
  });
  headers.set("x-request-id", headers.get("x-request-id") ?? crypto.randomUUID());
  try {
    const response = await fetch(url, {
      method: request.method,
      headers,
      body: ["GET", "HEAD"].includes(request.method) ? undefined : await request.arrayBuffer(),
      cache: "no-store",
      redirect: "manual",
      signal: AbortSignal.timeout(35_000),
    });
    const result = new NextResponse(response.body, {
      status: response.status,
      headers: {
        "content-type": response.headers.get("content-type") ?? "application/json; charset=UTF-8",
        "cache-control": "private, no-store, no-cache, must-revalidate, max-age=0",
        ...(response.headers.get("content-disposition") ? { "content-disposition": response.headers.get("content-disposition") as string } : {}),
        ...(response.headers.get("x-request-id") ? { "x-request-id": response.headers.get("x-request-id") as string } : {}),
      },
    });
    setCookies(response.headers).forEach((cookie) => result.headers.append("set-cookie", cookie));
    return result;
  } catch {
    return NextResponse.json({ status: "error", message: "O servidor PHP demorou para responder.", requestId: headers.get("x-request-id"), generatedAt: new Date().toISOString() }, { status: 504 });
  }
}

export const GET = proxy;
export const POST = proxy;
export const PATCH = proxy;
export const DELETE = proxy;
