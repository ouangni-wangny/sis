"use client";

import { useCallback, useEffect, useEffectEvent, useState } from "react";
import { getApiErrorMessage } from "@/shared/lib/api-error";

/**
 * Chargement de données d’un écran hors TanStack Query.
 *
 * - `charger` récupère les données sans toucher à l’état (stable : useCallback).
 * - `appliquer` range le résultat dans l’état du composant (stable : useCallback).
 *
 * Le chargement initial applique le résultat dans un callback de promesse
 * (aucun setState synchrone dans l’effet) et ignore une réponse arrivée après
 * démontage ou après un changement de `charger`. `recharger` remet chargement
 * et erreur à zéro, pour un bouton « Réessayer » ou après une action.
 */
export function useChargement<T>(
  charger: () => Promise<T>,
  appliquer: (data: T) => void,
  messageErreur: string,
) {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const appliquerResultat = useEffectEvent(appliquer);

  useEffect(() => {
    let actif = true;
    charger()
      .then(
        (data) => {
          if (!actif) return;
          appliquerResultat(data);
          setError(null);
        },
        (err: unknown) => {
          if (actif) setError(getApiErrorMessage(err, messageErreur));
        },
      )
      .finally(() => {
        if (actif) setLoading(false);
      });
    return () => {
      actif = false;
    };
  }, [charger, messageErreur]);

  const recharger = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      appliquer(await charger());
    } catch (err) {
      setError(getApiErrorMessage(err, messageErreur));
    } finally {
      setLoading(false);
    }
  }, [charger, appliquer, messageErreur]);

  return { loading, error, setError, recharger };
}
