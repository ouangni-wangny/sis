/** Coupure journée : avant = matin, à partir de = soir. */
export const CONTROLE_MATIN_SOIR_CUTOFF_HOUR = 14;

export type PassageControle = "matin" | "soir";

type PosteHours = {
  heure_debut?: string | null;
  heure_fin?: string | null;
  heure_debut_nuit?: string | null;
  heure_fin_nuit?: string | null;
};

export type VacationControleInput = {
  agent_id: string;
  heure_debut: string;
  heure_fin: string;
  poste?: (PosteHours & { id?: string; nom?: string }) | null;
};

function normalizeHm(time: string): string {
  const m = /^(\d{1,2}):(\d{2})/.exec(time.trim());
  if (!m) return "";
  return `${String(Number(m[1])).padStart(2, "0")}:${m[2]}`;
}

function isCycle24h(debut: string, fin: string): boolean {
  const d = debut.trim();
  const f = fin.trim();
  return Boolean(d && f && normalizeHm(d) === normalizeHm(f));
}

function isOvernight(debut: string, fin: string): boolean {
  const a = normalizeHm(debut);
  const b = normalizeHm(fin);
  if (!a || !b) return false;
  return b < a;
}

/**
 * - Poste 24h (cycle) → matin + soir sur le même agent
 * - Quarts → un passage selon le créneau (matin ou soir)
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

  if (!hasQuarts && isCycle24h(debut, fin)) return ["matin", "soir"];
  if (
    !hasQuarts &&
    poste?.heure_debut &&
    poste?.heure_fin &&
    isCycle24h(poste.heure_debut, poste.heure_fin)
  ) {
    return ["matin", "soir"];
  }

  if (isVacationQuartSoir(vacation)) return ["soir"];
  return ["matin"];
}

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
  return d.getHours() < cutoffHour ? "matin" : "soir";
}

export type PassageStatus = {
  requis: PassageControle[];
  faits: Partial<Record<PassageControle, true>>;
  manquants: PassageControle[];
  complet: boolean;
  dernierResultat?: "present" | "absent" | "enregistre";
  dernierEffectueAt?: string | null;
};

export type ControlePassageInput = {
  controle_agent_id: string;
  resultat: "present" | "absent" | "enregistre";
  effectue_at?: string | null;
};

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
    if (status.faits.matin && !status.faits.soir) {
      return { text: "Soir à faire", tone: "partial" };
    }
    if (status.faits.soir && !status.faits.matin) {
      return { text: "Matin à faire", tone: "partial" };
    }
    return { text: "Matin + soir", tone: "muted" };
  }
  if (status.requis[0] === "soir") {
    return { text: "Soir à faire", tone: "muted" };
  }
  return { text: "À faire", tone: "muted" };
}

export function hintPassagesRequis(status: PassageStatus): string | null {
  if (status.requis.length <= 1) {
    return status.requis[0] === "soir" ? "Quart soir" : null;
  }
  return "Poste 24h — contrôle matin et soir";
}

export type FenetreControle = {
  passage: PassageControle;
  /** HH:mm inclus */
  debutHm: string;
  /** HH:mm inclus (peut être le lendemain pour le soir/nuit) */
  finHm: string;
  /** true si finHm est le calendrier lendemain */
  finLendemain: boolean;
  label: string;
};

function hmToMinutes(hm: string): number {
  const [h, m] = hm.split(":").map(Number);
  return h * 60 + m;
}

/**
 * Fenêtre horaire pendant laquelle le contrôleur peut faire le passage.
 * Matin : début vacation → coupure 14h.
 * Soir : coupure 14h → fin vacation (nuit = lendemain).
 */
export function fenetrePourPassage(
  vacation: VacationControleInput,
  passage: PassageControle,
  cutoffHour = CONTROLE_MATIN_SOIR_CUTOFF_HOUR,
): FenetreControle {
  const debutVac =
    normalizeHm(vacation.heure_debut) || vacation.heure_debut.slice(0, 5) || "06:30";
  const finVac =
    normalizeHm(vacation.heure_fin) || vacation.heure_fin.slice(0, 5) || "18:30";
  const overnight = isOvernight(debutVac, finVac) || isCycle24h(debutVac, finVac);
  const cutoff = `${String(cutoffHour).padStart(2, "0")}:00`;

  if (passage === "matin") {
    const debutHm = debutVac;
    // Fin matin = min(cutoff, fin vacation) si même jour ; sinon cutoff.
    let finHm = cutoff;
    if (!overnight && hmToMinutes(finVac) < hmToMinutes(cutoff)) {
      finHm = finVac;
    }
    return {
      passage: "matin",
      debutHm,
      finHm,
      finLendemain: false,
      label: `Créneau matin ${debutHm}–${finHm}`,
    };
  }

  // Soir : max(cutoff, début vac) → fin vac (évent. lendemain)
  let debutHm = cutoff;
  if (hmToMinutes(debutVac) >= hmToMinutes(cutoff) || overnight) {
    // Quart nuit qui commence après 14h : fenêtre dès le début du quart.
    if (hmToMinutes(debutVac) >= hmToMinutes(cutoff)) {
      debutHm = debutVac;
    }
  }
  return {
    passage: "soir",
    debutHm,
    finHm: finVac,
    finLendemain: overnight || hmToMinutes(finVac) <= hmToMinutes(debutHm),
    label: `Créneau soir ${debutHm}–${finVac}${overnight || hmToMinutes(finVac) <= hmToMinutes(debutHm) ? " (+1j)" : ""}`,
  };
}

/** Le passage est-il dans sa fenêtre à l’instant `now` ? */
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
  // Traverse minuit : [debut, 24h) U [0, fin)
  return mins >= debut || mins < fin;
}

export type DisponibiliteControle = {
  /** Ligne cliquable pour démarrer un contrôle maintenant */
  cliquable: boolean;
  /** Passage attendu maintenant (parmi les manquants), si dans la fenêtre */
  passageActif: PassageControle | null;
  /** Fenêtres des passages encore manquants */
  fenetresManquantes: FenetreControle[];
  /** Texte court pour la carte */
  motif: string;
};

/**
 * Disponibilité contrôleur : désactive la ligne hors créneau.
 * Les contrôles déjà complets restent ouverts (recontrôle).
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

/** Intervalle vacation affiché (ex. 06:30–18:30). */
export function labelHorairesVacation(vacation: VacationControleInput): string {
  const d =
    normalizeHm(vacation.heure_debut) || vacation.heure_debut.slice(0, 5);
  const f = normalizeHm(vacation.heure_fin) || vacation.heure_fin.slice(0, 5);
  return `${d}–${f}`;
}
