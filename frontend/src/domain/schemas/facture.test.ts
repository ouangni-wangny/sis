import { describe, expect, it } from "vitest";
import { proformaFactureSchema } from "@/domain/schemas/facture";

const base = {
  client_nom: "Prospect Test",
  delai_paiement_jours: 30,
  appliquer_tva: true,
  lignes: [
    {
      description: "Gardiennage",
      quantite: 1,
      prix_unitaire: 100000,
    },
  ],
};

describe("proformaFactureSchema", () => {
  it("accepte une proforma avec TVA", () => {
    const parsed = proformaFactureSchema.parse(base);
    expect(parsed.appliquer_tva).toBe(true);
    expect(parsed.lignes).toHaveLength(1);
  });

  it("accepte une proforma exonérée de TVA", () => {
    const parsed = proformaFactureSchema.parse({
      ...base,
      appliquer_tva: false,
    });
    expect(parsed.appliquer_tva).toBe(false);
  });

  it("exige appliquer_tva", () => {
    const { appliquer_tva: _, ...withoutTva } = base;
    const result = proformaFactureSchema.safeParse(withoutTva);
    expect(result.success).toBe(false);
  });

  it("exige un client ou un nom destinataire", () => {
    const result = proformaFactureSchema.safeParse({
      ...base,
      client_nom: "",
      client_id: "",
    });
    expect(result.success).toBe(false);
  });

  it("exige au moins une ligne", () => {
    const result = proformaFactureSchema.safeParse({
      ...base,
      lignes: [],
    });
    expect(result.success).toBe(false);
  });
});
