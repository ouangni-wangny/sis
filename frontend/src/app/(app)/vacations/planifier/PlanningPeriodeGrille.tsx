"use client";

import { useState } from "react";
import type {
  CellulePlanning,
  CellulesPlanning,
} from "@/domain/schemas/agent-planning";
import { Button } from "@/presentation/components/ui/Button";
import { cn } from "@/shared/lib/cn";

const CASE: Record<CellulePlanning, { lettre: string; titre: string; classe: string }> = {
  jour: { lettre: "J", titre: "Jour", classe: "border-teal bg-teal text-white" },
  nuit: { lettre: "N", titre: "Nuit", classe: "border-ink bg-ink text-white" },
  les_deux: {
    lettre: "J+N",
    titre: "Jour et nuit",
    classe: "border-teal bg-teal/70 text-white",
  },
  repos: {
    lettre: "–",
    titre: "Repos",
    classe: "border-border bg-white text-ink-faint hover:border-teal/40",
  },
};

const JOURS = ["Dim", "Lun", "Mar", "Mer", "Jeu", "Ven", "Sam"];
const JOURS_LONG = [
  "Dimanche",
  "Lundi",
  "Mardi",
  "Mercredi",
  "Jeudi",
  "Vendredi",
  "Samedi",
];

function jourDe(date: string) {
  return new Date(`${date}T12:00:00`).getDay();
}

type Ligne = {
  index: number;
  label: string;
  cellules: CellulesPlanning;
  verrous: Record<string, string>;
};

function formatDateCourte(date: string) {
  return `${date.slice(8, 10)}/${date.slice(5, 7)}`;
}

/** Applique à un agent un cycle indépendant de la semaine (ex. 2 travaillés, 1 repos). */
function CycleAgent({
  ligne,
  onCycle,
}: {
  ligne: Ligne;
  onCycle: (
    index: number,
    cycle: { travail: number; repos: number; decalage: number },
  ) => void;
}) {
  const [travail, setTravail] = useState(6);
  const [repos, setRepos] = useState(1);
  const [decalage, setDecalage] = useState(0);
  const champ = (
    label: string,
    value: number,
    onChange: (n: number) => void,
    min: number,
  ) => (
    <label className="flex items-center gap-1 text-xs text-ink-muted">
      {label}
      <input
        type="number"
        min={min}
        max={60}
        value={value}
        onChange={(e) => onChange(Math.max(min, Number(e.target.value) || 0))}
        className="h-8 w-12 rounded-md border border-border bg-white px-1 text-center text-xs text-ink"
      />
    </label>
  );

  return (
    <div className="flex flex-wrap items-center gap-2">
      <span className="min-w-28 text-xs font-medium text-ink">{ligne.label}</span>
      {champ("Travail", travail, setTravail, 1)}
      {champ("Repos", repos, setRepos, 0)}
      {champ("Décalage", decalage, setDecalage, 0)}
      <Button
        type="button"
        variant="secondary"
        size="sm"
        onClick={() => onCycle(ligne.index, { travail, repos, decalage })}
      >
        Appliquer ce cycle
      </Button>
    </div>
  );
}

/** Grille agents × dates : le programme réel de la période, modifiable case par case. */
export function PlanningPeriodeGrille({
  dates,
  lignes,
  onCellule,
  onCycle,
}: {
  dates: string[];
  lignes: Ligne[];
  onCellule: (index: number, date: string) => void;
  onCycle: (
    index: number,
    cycle: { travail: number; repos: number; decalage: number },
  ) => void;
}) {
  return (
    <div className="space-y-3">
      <div className="overflow-x-auto rounded-lg border border-border/80">
        <table className="border-separate border-spacing-0 text-xs">
          <thead>
            <tr>
              <th className="sticky left-0 z-10 min-w-32 bg-white p-2 text-left font-semibold text-ink">
                Agent
              </th>
              {dates.map((date) => {
                const jour = jourDe(date);
                return (
                  <th
                    key={date}
                    title={JOURS_LONG[jour]}
                    className={cn(
                      "min-w-10 p-1 text-center font-medium text-ink-muted",
                      (jour === 0 || jour === 6) && "bg-paper-muted",
                      jour === 1 && "border-l border-border",
                    )}
                  >
                    <div className="text-[10px] font-semibold uppercase tracking-wide">
                      {JOURS[jour]}
                    </div>
                    <div className="tabular-nums text-ink">
                      {date.slice(8, 10)}/{date.slice(5, 7)}
                    </div>
                  </th>
                );
              })}
            </tr>
          </thead>
          <tbody>
            {lignes.map((ligne) => (
              <tr key={ligne.index}>
                <th
                  scope="row"
                  className="sticky left-0 z-10 max-w-40 truncate bg-white p-2 text-left font-medium text-ink"
                >
                  {ligne.label}
                </th>
                {dates.map((date) => {
                  const cellule = ligne.cellules[date] ?? "repos";
                  const verrou = ligne.verrous[date];
                  const info = CASE[cellule];
                  const titre = `${formatDateCourte(date)} — ${info.titre}${verrou ? ` (${verrou})` : ""}`;
                  return (
                    <td
                      key={date}
                      className={cn(
                        "p-0.5 text-center",
                        jourDe(date) === 1 && "border-l border-border",
                      )}
                    >
                      <button
                        type="button"
                        title={titre}
                        aria-label={`${ligne.label}, ${titre}`}
                        disabled={Boolean(verrou)}
                        onClick={() => onCellule(ligne.index, date)}
                        className={cn(
                          "inline-flex size-8 items-center justify-center rounded border text-[10px] font-semibold transition",
                          info.classe,
                          verrou && "cursor-not-allowed opacity-40",
                        )}
                      >
                        {info.lettre}
                      </button>
                    </td>
                  );
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <p className="text-xs text-ink-muted">
        J = jour · N = nuit · – = repos. Les cases estompées ne changent plus
        (date passée, vacation démarrée, remplacement) : survolez-les pour la raison.
      </p>

      <div className="space-y-2 border-t border-border/70 pt-3">
        <p className="text-xs font-medium text-ink">
          Rythme qui ne suit pas la semaine
        </p>
        {lignes.map((ligne) => (
          <CycleAgent key={ligne.index} ligne={ligne} onCycle={onCycle} />
        ))}
      </div>
    </div>
  );
}
