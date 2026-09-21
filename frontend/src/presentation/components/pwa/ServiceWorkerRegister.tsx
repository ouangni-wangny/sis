"use client";

import { useEffect } from "react";

/**
 * Enregistre le service worker PWA (production uniquement).
 * updateViaCache: 'none' évite un SW périmé servi depuis le cache HTTP.
 */
export function ServiceWorkerRegister() {
  useEffect(() => {
    if (process.env.NODE_ENV !== "production") return;
    if (!("serviceWorker" in navigator)) return;

    const register = async () => {
      try {
        const reg = await navigator.serviceWorker.register("/sw.js", {
          scope: "/",
          updateViaCache: "none",
        });
        // Force check on load so deploys pick up new SW quickly
        void reg.update();
      } catch (err) {
        console.warn("[PWA] Service worker non enregistré:", err);
      }
    };

    void register();
  }, []);

  return null;
}
