import type { NextConfig } from "next";

const phpBackendUrl = (process.env.PHP_BACKEND_URL ?? "http://127.0.0.1:8000").replace(/\/$/, "");

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
  async rewrites() {
    return {
      beforeFiles: [],
      afterFiles: [],
      fallback: [
        {
          source: "/:path*",
          destination: `${phpBackendUrl}/:path*`,
        },
      ],
    };
  },
};

export default nextConfig;
