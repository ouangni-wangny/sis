import { z } from "zod";
import type { JourSemaine, Poste, Vacation } from "@/domain/types/entities";
import {
  dateFinForShift,
  dayNightHalvesFromPoste,
  isCycle24h,
} from "@/domain/time/shift-interval";

const timeRegex = /^([01]\d|2[0-3]):[0-5]\d$/;

export const JOURS_SEMAINE: { value: JourSemaine; label: string; short: string }[] = [
  { value: "lundi", label: "Lundi", short: "Lun" },
  { value: "mardi", label: "Mardi", short: "Mar" },
  { value: "mercredi", label: "Mercredi", short: "Mer" },
  { value: "jeudi", label: "Jeudi", short: "Jeu" },
  { value: "vendredi", label: "Vendredi", short: "Ven" },
  { value: "samedi", label: "Samedi", short: "Sam" },
  { value: "dimanche", label: "Dimanche", short: "Dim" },
];

const JOUR_VALUES = [
  "lundi",
  "mardi",
  "mercredi",
  "jeudi",
  "vendredi",
  "samedi",
  "dimanche",
] as const;

const JOUR_INDEX: Record<JourSemaine, number> = {
  dimanche: 0,
  lundi: 1,
  mardi: 2,
  mercredi: 3,
  jeudi: 4,
  vendredi: 5,
  samedi: 6,
};

export const DEFAULT_JOURS_TRAVAILLES: JourSemaine[] = [...JOUR_VALUES];

export function frenchWeekdayFromIso(dateIso: string): JourSemaine {
  const names: JourSemaine[] = [
    "dimanche",
    "lundi",
    "mardi",
    "mercredi",
    "jeudi",
    "vendredi",
    "samedi",
  ];
  return names[new Date(`${dateIso.slice(0, 10)}T12:00:00`).getDay()];
}

/**
 * Déduit les jours travaillés depuis des dates de vacations existantes.
 * Ex. si aucune vacation un mardi → mardi = repos.
 *
 * @param weekStartMondayIso Si fourni (lundi Y-m-d), ne considère que cette semaine.
 *   Utile en mode alternance : la rotation hebdo fait apparaître tous les jours
 *   sur la période complète, ce qui fausse le motif de base.
 */
export function inferJoursTravaillesFromDates(
  dates: string[],
  weekStartMondayIso?: string | null,
): JourSemaine[] | null {
  let filtered = dates.map((d) => d.slice(0, 10));
  if (weekStartMondayIso) {
    const start = weekStartMondayIso.slice(0, 10);
    const end = addDaysToIsoDate(start, 6);
    filtered = filtered.filter((d) => d >= start && d <= end);
  }
  if (filtered.length === 0) return null;
  const present = new Set(filtered.map(frenchWeekdayFromIso));
  if (present.size === 0) return null;
  return DEFAULT_JOURS_TRAVAILLES.filter((j) => present.has(j));
}

/** Lundi (Y-m-d) de la semaine ISO contenant `dateIso`. */
export function mondayOfIsoWeek(dateIso: string): string {
  const d = new Date(`${dateIso.slice(0, 10)}T12:00:00`);
  const mondayOffset = (d.getDay() + 6) % 7;
  d.setDate(d.getDate() - mondayOffset);
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${y}-${m}-${day}`;
}

function addDaysToIsoDate(iso: string, days: number): string {
  const d = new Date(`${iso.slice(0, 10)}T12:00:00`);
  d.setDate(d.getDate() + days);
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${y}-${m}-${day}`;
}

/** Jour ISO 1=lundi … 7=dimanche. */
export function isoJourFromDate(dateIso: string): number {
  const d = new Date(`${dateIso.slice(0, 10)}T12:00:00`).getDay();
  return d === 0 ? 7 : d;
}

/** Année / numéro de semaine ISO + lundi de la semaine. */
export function isoWeekFromDate(dateIso: string): {
  annee_iso: number;
  semaine_iso: number;
  lundi: string;
  dimanche: string;
} {
  const lundi = mondayOfIsoWeek(dateIso);
  const thursday = addDaysToIsoDate(lundi, 3);
  const annee_iso = Number(thursday.slice(0, 4));
  const jan4 = `${annee_iso}-01-04`;
  const week1Monday = mondayOfIsoWeek(jan4);
  const lundiMs = new Date(`${lundi}T12:00:00`).getTime();
  const week1Ms = new Date(`${week1Monday}T12:00:00`).getTime();
  const semaine_iso = Math.floor((lundiMs - week1Ms) / 86_400_000 / 7) + 1;
  return {
    annee_iso,
    semaine_iso,
    lundi,
    dimanche: addDaysToIsoDate(lundi, 6),
  };
}

/** Lundi (Y-m-d) de la semaine ISO donnée. */
export function lundiFromIsoWeek(annee_iso: number, semaine_iso: number): string {
  const jan4 = `${annee_iso}-01-04`;
  const week1Monday = mondayOfIsoWeek(jan4);
  return addDaysToIsoDate(week1Monday, (semaine_iso - 1) * 7);
}

/** Décale une semaine ISO de `delta` semaines. */
export function shiftIsoWeek(
  annee_iso: number,
  semaine_iso: number,
  delta: number,
): { annee_iso: number; semaine_iso: number; lundi: string; dimanche: string } {
  const lundi = addDaysToIsoDate(lundiFromIsoWeek(annee_iso, semaine_iso), delta * 7);
  return isoWeekFromDate(lundi);
}

/**
 * Semaine de référence pour déduire le motif d’alternance :
 * première semaine (lundi→dimanche) avec le plus de jours distincts couverts.
 */
export function pickAlternanceReferenceWeekStart(
  dates: string[],
): string | null {
  if (dates.length === 0) return null;
  const sorted = [
    ...new Set(dates.map((d) => d.slice(0, 10))),
  ].sort();
  const mondays = [...new Set(sorted.map(mondayOfIsoWeek))].sort();
  let best: { monday: string; score: number } | null = null;
  for (const monday of mondays) {
    const end = addDaysToIsoDate(monday, 6);
    const inWeek = sorted.filter((d) => d >= monday && d <= end);
    const score = new Set(inWeek.map(frenchWeekdayFromIso)).size;
    if (!best || score > best.score) {
      best = { monday, score };
    }
    if (score >= 7) break;
  }
  return best?.monday ?? null;
}

const CELLULES = ["jour", "nuit", "les_deux", "repos"] as const;
export type CellulePlanning = (typeof CELLULES)[number];
/** Programme d’un agent, jour par jour (clé Y-m-d). */
export type CellulesPlanning = Record<string, CellulePlanning>;

export const agentPlanningSlotSchema = z.object({
  agent_id: z.string(),
  /** Quart choisi par agent (mode Matin/Soir ou Jour/Nuit). */
  quart: z.enum(["jour", "nuit"]),
  jours_travailles: z
    .array(z.enum(JOUR_VALUES))
    .min(1, "Sélectionnez au moins un jour travaillé"),
  jour_repos: z.enum([...JOUR_VALUES, ""]).optional(),
  /** Le planning réel sur la période : les jours ci-dessus ne servent qu’à le remplir. */
  cellules: z.record(z.string(), z.enum(CELLULES)).optional(),
});

export type AgentPlanningSlot = z.infer<typeof agentPlanningSlotSchema>;

export function emptyPlanningSlot(
  agentId = "",
  quart: "jour" | "nuit" = "jour",
): AgentPlanningSlot {
  return {
    agent_id: agentId,
    quart,
    jours_travailles: [...DEFAULT_JOURS_TRAVAILLES],
    jour_repos: "",
  };
}

/** Slot prérempli : fiche agent (jour_repos) ou rythme déduit des vacations. */
export function slotFromAgentRepos(
  agentId: string,
  jourRepos?: JourSemaine | null,
  vacationDates?: string[],
  quart: "jour" | "nuit" = "jour",
  /** Lundi de référence (alternance : une seule semaine pour le motif). */
  referenceWeekStart?: string | null,
): AgentPlanningSlot {
  if (jourRepos) {
    return {
      agent_id: agentId,
      quart,
      jours_travailles: DEFAULT_JOURS_TRAVAILLES.filter((j) => j !== jourRepos),
      jour_repos: jourRepos,
    };
  }
  const inferred = inferJoursTravaillesFromDates(
    vacationDates ?? [],
    referenceWeekStart,
  );
  if (inferred && inferred.length > 0) {
    return {
      agent_id: agentId,
      quart,
      jours_travailles: inferred,
      jour_repos: "",
    };
  }
  return emptyPlanningSlot(agentId, quart);
}

export const agentPlanningSchema = z
  .object({
    /** Un slot = un agent + son rythme (jours / repos). */
    slots: z
      .array(agentPlanningSlotSchema)
      .min(1, "Sélectionnez les agents du poste"),
    site_id: z.string().uuid("Site requis"),
    poste_id: z.string().uuid("Poste requis"),
    date_debut: z.string().min(1, "Date de début requise"),
    date_fin: z.string().min(1, "Date de fin requise"),
    /** jour_entier = même horaire pour tous ; moities = A/B partagent la journée (2 agents). */
    repartition: z.enum(["jour_entier", "moities"]),
    /** Sans quarts : ensemble = mêmes jours OK ; alternance = un agent / jour. */
    mode_effectif: z.enum(["ensemble", "alternance"]).optional(),
    quart: z.enum(["jour", "nuit", "les_deux"]),
    heure_debut: z
      .string()
      .regex(timeRegex, "Format HH:mm")
      .optional()
      .or(z.literal("")),
    heure_fin: z
      .string()
      .regex(timeRegex, "Format HH:mm")
      .optional()
      .or(z.literal("")),
    heure_debut_nuit: z
      .string()
      .regex(timeRegex, "Format HH:mm")
      .optional()
      .or(z.literal("")),
    heure_fin_nuit: z
      .string()
      .regex(timeRegex, "Format HH:mm")
      .optional()
      .or(z.literal("")),
  })
  .superRefine((v, ctx) => {
    if (v.date_fin < v.date_debut) {
      ctx.addIssue({
        code: "custom",
        path: ["date_fin"],
        message: "La fin doit être ≥ au début",
      });
    }

    const filled = v.slots.map((s) => s.agent_id).filter(Boolean);
    if (filled.length === 0) {
      ctx.addIssue({
        code: "custom",
        path: ["slots"],
        message: "Sélectionnez au moins un agent",
      });
    }

    for (let i = 0; i < v.slots.length; i++) {
      const slot = v.slots[i];
      if (!slot.agent_id) {
        ctx.addIssue({
          code: "custom",
          path: ["slots", i, "agent_id"],
          message: "Agent requis",
        });
      } else if (!z.string().uuid().safeParse(slot.agent_id).success) {
        ctx.addIssue({
          code: "custom",
          path: ["slots", i, "agent_id"],
          message: "Agent invalide",
        });
      }

      if (slot.jours_travailles.length === 0) {
        ctx.addIssue({
          code: "custom",
          path: ["slots", i, "jours_travailles"],
          message: "Sélectionnez au moins un jour travaillé",
        });
      }

      if (slot.jour_repos && slot.jours_travailles.includes(slot.jour_repos)) {
        ctx.addIssue({
          code: "custom",
          path: ["slots", i, "jour_repos"],
          message: "Le jour de repos ne peut pas être un jour travaillé",
        });
      }
    }

    const parCellules = v.slots.every((s) => s.cellules);

    if (!parCellules && v.repartition === "moities" && v.slots.length >= 2) {
      const q0 = v.slots[0]?.quart;
      const q1 = v.slots[1]?.quart;
      if (q0 && q1 && q0 === q1) {
        ctx.addIssue({
          code: "custom",
          path: ["slots", 1, "quart"],
          message:
            "En couverture matin/soir, les deux agents doivent avoir des quarts différents (Jour et Nuit).",
        });
      }
    }

    // Alternance (sans quarts) : un seul agent par jour — jours complémentaires.
    if (
      parCellules &&
      v.repartition === "jour_entier" &&
      v.mode_effectif === "alternance" &&
      v.slots.length >= 2
    ) {
      const seen = new Set<string>();
      for (let i = 0; i < v.slots.length; i++) {
        for (const [date, cellule] of Object.entries(v.slots[i].cellules ?? {})) {
          if (cellule === "repos" || date < v.date_debut || date > v.date_fin) continue;
          if (seen.has(date)) {
            ctx.addIssue({
              code: "custom",
              path: ["slots", i, "jours_travailles"],
              message: `En mode alternance, un seul agent peut travailler le ${date}.`,
            });
            break;
          }
          seen.add(date);
        }
      }
    }

    if (
      !parCellules &&
      v.repartition === "jour_entier" &&
      v.mode_effectif === "alternance" &&
      v.slots.length >= 2
    ) {
      const seen = new Map<string, number>();
      for (let i = 0; i < v.slots.length; i++) {
        for (const jour of v.slots[i].jours_travailles) {
          const prev = seen.get(jour);
          if (prev !== undefined) {
            ctx.addIssue({
              code: "custom",
              path: ["slots", i, "jours_travailles"],
              message:
                "En mode alternance, un seul agent peut travailler ce jour — choisissez des jours complémentaires.",
            });
            break;
          }
          seen.set(jour, i);
        }
      }
    }

    if (filled.length !== new Set(filled).size) {
      ctx.addIssue({
        code: "custom",
        path: ["slots"],
        message: "Le même agent ne peut pas être choisi deux fois",
      });
    }
  });

export type AgentPlanningFormValues = z.infer<typeof agentPlanningSchema>;

export type PlannedShift = {
  date: string;
  date_fin: string | null;
  heure_debut: string;
  heure_fin: string;
  quart: "jour" | "nuit";
  weekday: JourSemaine;
};

export type AgentShiftHours = {
  heure_debut: string;
  heure_fin: string;
  /** Couleur calendrier / type de créneau. */
  quart: "jour" | "nuit" | "les_deux";
  label: string;
  hint?: string;
};

/**
 * Horaires d’un agent selon l’effectif, la répartition et le quart choisi.
 * - jour_entier : même plage poste pour chaque agent
 * - moities : chaque agent choisit Jour ou Nuit (horaires du quart)
 */
export function hoursForAgentSlot(
  index: number,
  total: number,
  opts: {
    heure_debut: string;
    heure_fin: string;
    heure_debut_nuit?: string;
    heure_fin_nuit?: string;
    repartition?: "jour_entier" | "moities";
    /** Quart choisi pour cet agent (mode moities). */
    quart?: "jour" | "nuit";
  },
): AgentShiftHours {
  const letter = String.fromCharCode(65 + index);
  const wantMoities = opts.repartition === "moities" && total >= 2;
  const slotQuart = opts.quart ?? (index === 0 ? "jour" : "nuit");

  if (wantMoities) {
    const halves = dayNightHalvesFromPoste({
      heure_debut: opts.heure_debut,
      heure_fin: opts.heure_fin,
      heure_debut_nuit: opts.heure_debut_nuit,
      heure_fin_nuit: opts.heure_fin_nuit,
    });
    if (halves) {
      const half = slotQuart === "jour" ? halves.jour : halves.nuit;
      return {
        ...half,
        quart: slotQuart,
        label: `Agent ${letter}`,
        hint: `${slotQuart === "jour" ? "Jour" : "Nuit"} ${half.heure_debut}–${half.heure_fin}`,
      };
    }
  }

  const hasNuit = Boolean(
    opts.heure_debut_nuit?.trim() && opts.heure_fin_nuit?.trim(),
  );

  // 1 agent sur poste 24h découpé → jour + nuit (quarts réels, jamais le cycle brut).
  if (total <= 1 && hasNuit) {
    const halves = dayNightHalvesFromPoste({
      heure_debut: opts.heure_debut,
      heure_fin: opts.heure_fin,
      heure_debut_nuit: opts.heure_debut_nuit,
      heure_fin_nuit: opts.heure_fin_nuit,
    });
    const jour = halves?.jour ?? {
      heure_debut: opts.heure_debut,
      heure_fin: opts.heure_fin,
    };
    const nuit = halves?.nuit;
    return {
      heure_debut: jour.heure_debut,
      heure_fin: jour.heure_fin,
      quart: "les_deux",
      label: `Agent ${letter} (jour + nuit)`,
      hint: nuit
        ? `Jour ${jour.heure_debut}–${jour.heure_fin} · Nuit ${nuit.heure_debut}–${nuit.heure_fin}`
        : `${opts.heure_debut}–${opts.heure_fin}`,
    };
  }

  // 1 agent sur cycle 24h sans découpage : une vacation continue (API gère date_fin +1).
  if (total <= 1 && isCycle24h(opts.heure_debut, opts.heure_fin)) {
    return {
      heure_debut: opts.heure_debut,
      heure_fin: opts.heure_fin,
      quart: "jour",
      label: `Agent ${letter}`,
      hint: `24h (${opts.heure_debut} → ${opts.heure_fin})`,
    };
  }

  // Agent par jour : même horaire pour tous
  return {
    heure_debut: opts.heure_debut,
    heure_fin: opts.heure_fin,
    quart: "jour",
    label: `Agent ${letter}`,
    hint: `${opts.heure_debut}–${opts.heure_fin}`,
  };
}

function eachDateInclusive(from: string, to: string): string[] {
  const out: string[] = [];
  const cur = new Date(`${from}T12:00:00`);
  const end = new Date(`${to}T12:00:00`);
  while (cur <= end) {
    out.push(cur.toISOString().slice(0, 10));
    cur.setDate(cur.getDate() + 1);
  }
  return out;
}

/** Lundi de la semaine calendaire FR (lun→dim) contenant `dateIso`. */
export function mondayOfIso(dateIso: string): string {
  const d = new Date(`${dateIso.slice(0, 10)}T12:00:00`);
  const day = d.getDay(); // 0 = dimanche
  const diff = day === 0 ? -6 : 1 - day;
  d.setDate(d.getDate() + diff);
  return d.toISOString().slice(0, 10);
}

/**
 * Offset de semaine calendaire relatif à `dateDebutIso` (semaine 0 = celle de date_debut).
 */
export function weekOffsetFrom(dateIso: string, dateDebutIso: string): number {
  const a = new Date(`${mondayOfIso(dateIso)}T12:00:00`).getTime();
  const b = new Date(`${mondayOfIso(dateDebutIso)}T12:00:00`).getTime();
  return Math.round((a - b) / (7 * 24 * 60 * 60 * 1000));
}

/** Jours de la semaine absents de `jours` (complément lun…dim). */
export function complementJours(jours: JourSemaine[]): JourSemaine[] {
  const set = new Set(jours);
  return JOUR_VALUES.filter((j) => !set.has(j));
}

/**
 * Répartit lun…dim entre `agentCount` agents (1 jour chacun à tour de rôle).
 * Ex. 2 → lun/mer/ven/dim + mar/jeu/sam
 */
export function buildAlternanceMotifs(agentCount: number): JourSemaine[][] {
  const n = Math.max(1, Math.floor(agentCount));
  if (n === 1) return [[...JOUR_VALUES]];
  const motifs: JourSemaine[][] = Array.from({ length: n }, () => []);
  JOUR_VALUES.forEach((jour, i) => {
    motifs[i % n]!.push(jour);
  });
  return motifs;
}

/**
 * Motif de l’agent `slotIndex` pour la semaine `weekOffset`
 * (rotation circulaire pour équilibrer sur N semaines).
 */
export function joursForAlternanceWeek(
  motifs: JourSemaine[][],
  slotIndex: number,
  weekOffset: number,
): JourSemaine[] {
  const n = motifs.length;
  if (n === 0) return [];
  const idx = ((slotIndex + weekOffset) % n + n) % n;
  return motifs[idx] ?? [];
}

/** True si le slot a encore le défaut « tous les jours ». */
export function isDefaultJoursTravailles(jours: JourSemaine[] | undefined) {
  if (!jours || jours.length !== DEFAULT_JOURS_TRAVAILLES.length) return false;
  const set = new Set(jours);
  return DEFAULT_JOURS_TRAVAILLES.every((j) => set.has(j));
}

/** True si tous les slots sont encore au défaut (prêt pour préremplissage). */
export function needsAlternanceMotifPrefill(
  slots: { jours_travailles?: JourSemaine[] }[],
  agentCount: number,
) {
  if (agentCount < 2 || slots.length < agentCount) return false;
  return slots
    .slice(0, agentCount)
    .every((s) => isDefaultJoursTravailles(s.jours_travailles));
}

/** Développe le rythme en créneaux vacation (1 vacation / jour / quart). */
export function expandPlanningShifts(input: {
  date_debut: string;
  date_fin: string;
  jours_travailles: JourSemaine[];
  jour_repos?: JourSemaine | "" | null;
  quart: "jour" | "nuit" | "les_deux";
  heure_debut: string;
  heure_fin: string;
  heure_debut_nuit?: string;
  heure_fin_nuit?: string;
  /** En alternance : rotation des motifs chaque semaine (1 agent / jour). */
  mode_effectif?: "ensemble" | "alternance";
  /**
   * Motifs semaine 0 de tous les agents (ordre des slots).
   * Semaine k : l’agent `slotIndex` reçoit motifs[(slotIndex + k) % n].
   */
  alternanceMotifs?: JourSemaine[][];
  slotIndex?: number;
}): PlannedShift[] {
  const motifs =
    input.mode_effectif === "alternance" &&
    input.alternanceMotifs &&
    input.alternanceMotifs.length >= 2
      ? input.alternanceMotifs
      : null;
  const slotIndex = input.slotIndex ?? 0;
  const workBase = new Set(input.jours_travailles);
  const repos = input.jour_repos || null;
  const shifts: PlannedShift[] = [];

  for (const date of eachDateInclusive(input.date_debut, input.date_fin)) {
    const weekday = frenchWeekdayFromIso(date);
    if (repos && weekday === repos) continue;

    const work = motifs
      ? new Set(
          joursForAlternanceWeek(
            motifs,
            slotIndex,
            weekOffsetFrom(date, input.date_debut),
          ),
        )
      : workBase;
    if (!work.has(weekday)) continue;

    if (input.quart === "jour" || input.quart === "les_deux") {
      shifts.push({
        date,
        date_fin: dateFinForShift(date, input.heure_debut, input.heure_fin),
        heure_debut: input.heure_debut,
        heure_fin: input.heure_fin,
        quart: "jour",
        weekday,
      });
    }
    if (input.quart === "nuit") {
      const debut = input.heure_debut_nuit || input.heure_debut;
      const fin = input.heure_fin_nuit || input.heure_fin;
      shifts.push({
        date,
        date_fin: dateFinForShift(date, debut, fin),
        heure_debut: debut,
        heure_fin: fin,
        quart: "nuit",
        weekday,
      });
    } else if (
      input.quart === "les_deux" &&
      input.heure_debut_nuit &&
      input.heure_fin_nuit
    ) {
      shifts.push({
        date,
        date_fin: dateFinForShift(
          date,
          input.heure_debut_nuit,
          input.heure_fin_nuit,
        ),
        heure_debut: input.heure_debut_nuit,
        heure_fin: input.heure_fin_nuit,
        quart: "nuit",
        weekday,
      });
    }
  }

  return shifts;
}

export const datesDePeriode = eachDateInclusive;

/** Remplit la période en répétant un motif de semaine (alternance : motifs tournants). */
export function cellulesDepuisSemaine(input: {
  date_debut: string;
  date_fin: string;
  jours_travailles: JourSemaine[];
  travail: CellulePlanning;
  mode_effectif?: "ensemble" | "alternance";
  alternanceMotifs?: JourSemaine[][];
  slotIndex?: number;
}): CellulesPlanning {
  const cellules: CellulesPlanning = {};
  for (const date of eachDateInclusive(input.date_debut, input.date_fin)) {
    cellules[date] = "repos";
  }
  const travaille = expandPlanningShifts({
    ...input,
    quart: "jour",
    heure_debut: "06:00",
    heure_fin: "18:00",
  });
  for (const { date } of travaille) cellules[date] = input.travail;
  return cellules;
}

/**
 * Remplit la période avec un cycle indépendant de la semaine (ex. 2 jours
 * travaillés, 1 de repos). `decalage` fait démarrer l’agent plus loin dans le cycle.
 */
export function cellulesDepuisCycle(input: {
  date_debut: string;
  date_fin: string;
  travail: number;
  repos: number;
  decalage?: number;
  cellule: CellulePlanning;
}): CellulesPlanning {
  const total = Math.max(1, input.travail + input.repos);
  const decalage = input.decalage ?? 0;
  const cellules: CellulesPlanning = {};
  eachDateInclusive(input.date_debut, input.date_fin).forEach((date, i) => {
    cellules[date] = (i + decalage) % total < input.travail ? input.cellule : "repos";
  });
  return cellules;
}

/** Créneau tenu par le titulaire (cédé à un remplaçant compris), pas par un remplaçant. */
function estDuTitulaire(v: Vacation) {
  if (v.est_remplacement) return false;
  return v.statut !== "annulee" || Boolean(v.remplacee);
}

/** Programme enregistré d’un agent : ce que la grille doit réafficher à l’identique. */
export function cellulesEnregistrees(
  agentId: string,
  vacations: Vacation[],
  quartDe: (v: Vacation) => "jour" | "nuit",
): CellulesPlanning {
  const cellules: CellulesPlanning = {};
  for (const v of vacations) {
    if (v.agent_id !== agentId || !estDuTitulaire(v)) continue;
    const date = v.date_debut.slice(0, 10);
    const quart = quartDe(v);
    const deja = cellules[date];
    cellules[date] = deja && deja !== quart ? "les_deux" : quart;
  }
  return cellules;
}

/**
 * Titulaires du poste sur la période, dans un ordre stable (jour puis nuit, le
 * plus présent d’abord) : la même liste à chaque retour sur l’écran.
 */
export function titulairesDuPoste(
  vacations: Vacation[],
  dateDebut: string,
  dateFin: string,
  quartDe: (v: Vacation) => "jour" | "nuit",
): { id: string; quart: "jour" | "nuit"; dates: string[] }[] {
  const titulaires = vacations.filter(estDuTitulaire);
  const surLaPeriode = titulaires.filter((v) => {
    const date = v.date_debut.slice(0, 10);
    return date >= dateDebut && date <= dateFin;
  });
  const source = surLaPeriode.length > 0 ? surLaPeriode : titulaires;

  const parAgent = new Map<string, { jour: number; nuit: number; dates: string[] }>();
  for (const v of source) {
    const ligne = parAgent.get(v.agent_id) ?? { jour: 0, nuit: 0, dates: [] };
    ligne[quartDe(v)] += 1;
    ligne.dates.push(v.date_debut.slice(0, 10));
    parAgent.set(v.agent_id, ligne);
  }

  return [...parAgent.entries()]
    .map(([id, l]) => ({
      id,
      quart: (l.nuit > l.jour ? "nuit" : "jour") as "jour" | "nuit",
      dates: l.dates,
    }))
    .sort(
      (a, b) =>
        a.quart.localeCompare(b.quart) ||
        b.dates.length - a.dates.length ||
        a.id.localeCompare(b.id),
    );
}

/**
 * Agents du poste hors de la grille (ex. relève du jour de repos) : leurs créneaux
 * à venir, ceux qu’un enregistrement du poste effacerait s’ils n’étaient pas gardés.
 */
export function agentsEnPlus(
  vacations: Vacation[],
  agentsDeLaGrille: Set<string>,
  aujourdhui: string,
): { id: string; nom: string; creneaux: number }[] {
  const parAgent = new Map<string, { id: string; nom: string; creneaux: number }>();
  for (const v of vacations) {
    if (agentsDeLaGrille.has(v.agent_id) || v.est_remplacement) continue;
    if (v.date_debut.slice(0, 10) < aujourdhui) continue;
    if (v.statut !== "planifiee" && !(v.statut === "a_recouvrir" && v.absence_id)) continue;
    const ligne = parAgent.get(v.agent_id) ?? {
      id: v.agent_id,
      nom: v.agent ? `${v.agent.prenom} ${v.agent.nom}` : "Agent",
      creneaux: 0,
    };
    ligne.creneaux += 1;
    parAgent.set(v.agent_id, ligne);
  }
  return [...parAgent.values()].sort(
    (a, b) => b.creneaux - a.creneaux || a.id.localeCompare(b.id),
  );
}

/** Période à réafficher : la dernière enregistrée, tant qu’elle n’est pas terminée. */
export function periodeEnregistree(
  poste: Pick<Poste, "planning_date_debut" | "planning_date_fin">,
  aujourdhui: string,
): { debut: string; fin: string } | null {
  const debut = poste.planning_date_debut?.slice(0, 10);
  const fin = poste.planning_date_fin?.slice(0, 10);
  if (!debut || !fin || fin < aujourdhui) return null;
  return { debut, fin };
}

/**
 * Cases que l’enregistrement ne peut plus changer pour cet agent : le passé, ce
 * qui a démarré, ce qu’il a cédé à un remplaçant. Valeur : la raison affichée.
 */
export function joursVerrouilles(
  agentId: string,
  vacations: Vacation[],
  dates: string[],
  aujourdhui: string,
): Record<string, string> {
  const verrous: Record<string, string> = {};
  for (const date of dates) {
    if (date < aujourdhui) verrous[date] = "Date passée";
  }
  for (const v of vacations) {
    const date = v.date_debut.slice(0, 10);
    if (v.agent_id !== agentId) continue;
    if (v.statut === "en_cours" || v.statut === "terminee") {
      verrous[date] = "Vacation déjà démarrée";
    } else if (v.statut === "annulee" && v.remplacee) {
      const remplacant = vacations.find(
        (r) =>
          r.est_remplacement &&
          r.statut !== "annulee" &&
          r.date_debut.slice(0, 10) === date &&
          r.heure_debut === v.heure_debut,
      );
      verrous[date] = remplacant?.agent
        ? `Remplacé par ${remplacant.agent.prenom} ${remplacant.agent.nom}`
        : "Remplacé";
    }
  }
  return verrous;
}

/**
 * Complète la période sans toucher aux cases déjà connues : l’enregistré d’abord
 * (repos entre deux jours enregistrés), le rythme au-delà du dernier jour enregistré.
 */
export function completerCellules(
  actuelles: CellulesPlanning | undefined,
  dates: string[],
  enregistrees: CellulesPlanning,
  rythme: CellulesPlanning,
): CellulesPlanning {
  const dernier = Object.keys(enregistrees).sort().at(-1);
  const cellules: CellulesPlanning = { ...actuelles };
  for (const date of dates) {
    if (cellules[date]) continue;
    cellules[date] =
      enregistrees[date] ??
      (dernier && date <= dernier ? "repos" : (rythme[date] ?? "repos"));
  }
  return cellules;
}

/** Case suivante au clic : les choix possibles dépendent des quarts du poste. */
export function celluleSuivante(
  cellule: CellulePlanning,
  options: { decoupage: boolean; agentSeul: boolean },
): CellulePlanning {
  const ordre: CellulePlanning[] = !options.decoupage
    ? ["jour", "repos"]
    : options.agentSeul
      ? ["les_deux", "jour", "nuit", "repos"]
      : ["jour", "nuit", "repos"];
  return ordre[(ordre.indexOf(cellule) + 1) % ordre.length];
}

/** Créneaux à créer depuis la grille : une vacation par case travaillée (hors verrous). */
export function creneauxDepuisCellules(input: {
  cellules: CellulesPlanning;
  date_debut: string;
  date_fin: string;
  index: number;
  total: number;
  verrous?: Record<string, string>;
  heure_debut: string;
  heure_fin: string;
  heure_debut_nuit?: string;
  heure_fin_nuit?: string;
  repartition?: "jour_entier" | "moities";
}): PlannedShift[] {
  return eachDateInclusive(input.date_debut, input.date_fin).flatMap((date) => {
    const cellule = input.cellules[date];
    if (!cellule || cellule === "repos" || input.verrous?.[date]) return [];
    const hours = hoursForAgentSlot(input.index, input.total, {
      ...input,
      quart: cellule === "nuit" ? "nuit" : "jour",
    });
    const seul = hours.quart === "les_deux";
    return expandPlanningShifts({
      date_debut: date,
      date_fin: date,
      jours_travailles: [...JOUR_VALUES],
      quart: cellule,
      heure_debut: hours.heure_debut,
      heure_fin: hours.heure_fin,
      heure_debut_nuit: seul ? input.heure_debut_nuit : undefined,
      heure_fin_nuit: seul ? input.heure_fin_nuit : undefined,
    });
  });
}

export function weekdayIndex(jour: JourSemaine) {
  return JOUR_INDEX[jour];
}
