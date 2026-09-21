import { z } from "zod";

export const abonnementSchema = z.object({
  client_id: z.string().uuid("Client requis"),
  offre_id: z.string().uuid("Offre requise"),
  site_id: z.string().uuid().optional().or(z.literal("")),
  periodicite: z.enum(["mensuel", "trimestriel", "annuel"], {
    message: "Périodicité requise",
  }),
  date_debut: z.string().min(1, "Date de début requise"),
  date_fin: z.string().optional().or(z.literal("")),
  statut: z.enum(["actif", "suspendu", "resilie", "expire"]),
});

export type AbonnementFormValues = z.infer<typeof abonnementSchema>;

export const STATUT_ABONNEMENT_FILTER_OPTIONS = [
  { value: "", label: "Tous les statuts" },
  { value: "actif", label: "Actif" },
  { value: "suspendu", label: "Suspendu" },
  { value: "resilie", label: "Résilié" },
  { value: "expire", label: "Expiré" },
] as const;

export const PERIODICITE_ABONNEMENT_FILTER_OPTIONS = [
  { value: "", label: "Toutes périodicités" },
  { value: "mensuel", label: "Mensuel" },
  { value: "trimestriel", label: "Trimestriel" },
  { value: "annuel", label: "Annuel" },
] as const;
