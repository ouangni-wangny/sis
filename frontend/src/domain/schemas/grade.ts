import { z } from "zod";

export const gradeSchema = z.object({
  libelle: z.string().trim().min(1, "Libellé requis").max(255),
  type_agent: z.enum(["agent", "controleur", "administration"]),
  /** Agent de relève : tient les postes des titulaires en repos / absents. */
  est_rondier: z.boolean(),
  description: z.string().optional().or(z.literal("")),
});

export type GradeFormValues = z.infer<typeof gradeSchema>;
