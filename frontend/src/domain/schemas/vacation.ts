import { z } from "zod";

const timeRegex = /^([01]\d|2[0-3]):[0-5]\d$/;

export const vacationSchema = z.object({
  agent_id: z.string().uuid("Agent requis"),
  site_id: z.string().uuid("Site requis"),
  poste_id: z.string().uuid().optional().or(z.literal("")),
  date_debut: z.string().min(1, "Date de début requise"),
  date_fin: z.string().optional().or(z.literal("")),
  heure_debut: z
    .string()
    .regex(timeRegex, "Format HH:mm attendu")
    .min(1, "Heure de début requise"),
  heure_fin: z
    .string()
    .regex(timeRegex, "Format HH:mm attendu")
    .min(1, "Heure de fin requise"),
  repeat_frequency: z.enum(["aucune", "hebdomadaire"]).optional(),
  repeat_count: z.string().optional(),
});

export type VacationFormValues = z.infer<typeof vacationSchema>;
