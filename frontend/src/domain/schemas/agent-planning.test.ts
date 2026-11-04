import { describe, expect, it } from "vitest";
import {
  buildAlternanceMotifs,
  cellulesDepuisCycle,
  cellulesEnregistrees,
  completerCellules,
  complementJours,
  creneauxDepuisCellules,
  expandPlanningShifts,
  frenchWeekdayFromIso,
  joursForAlternanceWeek,
  agentsEnPlus,
  joursVerrouilles,
  mondayOfIso,
  periodeEnregistree,
  titulairesDuPoste,
  weekOffsetFrom,
} from "@/domain/schemas/agent-planning";
import type { JourSemaine, Vacation } from "@/domain/types/entities";
import { isCycle24h, isOvernight, minutesOf } from "@/domain/time/shift-interval";

const TOUS: JourSemaine[] = [
  "lundi",
  "mardi",
  "mercredi",
  "jeudi",
  "vendredi",
  "samedi",
  "dimanche",
];
const base = {
  date_debut: "2026-11-09", // lundi
  date_fin: "2026-11-22",
  jours_travailles: TOUS,
  quart: "jour" as const,
  heure_debut: "06:00",
  heure_fin: "18:00",
};

describe("expandPlanningShifts", () => {
  it("crée un créneau par jour sur la période, bornes incluses", () => {
    const shifts = expandPlanningShifts(base);
    expect(shifts).toHaveLength(14);
    expect(shifts[0].date).toBe("2026-11-09");
    expect(shifts[13].date).toBe("2026-11-22");
  });

  it("n'a aucun doublon et aucun trou sur un changement de mois et d'année", () => {
    const shifts = expandPlanningShifts({
      ...base,
      date_debut: "2026-12-28",
      date_fin: "2027-01-10",
    });
    expect(new Set(shifts.map((s) => s.date)).size).toBe(14);
    expect(shifts.map((s) => s.date)).toContain("2026-12-31");
    expect(shifts.map((s) => s.date)).toContain("2027-01-01");
  });

  it("passe le 29 février d'une année bissextile", () => {
    const dates = expandPlanningShifts({
      ...base,
      date_debut: "2028-02-28",
      date_fin: "2028-03-01",
    }).map((s) => s.date);
    expect(dates).toEqual(["2028-02-28", "2028-02-29", "2028-03-01"]);
  });

  it("met fin de jour au même jour et fin de nuit au lendemain", () => {
    expect(expandPlanningShifts(base)[0].date_fin).toBe("2026-11-09");

    const nuit = expandPlanningShifts({
      ...base,
      quart: "nuit",
      heure_debut: "18:00",
      heure_fin: "06:00",
    });
    expect(nuit[0].date_fin).toBe("2026-11-10");
    expect(nuit[13].date_fin).toBe("2026-11-23");
  });

  it("respecte les jours travaillés et le jour de repos", () => {
    const sansDimanche = expandPlanningShifts({
      ...base,
      jours_travailles: TOUS.filter((j) => j !== "dimanche"),
    });
    expect(sansDimanche).toHaveLength(12);
    expect(sansDimanche.some((s) => s.weekday === "dimanche")).toBe(false);

    expect(expandPlanningShifts({ ...base, jour_repos: "lundi" })).toHaveLength(12);
  });

  it("crée jour et nuit pour un agent sur les deux quarts", () => {
    const shifts = expandPlanningShifts({
      ...base,
      date_fin: base.date_debut,
      quart: "les_deux",
      heure_debut_nuit: "18:00",
      heure_fin_nuit: "06:00",
    });
    expect(shifts.map((s) => s.quart)).toEqual(["jour", "nuit"]);
  });

  it("ne crée rien pour une période inversée", () => {
    expect(
      expandPlanningShifts({
        ...base,
        date_debut: "2026-11-22",
        date_fin: "2026-11-09",
      }),
    ).toEqual([]);
  });
});

describe("alternance", () => {
  it.each([2, 3, 4, 5, 7])(
    "répartit les 7 jours entre %i agents sans doublon",
    (n) => {
      const motifs = buildAlternanceMotifs(n);
      expect(motifs).toHaveLength(n);
      expect(new Set(motifs.flat()).size).toBe(7);
      expect(motifs.flat()).toHaveLength(7);
    },
  );

  it.each([2, 3, 4])(
    "met exactement un agent par jour pendant 8 semaines avec %i agents",
    (n) => {
      const motifs = buildAlternanceMotifs(n);
      const parDate = new Map<string, number>();
      for (let slotIndex = 0; slotIndex < n; slotIndex++) {
        for (const s of expandPlanningShifts({
          ...base,
          date_fin: "2027-01-03",
          jours_travailles: motifs[slotIndex],
          mode_effectif: "alternance",
          alternanceMotifs: motifs,
          slotIndex,
        })) {
          parDate.set(s.date, (parDate.get(s.date) ?? 0) + 1);
        }
      }
      expect(parDate.size).toBe(56);
      expect([...parDate.values()].every((c) => c === 1)).toBe(true);
    },
  );

  it("équilibre les jours entre 2 agents sur 2 semaines", () => {
    const motifs = buildAlternanceMotifs(2);
    const compte = [0, 1].map(
      (slotIndex) =>
        expandPlanningShifts({
          ...base,
          jours_travailles: motifs[slotIndex],
          mode_effectif: "alternance",
          alternanceMotifs: motifs,
          slotIndex,
        }).length,
    );
    expect(compte).toEqual([7, 7]);
  });

  it("fait tourner les motifs chaque semaine", () => {
    const motifs = buildAlternanceMotifs(2);
    expect(joursForAlternanceWeek(motifs, 0, 0)).toEqual(motifs[0]);
    expect(joursForAlternanceWeek(motifs, 0, 1)).toEqual(motifs[1]);
    expect(joursForAlternanceWeek(motifs, 0, 2)).toEqual(motifs[0]);
  });

  it("donne le complément exact des jours", () => {
    expect([...complementJours(["lundi", "mardi"])].sort()).toEqual(
      TOUS.filter((j) => j !== "lundi" && j !== "mardi").sort(),
    );
  });
});

describe("calendrier", () => {
  it("reconnaît les jours de la semaine, y compris le dimanche", () => {
    expect(frenchWeekdayFromIso("2026-11-09")).toBe("lundi");
    expect(frenchWeekdayFromIso("2026-11-15")).toBe("dimanche");
  });

  it("trouve le lundi depuis un dimanche, dans la semaine précédente", () => {
    expect(mondayOfIso("2026-11-15")).toBe("2026-11-09");
    expect(mondayOfIso("2026-11-09")).toBe("2026-11-09");
  });

  it("compte les semaines calendaires, pas les blocs de 7 jours", () => {
    expect(weekOffsetFrom("2026-11-15", "2026-11-11")).toBe(0);
    expect(weekOffsetFrom("2026-11-16", "2026-11-11")).toBe(1);
  });
});

describe("horaires", () => {
  it("distingue jour, nuit et cycle de 24 h", () => {
    expect(isOvernight("06:00", "18:00")).toBe(false);
    expect(isOvernight("18:00", "06:00")).toBe(true);
    expect(isOvernight("14:00", "00:00")).toBe(true);
    expect(isOvernight("07:00", "07:00")).toBe(false);
    expect(isCycle24h("07:00", "07:00")).toBe(true);
  });

  it("refuse une heure invalide", () => {
    expect(minutesOf("")).toBeNull();
    expect(minutesOf("abc")).toBeNull();
  });
});

describe("planning de période", () => {
  const vacation = (
    agent_id: string,
    date: string,
    extra: Partial<Vacation> = {},
  ) =>
    ({
      id: `${agent_id}-${date}`,
      agent_id,
      site_id: "s",
      poste_id: "p",
      date_debut: date,
      date_fin: date,
      heure_debut: "06:00",
      heure_fin: "18:00",
      statut: "planifiee",
      ...extra,
    }) as Vacation;
  const quartDe = (v: Vacation) =>
    (v.heure_debut === "18:00" ? "nuit" : "jour") as "jour" | "nuit";
  const heures = {
    date_debut: "2026-11-09",
    date_fin: "2026-11-11",
    index: 0,
    total: 2,
    heure_debut: "06:00",
    heure_fin: "18:00",
    heure_debut_nuit: "18:00",
    heure_fin_nuit: "06:00",
    repartition: "moities" as const,
  };

  it("change de jour à nuit au fil de la période, date par date", () => {
    const shifts = creneauxDepuisCellules({
      ...heures,
      cellules: {
        "2026-11-09": "jour",
        "2026-11-10": "nuit",
        "2026-11-11": "repos",
      },
    });
    expect(shifts.map((s) => [s.date, s.heure_debut, s.date_fin])).toEqual([
      ["2026-11-09", "06:00", "2026-11-09"],
      ["2026-11-10", "18:00", "2026-11-11"],
    ]);
  });

  it("ne crée rien sur une case verrouillée", () => {
    const shifts = creneauxDepuisCellules({
      ...heures,
      cellules: { "2026-11-09": "jour", "2026-11-10": "jour" },
      verrous: { "2026-11-09": "Date passée" },
    });
    expect(shifts.map((s) => s.date)).toEqual(["2026-11-10"]);
  });

  it("donne jour et nuit à l’agent seul sur un poste découpé", () => {
    const shifts = creneauxDepuisCellules({
      ...heures,
      total: 1,
      date_fin: "2026-11-09",
      repartition: "jour_entier",
      cellules: { "2026-11-09": "les_deux" },
    });
    expect(shifts.map((s) => s.heure_debut)).toEqual(["06:00", "18:00"]);
  });

  it("suit un cycle qui ne tombe pas sur la semaine", () => {
    const c = cellulesDepuisCycle({
      date_debut: "2026-11-09",
      date_fin: "2026-11-15",
      travail: 2,
      repos: 1,
      decalage: 1,
      cellule: "jour",
    });
    expect(Object.values(c)).toEqual(["jour", "repos", "jour", "jour", "repos", "jour", "jour"]);
  });

  it("réaffiche l’enregistré à l’identique, repos compris", () => {
    const enregistrees = cellulesEnregistrees(
      "a",
      [
        vacation("a", "2026-11-09"),
        vacation("a", "2026-11-11", { heure_debut: "18:00", heure_fin: "06:00" }),
        vacation("a", "2026-11-12", { statut: "annulee", remplacee: true }),
        vacation("a", "2026-11-13", { statut: "annulee" }),
        vacation("a", "2026-11-14", { est_remplacement: true }),
      ],
      quartDe,
    );
    expect(enregistrees).toEqual({
      "2026-11-09": "jour",
      "2026-11-11": "nuit",
      "2026-11-12": "jour",
    });

    const rythme = { "2026-11-10": "jour", "2026-11-13": "nuit" } as const;
    expect(
      completerCellules(
        undefined,
        ["2026-11-09", "2026-11-10", "2026-11-13"],
        enregistrees,
        rythme,
      ),
    ).toEqual({
      "2026-11-09": "jour",
      "2026-11-10": "repos",
      "2026-11-13": "nuit",
    });
  });

  it("ne prend pas un remplaçant pour un titulaire, et garde un ordre stable", () => {
    const vacations = [
      vacation("remplacant", "2026-11-10", { est_remplacement: true }),
      vacation("nuit", "2026-11-09", { heure_debut: "18:00", heure_fin: "06:00" }),
      vacation("jour", "2026-11-09"),
    ];
    const liste = (v: Vacation[]) =>
      titulairesDuPoste(v, "2026-11-09", "2026-11-30", quartDe).map((t) => t.id);
    expect(liste(vacations)).toEqual(["jour", "nuit"]);
    expect(liste([...vacations].reverse())).toEqual(["jour", "nuit"]);
  });

  it("reprend la période enregistrée tant qu’elle n’est pas finie", () => {
    const poste = {
      planning_date_debut: "2026-11-01",
      planning_date_fin: "2026-11-30",
    };
    expect(periodeEnregistree(poste, "2026-11-15")).toEqual({
      debut: "2026-11-01",
      fin: "2026-11-30",
    });
    expect(periodeEnregistree(poste, "2026-12-01")).toBeNull();
  });

  it("verrouille le passé et ce qui est cédé à un remplaçant", () => {
    const enPlus = agentsEnPlus(
      [
        vacation("grille", "2026-11-12"),
        vacation("releve", "2026-11-01"),
        vacation("releve", "2026-11-12", {
          agent: { id: "releve", prenom: "Awa", nom: "Koné", matricule: "M" },
        }),
        vacation("releve", "2026-11-13", { statut: "annulee" }),
        vacation("remplacant", "2026-11-12", { est_remplacement: true }),
      ],
      new Set(["grille"]),
      "2026-11-04",
    );
    expect(enPlus).toEqual([{ id: "releve", nom: "Awa Koné", creneaux: 1 }]);

    const verrous = joursVerrouilles(
      "a",
      [
        vacation("a", "2026-11-12", { statut: "annulee", remplacee: true }),
        vacation("b", "2026-11-12", {
          est_remplacement: true,
          agent: { id: "b", prenom: "Awa", nom: "Koné", matricule: "M" },
        }),
      ],
      ["2026-11-09", "2026-11-12", "2026-11-13"],
      "2026-11-10",
    );
    expect(verrous).toEqual({
      "2026-11-09": "Date passée",
      "2026-11-12": "Remplacé par Awa Koné",
    });
  });
});
