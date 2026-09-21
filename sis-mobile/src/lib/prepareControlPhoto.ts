import * as ImageManipulator from "expo-image-manipulator";

/**
 * Prépare une photo terrain pour l’API :
 * - redimensionne (max 1024px)
 * - JPEG compressé
 * - base64 compact (~200–500 Ko) pour rester sous les limites PHP
 */
export async function prepareControlPhoto(uri: string): Promise<{
  uri: string;
  base64: string;
}> {
  const result = await ImageManipulator.manipulateAsync(
    uri,
    [{ resize: { width: 1024 } }],
    {
      compress: 0.55,
      format: ImageManipulator.SaveFormat.JPEG,
      base64: true,
    },
  );

  if (!result.base64) {
    throw new Error("Impossible de compresser la photo.");
  }

  // Garde-fou : ~900 Ko JSON max après envelope (~post_max 2M trop juste sinon)
  if (result.base64.length > 900_000) {
    const tighter = await ImageManipulator.manipulateAsync(
      result.uri,
      [{ resize: { width: 720 } }],
      {
        compress: 0.4,
        format: ImageManipulator.SaveFormat.JPEG,
        base64: true,
      },
    );
    if (!tighter.base64) {
      throw new Error("Photo trop lourde après compression.");
    }
    return { uri: tighter.uri, base64: tighter.base64 };
  }

  return { uri: result.uri, base64: result.base64 };
}
