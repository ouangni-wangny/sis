import { z } from "zod";

export const villeSchema = z.object({
  libelle: z.string().trim().min(1, "Libellé requis").max(100),
});

export type VilleFormValues = z.infer<typeof villeSchema>;
