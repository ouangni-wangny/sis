import {
  isCycle24h,
  isOvernight,
  normalizeHm,
} from "@/domain/time/shift-interval";

/** Coupure journée : avant = passage matin, à partir de = passage soir. */
export const CONTROLE_MATIN_SOIR_CUTOFF_HOUR = 14;

export type PassageControle = "matin" | "soir";

export type PosteControleHours = {
  heure_debut?: string | null;
  heure_fin?: string | null;
  heure_debut_nuit?: string | null;
  heure_fin_nuit?: string | null;
};

export type VacationControleInput = {
  agent_id: string;
  heure_debut: string;
  heure_fin: string;
  poste?: (PosteControleHours & { id?: string; nom?: string }) | null;
};

/**
 * Passages de contrôle exigés pour une vacation / poste.
 *
 * - Poste **24h** (cycle continu début = fin) → même agent **matin et soir**.
 * - Poste en **quarts** (découpage jour/nuit) → agent du quart matin **ou**
 *   agent du quart soir (un passage chacun).
 * - Quart / plage simple → un seul passage selon le créneau.
 */
export function passagesRequisPourVacation(
  vacation: VacationControleInput,
): PassageControle[] {
  const poste = vacation.poste;
  const hasQuarts = Boolean(
    poste?.heure_debut_nuit?.trim() && poste?.heure_fin_nuit?.trim(),
  );

  const debut = normalizeHm(vacation.heure_debut) || vacation.heure_debut.slice(0, 5);
  const fin = normalizeHm(vacation.heure_fin) || vacation.heure_fin.slice(0, 5);

  // Cycle 24h continu : 2 passages sur le même agent.
  if (!hasQuarts && isCycle24h(debut, fin)) {
    return ["matin", "soir"];
  }
  if (
    !hasQuarts &&
    poste?.heure_debut &&
    poste?.heure_fin &&
    isCycle24h(
      normalizeHm(poste.heure_debut) || poste.heure_debut.slice(0, 5),
      normalizeHm(poste.heure_fin) || poste.heure_fin.slice(0, 5),
    )
  ) {
    return ["matin", "soir"];
  }

  // Quarts ou plage simple : un passage selon le créneau de la vacation.
  if (isVacationQuartSoir(vacation)) {
    return ["soir"];
  }
  return ["matin"];
}

/** Vacation de nuit / quart soir (chevauche minuit ou aligne sur heure_debut_nuit). */
export function isVacationQuartSoir(vacation: VacationControleInput): boolean {
  const debut =
    normalizeHm(vacation.heure_debut) || vacation.heure_debut.slice(0, 5);
  const fin = normalizeHm(vacation.heure_fin) || vacation.heure_fin.slice(0, 5);
  const nuitPoste = vacation.poste?.heure_debut_nuit?.trim();
  if (nuitPoste) {
    const n = normalizeHm(nuitPoste) || nuitPoste.slice(0, 5);
    if (debut === n) return true;
  }
  return isOvernight(debut, fin) && !isCycle24h(debut, fin);
}

export function passageDepuisHeure(
  at: Date | string,
  cutoffHour = CONTROLE_MATIN_SOIR_CUTOFF_HOUR,
): PassageControle {
  const d = typeof at === "string" ? new Date(at) : at;
  const hour = d.getHours();
  return hour < cutoffHour ? "matin" : "soir";
}

export type PassageStatus = {
  requis: PassageControle[];
  faits: Partial<Record<PassageControle, true>>;
  manquants: PassageControle[];
  complet: boolean;
  /** Dernier résultat connu (tous passages confondus). */
  dernierResultat?: "present" | "absent" | "enregistre";
  dernierEffectueAt?: string | null;
};

export type ControlePassageInput = {
  controle_agent_id: string;
  resultat: "present" | "absent" | "enregistre";
  effectue_at?: string | null;
};

/** Index agent → contrôles du jour classés matin / soir. */
export function indexControlesParPassage(controles: ControlePassageInput[]) {
  const byAgent = new Map<
    string,
    {
      passages: Partial<
        Record<
          PassageControle,
          { resultat: ControlePassageInput["resultat"]; effectue_at?: string | null }
        >
      >;
      dernier?: {
        resultat: ControlePassageInput["resultat"];
        effectue_at?: string | null;
      };
    }
  >();

  // Plus récent d’abord si la liste est déjà triée desc ; sinon on écrase
  // seulement si le passage n’est pas encore pris (premier = plus récent).
  for (const c of controles) {
    if (!c.controle_agent_id) continue;
    const entry = byAgent.get(c.controle_agent_id) ?? { passages: {} };
    if (!entry.dernier) {
      entry.dernier = {
        resultat: c.resultat,
        effectue_at: c.effectue_at,
      };
    }
    const passage = passageDepuisHeure(c.effectue_at ?? new Date());
    if (!entry.passages[passage]) {
      entry.passages[passage] = {
        resultat: c.resultat,
        effectue_at: c.effectue_at,
      };
    }
    byAgent.set(c.controle_agent_id, entry);
  }

  return byAgent;
}

export function statusControleVacation(
  vacation: VacationControleInput,
  byAgent: ReturnType<typeof indexControlesParPassage>,
): PassageStatus {
  const requis = passagesRequisPourVacation(vacation);
  const entry = byAgent.get(vacation.agent_id);
  const faits: Partial<Record<PassageControle, true>> = {};
  for (const p of requis) {
    if (entry?.passages[p]) faits[p] = true;
  }
  const manquants = requis.filter((p) => !faits[p]);
  return {
    requis,
    faits,
    manquants,
    complet: manquants.length === 0,
    dernierResultat: entry?.dernier?.resultat,
    dernierEffectueAt: entry?.dernier?.effectue_at,
  };
}

export function labelBadgeControle(status: PassageStatus): {
  text: string;
  tone: "ok" | "partial" | "danger" | "muted";
} {
  if (status.complet) {
    if (status.dernierResultat === "absent") {
      return { text: "Absent", tone: "danger" };
    }
    if (status.requis.length > 1) {
      return { text: "Matin + soir", tone: "ok" };
    }
    return { text: "Fait", tone: "ok" };
  }

  if (status.requis.length > 1) {
    const hasMatin = Boolean(status.faits.matin);
    const hasSoir = Boolean(status.faits.soir);
    if (hasMatin && !hasSoir) {
      return { text: "Soir à faire", tone: "partial" };
    }
    if (hasSoir && !hasMatin) {
      return { text: "Matin à faire", tone: "partial" };
    }
    return { text: "Matin + soir", tone: "muted" };
  }

  const only = status.requis[0];
  if (only === "soir") {
    return { text: "Soir à faire", tone: "muted" };
  }
  return { text: "À faire", tone: "muted" };
}

/** Hint court sous le nom du poste. */
export function hintPassagesRequis(status: PassageStatus): string | null {
  if (status.requis.length <= 1) {
    return status.requis[0] === "soir" ? "Quart soir" : null;
  }
  return "Poste 24h — contrôle matin et soir";
}
