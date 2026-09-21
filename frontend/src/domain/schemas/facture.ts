import { z } from "zod";

const ligneSchema = z.object({
  offre_id: z.string().uuid().optional().or(z.literal("")),
  code_article: z.string().optional().or(z.literal("")),
  description: z.string().trim().min(1, "Désignation requise").max(500),
  quantite: z.coerce.number().positive("Quantité > 0"),
  prix_unitaire: z.coerce.number().min(0, "Prix invalide"),
});

export const proformaFactureSchema = z
  .object({
    client_id: z.string().uuid().optional().or(z.literal("")),
    client_nom: z.string().max(255).optional().or(z.literal("")),
    client_adresse: z.string().max(1000).optional().or(z.literal("")),
    client_telephone: z.string().max(50).optional().or(z.literal("")),
    client_email: z
      .string()
      .email("Email invalide")
      .optional()
      .or(z.literal("")),
    abonnement_id: z.string().uuid().optional().or(z.literal("")),
    creer_abonnement: z.boolean().optional(),
    site_id: z.string().uuid().optional().or(z.literal("")),
    periodicite: z
      .enum(["mensuel", "trimestriel", "annuel"])
      .optional()
      .or(z.literal("")),
    date_debut_service: z.string().optional().or(z.literal("")),
    date_fin_service: z.string().optional().or(z.literal("")),
    delai_paiement_jours: z.coerce
      .number({ error: "Délai de paiement requis" })
      .refine((v) => [0, 15, 30, 45, 60].includes(v), "Délai de paiement invalide"),
    notes: z.string().optional().or(z.literal("")),
    conditions_paiement: z.string().optional().or(z.literal("")),
    delai_validite: z.string().optional().or(z.literal("")),
    duree_contrat_min: z.string().optional().or(z.literal("")),
    signataire_nom: z.string().optional().or(z.literal("")),
    signataire_fonction: z.string().optional().or(z.literal("")),
    lignes: z.array(ligneSchema).min(1, "Ajoutez au moins une ligne"),
  })
  .superRefine((values, ctx) => {
    const hasClient = Boolean(values.client_id);
    const hasNom = Boolean(values.client_nom?.trim());

    if (!hasClient && !hasNom) {
      ctx.addIssue({
        code: "custom",
        message: "Sélectionnez un client ou saisissez le nom du destinataire",
        path: ["client_id"],
      });
      ctx.addIssue({
        code: "custom",
        message: "Nom du destinataire requis si ce n’est pas un client du système",
        path: ["client_nom"],
      });
    }

    if (!hasClient && values.abonnement_id) {
      ctx.addIssue({
        code: "custom",
        message: "Un abonnement nécessite un client du système",
        path: ["abonnement_id"],
      });
    }

    const offreIds = [
      ...new Set(
        (values.lignes ?? [])
          .map((l) => l.offre_id)
          .filter((id): id is string => Boolean(id)),
      ),
    ];

    if (values.creer_abonnement) {
      if (!hasClient) {
        ctx.addIssue({
          code: "custom",
          message: "Un abonnement nécessite un client du système",
          path: ["client_id"],
        });
      }
      if (offreIds.length === 0) {
        ctx.addIssue({
          code: "custom",
          message: "Choisissez une offre sur au moins une ligne du devis",
          path: ["lignes"],
        });
      }
      if (!values.periodicite) {
        ctx.addIssue({
          code: "custom",
          message: "Périodicité requise",
          path: ["periodicite"],
        });
      }
    }
  });

export type ProformaFactureFormValues = z.infer<typeof proformaFactureSchema>;
/** Forme brute des champs avant coercion (quantite/prix_unitaire saisis via inputs) — à utiliser comme TFieldValues de useForm. */
export type ProformaFactureFormInput = z.input<typeof proformaFactureSchema>;

/** @deprecated Conservé pour l’ancien endpoint /factures/generer */
export const genererFactureSchema = z.object({
  client_id: z.string().uuid("Client requis"),
  date_debut: z.string().min(1, "Date de début requise"),
  date_fin: z.string().min(1, "Date de fin requise"),
});

export type GenererFactureFormValues = z.infer<typeof genererFactureSchema>;

export const PERIODICITE_FACTURE_FILTER_OPTIONS = [
  { value: "", label: "Toutes périodicités" },
  { value: "mensuel", label: "Mensuel" },
  { value: "trimestriel", label: "Trimestriel" },
  { value: "annuel", label: "Annuel" },
] as const;

export const STATUT_PAIEMENT_FACTURE_FILTER_OPTIONS = [
  { value: "", label: "Tous paiements" },
  { value: "non_payee", label: "Non payée" },
  { value: "partiel", label: "Partiel" },
  { value: "soldee", label: "Soldée" },
] as const;
