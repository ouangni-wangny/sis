/** Note PDF auto : alignée sur ConditionsCommerciales::noteAbonnement (API). */
export function noteAbonnement(
  periodicite: string | null | undefined,
  montantHt: number,
): string {
  const peri = periodicite || "mensuel";
  const montant = Math.round(montantHt)
    .toString()
    .replace(/\B(?=(\d{3})+(?!\d))/g, " ");

  return `L'abonnement ${peri} sera de ${montant} FCFA HT.`;
}

const AUTO_NOTE_RE =
  /^L'abonnement (mensuel|trimestriel|annuel) sera de [\d\s\u00a0\u202f]+ FCFA HT\.$/;

/** True si vide ou texte généré automatiquement (modifiable librement sinon). */
export function isAutoNoteAbonnement(notes: string | null | undefined): boolean {
  const trimmed = (notes ?? "").trim();
  if (!trimmed) return true;
  return AUTO_NOTE_RE.test(trimmed);
}
