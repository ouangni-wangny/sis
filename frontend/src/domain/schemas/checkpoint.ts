import { z } from "zod";

export const checkpointSchema = z.object({
  site_id: z.string().uuid("Site requis"),
  nom: z.string().trim().min(1, "Nom requis").max(255),
  code_qr: z.string().optional().or(z.literal("")),
  latitude: z.union([z.string(), z.number()]).optional().or(z.literal("")),
  longitude: z.union([z.string(), z.number()]).optional().or(z.literal("")),
  ordre: z.union([z.string(), z.number()]).optional().or(z.literal("")),
});

export type CheckpointFormValues = z.infer<typeof checkpointSchema>;
