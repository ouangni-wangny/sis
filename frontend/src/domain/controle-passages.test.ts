import { describe, expect, it } from "vitest";
import {
  indexControlesParPassage,
  statusControleVacation,
  vacationDuJour,
} from "./controle-passages";

const jourA = { agent_id: "ag1", site_id: "A", heure_debut: "06:30", heure_fin: "18:30" };
const nuitB = { agent_id: "ag1", site_id: "B", heure_debut: "18:30", heure_fin: "06:30" };
const at = (iso: string) => new Date(iso);

describe("statut de contrôle par agent ET par site", () => {
  it("un contrôle sur le site A ne vaut pas pour la nuit sur le site B", () => {
    const idx = indexControlesParPassage([
      { controle_agent_id: "ag1", site_id: "A", resultat: "present", effectue_at: "2026-11-26T16:00:00" },
    ]);
    const now = at("2026-11-26T17:00:00");
    const jour = { ...jourA, date_debut: "2026-11-26", date_fin: "2026-11-26" };
    const nuit = { ...nuitB, date_debut: "2026-11-26", date_fin: "2026-11-27" };
    expect(statusControleVacation(jour, idx, now).complet).toBe(true);
    expect(statusControleVacation(nuit, idx, now).complet).toBe(false);
  });

  it("un contrôle de nuit après minuit valide le quart de nuit", () => {
    const idx = indexControlesParPassage([
      { controle_agent_id: "ag1", site_id: "B", resultat: "present", effectue_at: "2026-11-27T02:00:00" },
    ]);
    const nuit = { ...nuitB, date_debut: "2026-11-26", date_fin: "2026-11-27" };
    expect(statusControleVacation(nuit, idx, at("2026-11-27T03:00:00")).complet).toBe(true);
  });

  it("le contrôle de la nuit précédente ne vaut pas pour la nuit de ce soir (vacation sur période)", () => {
    const periode = { ...nuitB, date_debut: "2026-11-09", date_fin: "2026-11-16" };
    const idx = indexControlesParPassage([
      { controle_agent_id: "ag1", site_id: "B", resultat: "present", effectue_at: "2026-11-10T02:00:00" },
    ]);
    // 10/11 à 03:00 : la nuit du 9 est en cours → contrôlée.
    expect(statusControleVacation(periode, idx, at("2026-11-10T03:00:00")).complet).toBe(true);
    // 10/11 à 20:00 : nuit du 10 → pas encore contrôlée.
    expect(statusControleVacation(periode, idx, at("2026-11-10T20:00:00")).complet).toBe(false);
  });

  it("jour et nuit sur le même site : le contrôle de 10h ne valide pas la nuit", () => {
    const idx = indexControlesParPassage([
      { controle_agent_id: "ag1", site_id: "A", resultat: "present", effectue_at: "2026-11-26T10:00:00" },
    ]);
    const nuitA = { ...nuitB, site_id: "A", date_debut: "2026-11-26", date_fin: "2026-11-27" };
    expect(statusControleVacation(nuitA, idx, at("2026-11-26T19:00:00")).complet).toBe(false);
  });
});

describe("vacationDuJour", () => {
  const now = (h: number) => new Date(`2026-11-27T${String(h).padStart(2, "0")}:00:00`);

  it("garde la vacation qui commence aujourd’hui", () => {
    expect(vacationDuJour({ ...jourA, date_debut: "2026-11-27", date_fin: "2026-11-27" }, now(10))).toBe(true);
  });

  it("garde la nuit d’hier tant qu’elle n’est pas finie, puis la retire", () => {
    const nuitHier = { ...nuitB, date_debut: "2026-11-26", date_fin: "2026-11-27" };
    expect(vacationDuJour(nuitHier, now(5))).toBe(true);
    expect(vacationDuJour(nuitHier, now(10))).toBe(false);
  });

  it("garde une affectation continue sans date de fin", () => {
    expect(vacationDuJour({ ...jourA, date_debut: "2026-11-01", date_fin: null }, now(10))).toBe(true);
  });
});
