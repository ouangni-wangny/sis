import { describe, expect, it } from "vitest";
import {
  suggestRenouvellementDates,
  validateContratDateFin,
} from "@/domain/schemas/contrat-rules";

const today = new Date("2026-11-02T12:00:00");

describe("suggestRenouvellementDates", () => {
  it("démarre le lendemain de la fin, pour la même durée", () => {
    expect(
      suggestRenouvellementDates(
        { date_debut: "2026-10-24", date_fin: "2027-10-23" },
        today,
      ),
    ).toEqual({ date_debut: "2027-10-24", date_fin: "2028-10-23" });
  });

  it("démarre aujourd’hui si le contrat est déjà expiré", () => {
    expect(
      suggestRenouvellementDates(
        { date_debut: "2026-01-01", date_fin: "2026-03-31" },
        today,
      ),
    ).toEqual({ date_debut: "2026-11-02", date_fin: "2027-02-01" });
  });

  it("démarre le lendemain si le contrat finit aujourd’hui", () => {
    expect(
      suggestRenouvellementDates(
        { date_debut: "2026-10-01", date_fin: "2026-11-02" },
        today,
      ).date_debut,
    ).toBe("2026-11-03");
  });

  it("conserve une durée qui ne tombe pas sur des mois entiers", () => {
    expect(
      suggestRenouvellementDates(
        { date_debut: "2026-11-10", date_fin: "2026-12-14" },
        today,
      ),
    ).toEqual({ date_debut: "2026-12-15", date_fin: "2027-01-18" });
  });

  it("laisse la fin vide pour un contrat sans date de fin", () => {
    expect(
      suggestRenouvellementDates({ date_debut: "2020-01-01" }, today),
    ).toEqual({ date_debut: "2026-11-02", date_fin: "" });
  });

  it("propose des dates valides pour un stage de durée maximale", () => {
    const dates = suggestRenouvellementDates(
      { date_debut: "2026-10-24", date_fin: "2027-10-23" },
      today,
    );
    expect(
      validateContratDateFin("stage", dates.date_debut, dates.date_fin),
    ).toBeNull();
  });
});
