import { z } from "zod";

export const TYPE_ABSENCE_VALUES = [
  "conge",
  "maladie",
  "permission",
  "autre",
] as const;

export type TypeAbsence = (typeof TYPE_ABSENCE_VALUES)[number];

export const STATUT_ABSENCE_VALUES = [
  "en_attente",
  "approuvee",
  "refusee",
  "annulee",
] as const;

export type StatutAbsence = (typeof STATUT_ABSENCE_VALUES)[number];

export const TYPE_ABSENCE_OPTIONS = [
  { value: "conge", label: "Congé" },
  { value: "maladie", label: "Maladie" },
  { value: "permission", label: "Permission" },
  { value: "autre", label: "Autre" },
] as const;

export const STATUT_ABSENCE_FILTER_OPTIONS = [
  { value: "", label: "Tous les statuts" },
  { value: "en_attente", label: "En attente" },
  { value: "approuvee", label: "Approuvées" },
  { value: "refusee", label: "Refusées" },
  { value: "annulee", label: "Annulées" },
] as const;

export function labelTypeAbsence(type: string): string {
  return (
    TYPE_ABSENCE_OPTIONS.find((o) => o.value === type)?.label ?? type
  );
}

export function labelSourceAbsence(source?: string | null): string {
  if (source === "controle") return "Contrôle terrain";
  return "RH";
}

export function sourceAbsenceTone(
  source?: string | null,
): "warning" | "info" | "neutral" {
  if (source === "controle") return "warning";
  return "info";
}

export function isTerminalAbsenceStatut(statut: string): boolean {
  return statut === "refusee" || statut === "annulee";
}

export function allowedStatutsForForm(
  current: StatutAbsence | null,
  isCreate: boolean,
): StatutAbsence[] {
  if (isCreate) {
    return ["en_attente", "approuvee"];
  }

  if (!current || isTerminalAbsenceStatut(current)) {
    return [current ?? "en_attente"];
  }

  if (current === "en_attente") {
    return ["en_attente", "approuvee", "refusee", "annulee"];
  }

  if (current === "approuvee") {
    return ["approuvee", "annulee"];
  }

  return [current];
}

export function statutAbsenceOptions(
  current: StatutAbsence | null,
  isCreate: boolean,
) {
  const labels: Record<StatutAbsence, string> = {
    en_attente: "En attente",
    approuvee: "Approuvée",
    refusee: "Refusée",
    annulee: "Annulée",
  };

  return allowedStatutsForForm(current, isCreate).map((value) => ({
    value,
    label: labels[value],
  }));
}

export function computeAbsenceDurationDays(
  debut: string | null | undefined,
  fin: string | null | undefined,
): number | null {
  if (!debut || !fin) return null;
  const start = new Date(`${debut}T00:00:00`);
  const end = new Date(`${fin}T00:00:00`);
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return null;
  if (end < start) return null;
  const diff = end.getTime() - start.getTime();
  return Math.floor(diff / 86_400_000) + 1;
}

export function formatAbsenceDuration(
  debut: string | null | undefined,
  fin: string | null | undefined,
): string {
  const days = computeAbsenceDurationDays(debut, fin);
  if (days == null) return "—";
  return days <= 1 ? "1 jour" : `${days} jours`;
}

export const absenceSchema = z
  .object({
    agent_id: z.string().uuid("Agent requis"),
    type: z.enum(TYPE_ABSENCE_VALUES, { message: "Type requis" }),
    date_debut: z.string().min(1, "Date de début requise"),
    date_fin: z.string().min(1, "Date de fin requise"),
    motif: z.string().max(500, "500 caractères maximum").optional().or(z.literal("")),
    statut: z.enum(STATUT_ABSENCE_VALUES),
  })
  .superRefine((data, ctx) => {
    if (data.date_fin < data.date_debut) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ["date_fin"],
        message: "La date de fin doit être postérieure ou égale à la date de début.",
      });
    }
  });

export type AbsenceFormValues = z.infer<typeof absenceSchema>;

export type AbsenceSideEffectMeta = {
  vacations_marquees_a_recouvrir?: number;
  vacations_restaurees?: number;
};

export function absenceSideEffectMessage(
  data: AbsenceSideEffectMeta,
): string | null {
  const marked = data.vacations_marquees_a_recouvrir ?? 0;
  const restored = data.vacations_restaurees ?? 0;

  if (marked > 0 && restored > 0) {
    return `${marked} vacation(s) à recouvrir, ${restored} vacation(s) restaurée(s).`;
  }
  if (marked > 0) {
    return `${marked} vacation(s) marquée(s) à recouvrir.`;
  }
  if (restored > 0) {
    return `${restored} vacation(s) restaurée(s) au planning.`;
  }
  return null;
}
