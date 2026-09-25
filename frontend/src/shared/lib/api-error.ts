import axios from "axios";

/** Clés d’erreur 422 techniques (pas destinées à l’affichage utilisateur). */
const META_ERROR_KEYS = new Set([
  "blocking_vacation_id",
  "blocking_vacation_ids",
]);

/** Extrait un message d’erreur API compréhensible pour l’utilisateur. */
export function getApiErrorMessage(
  err: unknown,
  fallback = "Une erreur est survenue.",
): string {
  if (!axios.isAxiosError(err)) return fallback;

  if (!err.response) {
    const code = err.code;
    if (code === "ECONNABORTED" || code === "ETIMEDOUT") {
      return "Délai dépassé. Affinez les filtres ou réessayez.";
    }
    if (code === "ERR_NETWORK" || code === "ECONNRESET") {
      return "Export interrompu (volume trop important ou serveur saturé). Affinez les filtres et réessayez.";
    }
    return "API injoignable. Vérifiez que le serveur Laravel tourne.";
  }

  const status = err.response.status;
  const data = err.response.data as
    | {
        message?: string;
        errors?: Record<string, string[]>;
        blocking_vacation_id?: string | null;
      }
    | undefined;

  if (status === 403) {
    return "Action non autorisée pour votre rôle.";
  }

  if (status === 422 && data?.errors) {
    const first = Object.entries(data.errors).find(
      ([key, messages]) => !META_ERROR_KEYS.has(key) && messages?.[0],
    )?.[1]?.[0];
    if (first) return first;
  }

  return data?.message ?? fallback;
}

/**
 * Comme getApiErrorMessage, mais lit aussi les corps d’erreur en Blob
 * (requêtes `responseType: "blob"` : export PDF, etc.).
 */
export async function getApiErrorMessageAsync(
  err: unknown,
  fallback = "Une erreur est survenue.",
): Promise<string> {
  if (!axios.isAxiosError(err)) return fallback;

  if (!err.response) {
    return getApiErrorMessage(err, fallback);
  }

  const raw = err.response.data;
  if (raw instanceof Blob) {
    try {
      const text = await raw.text();
      const json = JSON.parse(text) as {
        message?: string;
        errors?: Record<string, string[]>;
      };
      if (err.response.status === 422 && json.errors) {
        const first = Object.entries(json.errors).find(
          ([key, messages]) => !META_ERROR_KEYS.has(key) && messages?.[0],
        )?.[1]?.[0];
        if (first) return first;
      }
      if (json.message) return json.message;
    } catch {
      // ignore parse errors
    }
  }

  return getApiErrorMessage(err, fallback);
}

/**
 * Vacation déjà planifiée qui bloque (repos / chevauchement).
 * Présente en 422 (`errors.blocking_vacation_id`) ou 409 (`blocking_vacation_id`).
 */
export function getBlockingVacationId(err: unknown): string | null {
  const ids = getBlockingVacationIds(err);
  return ids[0] ?? null;
}

/** Toutes les vacations bloquantes renvoyées par l’API (422 ou 409). */
export function getBlockingVacationIds(err: unknown): string[] {
  if (!axios.isAxiosError(err) || !err.response?.data) return [];

  const data = err.response.data as {
    blocking_vacation_id?: string | null;
    blocking_vacation_ids?: string[] | null;
    errors?: Record<string, string[]>;
  };

  const fromList =
    data.blocking_vacation_ids ?? data.errors?.blocking_vacation_ids ?? [];
  const ids = (Array.isArray(fromList) ? fromList : [])
    .filter((id): id is string => typeof id === "string" && id.length > 0);

  if (ids.length > 0) {
    return [...new Set(ids)];
  }

  const single =
    (typeof data.blocking_vacation_id === "string"
      ? data.blocking_vacation_id
      : null) ?? data.errors?.blocking_vacation_id?.[0];

  return typeof single === "string" && single.length > 0 ? [single] : [];
}
