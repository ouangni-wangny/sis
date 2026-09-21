import { z } from "zod";

export const zoneSchema = z.object({
  nom: z.string().min(1, "Nom requis").max(255),
  description: z.string().optional().or(z.literal("")),
});

export type ZoneFormValues = z.infer<typeof zoneSchema>;
