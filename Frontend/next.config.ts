import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // LAN addresses allowed to open the dev server (testing from a phone).
  allowedDevOrigins: ["192.168.0.146", "192.168.1.25"],
};

export default nextConfig;