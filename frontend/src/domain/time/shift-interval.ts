/**
 * Source unique — intervalles horaires (jour / nuit / cycle 24h).
 *
 * Règles fixées (ne pas redéfinir ailleurs) :
 * - overnight  = fin < début (strict) — traverse minuit
 * - cycle 24h  = début === fin (non vide) — relève → relève
 * - demi-journées Jour/Nuit = plage diurne / overnight, PAS 1ʳᵉ/2ᵉ moitié brute
 */

export const MINUTES_PER_DAY = 24 * 60;

/** Heure de bascule heuristique pour classifier une prise de poste (sans halves). */
export const QUART_NUIT_START_MINUTES = 14 * 60; // 14:00

export type QuartId = "jour" | "nuit";

export type TimeRange = {
  heure_debut: string;
  heure_fin: string;
};

export type DayNightHalves = {
  jour: TimeRange;
  nuit: TimeRange;
};

export function minutesOf(time: string): number | null {
  const m = /^(\d{1,2}):(\d{2})$/.exec(time.trim());
  if (!m) return null;
  return Number(m[1]) * 60 + Number(m[2]);
}

export function formatMinutes(total: number): string {
  const normalized = ((total % MINUTES_PER_DAY) + MINUTES_PER_DAY) % MINUTES_PER_DAY;
  const h = Math.floor(normalized / 60);
  const mm = normalized % 60;
  return `${String(h).padStart(2, "0")}:${String(mm).padStart(2, "0")}`;
}

export function normalizeHm(time: string): string {
  const m = minutesOf(time);
  return m == null ? "" : formatMinutes(m);
}

/** Traverse minuit (ex. 18:30→06:30). début === fin n’est PAS overnight. */
export function isOvernight(debut: string, fin: string): boolean {
  const a = minutesOf(debut);
  const b = minutesOf(fin);
  if (a == null || b == null) return false;
  return b < a;
}

/** Cycle 24h continu (ex. 06:30→06:30). */
export function isCycle24h(debut: string, fin: string): boolean {
  const d = debut.trim();
  const f = fin.trim();
  return Boolean(d && f && normalizeHm(d) === normalizeHm(f));
}

/** Durée en minutes (cycle / overnight → +24h). */
export function spanMinutes(debut: string, fin: string): number | null {
  const a = minutesOf(debut);
  const b0 = minutesOf(fin);
  if (a == null || b0 == null) return null;
  let b = b0;
  if (b <= a) b += MINUTES_PER_DAY;
  return b - a;
}

export function shiftDurationHours(debut: string, fin: string): number | null {
  const m = spanMinutes(debut, fin);
  return m == null ? null : Math.round((m / 60) * 10) / 10;
}

/**
 * Date de fin calendaire d’un créneau démarrant à `dateDebut`.
 * - overnight → lendemain
 * - cycle 24h → lendemain (span réel 24h)
 * - sinon → dateDebut (sauf dateFin fournie)
 */
export function dateFinForShift(
  dateDebut: string,
  debut: string,
  fin: string,
  dateFin?: string | null,
): string {
  const d = dateDebut.slice(0, 10);
  if (dateFin?.trim()) {
    const end = dateFin.slice(0, 10);
    if (isOvernight(debut, fin) && end <= d) return addOneDayIso(d);
    if (isCycle24h(debut, fin) && end <= d) return addOneDayIso(d);
    return end;
  }
  if (isOvernight(debut, fin) || isCycle24h(debut, fin)) return addOneDayIso(d);
  return d;
}

export function addOneDayIso(iso: string): string {
  const d = new Date(`${iso.slice(0, 10)}T12:00:00`);
  d.setDate(d.getDate() + 1);
  return d.toISOString().slice(0, 10);
}

/** Coupe chronologique (1ʳᵉ / 2ᵉ moitié). */
export function splitTimeRangeInHalf(
  debut: string,
  fin: string,
): { premiere: TimeRange; seconde: TimeRange } | null {
  const a = minutesOf(debut);
  const b0 = minutesOf(fin);
  if (a == null || b0 == null) return null;
  let b = b0;
  if (b <= a) b += MINUTES_PER_DAY;
  const mid = a + Math.round((b - a) / 2);
  const midTime = formatMinutes(mid);
  return {
    premiere: { heure_debut: debut, heure_fin: midTime },
    seconde: { heure_debut: midTime, heure_fin: fin },
  };
}

/**
 * Demi-journées logiques Jour (diurne) / Nuit (overnight)
 * depuis une relève de cycle 24h.
 */
export function dayNightHalvesFromCycle(releve: string): DayNightHalves | null {
  const t = normalizeHm(releve) || releve.trim();
  if (!t) return null;
  const halves = splitTimeRangeInHalf(t, t);
  if (!halves) return null;
  return remapPremiereSecondeToDayNight(halves.premiere, halves.seconde);
}

/** Demi-journées depuis une plage quelconque (cycle ou overnight inclus). */
export function dayNightHalvesFromRange(
  debut: string,
  fin: string,
): DayNightHalves | null {
  if (isCycle24h(debut, fin)) return dayNightHalvesFromCycle(debut);
  const halves = splitTimeRangeInHalf(debut, fin);
  if (!halves) return null;
  return remapPremiereSecondeToDayNight(halves.premiere, halves.seconde);
}

function remapPremiereSecondeToDayNight(
  premiere: TimeRange,
  seconde: TimeRange,
): DayNightHalves {
  if (isOvernight(premiere.heure_debut, premiere.heure_fin)) {
    return { nuit: premiere, jour: seconde };
  }
  return { jour: premiere, nuit: seconde };
}

/**
 * Normalise un découpage stocké : champs jour = diurne, nuit = overnight.
 * Corrige les postes créés avec première moitié overnight en « jour ».
 */
export function normalizeDecoupageJourNuit(values: {
  heure_debut: string;
  heure_fin: string;
  heure_debut_nuit: string;
  heure_fin_nuit: string;
}): {
  heure_debut: string;
  heure_fin: string;
  heure_debut_nuit: string;
  heure_fin_nuit: string;
} {
  const jd = values.heure_debut?.trim() || "";
  const jf = values.heure_fin?.trim() || "";
  const nd = values.heure_debut_nuit?.trim() || "";
  const nf = values.heure_fin_nuit?.trim() || "";
  if (!jd || !jf || !nd || !nf) {
    return {
      heure_debut: jd,
      heure_fin: jf,
      heure_debut_nuit: nd,
      heure_fin_nuit: nf,
    };
  }
  // Cycle (début=fin) + champs nuit : le « jour » va de la relève au début de nuit.
  if (isCycle24h(jd, jf)) {
    return {
      heure_debut: jd,
      heure_fin: nd,
      heure_debut_nuit: nd,
      heure_fin_nuit: nf,
    };
  }
  if (isOvernight(jd, jf) && !isOvernight(nd, nf)) {
    return {
      heure_debut: nd,
      heure_fin: nf,
      heure_debut_nuit: jd,
      heure_fin_nuit: jf,
    };
  }
  return {
    heure_debut: jd,
    heure_fin: jf,
    heure_debut_nuit: nd,
    heure_fin_nuit: nf,
  };
}

export function dayNightHalvesFromPoste(values: {
  heure_debut: string;
  heure_fin: string;
  heure_debut_nuit?: string | null;
  heure_fin_nuit?: string | null;
}): DayNightHalves | null {
  const nd = values.heure_debut_nuit?.trim() || "";
  const nf = values.heure_fin_nuit?.trim() || "";
  if (nd && nf) {
    const normalized = normalizeDecoupageJourNuit({
      heure_debut: values.heure_debut || "",
      heure_fin: values.heure_fin || "",
      heure_debut_nuit: nd,
      heure_fin_nuit: nf,
    });
    return {
      jour: {
        heure_debut: normalized.heure_debut,
        heure_fin: normalized.heure_fin,
      },
      nuit: {
        heure_debut: normalized.heure_debut_nuit,
        heure_fin: normalized.heure_fin_nuit,
      },
    };
  }
  if (isCycle24h(values.heure_debut || "", values.heure_fin || "")) {
    return dayNightHalvesFromCycle(values.heure_debut);
  }
  if (values.heure_debut?.trim() && values.heure_fin?.trim()) {
    return dayNightHalvesFromRange(values.heure_debut, values.heure_fin);
  }
  return null;
}

/** Déduit Jour/Nuit depuis une vacation + halves optionnelles du poste. */
export function inferQuartFromHours(
  debut: string,
  fin: string,
  halves?: DayNightHalves | null,
): QuartId {
  const d = normalizeHm(debut) || debut.slice(0, 5);
  const f = normalizeHm(fin) || fin.slice(0, 5);
  if (halves) {
    if (
      d === normalizeHm(halves.nuit.heure_debut) &&
      f === normalizeHm(halves.nuit.heure_fin)
    ) {
      return "nuit";
    }
    if (
      d === normalizeHm(halves.jour.heure_debut) &&
      f === normalizeHm(halves.jour.heure_fin)
    ) {
      return "jour";
    }
  }
  if (isCycle24h(d, f)) return "jour";
  if (isOvernight(d, f)) return "nuit";
  const m = minutesOf(d);
  if (m != null && m >= QUART_NUIT_START_MINUTES) return "nuit";
  return "jour";
}

export function rangesOverlapMs(
  a: [number, number],
  b: [number, number],
): boolean {
  return a[0] < b[1] && a[1] > b[0];
}

/** Repos minimum entre deux vacations (aligné config sis.vacation.repos_min_heures). */
export const REPOS_MIN_HEURES = 11;

/**
 * true si le gap entre deux plages est < repos min.
 * Exemptions (même poste) : cycle→cycle, ou jour→nuit uniquement — jamais nuit→jour.
 */
export function hasInsufficientReposMs(
  proposed: [number, number],
  other: [number, number],
  opts: {
    samePoste?: boolean;
    proposedOvernight?: boolean;
    otherOvernight?: boolean;
    proposedCycle?: boolean;
    otherCycle?: boolean;
    reposMinHeures?: number;
  } = {},
): boolean {
  const reposMin = (opts.reposMinHeures ?? REPOS_MIN_HEURES) * 60 * 60 * 1000;
  let gapMs: number;
  if (proposed[1] <= other[0]) {
    gapMs = other[0] - proposed[1];
  } else if (other[1] <= proposed[0]) {
    gapMs = proposed[0] - other[1];
  } else {
    // Chevauchement : traité à part (conflit).
    return false;
  }

  if (opts.samePoste && gapMs <= 60_000) {
    if (opts.proposedCycle && opts.otherCycle) return false;
    // other puis proposed : jour → nuit
    if (
      other[1] === proposed[0] &&
      !opts.otherOvernight &&
      !opts.otherCycle &&
      opts.proposedOvernight
    ) {
      return false;
    }
    // proposed puis other : jour → nuit
    if (
      proposed[1] === other[0] &&
      !opts.proposedOvernight &&
      !opts.proposedCycle &&
      opts.otherOvernight
    ) {
      return false;
    }
  }

  return gapMs < reposMin;
}

/** Plage [start, end] ms pour détection de conflits (alignée API). */
export function vacationRangeMs(
  dateDebut: string,
  heureDebut: string,
  heureFin: string,
  dateFin?: string | null,
): [number, number] {
  const d = dateDebut.slice(0, 10);
  const hd = normalizeHm(heureDebut) || heureDebut.slice(0, 5);
  const hf = normalizeHm(heureFin) || heureFin.slice(0, 5);
  const start = new Date(`${d}T${hd}:00`).getTime();
  if (!dateFin?.trim()) {
    // Open-ended : horizon long (comme DetecterConflitsVacationAction)
    const end = new Date(`${d}T${hf}:00`);
    end.setFullYear(end.getFullYear() + 5);
    if (isOvernight(hd, hf) || isCycle24h(hd, hf)) {
      end.setDate(end.getDate() + 1);
    }
    return [start, end.getTime()];
  }
  const endDate = dateFinForShift(d, hd, hf, dateFin);
  return [start, new Date(`${endDate}T${hf}:00`).getTime()];
}
