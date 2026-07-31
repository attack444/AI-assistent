import type { NextConfig } from "next";

const apiUrl = process.env.API_INTERNAL_URL || "http://127.0.0.1:8502";

const nextConfig: NextConfig = {
  output: "standalone",
  async rewrites() {
    return [
      {
        source: "/api/:path*",
        destination: `${apiUrl}/:path*`,
      },
    ];
  },
};

export default nextConfig;
