"use client";

import { useMemo } from "react";
import { ChevronLeft, ChevronRight } from "lucide-react";
import type { Absence, Controle, Vacation } from "@/domain/types/entities";
import type { PlannedShift } from "@/domain/schemas/agent-planning";
import { cn } from "@/shared/lib/cn";

const WEEKDAYS_SHORT = ["Lun", "Mar", "Mer", "Jeu", "Ven", "Sam", "Dim"];

const LEGEND_ITEMS = [
  {
    key: "jour",
    label: "À créer · jour",
    swatch: "bg-emerald-500",
    hint: "Créneaux générés par ce formulaire",
    planifierOnly: true,
  },
  {
    key: "nuit",
    label: "À créer · nuit",
    swatch: "bg-violet-600",
    hint: "Quarts de nuit à créer",
    planifierOnly: true,
  },
  {
    key: "existant",
    label: "Planifié",
    swatch: "bg-sky-500",
    hint: "Vacation enregistrée",
    planifierOnly: false,
  },
  {
    key: "trou",
    label: "À recouvrir",
    swatch: "bg-rose-500",
    hint: "Trou suite à une absence (contrôle ou RH)",
    planifierOnly: false,
    readonlyOnly: true,
  },
  {
    key: "present",
    label: "Présent",
    swatch: "bg-teal",
    hint: "Contrôle terrain : agent présent",
    planifierOnly: false,
    readonlyOnly: true,
  },
  {
    key: "absent",
    label: "Absent (contrôle)",
    swatch: "bg-orange-500",
    hint: "Absence constatée lors d’un contrôle terrain",
    planifierOnly: false,
    readonlyOnly: true,
  },
  {
    key: "absent_rh",
    label: "Absence RH",
    swatch: "bg-indigo-500",
    hint: "Absence octroyée par la RH",
    planifierOnly: false,
    readonlyOnly: true,
  },
  {
    key: "repos",
    label: "Repos",
    swatch: "bg-amber-400",
    hint: "Jour de repos de l’agent",
    planifierOnly: false,
  },
  {
    key: "conflit",
    label: "Conflit",
    swatch: "bg-rose-500",
    hint: "Chevauchement avec une vacation qui ne sera pas remplacée",
    planifierOnly: true,
  },
] as const;

export function PlanningCalendarLegend({
  className,
  compact = false,
  mode = "planifier",
}: {
  className?: string;
  compact?: boolean;
  /** En lecture seule (fiche agent) : cache les pastilles « à créer / conflit ». */
  mode?: "planifier" | "readonly";
}) {
  const items =
    mode === "readonly"
      ? LEGEND_ITEMS.filter((item) => !item.planifierOnly)
      : LEGEND_ITEMS.filter(
          (item) => !("readonlyOnly" in item && item.readonlyOnly),
        );

  return (
    <div
      className={cn(
        compact
          ? "flex flex-wrap gap-x-3 gap-y-1.5"
          : "grid gap-2 rounded-lg border border-border bg-slate-50/90 p-3 sm:grid-cols-2",
        className,
      )}
      role="list"
      aria-label="Légende du calendrier"
    >
      {!compact ? (
        <p className="text-[10px] font-bold uppercase tracking-wide text-ink-faint sm:col-span-2">
          Légende
        </p>
      ) : null}
      {items.map((item) => (
        <div
          key={item.key}
          role="listitem"
          className={cn(
            "inline-flex items-start gap-2",
            compact ? "text-[10px] font-medium text-ink" : "text-xs text-ink",
          )}
          title={item.hint}
        >
          <span
            className={cn(
              "mt-0.5 shrink-0 rounded-sm shadow-sm",
              compact ? "size-2.5" : "size-3",
              item.swatch,
            )}
            aria-hidden
          />
          <span>
            <span className="font-semibold">{item.label}</span>
            {!compact ? (
              <span className="mt-0.5 block text-[10px] leading-snug text-ink-faint">
                {item.hint}
              </span>
            ) : null}
          </span>
        </div>
      ))}
    </div>
  );
}

function startOfMonth(isoYearMonth: string) {
  const [y, m] = isoYearMonth.split("-").map(Number);
  return new Date(y, m - 1, 1);
}

function toIso(d: Date) {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${y}-${m}-${day}`;
}

function monthLabel(isoYearMonth: string) {
  const d = startOfMonth(isoYearMonth);
  return d.toLocaleDateString("fr-FR", { month: "long", year: "numeric" });
}

function eachDateInclusive(from: string, to: string): string[] {
  const out: string[] = [];
  const cur = new Date(`${from.slice(0, 10)}T12:00:00`);
  const end = new Date(`${to.slice(0, 10)}T12:00:00`);
  while (cur <= end) {
    out.push(toIso(cur));
    cur.setDate(cur.getDate() + 1);
  }
  return out;
}

export function AgentMonthCalendar({
  month,
  onMonthChange,
  planned = [],
  existing,
  absences = [],
  controles = [],
  reposDayIndex,
  reposDayIndexes,
  mode = "planifier",
  showLegend = true,
  density = "comfortable",
}: {
  month: string;
  onMonthChange: (next: string) => void;
  planned?: PlannedShift[];
  existing: Vacation[];
  /** Absences RH (approuvées) à afficher sur le calendrier. */
  absences?: Absence[];
  /** Contrôles terrain (présent / absent). */
  controles?: Controle[];
  /** @deprecated Prefer reposDayIndexes */
  reposDayIndex?: number | null;
  /** Indices JS getDay() (0=dim … 6=sam) pour les jours non travaillés. */
  reposDayIndexes?: number[];
  mode?: "planifier" | "readonly";
  showLegend?: boolean;
  /** compact = aperçu latéral densifié (points + cellules basses). */
  density?: "comfortable" | "compact";
}) {
  const compact = density === "compact";
  const reposSet = useMemo(() => {
    const fromList = reposDayIndexes ?? [];
    if (fromList.length > 0) return new Set(fromList);
    if (reposDayIndex != null) return new Set([reposDayIndex]);
    return new Set<number>();
  }, [reposDayIndex, reposDayIndexes]);

  const plannedByDate = useMemo(() => {
    const map = new Map<string, PlannedShift[]>();
    for (const s of planned) {
      const list = map.get(s.date) ?? [];
      list.push(s);
      map.set(s.date, list);
    }
    return map;
  }, [planned]);

  const existingByDate = useMemo(() => {
    const map = new Map<string, Vacation[]>();
    for (const v of existing) {
      if (v.statut === "annulee") continue;
      const from = v.date_debut.slice(0, 10);
      const overnight = v.heure_fin.slice(0, 5) < v.heure_debut.slice(0, 5);
      if (overnight || v.heure_debut?.slice(0, 5) === v.heure_fin?.slice(0, 5)) {
        // Nuit / cycle 24h : rattaché au jour de prise de poste.
        const list = map.get(from) ?? [];
        list.push(v);
        map.set(from, list);
        continue;
      }
      const to = (v.date_fin ?? v.date_debut).slice(0, 10);
      for (const key of eachDateInclusive(from, to)) {
        const list = map.get(key) ?? [];
        list.push(v);
        map.set(key, list);
      }
    }
    return map;
  }, [existing]);

  const absenceByDate = useMemo(() => {
    const map = new Map<string, Absence[]>();
    for (const a of absences) {
      if (a.statut !== "approuvee") continue;
      const from = a.date_debut.slice(0, 10);
      const to = a.date_fin.slice(0, 10);
      for (const key of eachDateInclusive(from, to)) {
        const list = map.get(key) ?? [];
        list.push(a);
        map.set(key, list);
      }
    }
    return map;
  }, [absences]);

  const controleByDate = useMemo(() => {
    const map = new Map<string, Controle[]>();
    for (const c of controles) {
      if (c.resultat !== "present" && c.resultat !== "absent") continue;
      const key = c.effectue_at.slice(0, 10);
      const list = map.get(key) ?? [];
      list.push(c);
      map.set(key, list);
    }
    return map;
  }, [controles]);

  const cells = useMemo(() => {
    const first = startOfMonth(month);
    const mondayOffset = (first.getDay() + 6) % 7;
    const start = new Date(first);
    start.setDate(first.getDate() - mondayOffset);
    return Array.from({ length: 42 }, (_, i) => {
      const d = new Date(start);
      d.setDate(start.getDate() + i);
      return d;
    });
  }, [month]);

  const shiftMonth = (delta: number) => {
    const d = startOfMonth(month);
    d.setMonth(d.getMonth() + delta);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    onMonthChange(`${y}-${m}`);
  };

  const inMonth = (d: Date) => {
    const [y, m] = month.split("-").map(Number);
    return d.getFullYear() === y && d.getMonth() === m - 1;
  };

  return (
    <div className="space-y-3">
      {showLegend ? (
        <PlanningCalendarLegend mode={mode} compact={compact} />
      ) : null}

      <div
        className={cn(
          "overflow-hidden border border-border bg-white",
          compact ? "rounded-lg" : "rounded-xl",
        )}
      >
        <div
          className={cn(
            "flex items-center justify-between",
            compact ? "px-1.5 py-1.5" : "px-2 py-2",
          )}
        >
          <button
            type="button"
            className="inline-flex size-8 items-center justify-center rounded-md text-ink-muted transition hover:bg-paper-muted hover:text-ink"
            onClick={() => shiftMonth(-1)}
            aria-label="Mois précédent"
          >
            <ChevronLeft className="size-4" />
          </button>
          <p
            className={cn(
              "font-semibold capitalize text-ink",
              compact ? "text-xs" : "text-sm",
            )}
          >
            {monthLabel(month)}
          </p>
          <button
            type="button"
            className="inline-flex size-8 items-center justify-center rounded-md text-ink-muted transition hover:bg-paper-muted hover:text-ink"
            onClick={() => shiftMonth(1)}
            aria-label="Mois suivant"
          >
            <ChevronRight className="size-4" />
          </button>
        </div>

        <div
          className={cn(
            "grid grid-cols-7 border-t border-border bg-emerald-50/40 text-center font-bold uppercase tracking-wide text-emerald-800/70",
            compact ? "text-[9px]" : "text-[10px]",
          )}
        >
          {WEEKDAYS_SHORT.map((d) => (
            <div key={d} className={compact ? "py-1" : "py-1.5"}>
              {compact ? d.charAt(0) : d}
            </div>
          ))}
        </div>

        <div className="grid grid-cols-7 border-t border-border">
          {cells.map((d) => {
            const iso = toIso(d);
            const plannedDay = plannedByDate.get(iso) ?? [];
            const existingDay = existingByDate.get(iso) ?? [];
            const absenceDay = absenceByDate.get(iso) ?? [];
            const controleDay = controleByDate.get(iso) ?? [];
            const isRepos = reposSet.has(d.getDay());
            const muted = !inMonth(d);
            const hasPlanned = plannedDay.length > 0;
            const hasExisting = existingDay.length > 0;
            const hasTrou = existingDay.some((v) => v.statut === "a_recouvrir");
            const hasPlanifie = existingDay.some(
              (v) => v.statut === "planifiee" || v.statut === "en_cours",
            );
            const hasAbsent =
              absenceDay.length > 0 ||
              controleDay.some((c) => c.resultat === "absent");
            const hasPresent = controleDay.some((c) => c.resultat === "present");
            const conflict = hasPlanned && hasExisting && !isRepos;
            const plannedJour = plannedDay.some((s) => s.quart !== "nuit");
            const plannedNuit = plannedDay.some((s) => s.quart === "nuit");

            return (
              <div
                key={iso}
                title={
                  hasPlanned
                    ? plannedDay
                        .map(
                          (s) =>
                            `${s.quart === "nuit" ? "Nuit" : "Jour"} ${s.heure_debut}–${s.heure_fin}`,
                        )
                        .join(" · ")
                    : isRepos
                      ? "Repos"
                      : undefined
                }
                className={cn(
                  "relative border-b border-r border-border/70 last:border-r-0",
                  compact ? "min-h-[44px] p-0.5" : "min-h-[72px] p-1",
                  muted && "bg-slate-50 text-ink-faint",
                  isRepos && !muted && (compact ? "bg-amber-50" : "bg-amber-100"),
                  hasPlanned &&
                    !muted &&
                    !isRepos &&
                    !conflict &&
                    "bg-lime-50",
                  conflict && !muted && "bg-rose-50",
                  hasTrou && !muted && !isRepos && "bg-rose-50/70",
                  hasPlanifie &&
                    !hasPlanned &&
                    !muted &&
                    !isRepos &&
                    !hasTrou &&
                    "bg-sky-50/80",
                  hasAbsent &&
                    !hasTrou &&
                    !hasPlanifie &&
                    !muted &&
                    !isRepos &&
                    "bg-orange-50/80",
                )}
              >
                <div
                  className={cn(
                    "flex items-center justify-between gap-0.5 font-semibold tabular-nums",
                    compact ? "text-[10px]" : "mb-0.5 text-[11px]",
                    hasPlanned && !muted && "text-emerald-800",
                    conflict && !muted && "text-rose-700",
                  )}
                >
                  <span
                    className={cn(
                      compact &&
                        hasPlanned &&
                        !muted &&
                        !conflict &&
                        "inline-flex size-5 items-center justify-center rounded-full bg-emerald-500 text-[10px] text-white",
                      compact &&
                        isRepos &&
                        !muted &&
                        !hasPlanned &&
                        "text-amber-700",
                    )}
                  >
                    {d.getDate()}
                  </span>
                  {!compact ? (
                    <span className="flex items-center gap-0.5">
                      {isRepos && !muted ? (
                        <span className="rounded bg-amber-400 px-1 text-[8px] font-bold uppercase text-amber-950">
                          R
                        </span>
                      ) : null}
                      {hasPresent && !muted ? (
                        <span
                          className="rounded bg-teal px-1 text-[8px] font-bold uppercase text-white"
                          title="Contrôle : présent"
                        >
                          P
                        </span>
                      ) : null}
                      {hasAbsent && !muted ? (
                        <span
                          className="rounded bg-orange-500 px-1 text-[8px] font-bold uppercase text-white"
                          title="Absent"
                        >
                          A
                        </span>
                      ) : null}
                      {conflict && !muted ? (
                        <span
                          className="cursor-help rounded bg-rose-500 px-1 text-[8px] font-bold uppercase text-white"
                          title={`Conflit le ${d.toLocaleDateString("fr-FR")}`}
                        >
                          !
                        </span>
                      ) : null}
                    </span>
                  ) : (
                    <span className="flex items-center gap-0.5">
                      {conflict && !muted ? (
                        <span className="size-1.5 rounded-full bg-rose-500" />
                      ) : null}
                      {isRepos && !muted && !hasPlanned ? (
                        <span className="size-1.5 rounded-full bg-amber-400" />
                      ) : null}
                    </span>
                  )}
                </div>

                {compact ? (
                  <div className="mt-0.5 flex flex-wrap items-center justify-center gap-0.5">
                    {plannedJour ? (
                      <span className="h-1 w-2.5 rounded-full bg-emerald-500" />
                    ) : null}
                    {plannedNuit ? (
                      <span className="h-1 w-2.5 rounded-full bg-violet-600" />
                    ) : null}
                    {!hasPlanned && hasPlanifie ? (
                      <span className="h-1 w-2.5 rounded-full bg-sky-500" />
                    ) : null}
                    {!hasPlanned && hasTrou ? (
                      <span className="h-1 w-2.5 rounded-full bg-rose-500" />
                    ) : null}
                  </div>
                ) : (
                  <>
                    {plannedDay.map((s, i) => (
                      <div
                        key={`${s.date}-${s.quart}-${i}`}
                        className={cn(
                          "mb-0.5 truncate rounded-md px-1 py-0.5 text-[9px] font-bold shadow-sm",
                          s.quart === "nuit"
                            ? "bg-violet-600 text-white"
                            : "bg-emerald-500 text-white",
                        )}
                        title={`${s.heure_debut}–${s.heure_fin}`}
                      >
                        {s.quart === "nuit" ? "N" : "J"} {s.heure_debut}
                      </div>
                    ))}
                    {existingDay.slice(0, 2).map((v) => {
                      const trou = v.statut === "a_recouvrir";
                      return (
                        <div
                          key={v.id}
                          className={cn(
                            "mb-0.5 truncate rounded-md px-1 py-0.5 text-[9px] font-bold text-white shadow-sm",
                            trou ? "bg-rose-500" : "bg-sky-500",
                          )}
                          title={
                            trou
                              ? `À recouvrir · ${v.site?.nom ?? "Site"} ${v.heure_debut?.slice(0, 5)}–${v.heure_fin?.slice(0, 5)}`
                              : `${v.site?.nom ?? "Site"} ${v.heure_debut?.slice(0, 5)}–${v.heure_fin?.slice(0, 5)}`
                          }
                        >
                          {trou ? "Trou" : (v.site?.nom ?? "Existant")}
                        </div>
                      );
                    })}
                    {existingDay.length > 2 ? (
                      <span className="text-[9px] font-semibold text-sky-700">
                        +{existingDay.length - 2}
                      </span>
                    ) : null}
                    {mode === "readonly" &&
                    absenceDay.length > 0 &&
                    !hasTrou ? (
                      <div
                        className={cn(
                          "mb-0.5 truncate rounded-md px-1 py-0.5 text-[9px] font-bold text-white shadow-sm",
                          absenceDay[0]?.source === "controle"
                            ? "bg-orange-500"
                            : "bg-indigo-500",
                        )}
                        title={
                          absenceDay[0]?.source === "controle"
                            ? `Contrôle · ${absenceDay[0]?.motif || "Absent"}`
                            : `RH · ${absenceDay[0]?.motif || "Absence"}`
                        }
                      >
                        {absenceDay[0]?.source === "controle"
                          ? "Ctrl absent"
                          : "Absence RH"}
                      </div>
                    ) : null}
                    {mode === "readonly" && hasPresent ? (
                      <div
                        className="mb-0.5 truncate rounded-md bg-teal px-1 py-0.5 text-[9px] font-bold text-white shadow-sm"
                        title="Contrôle terrain : présent"
                      >
                        Présent
                      </div>
                    ) : null}
                  </>
                )}
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
