import type { User } from "@/domain/types/entities";
import { format, parseISO, isValid } from "date-fns";
import { fr } from "date-fns/locale";
import { canSeeSalaire } from "@/shared/lib/can";

export function formatDate(value: string | null | undefined) {
  if (!value) return "—";
  try {
    const d = parseISO(value);
    if (!isValid(d)) return value;
    return format(d, "dd MMM yyyy", { locale: fr });
  } catch {
    return value;
  }
}

export function formatDateTime(value: string | null | undefined) {
  if (!value) return "—";
  try {
    const d = parseISO(value);
    if (!isValid(d)) return value;
    return format(d, "dd MMM yyyy HH:mm", { locale: fr });
  } catch {
    return value;
  }
}

export function formatFcfa(value: string | number | null | undefined) {
  if (value === null || value === undefined || value === "") return "—";
  const n = typeof value === "string" ? Number(value) : value;
  if (Number.isNaN(n)) return String(value);
  return new Intl.NumberFormat("fr-CI", {
    style: "currency",
    currency: "XOF",
    maximumFractionDigits: 0,
  }).format(n);
}

const SALAIRE_MASK = "••••••";

/** Affiche un montant salarial ou le masque si l’utilisateur n’a pas les droits. */
export function formatSalaire(
  value: string | number | null | undefined,
  user: User | null | undefined,
) {
  if (!canSeeSalaire(user)) return SALAIRE_MASK;
  return formatFcfa(value);
}

export const MOIS_LABELS = [
  "Janvier",
  "Février",
  "Mars",
  "Avril",
  "Mai",
  "Juin",
  "Juillet",
  "Août",
  "Septembre",
  "Octobre",
  "Novembre",
  "Décembre",
] as const;

export function labelMoisAnnee(mois: number, annee: number) {
  const label = MOIS_LABELS[mois - 1] ?? String(mois);
  return `${label} ${annee}`;
}

export function labelize(value: string) {
  return value.replaceAll("_", " ");
}

const ROLE_LABELS: Record<string, string> = {
  developpeur: "Développeur",
  "super-admin": "Super admin",
  operation: "Opération",
  superviseur: "Opération",
  rh: "RH",
  commercial: "Commercial",
  comptable: "Comptable",
  agent: "Agent",
  controleur: "Contrôleur",
  administration: "Administration",
  // legacy
  rondier: "Contrôleur",
};

export function labelRole(value: string | null | undefined) {
  if (!value) return "Utilisateur";
  return ROLE_LABELS[value] ?? labelize(value);
}

const TYPE_AGENT_LABELS: Record<string, string> = {
  agent: "Agent posté",
  controleur: "Contrôleur",
  administration: "Administration",
  // legacy
  rondier: "Contrôleur",
};

export function labelTypeAgent(value: string | null | undefined) {
  if (!value) return "—";
  return TYPE_AGENT_LABELS[value] ?? labelize(value);
}

const AGENT_STATUT_LABELS: Record<string, string> = {
  disponible: "Disponible",
  en_activite: "En activité",
  conge: "Congé",
  malade: "Malade",
  suspendu: "Suspendu",
  archive: "Archivé",
  // legacy
  en_mission: "En activité",
  en_conge: "Congé",
};

export function labelAgentStatut(value: string) {
  return AGENT_STATUT_LABELS[value] ?? labelize(value);
}
