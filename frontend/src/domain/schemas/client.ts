import { z } from "zod";

export const clientSchema = z
  .object({
    type: z.enum(["entreprise", "particulier"]),
    raison_sociale: z.string().trim().min(1).max(255),
    personne_contact: z.string().max(255).optional().or(z.literal("")),
    telephone: z.string().max(50).optional().or(z.literal("")),
    email: z.string().email("Email invalide").optional().or(z.literal("")),
    adresse: z.string().max(1000).optional().or(z.literal("")),
    statut: z.enum(["actif", "resilie", "suspendu"]),
  })
  .superRefine((data, ctx) => {
    if (!data.raison_sociale.trim()) {
      ctx.addIssue({
        code: "custom",
        path: ["raison_sociale"],
        message:
          data.type === "particulier"
            ? "Nom complet requis"
            : "Raison sociale requise",
      });
    }

    if (
      data.type === "entreprise" &&
      !(data.personne_contact ?? "").trim()
    ) {
      ctx.addIssue({
        code: "custom",
        path: ["personne_contact"],
        message: "Personne contact requise pour une entreprise",
      });
    }
  });

export type ClientFormValues = z.infer<typeof clientSchema>;

export function clientIdentityLabel(type: ClientFormValues["type"]): string {
  return type === "particulier" ? "Nom complet" : "Raison sociale";
}

export function clientIdentityHint(type: ClientFormValues["type"]): string {
  return type === "particulier"
    ? "Nom et prénom du particulier."
    : "Nom officiel de l’entreprise ou de la structure.";
}

export function clientIdentityPlaceholder(
  type: ClientFormValues["type"],
): string {
  return type === "particulier"
    ? "ex. Adjoua Yao"
    : "ex. Orange Côte d'Ivoire";
}
