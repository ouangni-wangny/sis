"use client";

import { format, parseISO, isToday as isTodayFn } from "date-fns";
import { fr } from "date-fns/locale";
import { useEffect, useMemo, useState } from "react";
import { useQueries } from "@tanstack/react-query";
import {
  ChevronLeft,
  ChevronRight,
  Moon,
  Plus,
  Sun,
  AlertTriangle,
} from "lucide-react";
import {
  usePostes,
  useVacations,
} from "@/application/hooks/useResources";
import { postesApi } from "@/infrastructure/http/resources";
import type { Poste, PosteCoverage, Vacation } from "@/domain/types/entities";
import {
  hasDecoupageQuarts,
  isCycle24hInterval,
  planningCouvertureHint,
  planningCouvertureResume,
} from "@/domain/schemas/poste-horaires";
import { isCycle24h, isOvernight } from "@/domain/time/shift-interval";
import { Badge } from "@/presentation/components/ui/Badge";
import { Button } from "@/presentation/components/ui/Button";
import { Select } from "@/presentation/components/ui/Select";
import { Spinner } from "@/presentation/components/ui/Spinner";
import { cn } from "@/shared/lib/cn";

const weekdayLabels = ["Lun", "Mar", "Mer", "Jeu", "Ven", "Sam", "Dim"];

type WeekPosteAgent = {
  agentId: string;
  label: string;
  /** Jours réellement travaillés (hors annulé / trou). */
  workDates: Set<string>;
  /** Jours où l’agent était l’affecté d’origine (y compris trou / annulé). */
  rosterDates: Set<string>;
};

/**
 * Agents du poste sur la semaine.
 * Les trous / annulées comptent pour la rotation (titulaire),
 * pas seulement les vacations actives (sinon un remplaçant 1 jour
 * est pris pour un agent « habituel » en repos les autres jours).
 */
function posteAgentsThisWeek(
  vacations: Vacation[],
  posteId: string,
  weekDatesList: string[],
): WeekPosteAgent[] {
  const weekSet = new Set(weekDatesList);
  const byAgent = new Map<string, WeekPosteAgent>();

  for (const v of vacations) {
    if (v.poste_id !== posteId) continue;
    if (!v.agent_id) continue;

    const start = v.date_debut.slice(0, 10);
    const touchesWeek = weekDatesList.some((d) => vacationShowsOnDate(v, d));
    if (!touchesWeek && !weekSet.has(start)) continue;

    const isHoleOrCancelled =
      v.statut === "annulee" || v.statut === "a_recouvrir";

    let entry = byAgent.get(v.agent_id);
    if (!entry) {
      const prenom = v.agent?.prenom?.trim() || "";
      const nom = v.agent?.nom?.trim() || "";
      entry = {
        agentId: v.agent_id,
        label: `${prenom} ${nom}`.trim() || "Agent",
        workDates: new Set(),
        rosterDates: new Set(),
      };
      byAgent.set(v.agent_id, entry);
    }

    for (const d of weekDatesList) {
      if (!vacationShowsOnDate(v, d)) continue;
      entry.rosterDates.add(d);
      if (!isHoleOrCancelled) entry.workDates.add(d);
    }
  }

  return [...byAgent.values()];
}

/** Remplaçant ponctuel : ne travaille que sur des jours où un titulaire a un trou/annulation. */
function isRemplacantPonctuel(
  agent: WeekPosteAgent,
  vacations: Vacation[],
  posteId: string,
): boolean {
  if (agent.workDates.size === 0) return false;
  // Titulaire s’il a au moins un trou / annulation à son nom.
  if (agent.rosterDates.size > agent.workDates.size) return false;

  for (const d of agent.workDates) {
    const coversHole = vacations.some(
      (v) =>
        v.poste_id === posteId &&
        Boolean(v.agent_id) &&
        v.agent_id !== agent.agentId &&
        (v.statut === "annulee" || v.statut === "a_recouvrir") &&
        vacationShowsOnDate(v, d),
    );
    if (!coversHole) return false;
  }
  return true;
}

/**
 * Agents de la rotation absents ce jour → repos inféré.
 * Un remplaçant ponctuel ne s’affiche pas en « Repos » les autres jours.
 * Un titulaire dont la vacation du jour est annulée / à recouvrir
 * (absent, remplacé) n’est pas en repos non plus.
 * Jour sans vacation active → pas de liste repos (jour vide / non planifié).
 */
function restingAgentsForDay(
  weekAgents: WeekPosteAgent[],
  date: string,
  dayVacations: Vacation[],
  allVacations: Vacation[],
  posteId: string,
): WeekPosteAgent[] {
  if (dayVacations.length === 0) return [];

  const present = new Set(dayVacations.map((v) => v.agent_id).filter(Boolean));

  // Titulaires prévus ce jour mais absents / remplacés (trou ou annulation).
  const scheduledOff = new Set(
    allVacations
      .filter(
        (v) =>
          v.poste_id === posteId &&
          Boolean(v.agent_id) &&
          (v.statut === "annulee" || v.statut === "a_recouvrir") &&
          vacationShowsOnDate(v, date),
      )
      .map((v) => v.agent_id as string),
  );

  return weekAgents.filter((a) => {
    if (present.has(a.agentId)) return false;
    if (a.workDates.has(date)) return false;
    if (scheduledOff.has(a.agentId)) return false;
    if (isRemplacantPonctuel(a, allVacations, posteId)) return false;
    return a.rosterDates.size > 0;
  });
}

function toIso(d: Date) {
  return d.toISOString().slice(0, 10);
}

function formatWeekDayLong(iso: string) {
  const label = format(parseISO(iso), "EEEE d MMMM yyyy", { locale: fr });
  return label.charAt(0).toUpperCase() + label.slice(1);
}

function mondayOf(iso: string): Date {
  const d = new Date(`${iso}T00:00:00`);
  const day = d.getDay();
  const diff = day === 0 ? -6 : 1 - day;
  d.setDate(d.getDate() + diff);
  return d;
}

function weekDates(mondayIso: string): string[] {
  const monday = new Date(`${mondayIso}T00:00:00`);
  return Array.from({ length: 7 }, (_, i) => {
    const d = new Date(monday);
    d.setDate(d.getDate() + i);
    return toIso(d);
  });
}

function vacationShowsOnDate(v: Vacation, targetIso: string): boolean {
  const startDate = v.date_debut.slice(0, 10);
  // Overnight / cycle 24h : le créneau est rattaché au jour de début (relève).
  if (
    isOvernight(v.heure_debut, v.heure_fin) ||
    isCycle24h(v.heure_debut, v.heure_fin)
  ) {
    return targetIso === startDate;
  }
  if (!v.date_fin) return targetIso >= startDate;
  const endDate = v.date_fin.slice(0, 10);
  return targetIso >= startDate && targetIso <= endDate;
}

function vacationCoversDate(v: Vacation, targetIso: string): boolean {
  if (v.statut === "annulee" || v.statut === "a_recouvrir") return false;
  return vacationShowsOnDate(v, targetIso);
}

function coverageCellClass(statut: PosteCoverage["statut"] | undefined) {
  switch (statut) {
    case "ok":
      return "border-emerald-500/50 bg-emerald-50";
    case "sous_effectif":
      return "border-amber-500/60 bg-amber-50";
    case "sur_effectif":
      return "border-violet-500/55 bg-violet-50";
    case "non_couvert":
      return "border-rose-500/55 bg-rose-50";
    default:
      return "border-border/80 bg-paper/40";
  }
}

function coverageChipClass(statut: PosteCoverage["statut"] | undefined) {
  switch (statut) {
    case "ok":
      return "bg-emerald-600 text-white";
    case "sous_effectif":
      return "bg-amber-500 text-white";
    case "sur_effectif":
      return "bg-violet-600 text-white";
    case "non_couvert":
      return "bg-rose-600 text-white";
    default:
      return "bg-paper-muted text-ink-faint";
  }
}

function coverageHint(
  statut: PosteCoverage["statut"] | undefined,
  reposLabels?: string[],
) {
  const repos =
    reposLabels && reposLabels.length > 0
      ? ` · Repos : ${reposLabels.join(", ")}`
      : "";
  switch (statut) {
    case "ok":
      return "Effectif complet";
    case "sous_effectif":
      return `Sous-effectif${repos || " — ajoutez un agent"}`;
    case "sur_effectif":
      return "Anomalie — trop d’agents (données à corriger, ne devrait plus être créable)";
    case "non_couvert":
      return repos
        ? `Non couvert${repos}`
        : "Non couvert — planifiez un agent";
    default:
      return "Couverture en cours de calcul";
  }
}

/** Capacité journalière affichée (alternance = 1). */
function posteCapaciteJour(poste: Pick<
  Poste,
  | "agents_requis"
  | "mode_effectif"
  | "heure_debut_nuit"
  | "heure_fin_nuit"
>): number {
  const n = Math.max(1, poste.agents_requis ?? 1);
  const hours = {
    heure_debut: "",
    heure_fin: "",
    heure_debut_nuit: poste.heure_debut_nuit?.slice(0, 5) ?? "",
    heure_fin_nuit: poste.heure_fin_nuit?.slice(0, 5) ?? "",
  };
  if (hasDecoupageQuarts(hours)) return n;
  if (n >= 2 && poste.mode_effectif === "alternance") return 1;
  return n;
}

function posteSidebarMeta(poste: Poste) {
  const agentsRequis = Math.max(1, poste.agents_requis ?? 1);
  const hours = {
    heure_debut: poste.heure_debut?.slice(0, 5) ?? "",
    heure_fin: poste.heure_fin?.slice(0, 5) ?? "",
    heure_debut_nuit: poste.heure_debut_nuit?.slice(0, 5) ?? "",
    heure_fin_nuit: poste.heure_fin_nuit?.slice(0, 5) ?? "",
  };
  const hasQuarts = hasDecoupageQuarts(hours);
  const cycle24h = isCycle24hInterval(hours);
  const modeEffectif =
    !hasQuarts && agentsRequis >= 2 && poste.mode_effectif === "alternance"
      ? ("alternance" as const)
      : ("ensemble" as const);

  const badgeParts = [
    `${agentsRequis} agent${agentsRequis > 1 ? "s" : ""}`,
    hasQuarts ? "jour & nuit" : cycle24h ? "24h" : null,
    !hasQuarts && agentsRequis >= 2
      ? modeEffectif === "alternance"
        ? "alternance"
        : "ensemble"
      : null,
  ].filter(Boolean);

  return {
    badge: badgeParts.join(" · "),
    horaires: hours.heure_debut && hours.heure_fin
      ? planningCouvertureResume(hours, agentsRequis)
      : null,
    hint: planningCouvertureHint(hours, agentsRequis, modeEffectif),
    capaciteJour: posteCapaciteJour(poste),
  };
}

/** Peut-on encore ajouter un agent ce jour sans dépasser l’effectif ? */
function canAddAgentToCell(
  cov: PosteCoverage | undefined,
  agentsRequis: number,
  dayCount: number,
): boolean {
  if (cov?.statut === "sur_effectif" || cov?.statut === "ok") return false;
  if (cov?.couverture_24h && cov.jour && cov.nuit) {
    return (cov.jour.manquant ?? 0) > 0 || (cov.nuit.manquant ?? 0) > 0;
  }
  if (cov) return (cov.manquant ?? 0) > 0;
  return dayCount < agentsRequis;
}

function isNightShift(heureDebut: string, heureFin: string) {
  return isOvernight(heureDebut, heureFin);
}

export type WeekCoverageStats = {
  ok: number;
  sous: number;
  sur: number;
  non: number;
  trous: number;
  cells: number;
};

/** Contexte pour ajouter un agent sur un jour précis depuis la grille. */
export type CreateForDayContext = {
  posteId: string;
  siteId: string;
  date: string;
  posteNom: string;
  agentsRequis: number;
  heure_debut: string | null;
  heure_fin: string | null;
  heure_debut_nuit: string | null;
  heure_fin_nuit: string | null;
  occupied: Array<{
    agent_id: string;
    heure_debut: string;
    heure_fin: string;
  }>;
};

export function WeekGrid({
  siteId,
  siteOptions,
  onSiteChange,
  onCreateFor,
  onEditVacation,
  onWeekStats,
}: {
  siteId: string;
  siteOptions: { value: string; label: string }[];
  onSiteChange: (siteId: string) => void;
  onCreateFor: (ctx: CreateForDayContext) => void;
  onEditVacation: (vacation: Vacation) => void;
  onWeekStats?: (stats: WeekCoverageStats | null) => void;
}) {
  const [weekStart, setWeekStart] = useState(() =>
    toIso(mondayOf(toIso(new Date()))),
  );
  const dates = useMemo(() => weekDates(weekStart), [weekStart]);
  const todayIso = toIso(new Date());

  const { data: postesData, isLoading: postesLoading } = usePostes(
    siteId ? { site_id: siteId, all: true } : undefined,
    { enabled: !!siteId },
  );
  const { data: vacationsData, isLoading: vacationsLoading } = useVacations(
    siteId ? { site_id: siteId, per_page: 200 } : undefined,
  );

  const coverageQueries = useQueries({
    queries: dates.map((date) => ({
      queryKey: ["postes", "coverage", { site_id: siteId, date }],
      queryFn: () => postesApi.coverage({ site_id: siteId, date }),
      enabled: !!siteId,
    })),
  });

  const coverageByDate = useMemo(() => {
    const map = new Map<string, PosteCoverage[]>();
    dates.forEach((date, i) => {
      map.set(date, coverageQueries[i]?.data?.data ?? []);
    });
    return map;
  }, [dates, coverageQueries]);

  const postes = postesData?.data ?? [];
  const vacations = vacationsData?.data ?? [];
  const loading = postesLoading || vacationsLoading;

  const weekStats = useMemo(() => {
    let ok = 0;
    let sous = 0;
    let sur = 0;
    let non = 0;
    let trous = 0;
    for (const date of dates) {
      for (const c of coverageByDate.get(date) ?? []) {
        if (c.statut === "ok") ok++;
        else if (c.statut === "sous_effectif") sous++;
        else if (c.statut === "sur_effectif") sur++;
        else if (c.statut === "non_couvert") non++;
      }
      trous += vacations.filter(
        (v) => v.statut === "a_recouvrir" && vacationShowsOnDate(v, date),
      ).length;
    }
    return { ok, sous, sur, non, trous, cells: ok + sous + sur + non };
  }, [dates, coverageByDate, vacations]);

  useEffect(() => {
    if (!onWeekStats) return;
    if (!siteId) {
      onWeekStats(null);
      return;
    }
    onWeekStats(weekStats);
  }, [
    onWeekStats,
    siteId,
    weekStats.ok,
    weekStats.sous,
    weekStats.sur,
    weekStats.non,
    weekStats.trous,
    weekStats.cells,
  ]);

  const goThisWeek = () => setWeekStart(toIso(mondayOf(todayIso)));

  return (
    <div className="space-y-4">
      {/* Toolbar */}
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div className="w-full max-w-xs sm:w-72">
          <Select
            label="Site à planifier"
            placeholder="Sélectionnez un site"
            options={siteOptions}
            value={siteId}
            onChange={(e) => onSiteChange(e.target.value)}
          />
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Button
            variant="secondary"
            size="sm"
            onClick={() =>
              setWeekStart((w) => {
                const d = new Date(`${w}T00:00:00`);
                d.setDate(d.getDate() - 7);
                return toIso(d);
              })
            }
            aria-label="Semaine précédente"
          >
            <ChevronLeft className="size-4" />
          </Button>
          <div className="min-w-[14rem] text-center">
            <p className="text-sm font-semibold text-ink">
              {dates[0] ? format(parseISO(dates[0]), "d MMM", { locale: fr }) : "…"}
              {" – "}
              {dates[6]
                ? format(parseISO(dates[6]), "d MMM yyyy", { locale: fr })
                : "…"}
            </p>
            <p className="text-[11px] text-ink-faint">
              Semaine du {dates[0] ? formatWeekDayLong(dates[0]) : "…"}
            </p>
          </div>
          <Button
            variant="secondary"
            size="sm"
            onClick={() =>
              setWeekStart((w) => {
                const d = new Date(`${w}T00:00:00`);
                d.setDate(d.getDate() + 7);
                return toIso(d);
              })
            }
            aria-label="Semaine suivante"
          >
            <ChevronRight className="size-4" />
          </Button>
          <Button variant="secondary" size="sm" onClick={goThisWeek}>
            Aujourd’hui
          </Button>
        </div>
      </div>

      {/* Légende + résumé */}
      {siteId ? (
        <div className="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-lg border border-border bg-white px-3 py-2.5 text-xs text-ink">
          <span className="font-bold uppercase tracking-wide text-ink-muted">
            Légende
          </span>
          <span className="inline-flex items-center gap-1.5 font-medium">
            <span className="size-3 rounded-sm bg-emerald-500 ring-1 ring-emerald-700/30" />
            Complet
          </span>
          <span className="inline-flex items-center gap-1.5 font-medium">
            <span className="size-3 rounded-sm bg-amber-400 ring-1 ring-amber-700/30" />
            Sous-effectif
          </span>
          <span className="inline-flex items-center gap-1.5 font-medium">
            <span className="size-3 rounded-sm bg-violet-500 ring-1 ring-violet-800/30" />
            Sur-effectif (anomalie)
          </span>
          <span className="inline-flex items-center gap-1.5 font-medium">
            <span className="size-3 rounded-sm bg-rose-500 ring-1 ring-rose-800/30" />
            Non couvert
          </span>
          <span className="inline-flex items-center gap-1.5 font-medium">
            <span className="size-3 rounded-sm border border-dashed border-amber-500 bg-amber-50 ring-1 ring-amber-700/20" />
            Jour de repos
          </span>
          <span className="inline-flex items-center gap-1.5 font-medium">
            <span className="size-3 rounded-sm bg-fuchsia-500 ring-2 ring-fuchsia-700" />
            Trou à recouvrir
          </span>
          <span className="ml-auto flex flex-wrap gap-3 font-mono text-[11px] tabular-nums">
            <span>
              <strong className="text-emerald-600">{weekStats.ok}</strong> ok
            </span>
            <span>
              <strong className="text-amber-600">{weekStats.sous}</strong> sous
            </span>
            {weekStats.sur > 0 ? (
              <span>
                <strong className="text-violet-600">{weekStats.sur}</strong> sur
              </span>
            ) : null}
            <span>
              <strong className="text-rose-600">{weekStats.non}</strong> vides
            </span>
            {weekStats.trous > 0 ? (
              <span className="inline-flex items-center gap-1 text-fuchsia-700">
                <AlertTriangle className="size-3" />
                <strong>{weekStats.trous}</strong> trou(s)
              </span>
            ) : null}
          </span>
        </div>
      ) : null}

      {!siteId ? (
        <div className="rounded-xl border border-dashed border-border bg-paper/50 px-6 py-10 text-center">
          <p className="text-sm font-semibold text-ink">
            Commencez par choisir un site
          </p>
          <p className="mx-auto mt-2 max-w-md text-sm text-ink-muted">
            La grille affiche chaque poste du site sur 7 jours. Les cases
            colorées indiquent la couverture ; le bouton « + » lance la
            planification d’un agent.
          </p>
          <ol className="mx-auto mt-4 max-w-sm space-y-1.5 text-left text-xs text-ink-muted">
            <li>
              <span className="font-mono font-semibold text-teal">1.</span>{" "}
              Sélectionnez le site ci-dessus
            </li>
            <li>
              <span className="font-mono font-semibold text-teal">2.</span>{" "}
              Repérez les cases rouges / orange
            </li>
            <li>
              <span className="font-mono font-semibold text-teal">3.</span>{" "}
              Cliquez « + » pour affecter un agent
            </li>
          </ol>
        </div>
      ) : loading ? (
        <div className="flex justify-center py-12">
          <Spinner className="size-6" />
        </div>
      ) : postes.length === 0 ? (
        <div className="rounded-xl border border-border bg-paper/50 px-6 py-8 text-center">
          <p className="text-sm font-semibold text-ink">Aucun poste sur ce site</p>
          <p className="mt-1 text-sm text-ink-muted">
            Créez d’abord des postes dans la fiche site, puis revenez planifier.
          </p>
        </div>
      ) : (
        <div className="overflow-x-auto rounded-xl border border-border shadow-sm">
          <table className="w-full min-w-[920px] border-collapse text-sm">
            <thead>
              <tr className="border-b border-border bg-teal/[0.06]">
                <th className="sticky left-0 z-10 w-44 bg-teal/[0.06] px-3 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-teal">
                  Poste
                </th>
                {dates.map((date, i) => {
                  const today = isTodayFn(parseISO(date));
                  return (
                    <th
                      key={date}
                      className={cn(
                        "min-w-[118px] px-2 py-3 text-center",
                        today && "bg-teal/10",
                      )}
                    >
                      <span
                        className={cn(
                          "block text-[10px] font-bold uppercase tracking-wider",
                          today ? "text-teal" : "text-ink-faint",
                        )}
                      >
                        {weekdayLabels[i]}
                      </span>
                      <span
                        className={cn(
                          "mt-0.5 block text-sm font-semibold tabular-nums",
                          today ? "text-teal" : "text-ink",
                        )}
                      >
                        {date.slice(8, 10)}/{date.slice(5, 7)}
                      </span>
                      {today ? (
                        <span className="mt-0.5 inline-block rounded-full bg-teal px-1.5 text-[9px] font-semibold uppercase tracking-wide text-white">
                          Aujourd’hui
                        </span>
                      ) : null}
                    </th>
                  );
                })}
              </tr>
            </thead>
            <tbody>
              {postes.map((poste) => {
                const meta = posteSidebarMeta(poste);
                const weekAgents = posteAgentsThisWeek(
                  vacations,
                  poste.id,
                  dates,
                );
                return (
                  <tr
                    key={poste.id}
                    className="border-b border-border/60 last:border-0"
                  >
                    <td className="sticky left-0 z-10 bg-white px-3 py-3 align-top shadow-[2px_0_6px_-4px_rgba(0,0,0,0.12)]">
                      <p className="font-semibold text-ink">{poste.nom}</p>
                      <div className="mt-1.5 space-y-1">
                        <Badge tone="success">{meta.badge}</Badge>
                        {meta.horaires ? (
                          <p className="text-[11px] text-ink-muted">
                            Horaires :{" "}
                            <span className="font-medium text-ink">
                              {meta.horaires}
                            </span>
                          </p>
                        ) : null}
                        {meta.hint ? (
                          <p className="text-[11px] leading-snug text-ink-muted">
                            {meta.hint}
                          </p>
                        ) : null}
                      </div>
                    </td>
                    {dates.map((date) => {
                      const dayVacations = vacations.filter(
                        (v) =>
                          v.poste_id === poste.id &&
                          vacationCoversDate(v, date),
                      );
                      const dayHoles = vacations.filter(
                        (v) =>
                          v.poste_id === poste.id &&
                          v.statut === "a_recouvrir" &&
                          vacationShowsOnDate(v, date),
                      );
                      const resting = restingAgentsForDay(
                        weekAgents,
                        date,
                        dayVacations,
                        vacations,
                        poste.id,
                      );
                      const cov = coverageByDate
                        .get(date)
                        ?.find((c) => c.poste_id === poste.id);
                      const today = date === todayIso;
                      const empty =
                        dayVacations.length === 0 &&
                        dayHoles.length === 0 &&
                        resting.length === 0;
                      const allowAdd = canAddAgentToCell(
                        cov,
                        meta.capaciteJour,
                        dayVacations.length,
                      );

                      return (
                        <td
                          key={date}
                          className={cn(
                            "px-1.5 py-1.5 align-top",
                            today && "bg-teal/[0.03]",
                          )}
                        >
                          <div
                            className={cn(
                              "flex min-h-[7.5rem] flex-col gap-1 rounded-lg border p-1.5 transition",
                              coverageCellClass(cov?.statut),
                            )}
                            title={coverageHint(
                              cov?.statut,
                              resting.map((a) => a.label),
                            )}
                          >
                            <div className="flex items-center justify-between gap-1">
                              <span
                                className={cn(
                                  "rounded px-1.5 py-0.5 font-mono text-[10px] font-semibold tabular-nums",
                                  coverageChipClass(cov?.statut),
                                )}
                              >
                                {dayVacations.length}/{meta.capaciteJour}
                              </span>
                              {allowAdd ? (
                                <button
                                  type="button"
                                  onClick={() =>
                                    onCreateFor({
                                      posteId: poste.id,
                                      siteId,
                                      date,
                                      posteNom: poste.nom,
                                      agentsRequis: poste.agents_requis,
                                      heure_debut: poste.heure_debut,
                                      heure_fin: poste.heure_fin,
                                      heure_debut_nuit: poste.heure_debut_nuit,
                                      heure_fin_nuit: poste.heure_fin_nuit,
                                      occupied: dayVacations.map((v) => ({
                                        agent_id: v.agent_id,
                                        heure_debut: v.heure_debut,
                                        heure_fin: v.heure_fin,
                                      })),
                                    })
                                  }
                                  className="inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 text-[10px] font-semibold text-teal transition hover:bg-teal/10"
                                  aria-label={`Ajouter un agent sur ${poste.nom} le ${date}`}
                                  title="Ajouter un agent pour ce jour"
                                >
                                  <Plus className="size-3.5" />
                                  Ajouter
                                </button>
                              ) : (
                                <span
                                  className="px-1 text-[10px] font-medium text-ink-faint"
                                  title={
                                    cov?.statut === "sur_effectif"
                                      ? "Sur-effectif — retirez un agent en trop"
                                      : "Effectif complet"
                                  }
                                >
                                  {cov?.statut === "sur_effectif"
                                    ? "Sur-effectif"
                                    : "Complet"}
                                </span>
                              )}
                            </div>

                            <div className="flex flex-1 flex-col gap-1">
                              {dayVacations.map((v) => {
                                const night = isNightShift(
                                  v.heure_debut,
                                  v.heure_fin,
                                );
                                return (
                                  <button
                                    key={v.id}
                                    type="button"
                                    onClick={() => onEditVacation(v)}
                                    className="w-full rounded-md border border-border/70 bg-white px-1.5 py-1 text-left shadow-sm transition hover:border-teal hover:shadow"
                                    title={`Modifier · ${v.agent?.prenom ?? ""} ${v.agent?.nom ?? ""} · ${v.heure_debut?.slice(0, 5)}–${v.heure_fin?.slice(0, 5)}`}
                                  >
                                    <span className="block truncate text-[11px] font-medium text-ink">
                                      {v.agent?.prenom} {v.agent?.nom}
                                    </span>
                                    <span className="mt-0.5 flex items-center gap-1 text-[10px] text-ink-faint">
                                      {night ? (
                                        <Moon className="size-2.5 shrink-0" />
                                      ) : (
                                        <Sun className="size-2.5 shrink-0" />
                                      )}
                                      {v.heure_debut?.slice(0, 5)}–
                                      {v.heure_fin?.slice(0, 5)}
                                    </span>
                                  </button>
                                );
                              })}

                              {dayHoles.map((v) => (
                                <button
                                  key={v.id}
                                  type="button"
                                  onClick={() => onEditVacation(v)}
                                  className="w-full rounded-md border-2 border-fuchsia-500 bg-fuchsia-50 px-1.5 py-1 text-left transition hover:bg-fuchsia-100"
                                  title={`Recouvrir ce trou · ${v.agent?.prenom ?? ""} ${v.agent?.nom ?? ""}`}
                                >
                                  <span className="flex items-center gap-1 truncate text-[11px] font-semibold text-fuchsia-800">
                                    <AlertTriangle className="size-3 shrink-0" />
                                    Trou · {v.agent?.prenom} {v.agent?.nom}
                                  </span>
                                  <span className="mt-0.5 block truncate text-[10px] text-fuchsia-700/90">
                                    Cliquez pour assigner un remplaçant
                                  </span>
                                </button>
                              ))}

                              {resting.map((a) => (
                                <div
                                  key={`repos-${a.agentId}`}
                                  className="w-full rounded-md border border-dashed border-amber-400/70 bg-amber-50/80 px-1.5 py-1 text-left"
                                  title={`Jour de repos · ${a.label}`}
                                >
                                  <span className="block truncate text-[11px] font-medium text-amber-900">
                                    Repos · {a.label}
                                  </span>
                                  <span className="mt-0.5 block text-[10px] text-amber-800/80">
                                    Jour de repos
                                  </span>
                                </div>
                              ))}

                              {empty ? (
                                <button
                                  type="button"
                                  onClick={() =>
                                    onCreateFor({
                                      posteId: poste.id,
                                      siteId,
                                      date,
                                      posteNom: poste.nom,
                                      agentsRequis: poste.agents_requis,
                                      heure_debut: poste.heure_debut,
                                      heure_fin: poste.heure_fin,
                                      heure_debut_nuit: poste.heure_debut_nuit,
                                      heure_fin_nuit: poste.heure_fin_nuit,
                                      occupied: [],
                                    })
                                  }
                                  className="mt-auto flex flex-1 flex-col items-center justify-center rounded-md border border-dashed border-border/80 px-1 py-2 text-center transition hover:border-teal hover:bg-teal/5"
                                >
                                  <Plus className="size-4 text-ink-faint" />
                                  <span className="mt-1 text-[10px] leading-tight text-ink-faint">
                                    {cov?.statut === "non_couvert"
                                      ? "Poste vide — ajouter"
                                      : "Ajouter un agent"}
                                  </span>
                                </button>
                              ) : null}
                            </div>
                          </div>
                        </td>
                      );
                    })}
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
