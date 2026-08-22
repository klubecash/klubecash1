import { after, NextRequest, NextResponse } from "next/server";

export const runtime = "nodejs";
export const dynamic = "force-dynamic";
export const maxDuration = 300;

const phpBackendUrl = (
  process.env.PHP_BACKEND_URL ?? "http://127.0.0.1:8000"
).replace(/\/$/, "");
const targets: Record<string, string> = {
  app: "/api/v1/store-app",
  "client-search": "/api/store-client-search",
  payments: "/api/payments",
  mercadopago: "/api/mercadopago",
  details: "/api/store-details",
  receipt: "/api/payment-receipt",
};

function getSetCookies(headers: Headers): string[] {
  const enhanced = headers as Headers & { getSetCookie?: () => string[] };
  return (
    enhanced.getSetCookie?.() ??
    (headers.get("set-cookie") ? [headers.get("set-cookie") as string] : [])
  );
}

function scheduleBatchNotifications() {
  const secret = process.env.CRON_SECRET?.trim();
  if (!secret) return;

  after(async () => {
    try {
      await fetch(`${phpBackendUrl}/api/internal/notifications?limit=10`, {
        method: "GET",
        headers: { authorization: `Bearer ${secret}` },
        cache: "no-store",
        signal: AbortSignal.timeout(240_000),
      });
    } catch {
      // A fila persistente sera tentada novamente pelo cron diario.
    }
  });
}

async function proxy(
  request: NextRequest,
  context: { params: Promise<{ path: string[] }> },
) {
  const { path } = await context.params;
  const requestedPath = path.join("/");
  const target =
    path[0] === "v2" &&
    path.length > 1 &&
    path.slice(1).every((segment) => /^[a-z0-9-]+$/i.test(segment))
      ? `/api/v2/store/${path.slice(1).join("/")}`
      : targets[requestedPath];
  if (!target) {
    return NextResponse.json(
      {
        status: "error",
        message: "Rota não permitida.",
        requestId: crypto.randomUUID(),
      },
      { status: 404 },
    );
  }

  const url = new URL(target, `${phpBackendUrl}/`);
  request.nextUrl.searchParams.forEach((value, key) =>
    url.searchParams.append(key, value),
  );
  const headers = new Headers();
  [
    "accept",
    "content-type",
    "cookie",
    "x-csrf-token",
    "x-idempotency-key",
    "x-request-id",
  ].forEach((key) => {
    const value = request.headers.get(key);
    if (value) headers.set(key, value);
  });
  headers.set(
    "x-request-id",
    headers.get("x-request-id") ?? crypto.randomUUID(),
  );

  try {
    const backend = await fetch(url, {
      method: request.method,
      headers,
      body: ["GET", "HEAD"].includes(request.method)
        ? undefined
        : await request.arrayBuffer(),
      cache: "no-store",
      redirect: "manual",
      signal: AbortSignal.timeout(35_000),
    });
    const response = new NextResponse(backend.body, {
      status: backend.status,
      headers: {
        "content-type":
          backend.headers.get("content-type") ??
          "application/json; charset=UTF-8",
        "cache-control":
          "private, no-store, no-cache, must-revalidate, max-age=0",
        ...(backend.headers.get("x-request-id")
          ? { "x-request-id": backend.headers.get("x-request-id") as string }
          : {}),
      },
    });
    getSetCookies(backend.headers).forEach((cookie) =>
      response.headers.append("set-cookie", cookie),
    );
    if (
      backend.ok &&
      request.method === "POST" &&
      requestedPath === "v2/transactions/batch"
    ) {
      scheduleBatchNotifications();
    }
    return response;
  } catch {
    return NextResponse.json(
      {
        status: "error",
        message: "O servidor PHP demorou para responder. Tente novamente.",
        requestId: headers.get("x-request-id"),
      },
      { status: 504 },
    );
  }
}

export const GET = proxy;
export const POST = proxy;
export const PATCH = proxy;
export const DELETE = proxy;
