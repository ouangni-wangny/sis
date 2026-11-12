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

export type FenetreControle = {
  passage: PassageControle;
  debutHm: string;
  finHm: string;
  finLendemain: boolean;
  label: string;
};

function hmToMinutes(hm: string): number {
  const [h, m] = hm.split(":").map(Number);
  return h * 60 + m;
}

/**
 * Fenêtre pendant laquelle le contrôleur peut faire le passage.
 * Matin : début vacation → coupure 14h.
 * Soir : coupure 14h → fin vacation (nuit = lendemain).
 */
export function fenetrePourPassage(
  vacation: VacationControleInput,
  passage: PassageControle,
  cutoffHour = CONTROLE_MATIN_SOIR_CUTOFF_HOUR,
): FenetreControle {
  const debutVac =
    normalizeHm(vacation.heure_debut) ||
    vacation.heure_debut.slice(0, 5) ||
    "06:30";
  const finVac =
    normalizeHm(vacation.heure_fin) ||
    vacation.heure_fin.slice(0, 5) ||
    "18:30";
  const overnight =
    isOvernight(debutVac, finVac) || isCycle24h(debutVac, finVac);
  const cutoff = `${String(cutoffHour).padStart(2, "0")}:00`;

  if (passage === "matin") {
    let finHm = cutoff;
    if (!overnight && hmToMinutes(finVac) < hmToMinutes(cutoff)) {
      finHm = finVac;
    }
    return {
      passage: "matin",
      debutHm: debutVac,
      finHm,
      finLendemain: false,
      label: `Créneau matin ${debutVac}–${finHm}`,
    };
  }

  let debutHm = cutoff;
  if (hmToMinutes(debutVac) >= hmToMinutes(cutoff)) {
    debutHm = debutVac;
  }
  const finLendemain =
    overnight || hmToMinutes(finVac) <= hmToMinutes(debutHm);
  return {
    passage: "soir",
    debutHm,
    finHm: finVac,
    finLendemain,
    label: `Créneau soir ${debutHm}–${finVac}${finLendemain ? " (+1j)" : ""}`,
  };
}

export function estDansFenetre(
  fenetre: FenetreControle,
  now: Date = new Date(),
): boolean {
  const mins = now.getHours() * 60 + now.getMinutes();
  const debut = hmToMinutes(fenetre.debutHm);
  const fin = hmToMinutes(fenetre.finHm);
  if (!fenetre.finLendemain) {
    return mins >= debut && mins < fin;
  }
  return mins >= debut || mins < fin;
}

export type DisponibiliteControle = {
  cliquable: boolean;
  passageActif: PassageControle | null;
  fenetresManquantes: FenetreControle[];
  motif: string;
};

/**
 * Désactive la ligne hors créneau. Les contrôles complets restent
 * ouverts (recontrôle).
 */
export function disponibiliteControle(
  vacation: VacationControleInput,
  status: PassageStatus,
  now: Date = new Date(),
): DisponibiliteControle {
  if (status.complet) {
    return {
      cliquable: true,
      passageActif: null,
      fenetresManquantes: [],
      motif: "Contrôle complet — recontrôle possible",
    };
  }

  const fenetresManquantes = status.manquants.map((p) =>
    fenetrePourPassage(vacation, p),
  );
  const ouvertes = fenetresManquantes.filter((f) => estDansFenetre(f, now));
  if (ouvertes.length > 0) {
    const f = ouvertes[0]!;
    return {
      cliquable: true,
      passageActif: f.passage,
      fenetresManquantes,
      motif: f.label,
    };
  }

  const prochaine = fenetresManquantes[0];
  return {
    cliquable: false,
    passageActif: null,
    fenetresManquantes,
    motif: prochaine
      ? `Hors créneau · ${prochaine.label}`
      : "Hors créneau de contrôle",
  };
}

/** Intervalle vacation (ex. 06:30–18:30). */
export function labelHorairesVacation(vacation: VacationControleInput): string {
  const d =
    normalizeHm(vacation.heure_debut) || vacation.heure_debut.slice(0, 5);
  const f =
    normalizeHm(vacation.heure_fin) || vacation.heure_fin.slice(0, 5);
  return `${d}–${f}`;
}
