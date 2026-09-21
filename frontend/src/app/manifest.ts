import type { MetadataRoute } from "next";

export default function manifest(): MetadataRoute.Manifest {
  return {
    name: "S.I.S — Société Ivoirienne de Sécurité",
    short_name: "S.I.S",
    description:
      "Pilotage opérations et terrain — gardiennage, contrôles, signalisation.",
    start_url: "/login",
    scope: "/",
    id: "/",
    display: "standalone",
    orientation: "any",
    background_color: "#F4F5F2",
    theme_color: "#2F3A24",
    lang: "fr",
    categories: ["business", "productivity", "security"],
    icons: [
      {
        src: "/icons/icon-192.png",
        sizes: "192x192",
        type: "image/png",
        purpose: "any",
      },
      {
        src: "/icons/icon-512.png",
        sizes: "512x512",
        type: "image/png",
        purpose: "any",
      },
      {
        src: "/icons/icon-512-maskable.png",
        sizes: "512x512",
        type: "image/png",
        purpose: "maskable",
      },
    ],
    shortcuts: [
      {
        name: "Terrain — Accueil",
        short_name: "Terrain",
        description: "Ouvrir l’espace terrain contrôleur",
        url: "/terrain",
        icons: [{ src: "/icons/icon-192.png", sizes: "192x192" }],
      },
      {
        name: "Contrôles",
        short_name: "Contrôles",
        description: "Contrôles de présence",
        url: "/terrain/controle",
        icons: [{ src: "/icons/icon-192.png", sizes: "192x192" }],
      },
      {
        name: "Signalisation",
        short_name: "Alerte",
        description: "Signaler une anomalie",
        url: "/terrain/anomalie",
        icons: [{ src: "/icons/icon-192.png", sizes: "192x192" }],
      },
    ],
  };
}
