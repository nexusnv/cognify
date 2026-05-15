import type { NextConfig } from "next";
import { resolve } from "node:path";

const apiUrl = process.env.COGNIFY_API_URL ?? "http://127.0.0.1:8890";

const nextConfig: NextConfig = {
  turbopack: {
    root: resolve(process.cwd(), "../.."),
  },
  async rewrites() {
    return [
      {
        source: "/api/:path*",
        destination: `${apiUrl}/api/:path*`,
      },
      {
        source: "/sanctum/:path*",
        destination: `${apiUrl}/sanctum/:path*`,
      },
    ];
  },
};

export default nextConfig;
