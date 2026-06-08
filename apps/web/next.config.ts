import type { NextConfig } from "next";
import { resolve } from "node:path";

const apiUrl = process.env.COGNIFY_API_URL ?? "http://127.0.0.1:8890";
const allowedDevOrigins = (process.env.COGNIFY_ALLOWED_DEV_ORIGINS ?? "")
  .split(",")
  .map((origin) => origin.trim())
  .filter(Boolean);

const nextConfig: NextConfig = {
  ...(allowedDevOrigins.length > 0 ? { allowedDevOrigins } : {}),
  turbopack: {
    root: resolve(__dirname, "../.."),
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
