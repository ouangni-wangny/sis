import { z } from "zod";
import type { JourSemaine } from "@/domain/types/entities";
import {
  dateFinForShift,
  dayNightHalvesFromPoste,
  isCycle24h,
} from "@/domain/time/shift-interval";

const timeRegex = /^([01]\d|2[0-3]):[0-5]\d$/;

export const JOURS_SEMAINE: { value: JourSemaine; label: string; short: string }[] = [
  { value: "lundi", label: "Lundi", short: "lun" },
  { value: "mardi", label: "Mardi", short: "mar" },
  { value: "mercredi", label: "Mercredi", short: "mer" },
  { value: "jeudi", label: "Jeudi", short: "jeu" },
  { value: "vendredi", label: "Vendredi", short: "ven" },
  { value: "samedi", label: "Samedi", short: "sam" },
  { value: "dimanche", label: "Dimanche", short: "dim" },
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

export const agentPlanningSlotSchema = z.object({
  agent_id: z.string(),
  /** Quart choisi par agent (mode Matin/Soir ou Jour/Nuit). */
  quart: z.enum(["jour", "nuit"]),
  jours_travailles: z
    .array(z.enum(JOUR_VALUES))
    .min(1, "Sélectionnez au moins un jour travaillé"),
  jour_repos: z.enum([...JOUR_VALUES, ""]).optional(),
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

    if (v.repartition === "moities" && v.slots.length >= 2) {
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

export function weekdayIndex(jour: JourSemaine) {
  return JOUR_INDEX[jour];
}
