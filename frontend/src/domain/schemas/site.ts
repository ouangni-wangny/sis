import { z } from "zod";
import {
  apply24hDecoupage,
  hasDecoupageQuarts,
  normalizePosteHoraires,
  releveFromPoste,
} from "@/domain/schemas/poste-horaires";

export const modeEffectifSchema = z.enum(["ensemble", "alternance"]);

export const sitePosteDraftSchema = z.object({
  nom: z.string().max(255),
  agents_requis: z.union([z.string(), z.number()]).optional().or(z.literal("")),
  heure_debut: z.string().optional().or(z.literal("")),
  heure_fin: z.string().optional().or(z.literal("")),
  heure_debut_nuit: z.string().optional().or(z.literal("")),
  heure_fin_nuit: z.string().optional().or(z.literal("")),
  mode_effectif: modeEffectifSchema.optional(),
});

export const siteSchema = z.object({
  client_id: z.string().uuid("Client requis"),
  zone_id: z.string().uuid("Zone requise"),
  nom: z.string().trim().min(1, "Nom requis").max(255),
  adresse: z.string().max(1000).optional().or(z.literal("")),
  interne: z.boolean().optional().default(false),
  postes: z.array(sitePosteDraftSchema),
});

export type SiteFormValues = z.infer<typeof siteSchema>;
export type SitePosteDraft = z.infer<typeof sitePosteDraftSchema>;
export type ModeEffectif = z.infer<typeof modeEffectifSchema>;

/** Résout le mode effectif envoyé à l’API (quarts / effectif 1 → ensemble). */
export function resolveModeEffectif(draft: {
  agents_requis?: string | number | null;
  heure_debut_nuit?: string | null;
  heure_fin_nuit?: string | null;
  mode_effectif?: ModeEffectif | null;
}): ModeEffectif {
  const effectif = Math.max(1, Number(draft.agents_requis) || 1);
  const hasQuarts = Boolean(
    draft.heure_debut_nuit?.toString().trim() &&
      draft.heure_fin_nuit?.toString().trim(),
  );
  if (effectif < 2 || hasQuarts) return "ensemble";
  return draft.mode_effectif === "alternance" ? "alternance" : "ensemble";
}

/** Payload horaires + mode : `null` explicite pour effacer les quarts en base. */
export function toPosteHoursPayload(draft: {
  heure_debut?: string | null;
  heure_fin?: string | null;
  heure_debut_nuit?: string | null;
  heure_fin_nuit?: string | null;
  agents_requis?: string | number | null;
  mode_effectif?: ModeEffectif | null;
}) {
  const effectif = Math.max(1, Number(draft.agents_requis) || 1);
  let hours = normalizePosteHoraires({
    heure_debut: draft.heure_debut || "",
    heure_fin: draft.heure_fin || "",
    heure_debut_nuit: draft.heure_debut_nuit || "",
    heure_fin_nuit: draft.heure_fin_nuit || "",
  });
  // 1 agent : pas de découpage quarts (cycle 24h ou plage simple).
  if (effectif < 2 && hasDecoupageQuarts(hours)) {
    hours = apply24hDecoupage(releveFromPoste(hours), false);
  }
  return {
    heure_debut: hours.heure_debut || null,
    heure_fin: hours.heure_fin || null,
    // Important : null (pas undefined) sinon le PUT n’efface pas les quarts.
    heure_debut_nuit: hours.heure_debut_nuit || null,
    heure_fin_nuit: hours.heure_fin_nuit || null,
    mode_effectif: resolveModeEffectif({
      ...draft,
      heure_debut_nuit: hours.heure_debut_nuit,
      heure_fin_nuit: hours.heure_fin_nuit,
    }),
  };
}

export function toPostesPayload(postes: SitePosteDraft[] | undefined) {
  return (postes ?? [])
    .filter((p) => p.nom.trim().length > 0)
    .map((p) => {
      const hours = toPosteHoursPayload(p);
      return {
        nom: p.nom.trim(),
        agents_requis:
          p.agents_requis === "" || p.agents_requis === undefined
            ? undefined
            : Number(p.agents_requis),
        ...hours,
      };
    });
}
