import { z } from "zod";

export const paiementSchema = z.object({
  facture_id: z.string().uuid("Facture requise"),
  montant: z.coerce.number().positive("Montant > 0"),
  date_paiement: z.string().min(1, "Date requise"),
  mode: z.string().min(1, "Mode de paiement requis"),
  compte_tresorerie_id: z.string().uuid("Compte de trésorerie requis"),
  reference: z.string().optional().or(z.literal("")),
  notes: z.string().optional().or(z.literal("")),
});

export type PaiementFormValues = z.infer<typeof paiementSchema>;
/** Forme brute des champs avant coercion (ex. montant saisi comme string) — à utiliser comme TFieldValues de useForm. */
export type PaiementFormInput = z.input<typeof paiementSchema>;

/** Fallback statique si l’API modes n’est pas encore chargée. */
export const MODE_PAIEMENT_FILTER_OPTIONS = [
  { value: "", label: "Tous les modes" },
  { value: "especes", label: "Espèces" },
  { value: "virement", label: "Virement" },
  { value: "cheque", label: "Chèque" },
  { value: "wave", label: "Wave" },
  { value: "mtn", label: "MTN Money" },
  { value: "moov", label: "Moov Money" },
  { value: "orange", label: "Orange Money" },
  { value: "autre", label: "Autre" },
] as const;

export const MODE_PAIEMENT_FORM_OPTIONS = MODE_PAIEMENT_FILTER_OPTIONS.filter(
  (o) => o.value !== "",
);
