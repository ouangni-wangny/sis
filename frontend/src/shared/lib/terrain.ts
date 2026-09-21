import type { Controle, ControleResultat } from "@/domain/types/entities";

/** @deprecated Préférer indexControlesParPassage + statusControleVacation. */
export function mapControlesByAgent(controles: Controle[]) {
  const map = new Map<
    string,
    { resultat: ControleResultat; effectue_at?: string | null }
  >();
  for (const c of controles) {
    if (!c.controle_agent_id || map.has(c.controle_agent_id)) continue;
    map.set(c.controle_agent_id, {
      resultat: c.resultat,
      effectue_at: c.effectue_at,
    });
  }
  return map;
}

export function uuid() {
  return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === "x" ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

export function getCurrentCoords(): Promise<{
  latitude: number;
  longitude: number;
}> {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject(new Error("Géolocalisation non disponible sur cet appareil."));
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (pos) =>
        resolve({
          latitude: pos.coords.latitude,
          longitude: pos.coords.longitude,
        }),
      () =>
        reject(
          new Error(
            "Impossible d’obtenir la position GPS. Autorisez la localisation.",
          ),
        ),
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 5000 },
    );
  });
}

/** Compresse une image File → JPEG base64 (sans préfixe data:). */
export async function fileToJpegBase64(
  file: File,
  maxSide = 1280,
  quality = 0.7,
): Promise<{ previewUrl: string; base64: string }> {
  const bitmap = await createImageBitmap(file);
  const scale = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height));
  const w = Math.max(1, Math.round(bitmap.width * scale));
  const h = Math.max(1, Math.round(bitmap.height * scale));
  const canvas = document.createElement("canvas");
  canvas.width = w;
  canvas.height = h;
  const ctx = canvas.getContext("2d");
  if (!ctx) throw new Error("Canvas indisponible.");
  ctx.drawImage(bitmap, 0, 0, w, h);
  bitmap.close();

  const dataUrl = canvas.toDataURL("image/jpeg", quality);
  const base64 = dataUrl.replace(/^data:image\/jpeg;base64,/, "");
  return { previewUrl: dataUrl, base64 };
}
