/**
 * Alternance agents (1 jour / 1 jour) + rotation hebdo.
 * Usage : npm run test:alternance
 */
import assert from "node:assert/strict";
import {
  buildAlternanceMotifs,
  complementJours,
  expandPlanningShifts,
  mondayOfIso,
  weekOffsetFrom,
} from "../src/domain/schemas/agent-planning.ts";

const dateDebut = "2026-07-27"; // lundi
const dateFin2w = "2026-08-09"; // 2 semaines
const dateFin3w = "2026-08-16"; // 3 semaines

assert.equal(mondayOfIso(dateDebut), "2026-07-27");
assert.equal(weekOffsetFrom("2026-08-03", dateDebut), 1);

const motifs2 = buildAlternanceMotifs(2);
assert.equal(motifs2.length, 2);
assert.equal(motifs2[0].length + motifs2[1].length, 7);
assert.deepEqual(complementJours(motifs2[0]), motifs2[1]);

const motifs3 = buildAlternanceMotifs(3);
assert.equal(motifs3.length, 3);
assert.equal(motifs3.flat().length, 7);

const motifs4 = buildAlternanceMotifs(4);
assert.equal(motifs4.length, 4);
assert.equal(motifs4.flat().length, 7);

const common = {
  date_debut: dateDebut,
  date_fin: dateFin2w,
  jour_repos: "",
  quart: "jour",
  heure_debut: "06:30",
  heure_fin: "06:30",
  mode_effectif: "alternance",
  alternanceMotifs: motifs2,
};

const shiftsA = expandPlanningShifts({
  ...common,
  jours_travailles: motifs2[0],
  slotIndex: 0,
});
const shiftsB = expandPlanningShifts({
  ...common,
  jours_travailles: motifs2[1],
  slotIndex: 1,
});

const countInWeek = (shifts, week) =>
  shifts.filter((s) => weekOffsetFrom(s.date, dateDebut) === week).length;

const a0 = motifs2[0].length;
const b0 = motifs2[1].length;
assert.equal(countInWeek(shiftsA, 0), a0, `A semaine 0 = ${a0}`);
assert.equal(countInWeek(shiftsA, 1), b0, `A semaine 1 = ${b0} (rotation)`);
assert.equal(countInWeek(shiftsB, 0), b0, `B semaine 0 = ${b0}`);
assert.equal(countInWeek(shiftsB, 1), a0, `B semaine 1 = ${a0} (rotation)`);

const datesA = new Set(shiftsA.map((s) => s.date));
for (const d of shiftsB.map((s) => s.date)) {
  assert.equal(datesA.has(d), false, `chevauchement ${d}`);
}

const common3 = {
  ...common,
  date_fin: dateFin3w,
  alternanceMotifs: motifs3,
};
const totals3 = [0, 1, 2].map((i) =>
  expandPlanningShifts({
    ...common3,
    jours_travailles: motifs3[i],
    slotIndex: i,
  }).length,
);
const expectedPerAgent = motifs3.reduce((s, m) => s + m.length, 0);
assert.deepEqual(totals3, [expectedPerAgent, expectedPerAgent, expectedPerAgent]);

const ensembleA = expandPlanningShifts({
  ...common,
  mode_effectif: "ensemble",
  jours_travailles: motifs2[0],
  slotIndex: 0,
});
assert.equal(countInWeek(ensembleA, 0), a0);
assert.equal(countInWeek(ensembleA, 1), a0, "ensemble ne tourne pas");

console.log("ok — alternance agents (1j/1j) + rotation");
