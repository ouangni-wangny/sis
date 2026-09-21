import { z } from "zod";

export const posteSchema = z.object({
  site_id: z.string().uuid("Site requis"),
  nom: z.string().trim().min(1, "Nom requis").max(255),
  agents_requis: z.union([z.string(), z.number()]).optional().or(z.literal("")),
  heure_debut: z.string().optional().or(z.literal("")),
  heure_fin: z.string().optional().or(z.literal("")),
});

export type PosteFormValues = z.infer<typeof posteSchema>;
