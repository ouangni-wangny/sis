import {
  dayNightHalvesFromCycle,
  dayNightHalvesFromRange,
  isCycle24h,
  isOvernight,
  normalizeDecoupageJourNuit,
  normalizeHm,
} from "@/domain/time/shift-interval";

export type PosteHorairesValues = {
  heure_debut: string;
  heure_fin: string;
  heure_debut_nuit: string;
  heure_fin_nuit: string;
};

/** 24h avec découpage en quarts jour / nuit (champs nuit renseignés). */
export function hasDecoupageQuarts(
  values: Pick<PosteHorairesValues, "heure_debut_nuit" | "heure_fin_nuit">,
) {
  return Boolean(
    values.heure_debut_nuit?.trim() && values.heure_fin_nuit?.trim(),
  );
}

/** Cycle 24h sans découpage : début = fin (ex. 06:30–06:30). */
export function isCycle24hInterval(
  values: Pick<PosteHorairesValues, "heure_debut" | "heure_fin">,
) {
  return isCycle24h(values.heure_debut || "", values.heure_fin || "");
}

/** Poste couvert 24h : découpage quarts OU cycle continu. */
export function isCouverture24h(values: PosteHorairesValues) {
  return hasDecoupageQuarts(values) || isCycle24hInterval(values);
}

export type PlanningRepartition = "jour_entier" | "moities";

/**
 * Répartition planning dérivée du poste (source de vérité = fiche poste).
 * - ≥2 agents + découpage quarts enregistré → moitiés Jour/Nuit du poste
 * - sinon → même plage (cycle ou horaire simple) pour chaque agent
 *
 * Un cycle 24h sans champs nuit n’est PAS découpé ici : le poste l’a défini
 * comme relève→relève ; le découpage se configure sur le poste si besoin.
 */
export function derivePlanningRepartition(
  hours: PosteHorairesValues,
  agentsRequis: number,
): PlanningRepartition {
  if (agentsRequis >= 2 && hasDecoupageQuarts(hours)) {
    return "moities";
  }
  return "jour_entier";
}

/** Libellé court = horaires du poste, langage simple. */
export function planningCouvertureResume(
  hours: PosteHorairesValues,
  agentsRequis: number,
): string {
  const n = Math.max(1, agentsRequis);
  const hd = hours.heure_debut || "—";
  const hf = hours.heure_fin || "—";

  if (hasDecoupageQuarts(hours)) {
    return `Jour ${hd}–${hf} · Nuit ${hours.heure_debut_nuit}–${hours.heure_fin_nuit}`;
  }

  if (isCycle24hInterval(hours)) {
    return `24h d’affilée (${hd} → ${hf} le lendemain)`;
  }

  return `${hd}–${hf}`;
}

export type ModeEffectif = "ensemble" | "alternance";

/** Consigne planning selon la fiche poste. */
export function planningCouvertureHint(
  hours: PosteHorairesValues,
  agentsRequis: number,
  modeEffectif: ModeEffectif = "ensemble",
): string | null {
  const n = Math.max(1, agentsRequis);

  if (hasDecoupageQuarts(hours) && n >= 2) {
    return "Chaque agent prend soit le jour, soit la nuit — pas les deux.";
  }

  if (n >= 2 && modeEffectif === "alternance") {
    if (isCycle24hInterval(hours)) {
      return `Les ${n} agents alternent sur la vacation de 24h — jours complémentaires (un seul agent / jour).`;
    }
    return `Les ${n} agents alternent à tour de rôle — jours complémentaires (un seul agent / jour).`;
  }

  if (isCycle24hInterval(hours) && n >= 2) {
    return `Les ${n} agents sont ensemble sur la vacation de 24h (mêmes jours).`;
  }

  if (n >= 2) {
    return `Les ${n} agents sont ensemble sur le même horaire (mêmes jours).`;
  }

  if (isCycle24hInterval(hours)) {
    return "Une vacation de 24h par jour travaillé.";
  }

  return null;
}

export function isOvernightRange(debut: string, fin: string): boolean {
  return isOvernight(debut, fin);
}

/**
 * Intervalle 24h → quarts jour/nuit (diurne / overnight).
 * Toujours via dayNightHalvesFromCycle — jamais premiere→jour brut.
 */
export function build24hFromInterval(
  debut: string,
  fin: string,
): PosteHorairesValues {
  const start = normalizeHm(debut) || debut || "07:00";
  const end = normalizeHm(fin) || fin || start;
  const halves =
    dayNightHalvesFromRange(start, end) ?? dayNightHalvesFromCycle(start);
  if (!halves) {
    return {
      heure_debut: start,
      heure_fin: "19:00",
      heure_debut_nuit: "19:00",
      heure_fin_nuit: start,
    };
  }
  return {
    heure_debut: halves.jour.heure_debut,
    heure_fin: halves.jour.heure_fin,
    heure_debut_nuit: halves.nuit.heure_debut,
    heure_fin_nuit: halves.nuit.heure_fin,
  };
}

/** Cycle 24h continu (pas de quarts). */
export function build24hCycle(releve: string): PosteHorairesValues {
  const t = normalizeHm(releve) || releve || "07:00";
  return {
    heure_debut: t,
    heure_fin: t,
    heure_debut_nuit: "",
    heure_fin_nuit: "",
  };
}

/**
 * Applique le mode 24h selon le choix de découpage.
 * - decoupage=true  → quarts jour / nuit (normalisés)
 * - decoupage=false → plage continue (relève → relève)
 */
export function apply24hDecoupage(
  releve: string,
  decoupage: boolean,
): PosteHorairesValues {
  return decoupage
    ? build24hFromInterval(releve, releve)
    : build24hCycle(releve);
}

/** Nettoie + normalise le découpage jour/nuit si présent. */
export function normalizePosteHoraires(
  values: PosteHorairesValues,
): PosteHorairesValues {
  const raw = {
    heure_debut: values.heure_debut?.trim() || "",
    heure_fin: values.heure_fin?.trim() || "",
    heure_debut_nuit: values.heure_debut_nuit?.trim() || "",
    heure_fin_nuit: values.heure_fin_nuit?.trim() || "",
  };
  if (hasDecoupageQuarts(raw)) {
    return normalizeDecoupageJourNuit(raw);
  }
  return raw;
}

/** Heure de relève affichée (cycle ou début du quart jour). */
export function releveFromPoste(values: PosteHorairesValues): string {
  if (hasDecoupageQuarts(values)) {
    return values.heure_debut || "07:00";
  }
  if (isCycle24hInterval(values)) {
    return values.heure_debut;
  }
  return values.heure_debut || "07:00";
}
