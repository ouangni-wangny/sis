import { z } from "zod";

export const loginSchema = z.object({
  email: z.string().email("Email invalide"),
  password: z.string().min(1, "Mot de passe requis"),
});

export type LoginFormValues = z.infer<typeof loginSchema>;

export const terrainLoginSchema = z.object({
  matricule: z
    .string()
    .trim()
    .min(1, "Matricule requis")
    .max(50),
  pin: z
    .string()
    .trim()
    .min(4, "PIN à 4 chiffres minimum")
    .max(6, "PIN trop long")
    .regex(/^\d+$/, "PIN numérique uniquement"),
});

export type TerrainLoginFormValues = z.infer<typeof terrainLoginSchema>;

/** Formulaire unique : email → back-office, matricule → terrain. */
export const unifiedLoginSchema = z
  .object({
    identifiant: z.string().trim().min(1, "Identifiant requis").max(120),
    secret: z.string().min(1, "Mot de passe ou PIN requis"),
  })
  .superRefine((data, ctx) => {
    const id = data.identifiant.trim();
    const secret = data.secret.trim();

    if (id.includes("@")) {
      if (!z.string().email().safeParse(id).success) {
        ctx.addIssue({
          code: "custom",
          path: ["identifiant"],
          message: "Email invalide",
        });
      }
      if (secret.length < 1) {
        ctx.addIssue({
          code: "custom",
          path: ["secret"],
          message: "Mot de passe requis",
        });
      }
      return;
    }

    if (id.length < 2) {
      ctx.addIssue({
        code: "custom",
        path: ["identifiant"],
        message: "Matricule invalide",
      });
    }
    if (!/^\d{4,6}$/.test(secret)) {
      ctx.addIssue({
        code: "custom",
        path: ["secret"],
        message: "PIN à 4–6 chiffres",
      });
    }
  });

export type UnifiedLoginFormValues = z.infer<typeof unifiedLoginSchema>;

export function isEmailIdentifiant(identifiant: string): boolean {
  return identifiant.trim().includes("@");
}
