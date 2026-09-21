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
