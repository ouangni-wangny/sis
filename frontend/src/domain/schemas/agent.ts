import { z } from "zod";

export const agentSchema = z.object({
  grade_id: z.string().uuid("Grade requis"),
  nom: z.string().trim().min(1, "Nom requis").max(255),
  prenom: z.string().trim().min(1, "Prénom requis").max(255),
  civilite: z
    .enum(["monsieur", "madame", "mademoiselle", ""])
    .optional()
    .or(z.literal("")),
  date_naissance: z.string().optional().or(z.literal("")),
  lieu_naissance: z.string().max(255).optional().or(z.literal("")),
  situation_matrimoniale: z
    .enum(["celibataire", "marie", "divorce", "veuf", ""])
    .optional()
    .or(z.literal("")),
  nombre_enfants: z.union([z.string(), z.number()]).optional().or(z.literal("")),
  nationalite: z.string().max(100).optional().or(z.literal("")),
  telephone: z.string().max(50).optional().or(z.literal("")),
  numero_cni: z.string().max(50).optional().or(z.literal("")),
  ville_id: z.string().uuid().optional().or(z.literal("")),
  domicile: z.string().max(1000).optional().or(z.literal("")),
  cnps: z.string().max(50).optional().or(z.literal("")),
  date_embauche: z.string().optional().or(z.literal("")),
  date_expiration_permis: z.string().optional().or(z.literal("")),
  statut: z
    .enum([
      "disponible",
      "en_activite",
      "conge",
      "malade",
      "suspendu",
      "archive",
    ])
    .optional(),
  jour_repos: z
    .enum([
      "lundi",
      "mardi",
      "mercredi",
      "jeudi",
      "vendredi",
      "samedi",
      "dimanche",
      "",
    ])
    .optional()
    .or(z.literal("")),
  pool_siege: z.boolean().optional(),
  poste_siege_id: z.string().uuid().optional().or(z.literal("")),
  pin: z.string().min(4).max(8).optional().or(z.literal("")),
  email: z.string().email("Email invalide").optional().or(z.literal("")),
});

export type AgentFormValues = z.infer<typeof agentSchema>;
