import { cn } from "@/shared/lib/cn";
import type { HTMLAttributes } from "react";

type Tone =
  | "neutral"
  | "success"
  | "warning"
  | "danger"
  | "info"
  | "critical"
  | "accent";

const tones: Record<Tone, string> = {
  neutral: "bg-paper-muted text-ink-muted border-border",
  success: "bg-teal/15 text-teal-dark border-teal/25",
  warning: "bg-amber-50 text-warning border-warning/25",
  danger: "bg-red-50 text-danger border-danger/25",
  info: "bg-sky-50 text-sky-800 border-sky-200",
  critical: "bg-danger text-white border-danger",
  accent: "bg-brand-accent/10 text-brand-accent border-brand-accent/25",
};

export function Badge({
  tone = "neutral",
  className,
  ...props
}: HTMLAttributes<HTMLSpanElement> & { tone?: Tone }) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded border px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide",
        tones[tone],
        className,
      )}
      {...props}
    />
  );
}

/** Couleur de pastille / badge selon le statut métier. */
export function statusTone(statut: string): Tone {
  const key = statut.trim().toLowerCase();
  const map: Record<string, Tone> = {
    non_payee: "danger",
    partiel: "warning",
    soldee: "success",

    // positif / actif
    actif: "success",
    active: "success",
    disponible: "success",
    payee: "success",
    paye: "success",
    resolue: "success",
    resolu: "success",
    terminee: "success",
    termine: "success",
    valide: "success",
    validé: "success",
    confirme: "success",
    confirmee: "success",

    // en cours / attention
    ouverte: "warning",
    ouvert: "warning",
    planifiee: "info",
    planifie: "info",
    en_cours: "warning",
    en_mission: "info",
    en_activite: "info",
    en_attente: "warning",
    approuvee: "success",
    pending: "warning",
    proforma: "accent",
    emise: "info",
    brouillon: "neutral",
    conge: "neutral",
    en_conge: "neutral",
    malade: "warning",
    suspendu: "warning",
    suspendue: "warning",
    expire: "warning",
    expiree: "warning",

    // négatif
    annule: "danger",
    annulee: "danger",
    a_recouvrir: "danger",
    resilie: "danger",
    resiliee: "danger",
    refuse: "danger",
    refusee: "danger",
    inactif: "neutral",
    inactive: "neutral",
    archive: "neutral",
    archivee: "neutral",

    // gravité
    critique: "critical",
    haute: "danger",
    moyenne: "warning",
    basse: "info",
    faible: "info",

    // rôles / divers parfois affichés comme statut
    admin: "accent",
    manager: "info",
    agent: "neutral",
  };
  return map[key] ?? "neutral";
}

/** Classes utilitaires pour colorer un select / chip selon le statut. */
export function statusToneClasses(statut: string): string {
  const tone = statusTone(statut);
  return tones[tone];
}
