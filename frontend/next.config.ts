import type { NextConfig } from "next";
import path from "path";

const projectRoot = path.join(__dirname);

const nextConfig: NextConfig = {
  // Build autonome pour le CI/CD mutualisé (cPanel Passenger).
  // Voir xsel-deploy-mutualise ADR-0003.
  output: "standalone",
  // Racine = frontend (évite un lockfile monorepo parent).
  // https://nextjs.org/docs/app/api-reference/config/next-config-js/turbopack#root-directory
  turbopack: {
    root: projectRoot,
  },
  outputFileTracingRoot: projectRoot,
  // Erreurs TS préexistantes (factures/abonnements/…) hors scope PWA.
  // À retirer quand le typage formulaires est assaini.
  typescript: {
    ignoreBuildErrors: true,
  },
  async headers() {
    return [
      {
        source: "/sw.js",
        headers: [
          {
            key: "Cache-Control",
            value: "no-cache, no-store, must-revalidate",
          },
          {
            key: "Service-Worker-Allowed",
            value: "/",
          },
        ],
      },
      {
        source: "/offline.html",
        headers: [
          {
            key: "Cache-Control",
            value: "public, max-age=0, must-revalidate",
          },
        ],
      },
      {
        source: "/manifest.webmanifest",
        headers: [
          {
            key: "Cache-Control",
            value: "public, max-age=0, must-revalidate",
          },
        ],
      },
    ];
  },
};

export default nextConfig;
