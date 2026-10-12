import { describe, expect, it } from "vitest";
import { rangesOverlapMs } from "@/domain/time/shift-interval";
import {
  shiftWindowOnDayMs,
  vacationCoversDay,
  vacationWindowsAroundDayMs,
} from "@/domain/time/vacation-days";

describe("vacation-days — un créneau par jour de prise de poste", () => {
  it("affiche une nuit sur période chaque jour (pas seulement le premier)", () => {
    // Nuit 19h–07h du 01 au 05 : date_fin stockée = 06 (fin du dernier créneau).
    const nuit = {
      date_debut: "2026-10-01",
      date_fin: "2026-10-06",
      heure_debut: "19:00",
      heure_fin: "07:00",
    };
    expect(vacationCoversDay(nuit, "2026-10-01")).toBe(true);
    expect(vacationCoversDay(nuit, "2026-10-02")).toBe(true);
    expect(vacationCoversDay(nuit, "2026-10-05")).toBe(true);
    expect(vacationCoversDay(nuit, "2026-10-06")).toBe(false);
  });

  it("laisse un agent de jour sur période libre pour une nuit ailleurs", () => {
    const jourSiteA = {
      date_debut: "2026-10-01",
      date_fin: "2026-10-31",
      heure_debut: "07:00",
      heure_fin: "19:00",
    };
    const nuitSiteB = shiftWindowOnDayMs("2026-10-10", "19:00", "07:00");
    const windows = vacationWindowsAroundDayMs(jourSiteA, "2026-10-10");

    expect(windows).toHaveLength(3);
    expect(windows.some((w) => rangesOverlapMs(nuitSiteB, w))).toBe(false);
  });

  it("détecte toujours un vrai chevauchement horaire", () => {
    const jour = {
      date_debut: "2026-10-01",
      date_fin: "2026-10-31",
      heure_debut: "07:00",
      heure_fin: "19:00",
    };
    const apresMidi = shiftWindowOnDayMs("2026-10-10", "13:00", "21:00");
    expect(
      vacationWindowsAroundDayMs(jour, "2026-10-10").some((w) =>
        rangesOverlapMs(apresMidi, w),
      ),
    ).toBe(true);
  });
});
