export const STATUT_ANOMALIE_FILTER_OPTIONS = [
  { value: "", label: "Tous les statuts" },
  { value: "ouverte", label: "Ouverte" },
  { value: "en_cours", label: "En cours" },
  { value: "resolue", label: "Résolue" },
] as const;

export const GRAVITE_ANOMALIE_FILTER_OPTIONS = [
  { value: "", label: "Toutes gravités" },
  { value: "basse", label: "Basse" },
  { value: "moyenne", label: "Moyenne" },
  { value: "haute", label: "Haute" },
  { value: "critique", label: "Critique" },
] as const;

export const TYPE_ANOMALIE_FILTER_OPTIONS = [
  { value: "", label: "Tous les types" },
  { value: "intrusion", label: "Intrusion" },
  { value: "vol", label: "Vol" },
  { value: "incendie", label: "Incendie" },
  { value: "technique", label: "Technique" },
  { value: "comportement", label: "Comportement" },
  { value: "absence_poste", label: "Absence poste" },
  { value: "autre", label: "Autre" },
] as const;
