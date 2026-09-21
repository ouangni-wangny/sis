import { z } from "zod";

export const gradeSchema = z.object({
  libelle: z.string().trim().min(1, "Libellé requis").max(255),
  type_agent: z.enum(["agent", "controleur", "administration"]),
  description: z.string().optional().or(z.literal("")),
});

export type GradeFormValues = z.infer<typeof gradeSchema>;
