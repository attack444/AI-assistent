import type { NextConfig } from "next";

const apiUrl = process.env.API_INTERNAL_URL || "http://127.0.0.1:8502";
// HTTPS панели без отдельного DNS: https://neobrain.site/console
const basePath = (process.env.PANEL_BASE_PATH || "").trim().replace(/\/$/, "");

const nextConfig: NextConfig = {
  output: "standalone",
  // Совпадает с nginx: /console → /console/ (без редирект-петли)
  ...(basePath
    ? {
        basePath,
        trailingSlash: true,
      }
    : {}),
  async rewrites() {
    // basePath: false — иначе rewrite станет /console/api/*, а клиент бьёт в /api/
    return [
      {
        source: "/api/:path*",
        destination: `${apiUrl}/:path*`,
        basePath: false,
      },
    ];
  },
};

export default nextConfig;
