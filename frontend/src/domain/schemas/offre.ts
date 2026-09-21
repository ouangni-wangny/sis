import { z } from "zod";

export const offreSchema = z.object({
  libelle: z.string().trim().min(1, "Libellé requis").max(255),
  description: z.string().optional().or(z.literal("")),
  prix_mensuel: z
    .union([z.string(), z.number()])
    .refine(
      (v) => String(v).trim() !== "" && !Number.isNaN(Number(v)) && Number(v) >= 0,
      "Prix mensuel requis",
    ),
  actif: z.boolean(),
});

export type OffreFormValues = z.infer<typeof offreSchema>;
