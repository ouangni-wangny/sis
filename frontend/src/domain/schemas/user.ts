import { z } from "zod";

const baseRoles = ["super-admin", "operation", "rh", "commercial", "comptable"] as const;
const allRoles = [...baseRoles, "developpeur"] as const;

export const userSchema = z
  .object({
    nom: z.string().trim().min(1, "Nom requis").max(255),
    prenom: z.string().trim().min(1, "Prénom requis").max(255),
    email: z.string().trim().email("Email invalide"),
    password: z.string().optional().or(z.literal("")),
    role: z.enum(allRoles),
    statut: z.enum(["actif", "inactif", "bloque"]),
  })
  .superRefine((data, ctx) => {
    if (data.password && data.password.length > 0 && data.password.length < 8) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        message: "Mot de passe : 8 caractères minimum",
        path: ["password"],
      });
    }
  });

export type UserFormValues = z.infer<typeof userSchema>;
export type BackofficeRole = UserFormValues["role"];

export const BASE_ROLE_OPTIONS: { value: BackofficeRole; label: string }[] = [
  { value: "super-admin", label: "Super admin" },
  { value: "operation", label: "Opération" },
  { value: "rh", label: "RH" },
  { value: "commercial", label: "Commercial" },
  { value: "comptable", label: "Comptable" },
];

export const DEVELOPPEUR_ROLE_OPTION: {
  value: BackofficeRole;
  label: string;
} = {
  value: "developpeur",
  label: "Développeur",
};
