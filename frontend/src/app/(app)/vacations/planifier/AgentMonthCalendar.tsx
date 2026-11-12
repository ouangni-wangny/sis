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

function hm(value?: string | null) {
  if (!value) return "";
  return value.slice(0, 5);
}

function todayIso() {
  return toIso(new Date());
}

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

  if (mode === "readonly") {
    return (
      <div
        className={cn(
          "flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl border border-border/80 bg-gradient-to-r from-paper via-white to-paper px-3 py-2.5",
          className,
        )}
        role="list"
        aria-label="Légende du calendrier"
      >
        <span className="text-[10px] font-bold uppercase tracking-[0.12em] text-ink-faint">
          Légende
        </span>
        {items.map((item) => (
          <div
            key={item.key}
            role="listitem"
            className="inline-flex items-center gap-1.5 text-[11px] font-medium text-ink"
            title={item.hint}
          >
            <span
              className={cn("size-2.5 shrink-0 rounded-full", item.swatch)}
              aria-hidden
            />
            {item.label}
          </div>
        ))}
      </div>
    );
  }

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

function EventChip({
  tone,
  title,
  subtitle,
  titleAttr,
}: {
  tone: "planifie" | "trou" | "present" | "absent" | "rh" | "jour" | "nuit";
  title: string;
  subtitle?: string;
  titleAttr?: string;
}) {
  return (
    <div
      title={titleAttr ?? (subtitle ? `${title} · ${subtitle}` : title)}
      className={cn(
        "mb-1 overflow-hidden rounded-md border px-1.5 py-1 shadow-[0_1px_0_rgba(15,23,42,0.04)]",
        tone === "planifie" &&
          "border-sky-200/80 bg-gradient-to-br from-sky-50 to-sky-100/80 text-sky-900",
        tone === "trou" &&
          "border-rose-200/80 bg-gradient-to-br from-rose-50 to-rose-100/90 text-rose-900",
        tone === "present" &&
          "border-teal/25 bg-gradient-to-br from-[#E8EEE4] to-[#D7E0D0] text-teal-dark",
        tone === "absent" &&
          "border-orange-200/80 bg-gradient-to-br from-orange-50 to-orange-100/90 text-orange-900",
        tone === "rh" &&
          "border-indigo-200/80 bg-gradient-to-br from-indigo-50 to-indigo-100/90 text-indigo-900",
        tone === "jour" &&
          "border-emerald-200/80 bg-gradient-to-br from-emerald-50 to-emerald-100/90 text-emerald-900",
        tone === "nuit" &&
          "border-violet-200/80 bg-gradient-to-br from-violet-50 to-violet-100/90 text-violet-900",
      )}
    >
      <div className="flex items-center gap-1">
        <span
          className={cn(
            "size-1.5 shrink-0 rounded-full",
            tone === "planifie" && "bg-sky-500",
            tone === "trou" && "bg-rose-500",
            tone === "present" && "bg-teal",
            tone === "absent" && "bg-orange-500",
            tone === "rh" && "bg-indigo-500",
            tone === "jour" && "bg-emerald-500",
            tone === "nuit" && "bg-violet-600",
          )}
          aria-hidden
        />
        <p className="truncate text-[10px] font-bold leading-tight">{title}</p>
      </div>
      {subtitle ? (
        <p className="mt-0.5 truncate pl-2.5 text-[9px] font-semibold tabular-nums opacity-75">
          {subtitle}
        </p>
      ) : null}
    </div>
  );
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
  const today = todayIso();
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
      // Absence sans date de fin = un seul jour.
      const to = (a.date_fin ?? a.date_debut).slice(0, 10);
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

  const goToday = () => {
    const now = new Date();
    onMonthChange(
      `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`,
    );
  };

  const inMonth = (d: Date) => {
    const [y, m] = month.split("-").map(Number);
    return d.getFullYear() === y && d.getMonth() === m - 1;
  };

  const readonly = mode === "readonly";

  return (
    <div className="space-y-3">
      {showLegend ? (
        <PlanningCalendarLegend mode={mode} compact={compact} />
      ) : null}

      <div
        className={cn(
          "overflow-hidden border border-border bg-white shadow-[0_8px_30px_rgba(15,23,42,0.04)]",
          compact ? "rounded-lg" : "rounded-2xl",
        )}
      >
        <div
          className={cn(
            "flex items-center justify-between gap-2 border-b border-border/70 bg-gradient-to-b from-paper to-white",
            compact ? "px-1.5 py-1.5" : "px-3 py-2.5",
          )}
        >
          <button
            type="button"
            className="inline-flex size-8 items-center justify-center rounded-lg text-ink-muted transition hover:bg-paper-muted hover:text-ink"
            onClick={() => shiftMonth(-1)}
            aria-label="Mois précédent"
          >
            <ChevronLeft className="size-4" />
          </button>
          <div className="flex min-w-0 flex-1 flex-col items-center gap-1 sm:flex-row sm:justify-center sm:gap-3">
            <p
              className={cn(
                "font-semibold capitalize text-ink",
                compact ? "text-xs" : "text-sm",
              )}
            >
              {monthLabel(month)}
            </p>
            {!compact ? (
              <button
                type="button"
                onClick={goToday}
                className="rounded-full border border-border bg-white px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-ink-muted transition hover:border-teal/40 hover:text-teal"
              >
                Aujourd’hui
              </button>
            ) : null}
          </div>
          <button
            type="button"
            className="inline-flex size-8 items-center justify-center rounded-lg text-ink-muted transition hover:bg-paper-muted hover:text-ink"
            onClick={() => shiftMonth(1)}
            aria-label="Mois suivant"
          >
            <ChevronRight className="size-4" />
          </button>
        </div>

        <div
          className={cn(
            "grid grid-cols-7 border-b border-border bg-teal/[0.04] text-center font-bold uppercase tracking-[0.08em] text-teal-dark/70",
            compact ? "text-[9px]" : "text-[10px]",
          )}
        >
          {WEEKDAYS_SHORT.map((d, idx) => (
            <div
              key={d}
              className={cn(
                compact ? "py-1" : "py-2",
                idx >= 5 && "text-ink-faint",
              )}
            >
              {compact ? d.charAt(0) : d}
            </div>
          ))}
        </div>

        <div className="grid grid-cols-7">
          {cells.map((d) => {
            const iso = toIso(d);
            const plannedDay = plannedByDate.get(iso) ?? [];
            const existingDay = existingByDate.get(iso) ?? [];
            const absenceDay = absenceByDate.get(iso) ?? [];
            const controleDay = controleByDate.get(iso) ?? [];
            const isRepos = reposSet.has(d.getDay());
            const muted = !inMonth(d);
            const isToday = iso === today;
            const weekend = d.getDay() === 0 || d.getDay() === 6;
            const hasPlanned = plannedDay.length > 0;
            const hasExisting = existingDay.length > 0;
            const hasTrou = existingDay.some((v) => v.statut === "a_recouvrir");
            const hasPlanifie = existingDay.some(
              (v) => v.statut === "planifiee" || v.statut === "en_cours",
            );
            const hasAbsent =
              absenceDay.length > 0 ||
              controleDay.some((c) => c.resultat === "absent");
            const hasPresent = controleDay.some(
              (c) => c.resultat === "present",
            );
            const conflict = hasPlanned && hasExisting && !isRepos;
            const plannedJour = plannedDay.some((s) => s.quart !== "nuit");
            const plannedNuit = plannedDay.some((s) => s.quart === "nuit");
            const hasContent =
              hasPlanned ||
              hasExisting ||
              (readonly && (hasAbsent || hasPresent || absenceDay.length > 0));

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
                  "relative border-b border-r border-border/60 transition-colors last:border-r-0",
                  compact ? "min-h-[44px] p-0.5" : "min-h-[92px] p-1.5",
                  muted && "bg-slate-50/80 text-ink-faint",
                  !muted && weekend && !isRepos && !hasContent && "bg-paper/50",
                  isRepos &&
                    !muted &&
                    (compact ? "bg-amber-50" : "bg-amber-50/90"),
                  hasPlanned &&
                    !muted &&
                    !isRepos &&
                    !conflict &&
                    "bg-lime-50/90",
                  conflict && !muted && "bg-rose-50",
                  hasTrou && !muted && !isRepos && "bg-rose-50/60",
                  hasPlanifie &&
                    !hasPlanned &&
                    !muted &&
                    !isRepos &&
                    !hasTrou &&
                    "bg-sky-50/50",
                  hasAbsent &&
                    !hasTrou &&
                    !hasPlanifie &&
                    !muted &&
                    !isRepos &&
                    "bg-orange-50/70",
                  isToday && !muted && "ring-1 ring-inset ring-teal/35",
                )}
              >
                <div
                  className={cn(
                    "flex items-center justify-between gap-0.5 font-semibold tabular-nums",
                    compact ? "text-[10px]" : "mb-1 text-[11px]",
                    hasPlanned && !muted && "text-emerald-800",
                    conflict && !muted && "text-rose-700",
                  )}
                >
                  <span
                    className={cn(
                      "inline-flex items-center justify-center",
                      !compact &&
                        isToday &&
                        !muted &&
                        "size-6 rounded-full bg-teal text-[11px] font-bold text-white shadow-sm",
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
                      !isToday && muted && "opacity-50",
                    )}
                  >
                    {d.getDate()}
                  </span>
                  {!compact ? (
                    <span className="flex items-center gap-0.5">
                      {isRepos && !muted ? (
                        <span className="rounded-full bg-amber-400/90 px-1.5 py-0.5 text-[8px] font-bold uppercase tracking-wide text-amber-950">
                          Repos
                        </span>
                      ) : null}
                      {conflict && !muted ? (
                        <span
                          className="cursor-help rounded-full bg-rose-500 px-1.5 py-0.5 text-[8px] font-bold uppercase text-white"
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
                      <EventChip
                        key={`${s.date}-${s.quart}-${i}`}
                        tone={s.quart === "nuit" ? "nuit" : "jour"}
                        title={s.quart === "nuit" ? "Nuit" : "Jour"}
                        subtitle={`${s.heure_debut}–${s.heure_fin}`}
                      />
                    ))}
                    {existingDay.slice(0, 2).map((v) => {
                      const trou = v.statut === "a_recouvrir";
                      const debut = hm(v.heure_debut);
                      const fin = hm(v.heure_fin);
                      return (
                        <EventChip
                          key={v.id}
                          tone={trou ? "trou" : "planifie"}
                          title={
                            trou
                              ? "À recouvrir"
                              : (v.site?.nom ?? v.poste?.nom ?? "Vacation")
                          }
                          subtitle={
                            debut && fin
                              ? `${debut}–${fin}`
                              : v.poste?.nom
                          }
                          titleAttr={
                            trou
                              ? `À recouvrir · ${v.site?.nom ?? "Site"} ${debut}–${fin}`
                              : `${v.site?.nom ?? "Site"} · ${v.poste?.nom ?? "Poste"} · ${debut}–${fin}`
                          }
                        />
                      );
                    })}
                    {existingDay.length > 2 ? (
                      <span className="mb-1 block text-[9px] font-semibold text-sky-700">
                        +{existingDay.length - 2} autre
                        {existingDay.length - 2 > 1 ? "s" : ""}
                      </span>
                    ) : null}
                    {readonly && absenceDay.length > 0 && !hasTrou ? (
                      <EventChip
                        tone={
                          absenceDay[0]?.source === "controle"
                            ? "absent"
                            : "rh"
                        }
                        title={
                          absenceDay[0]?.source === "controle"
                            ? "Absent (contrôle)"
                            : "Absence RH"
                        }
                        subtitle={absenceDay[0]?.motif || undefined}
                        titleAttr={
                          absenceDay[0]?.source === "controle"
                            ? `Contrôle · ${absenceDay[0]?.motif || "Absent"}`
                            : `RH · ${absenceDay[0]?.motif || "Absence"}`
                        }
                      />
                    ) : null}
                    {readonly && hasPresent ? (
                      <EventChip
                        tone="present"
                        title="Présent"
                        subtitle="Contrôle terrain"
                        titleAttr="Contrôle terrain : présent"
                      />
                    ) : null}
                    {readonly &&
                    hasAbsent &&
                    absenceDay.length === 0 &&
                    !hasTrou ? (
                      <EventChip
                        tone="absent"
                        title="Absent"
                        subtitle="Contrôle terrain"
                        titleAttr="Absence constatée au contrôle"
                      />
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
