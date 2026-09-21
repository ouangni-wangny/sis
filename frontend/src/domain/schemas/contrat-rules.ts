export type TypeContrat = "cdi" | "cdd" | "prestation" | "stage";

export type ReglesTypeContrat = {
  needsDateFin: boolean;
  dureeMaxMois: number | null;
  essaiVisible: boolean;
  essaiDefautMois: number | null;
  essaiMaxMois: number | null;
};

const REGLES: Record<TypeContrat, ReglesTypeContrat> = {
  cdi: {
    needsDateFin: false,
    dureeMaxMois: null,
    essaiVisible: true,
    essaiDefautMois: 3,
    essaiMaxMois: 6,
  },
  cdd: {
    needsDateFin: true,
    dureeMaxMois: 24,
    essaiVisible: true,
    essaiDefautMois: 1,
    essaiMaxMois: 2,
  },
  stage: {
    needsDateFin: true,
    dureeMaxMois: 12,
    essaiVisible: false,
    essaiDefautMois: null,
    essaiMaxMois: null,
  },
  prestation: {
    needsDateFin: true,
    dureeMaxMois: 36,
    essaiVisible: false,
    essaiDefautMois: null,
    essaiMaxMois: null,
  },
};

export function getReglesTypeContrat(type: TypeContrat): ReglesTypeContrat {
  return REGLES[type];
}

function parseIsoDate(iso: string): Date {
  return new Date(`${iso.slice(0, 10)}T12:00:00`);
}

function addMonths(date: Date, months: number): Date {
  const d = new Date(date);
  d.setMonth(d.getMonth() + months);
  return d;
}

function toIsoDate(d: Date): string {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${y}-${m}-${day}`;
}

/** Ajoute N mois à une date ISO (YYYY-MM-DD). */
function addMonthsToIso(dateIso: string, months: number): string {
  return toIsoDate(addMonths(parseIsoDate(dateIso), months));
}

export function validateContratDateFin(
  type: TypeContrat,
  dateDebut: string,
  dateFin?: string | null,
): string | null {
  const rules = getReglesTypeContrat(type);

  if (rules.needsDateFin && !dateFin) {
    return `Date de fin obligatoire pour un ${type.toUpperCase()}.`;
  }

  if (!dateFin) return null;

  if (dateFin < dateDebut) {
    return "La date de fin doit être ≥ la date de début.";
  }

  if (rules.dureeMaxMois != null) {
    const limite = addMonths(parseIsoDate(dateDebut), rules.dureeMaxMois);
    if (parseIsoDate(dateFin) > limite) {
      return `La durée maximale d’un contrat ${type.toUpperCase()} est de ${rules.dureeMaxMois} mois (fin au plus tard le ${toIsoDate(limite)}).`;
    }
  }

  return null;
}

export function validateContratPeriodeEssai(
  type: TypeContrat,
  periodeEssaiMois?: string | number | null,
): string | null {
  const rules = getReglesTypeContrat(type);

  if (!rules.essaiVisible) {
    const n =
      periodeEssaiMois === "" || periodeEssaiMois == null
        ? 0
        : Number(periodeEssaiMois);
    if (Number.isFinite(n) && n > 0) {
      return "La période d’essai ne s’applique pas à ce type de contrat.";
    }
    return null;
  }

  if (periodeEssaiMois === "" || periodeEssaiMois == null) return null;

  const mois = Number(periodeEssaiMois);
  if (!Number.isFinite(mois) || mois < 0) {
    return "La période d’essai ne peut pas être négative.";
  }

  if (rules.essaiMaxMois != null && mois > rules.essaiMaxMois) {
    return `La période d’essai maximale pour un ${type.toUpperCase()} est de ${rules.essaiMaxMois} mois.`;
  }

  return null;
}

export function hintDateFin(type: TypeContrat): string {
  const rules = getReglesTypeContrat(type);
  if (!rules.needsDateFin) {
    return "Optionnelle pour un CDI (départ ou fin prévue).";
  }
  if (rules.dureeMaxMois != null) {
    return `Obligatoire — durée maximale ${rules.dureeMaxMois} mois.`;
  }
  return "Obligatoire pour ce type de contrat.";
}

export function formatContratDuree(mois: number | null | undefined): string {
  if (mois == null) return "—";
  return `${mois} mois`;
}

export function hintPeriodeEssai(type: TypeContrat): string | null {
  const rules = getReglesTypeContrat(type);
  if (!rules.essaiVisible) return null;
  if (rules.essaiMaxMois != null) {
    return `Maximum ${rules.essaiMaxMois} mois pour un ${type.toUpperCase()}.`;
  }
  return null;
}

export function hintPeriodeEssaiVerrouillee(type: TypeContrat): string {
  if (type === "stage" || type === "prestation") {
    return "Non applicable — réservée aux CDI et CDD.";
  }
  return "Non applicable pour ce type de contrat.";
}

export type ContratReminder = {
  key: string;
  label: string;
  tone: "warning" | "danger";
};

const ESSAI_ALERT_DAYS = 14;
const FIN_ALERT_DAYS = 30;

export function getContratReminders(input: {
  statut: string;
  date_debut: string;
  date_fin?: string | null;
  periode_essai_mois?: number | null;
}): ContratReminder[] {
  if (input.statut !== "actif") return [];

  const today = new Date();
  today.setHours(12, 0, 0, 0);
  const reminders: ContratReminder[] = [];

  const essaiMois = input.periode_essai_mois ?? 0;
  if (essaiMois > 0) {
    const finEssai = addMonths(parseIsoDate(input.date_debut), essaiMois);
    const diffDays = Math.ceil(
      (finEssai.getTime() - today.getTime()) / (1000 * 60 * 60 * 24),
    );
    if (diffDays >= 0 && diffDays <= ESSAI_ALERT_DAYS) {
      reminders.push({
        key: "essai",
        label: "Essai bientôt fini",
        tone: diffDays <= 7 ? "danger" : "warning",
      });
    }
  }

  if (input.date_fin) {
    const fin = parseIsoDate(input.date_fin);
    const diffDays = Math.ceil(
      (fin.getTime() - today.getTime()) / (1000 * 60 * 60 * 24),
    );
    if (diffDays >= 0 && diffDays <= FIN_ALERT_DAYS) {
      reminders.push({
        key: "fin",
        label: "Contrat bientôt fini",
        tone: diffDays <= 14 ? "danger" : "warning",
      });
    }
  }

  return reminders;
}

export function contratNeedsSurveillance(input: {
  statut: string;
  date_debut: string;
  date_fin?: string | null;
  periode_essai_mois?: number | null;
}): boolean {
  return getContratReminders(input).length > 0;
}
