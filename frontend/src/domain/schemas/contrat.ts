import { z } from "zod";
import {
  validateContratDateFin,
  validateContratPeriodeEssai,
  type TypeContrat,
} from "./contrat-rules";

const money = z.union([z.string(), z.number()]).optional().or(z.literal(""));

export const contratSchema = z
  .object({
    agent_id: z.string().uuid("Agent requis"),
    type: z.enum(["cdi", "cdd", "prestation", "stage"]),
    reference: z.string().optional().or(z.literal("")),
    date_debut: z.string().min(1, "Date de début requise"),
    date_fin: z.string().optional().or(z.literal("")),
    periode_essai_mois: money,
    salaire_base: money,
    indemnite_fonction: money,
    prime_responsabilite: money,
    prime_transport: money,
    prime_entretien_tenue: money,
  sursalaire: money,
  parts_igr: money,
  statut: z.enum(["actif", "suspendu", "termine", "resilie"]),
})
  .superRefine((values, ctx) => {
    const type = values.type as TypeContrat;

    const dateFinError = validateContratDateFin(
      type,
      values.date_debut,
      values.date_fin,
    );
    if (dateFinError) {
      ctx.addIssue({
        code: "custom",
        path: ["date_fin"],
        message: dateFinError,
      });
    }

    const essaiError = validateContratPeriodeEssai(
      type,
      values.periode_essai_mois,
    );
    if (essaiError) {
      ctx.addIssue({
        code: "custom",
        path: ["periode_essai_mois"],
        message: essaiError,
      });
    }
  });

export type ContratFormValues = z.infer<typeof contratSchema>;

export const STATUT_CONTRAT_FILTER_OPTIONS = [
  { value: "", label: "Tous les statuts" },
  { value: "actif", label: "Actif" },
  { value: "suspendu", label: "Suspendu" },
  { value: "termine", label: "Terminé" },
  { value: "resilie", label: "Résilié" },
] as const;

export const TYPE_CONTRAT_FILTER_OPTIONS = [
  { value: "", label: "Tous les types" },
  { value: "cdi", label: "CDI" },
  { value: "cdd", label: "CDD" },
  { value: "prestation", label: "Prestation" },
  { value: "stage", label: "Stage" },
] as const;

/** Aperçu client des calculs RH CI (aligné sur le backend). */
export function previewRemunerationCi(input: {
  salaire_base?: string | number | null;
  indemnite_fonction?: string | number | null;
  prime_responsabilite?: string | number | null;
  prime_transport?: string | number | null;
  prime_entretien_tenue?: string | number | null;
  sursalaire?: string | number | null;
  nombre_enfants?: string | number | null;
  situation_matrimoniale?: string | null;
  parts_igr?: string | number | null;
}) {
  const n = (v: string | number | null | undefined) => {
    if (v === "" || v == null) return 0;
    const x = Number(v);
    return Number.isFinite(x) ? x : 0;
  };

  const brut =
    n(input.salaire_base) +
    n(input.indemnite_fonction) +
    n(input.prime_responsabilite) +
    n(input.prime_transport) +
    n(input.prime_entretien_tenue) +
    n(input.sursalaire);

  const enfants = Math.max(0, Math.min(12, Math.floor(n(input.nombre_enfants))));
  let parts =
    input.parts_igr !== "" && input.parts_igr != null
      ? n(input.parts_igr)
      : input.situation_matrimoniale === "marie"
        ? 2
        : input.situation_matrimoniale === "veuf" && enfants > 0
          ? 1.5
          : 1;
  if (input.parts_igr === "" || input.parts_igr == null) {
    parts += enfants * 0.5;
  }
  parts = Math.min(5, Math.max(1, Math.round(parts * 10) / 10));

  const cnpsAssiette = Math.min(brut, 1_645_315);
  const cnps = Math.round(cnpsAssiette * 0.063 * 100) / 100;

  // IGR simplifié pour l’aperçu UI (le backend reste la source de vérité)
  const revenuAnnuel = brut * 12 * 0.8;
  const quotient = parts > 0 ? revenuAnnuel / parts : 0;
  const tranches: Array<{ max: number | null; rate: number }> = [
    { max: 600_000, rate: 0 },
    { max: 1_560_000, rate: 0.1 },
    { max: 2_400_000, rate: 0.15 },
    { max: 3_240_000, rate: 0.2 },
    { max: 4_260_000, rate: 0.25 },
    { max: null, rate: 0.3 },
  ];
  let tax = 0;
  let prev = 0;
  for (const t of tranches) {
    const ceiling = t.max ?? quotient;
    if (quotient <= prev) break;
    const taxable = Math.min(quotient, ceiling) - prev;
    if (taxable > 0) tax += taxable * t.rate;
    if (t.max == null || quotient <= t.max) break;
    prev = t.max;
  }
  const igr = Math.round(((tax * parts) / 12) * 100) / 100;
  const net = Math.max(0, Math.round((brut - cnps - igr) * 100) / 100);

  return {
    salaire_brut: brut,
    parts_igr: parts,
    montant_igr: igr,
    retenue_cnps: cnps,
    salaire_net: net,
  };
}
