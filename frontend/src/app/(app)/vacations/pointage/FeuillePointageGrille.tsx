"use client";

import type {
  FeuillePointage,
  FeuillePointageCase,
} from "@/domain/types/entities";
import { Card, CardBody } from "@/presentation/components/ui/Card";

const CONTRAT: Record<string, string> = {
  cdi: "CDI",
  cdd: "CDD",
  stage: "STAGIAIRE",
  prestation: "PRESTATION",
};

function classeCase(v: FeuillePointageCase): string {
  if (v === "A") return "bg-danger/15 font-bold text-danger";
  if (v === "NC") return "bg-warning/15 text-[10px] font-semibold text-warning";
  if (v && Number(v) > 1) return "bg-teal/25 font-bold text-teal-dark";
  if (v) return "bg-teal/10 font-medium text-teal-dark";
  return "text-ink";
}

const LEGENDE: { valeur: FeuillePointageCase; texte: string }[] = [
  { valeur: "1", texte: "Présent" },
  { valeur: "2", texte: "Double vacation (compte 2)" },
  { valeur: "A", texte: "Absent" },
  { valeur: "NC", texte: "Non contrôlé (non compté)" },
  { valeur: null, texte: "Pas de vacation" },
];

/** Légende, à placer à côté de la recherche. */
export function LegendePointage() {
  return (
    <ul className="flex flex-wrap items-center gap-2">
      {LEGENDE.map((l) => (
        <li
          key={l.valeur ?? "vide"}
          className="flex h-10 items-center gap-2 rounded-md border border-border bg-white pl-1.5 pr-3 text-sm font-medium text-ink shadow-sm"
        >
          <span
            className={`inline-flex h-7 min-w-7 shrink-0 items-center justify-center rounded border border-border px-1 text-sm ${classeCase(
              l.valeur,
            )}`}
          >
            {l.valeur ?? ""}
          </span>
          {l.texte}
        </li>
      ))}
    </ul>
  );
}

/**
 * Feuille de pointage (état de présence) : une ligne par agent, une colonne
 * par jour de la période, comme la feuille papier signée par la RH.
 */
export function FeuillePointageGrille({
  feuille,
  recherche,
}: {
  feuille: FeuillePointage;
  recherche: string;
}) {
  const mots = recherche.toLowerCase().split(/\s+/).filter(Boolean);
  const categories = feuille.categories
    .map((c) => ({
      ...c,
      agents: c.agents.filter((a) => {
        const texte = `${a.nom} ${a.matricule ?? ""}`.toLowerCase();
        return mots.every((m) => texte.includes(m));
      }),
    }))
    .filter((c) => c.agents.length > 0);

  if (categories.length === 0) {
    return (
      <Card>
        <CardBody>
          <p className="py-8 text-center text-sm text-ink-muted">
            Aucun agent sur la période. Modifiez la période ou les filtres.
          </p>
        </CardBody>
      </Card>
    );
  }

  const cell = "border border-border px-0.5 py-1 text-center";

  return (
    <div className="space-y-6">
      {categories.map((categorie) => (
        <Card key={categorie.type}>
          <CardBody className="space-y-3">
            <h3 className="text-center text-sm font-semibold uppercase tracking-wider text-ink">
              {categorie.libelle}
              <span className="ml-2 font-normal normal-case tracking-normal text-ink-faint">
                {categorie.agents.length} agent
                {categorie.agents.length > 1 ? "s" : ""}
              </span>
            </h3>
            <div className="overflow-x-auto">
              <table className="w-max min-w-full border-collapse text-xs">
                <thead className="bg-paper-muted text-ink">
                  <tr>
                    <th
                      rowSpan={2}
                      className={`${cell} sticky left-0 z-10 min-w-56 bg-paper-muted px-2 text-left`}
                    >
                      Nom et prénoms
                    </th>
                    <th rowSpan={2} className={`${cell} px-2`}>
                      Statut
                    </th>
                    <th colSpan={feuille.jours.length} className={cell}>
                      État de présence
                    </th>
                    <th rowSpan={2} className={`${cell} w-20 px-1 leading-tight`}>
                      Jours de travail effectués
                    </th>
                    <th rowSpan={2} className={`${cell} w-16 px-1 leading-tight`}>
                      Jours d&apos;absence
                    </th>
                    <th rowSpan={2} className={`${cell} min-w-32 px-2`}>
                      Observations
                    </th>
                  </tr>
                  <tr>
                    {feuille.jours.map((j) => (
                      <th
                        key={j.date}
                        className={`${cell} w-7 font-medium leading-tight ${
                          j.jour === "D" ? "bg-border/60" : ""
                        }`}
                        title={j.date}
                      >
                        <span className="block text-[10px] text-ink-muted">
                          {j.jour}
                        </span>
                        {Number(j.date.slice(8, 10))}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {categorie.agents.map((a) => (
                    <tr key={a.agent_id} className="hover:bg-paper-muted/50">
                      <td
                        className={`${cell} sticky left-0 z-10 bg-white px-2 text-left font-medium whitespace-nowrap`}
                      >
                        {a.nom}
                        {a.matricule ? (
                          <span className="ml-1 text-[10px] font-normal text-ink-faint">
                            {a.matricule}
                          </span>
                        ) : null}
                      </td>
                      <td className={`${cell} px-2 whitespace-nowrap`}>
                        {a.contrat ? CONTRAT[a.contrat] ?? a.contrat : "—"}
                      </td>
                      {feuille.jours.map((j) => {
                        const v = a.jours[j.date] ?? null;
                        return (
                          <td key={j.date} className={`${cell} ${classeCase(v)}`}>
                            {v ?? ""}
                          </td>
                        );
                      })}
                      <td className={`${cell} font-semibold`}>{a.total}</td>
                      <td
                        className={`${cell} font-semibold ${
                          a.absences > 0 ? "text-danger" : "text-ink-faint"
                        }`}
                      >
                        {a.absences}
                      </td>
                      <td className={cell} />
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </CardBody>
        </Card>
      ))}
    </div>
  );
}
