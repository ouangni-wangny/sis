import { z } from "zod";

export const qualificationSchema = z.object({
  libelle: z.string().trim().min(1, "Libellé requis").max(100),
  abreviation: z
    .string()
    .trim()
    .min(1, "Abréviation requise")
    .max(10, "10 caractères au plus")
    .regex(/^[A-Za-z0-9]+$/, "Lettres et chiffres uniquement (ex. CP)"),
  description: z.string().trim().max(255).optional(),
  actif: z.enum(["1", "0"]),
});

export type QualificationFormValues = z.infer<typeof qualificationSchema>;

export const primeQualificationSchema = z.object({
  qualification_id: z.string().min(1, "Choisissez la prime"),
  montant: z
    .string()
    .trim()
    .min(1, "Montant requis")
    .refine((v) => !Number.isNaN(Number(v)) && Number(v) >= 0, "Montant invalide"),
  unite: z.enum(["mois", "jour"]),
  actif: z.enum(["1", "0"]),
});

export type PrimeQualificationFormValues = z.infer<typeof primeQualificationSchema>;
