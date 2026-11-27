"use client";

import { useMemo, useState } from "react";
import { format, parseISO } from "date-fns";
import { fr } from "date-fns/locale";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { useAbsences, useVacations } from "@/application/hooks/useResources";
import { labelTypeAbsence } from "@/domain/schemas/absence";
import type { Vacation } from "@/domain/types/entities";
import { Badge } from "@/presentation/components/ui/Badge";
import { Button } from "@/presentation/components/ui/Button";
import { Spinner } from "@/presentation/components/ui/Spinner";
import { cn } from "@/shared/lib/cn";

function isoLocal(d: Date) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}

export function lundiDe(iso: string) {
  const d = new Date(`${iso}T12:00:00`);
  const jour = d.getDay();
  d.setDate(d.getDate() + (jour === 0 ? -6 : 1 - jour));
  return isoLocal(d);
}

function plusJours(iso: string, n: number) {
  const d = new Date(`${iso}T12:00:00`);
  d.setDate(d.getDate() + n);
  return isoLocal(d);
}

const hm = (v: string | null | undefined) => (v ?? "").slice(0, 5);

const STATUT: Record<string, { label: string; tone: "neutral" | "success" | "warning" | "danger" | "info" }> = {
  planifiee: { label: "Prévue", tone: "info" },
  en_cours: { label: "En cours", tone: "success" },
  terminee: { label: "Effectuée", tone: "success" },
  annulee: { label: "Annulée", tone: "neutral" },
  a_recouvrir: { label: "Absent", tone: "danger" },
};

function statutVacation(v: Vacation) {
  if (v.remplacee) return { label: "Remplacé", tone: "neutral" as const };
  return STATUT[v.statut] ?? { label: v.statut, tone: "neutral" as const };
}

/**
 * Semaine de l’agent jour par jour : où il travaille (site, poste, horaires),
 * ses absences et ses repos.
 */
export function AgentSemainePlanning({
  agentId,
  reposDayIndex,
  estRondier,
  canViewAbsences,
}: {
  agentId: string;
  /** 0 = dimanche … 6 = samedi (jour de repos fixe), ou null. */
  reposDayIndex: number | null;
  estRondier: boolean;
  canViewAbsences: boolean;
}) {
  const aujourdhui = isoLocal(new Date());
  const [lundi, setLundi] = useState(() => lundiDe(aujourdhui));
  const dimanche = plusJours(lundi, 6);
  const jours = useMemo(
    () => Array.from({ length: 7 }, (_, i) => plusJours(lundi, i)),
    [lundi],
  );

  const { data: vacData, isLoading } = useVacations({
    agent_id: agentId,
    date_from: lundi,
    date_to: dimanche,
    per_page: 100,
  });
  const { data: absData } = useAbsences(
    { agent_id: agentId, per_page: 100 },
    { enabled: canViewAbsences },
  );

  const parJour = useMemo(() => {
    const map = new Map<string, Vacation[]>();
    for (const v of vacData?.data ?? []) {
      const d = v.date_debut.slice(0, 10);
      map.set(d, [...(map.get(d) ?? []), v]);
    }
    for (const list of map.values()) {
      list.sort((a, b) => a.heure_debut.localeCompare(b.heure_debut));
    }
    return map;
  }, [vacData]);

  const absenceDu = (jour: string) =>
    (absData?.data ?? []).find(
      (a) =>
        a.date_debut.slice(0, 10) <= jour &&
        (a.date_fin ? a.date_fin.slice(0, 10) : a.date_debut.slice(0, 10)) >= jour,
    );

  const titre = `Semaine du ${format(parseISO(lundi), "d MMMM", { locale: fr })} au ${format(parseISO(dimanche), "d MMMM yyyy", { locale: fr })}`;

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="text-sm font-medium text-ink">{titre}</p>
        <div className="flex items-center gap-1">
          <Button
            size="sm"
            variant="secondary"
            aria-label="Semaine précédente"
            onClick={() => setLundi(plusJours(lundi, -7))}
          >
            <ChevronLeft className="size-4" />
          </Button>
          <Button
            size="sm"
            variant="secondary"
            onClick={() => setLundi(lundiDe(aujourdhui))}
            disabled={lundi === lundiDe(aujourdhui)}
          >
            Cette semaine
          </Button>
          <Button
            size="sm"
            variant="secondary"
            aria-label="Semaine suivante"
            onClick={() => setLundi(plusJours(lundi, 7))}
          >
            <ChevronRight className="size-4" />
          </Button>
        </div>
      </div>

      {isLoading ? (
        <div className="flex justify-center py-12">
          <Spinner className="size-6" />
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-2 sm:grid-cols-7">
          {jours.map((jour) => {
            const vacations = parJour.get(jour) ?? [];
            const absence = absenceDu(jour);
            const jourIndex = new Date(`${jour}T12:00:00`).getDay();
            const estRepos = !estRondier && reposDayIndex === jourIndex;
            const estAujourdhui = jour === aujourdhui;
            const entete = format(parseISO(jour), "EEE d", { locale: fr });
            return (
              <div
                key={jour}
                className={cn(
                  "flex min-h-28 flex-col rounded-lg border bg-white p-2",
                  estAujourdhui ? "border-teal ring-1 ring-teal/30" : "border-border",
                  jour < aujourdhui && "bg-paper-muted/40",
                )}
              >
                <p
                  className={cn(
                    "mb-1.5 text-xs font-semibold capitalize",
                    estAujourdhui ? "text-teal-dark" : "text-ink-muted",
                  )}
                >
                  {entete}
                  {estAujourdhui ? " · aujourd’hui" : ""}
                </p>
                <div className="flex flex-1 flex-col gap-1.5">
                  {absence ? (
                    <div className="rounded-md border border-danger/25 bg-red-50 px-2 py-1 text-xs text-danger">
                      {labelTypeAbsence(absence.type)}
                    </div>
                  ) : null}
                  {vacations.map((v) => {
                    const s = statutVacation(v);
                    const barre = v.statut === "annulee" || v.remplacee;
                    return (
                      <div
                        key={v.id}
                        className={cn(
                          "rounded-md border border-border bg-paper-muted/50 px-2 py-1 text-xs",
                          barre && "opacity-60",
                        )}
                      >
                        <p className={cn("font-medium text-ink", barre && "line-through")}>
                          {v.site?.nom ?? "—"}
                        </p>
                        {v.poste?.nom ? (
                          <p className="text-ink-muted">{v.poste.nom}</p>
                        ) : null}
                        <p className="font-mono text-[11px] text-ink-muted">
                          {hm(v.heure_debut)} – {hm(v.heure_fin)}
                        </p>
                        <div className="mt-1 flex flex-wrap gap-1">
                          <Badge tone={s.tone}>{s.label}</Badge>
                          {v.est_remplacement ? <Badge tone="accent">Remplacement</Badge> : null}
                          {v.heures_supplementaires ? <Badge tone="warning">HS</Badge> : null}
                        </div>
                      </div>
                    );
                  })}
                  {vacations.length === 0 && !absence ? (
                    <p className="m-auto text-xs text-ink-faint">
                      {estRepos ? "Repos" : estRondier ? "Libre" : "—"}
                    </p>
                  ) : null}
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
