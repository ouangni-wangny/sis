import Constants from "expo-constants";
import { Platform } from "react-native";

/**
 * Sur téléphone (QR Expo Go), on réutilise l’IP LAN du packager Metro.
 * localhost / 10.0.2.2 ne marchent que simulateur / émulateur.
 */
function lanHostFromExpo(): string | null {
  const hostUri =
    Constants.expoConfig?.hostUri ??
    // Expo Go / legacy manifests
    (Constants as { manifest2?: { extra?: { expoGo?: { debuggerHost?: string } } } })
      .manifest2?.extra?.expoGo?.debuggerHost ??
    (Constants as { manifest?: { debuggerHost?: string } }).manifest
      ?.debuggerHost ??
    null;

  if (!hostUri) return null;
  const host = hostUri.split(":")[0]?.trim();
  if (!host || host === "127.0.0.1" || host === "localhost") return null;
  return host;
}

function resolveApiUrl(): string {
  const fromEnv = process.env.EXPO_PUBLIC_API_URL?.trim();
  if (fromEnv) return fromEnv.replace(/\/$/, "");

  const lan = lanHostFromExpo();
  if (lan) {
    return `http://${lan}:8000/api/v1`;
  }

  if (Platform.OS === "android") {
    // Émulateur Android → machine hôte
    return "http://10.0.2.2:8000/api/v1";
  }

  // Simulateur iOS
  return "http://localhost:8000/api/v1";
}

export const API_URL = resolveApiUrl();
