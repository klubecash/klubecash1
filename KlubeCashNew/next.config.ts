import type { NextConfig } from "next";

const phpBackendUrl = (process.env.PHP_BACKEND_URL ?? "http://127.0.0.1:8000").replace(/\/$/, "");
const storeUiMode = (process.env.STORE_UI_MODE ?? "next").toLowerCase();
const legacyStoreRoutes = (process.env.STORE_LEGACY_ROUTES ?? "")
  .split(",")
  .map((route) => route.trim().replace(/^\/+|\/+$/g, ""))
  .filter(Boolean);
const adminUiMode = (process.env.ADMIN_UI_MODE ?? "next").toLowerCase();
const isVercelServices = process.env.VERCEL === "1";
const legacyAdminRoutes = (process.env.ADMIN_LEGACY_ROUTES ?? "")
  .split(",")
  .map((route) => route.trim().replace(/^\/+|\/+$/g, ""))
  .filter(Boolean);

const nextConfig: NextConfig = {
  agentRules: false,
  turbopack: {
    root: process.cwd(),
  },
  images: {
    unoptimized: true,
  },
  async headers() {
    const recoveryHeaders = [
      { key: "Cache-Control", value: "private, no-store, no-cache, must-revalidate, max-age=0" },
      { key: "Pragma", value: "no-cache" },
      { key: "Referrer-Policy", value: "no-referrer" },
      { key: "X-Robots-Tag", value: "noindex, nofollow, noarchive" },
    ];

    return [
      { source: "/recuperar-senha", headers: recoveryHeaders },
      { source: "/api/auth/recovery", headers: recoveryHeaders },
      { source: "/api/auth/recovery/:path*", headers: recoveryHeaders },
    ];
  },
  async redirects() {
    const storeRedirects = storeUiMode === "legacy" ? [] : [
      {
        source: "/store/transacoes-pendentes",
        destination: "/store/dashboard?notice=commission-flow-retired",
        permanent: false,
      },
      {
        source: "/store/pagamento",
        destination: "/store/dashboard?notice=commission-flow-retired",
        permanent: false,
      },
      {
        source: "/store/pagamento-pix",
        destination: "/store/dashboard?notice=pix-not-available",
        permanent: false,
      },
      {
        source: "/store/historico-pagamentos",
        destination: "/store/dashboard?notice=financial-history-retired",
        permanent: false,
      },
      {
        source: "/store/fatura-pix",
        destination: "/store/meu-plano?notice=pix-not-available",
        permanent: false,
      },
    ].filter(({ source }) =>
      !legacyStoreRoutes.includes(source.replace(/^\/store\//, "")),
    );
    const adminRedirects = adminUiMode === "legacy" ? [] : [
      { source: "/admin/saldo", destination: "/admin/financeiro?tab=balance", permanent: false },
      { source: "/admin/pagamentos", destination: "/admin/financeiro", permanent: false },
      { source: "/admin/comissoes", destination: "/admin/financeiro?tab=commission", permanent: false },
      { source: "/admin/cashback-config", destination: "/admin/configuracoes", permanent: false },
      { source: "/admin/store-subscription", destination: "/admin/assinaturas", permanent: false },
    ].filter(({ source }) => !legacyAdminRoutes.includes(source.replace(/^\/admin\//, "")));
    return [...storeRedirects, ...adminRedirects];
  },
  async rewrites() {
    const storeRollbacks = storeUiMode === "legacy"
      ? [{ source: "/store/:path*", destination: `${phpBackendUrl}/store/:path*` }]
      : legacyStoreRoutes.map((route) => ({
          source: `/store/${route}`,
          destination: `${phpBackendUrl}/store/${route}`,
        }));
    const adminRollbacks = adminUiMode === "legacy"
      ? [{ source: "/admin/:path*", destination: `${phpBackendUrl}/admin/:path*` }]
      : legacyAdminRoutes.map((route) => ({ source: `/admin/${route}`, destination: `${phpBackendUrl}/admin/${route}` }));

    return {
      beforeFiles: [...storeRollbacks, ...adminRollbacks],
      afterFiles: [],
      fallback: isVercelServices
        ? []
        : [
            {
              source: "/:path*",
              destination: `${phpBackendUrl}/:path*`,
            },
          ],
    };
  },
};

export default nextConfig;
