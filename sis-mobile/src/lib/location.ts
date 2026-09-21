import * as Location from "expo-location";

export async function getCurrentCoords() {
  const { status } = await Location.requestForegroundPermissionsAsync();
  if (status !== "granted") {
    throw new Error("Permission de localisation refusée.");
  }

  try {
    const pos = await Promise.race([
      Location.getCurrentPositionAsync({
        accuracy: Location.Accuracy.Balanced,
      }),
      new Promise<never>((_, reject) =>
        setTimeout(
          () => reject(new Error("GPS trop lent. Réessayez à l’extérieur.")),
          15000,
        ),
      ),
    ]);

    return {
      latitude: pos.coords.latitude,
      longitude: pos.coords.longitude,
    };
  } catch (err) {
    // Fallback dernière position connue
    const last = await Location.getLastKnownPositionAsync();
    if (last) {
      return {
        latitude: last.coords.latitude,
        longitude: last.coords.longitude,
      };
    }
    throw err instanceof Error
      ? err
      : new Error("Impossible d’obtenir la position GPS.");
  }
}
