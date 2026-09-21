"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import { format, parseISO } from "date-fns";
import { fr } from "date-fns/locale";
import { ChevronLeft, ChevronRight, Plus } from "lucide-react";
import { indiceEnService } from "@/domain/controleur-releve-48h";
import type { Zone } from "@/domain/types/entities";
import { Badge } from "@/presentation/components/ui/Badge";
import { Button } from "@/presentation/components/ui/Button";
import { Select } from "@/presentation/components/ui/Select";
import { cn } from "@/shared/lib/cn";

const WEEKDAY_LABELS = ["Lun", "Mar", "Mer", "Jeu", "Ven", "Sam", "Dim"];

type Ctrl = NonNullable<Zone["controleurs"]>[number];

function toIso(d: Date) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
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

function sortedControleurs(zone: Zone): Ctrl[] {
  return [...(zone.controleurs ?? [])].sort(
    (a, b) => (a.indice_releve ?? 99) - (b.indice_releve ?? 99),
  );
}

function onDutyForDay(zone: Zone, dayIso: string): Ctrl | null {
  const ctrls = sortedControleurs(zone);
  if (ctrls.length === 0) return null;
  if (ctrls.length === 1) return ctrls[0]!;

  const depuis = ctrls[0]?.releve_depuis;
  const jusque = ctrls[0]?.releve_jusque;
  if (!depuis) return null;
  if (dayIso < depuis) return null;
  if (jusque && dayIso > jusque) return null;

  const idx = indiceEnService(depuis, dayIso);
  if (idx < 0) return null;
  return ctrls.find((c) => (c.indice_releve ?? ctrls.indexOf(c)) === idx) ?? ctrls[idx] ?? null;
}

export function ZoneWeekGrid({
  zones,
  canPlanifier,
}: {
  zones: Zone[];
  canPlanifier: boolean;
}) {
  const today = toIso(new Date());
  const [weekStart, setWeekStart] = useState(() => toIso(mondayOf(today)));
  const [filterZoneId, setFilterZoneId] = useState("");

  const dates = useMemo(() => weekDates(weekStart), [weekStart]);

  const readyZones = useMemo(
    () => zones.filter((z) => (z.controleurs?.length ?? 0) >= 2),
    [zones],
  );

  const rows = useMemo(() => {
    const list = filterZoneId
      ? readyZones.filter((z) => z.id === filterZoneId)
      : readyZones;
    return list;
  }, [readyZones, filterZoneId]);

  const weekLabel = useMemo(() => {
    const a = format(parseISO(dates[0]!), "d MMM", { locale: fr });
    const b = format(parseISO(dates[6]!), "d MMM yyyy", { locale: fr });
    return `${a} – ${b}`;
  }, [dates]);

  const shiftWeek = (delta: number) => {
    const d = new Date(`${weekStart}T00:00:00`);
    d.setDate(d.getDate() + delta * 7);
    setWeekStart(toIso(d));
  };

  const zoneOptions = [
    { value: "", label: "Toutes les zones" },
    ...readyZones.map((z) => ({ value: z.id, label: z.nom })),
  ];

  if (readyZones.length === 0) {
    return (
      <div className="rounded-lg border border-dashed border-border px-4 py-10 text-center">
        <p className="text-sm text-ink-muted">
          Aucun binôme prêt. Assignez 2 contrôleurs dans{" "}
          <Link href="/zones" className="text-teal hover:underline">
            Zones
          </Link>
          , puis planifiez la relève.
        </p>
        {canPlanifier ? (
          <Link
            href="/planning-controleurs/planifier"
            className="mt-4 inline-flex h-9 items-center gap-2 rounded-md bg-teal px-3 text-sm font-medium text-white hover:bg-teal-dark"
          >
            <Plus className="size-4" />
            Planifier la relève
          </Link>
        ) : null}
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div className="w-56">
          <Select
            label="Zone"
            optionalMark
            options={zoneOptions}
            value={filterZoneId}
            onChange={(e) => setFilterZoneId(e.target.value)}
          />
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            onClick={() => shiftWeek(-1)}
            aria-label="Semaine précédente"
          >
            <ChevronLeft className="size-4" />
          </Button>
          <Button
            type="button"
            variant="secondary"
            size="sm"
            onClick={() => setWeekStart(toIso(mondayOf(today)))}
          >
            Aujourd’hui
          </Button>
          <Button
            type="button"
            variant="secondary"
            size="sm"
            onClick={() => shiftWeek(1)}
            aria-label="Semaine suivante"
          >
            <ChevronRight className="size-4" />
          </Button>
          <span className="min-w-[10rem] text-sm font-medium capitalize text-ink">
            {weekLabel}
          </span>
        </div>
      </div>

      <div className="flex flex-wrap gap-3 text-xs text-ink-muted">
        <span className="inline-flex items-center gap-1.5">
          <span className="size-2.5 rounded-sm bg-teal" />
          En service
        </span>
        <span className="inline-flex items-center gap-1.5">
          <span className="size-2.5 rounded-sm border border-dashed border-amber-400 bg-amber-50" />
          Repos
        </span>
        <span className="inline-flex items-center gap-1.5">
          <span className="size-2.5 rounded-sm bg-paper-muted" />
          Hors période / non planifié
        </span>
      </div>

      <div className="overflow-x-auto rounded-lg border border-border">
        <table className="w-full min-w-[640px] border-collapse text-sm">
          <thead>
            <tr className="bg-paper-muted/60 text-left">
              <th className="sticky left-0 z-10 bg-paper-muted/95 px-3 py-2.5 font-semibold text-ink">
                Zone
              </th>
              {dates.map((d, i) => {
                const isToday = d === today;
                return (
                  <th
                    key={d}
                    className={cn(
                      "px-2 py-2.5 text-center font-semibold",
                      isToday ? "bg-teal/10 text-teal" : "text-ink-muted",
                    )}
                  >
                    <div className="text-[10px] uppercase tracking-wide">
                      {WEEKDAY_LABELS[i]}
                    </div>
                    <div className="tabular-nums">
                      {format(parseISO(d), "d MMM", { locale: fr })}
                    </div>
                  </th>
                );
              })}
            </tr>
          </thead>
          <tbody>
            {rows.map((zone) => {
              const ctrls = sortedControleurs(zone);
              const hasPeriod = Boolean(ctrls[0]?.releve_depuis);
              return (
                <tr key={zone.id} className="border-t border-border">
                  <td className="sticky left-0 z-10 max-w-[12rem] bg-white px-3 py-2.5 align-top">
                    <p className="font-medium text-ink">{zone.nom}</p>
                    <p className="mt-0.5 text-xs text-ink-muted">
                      {ctrls.map((c) => `${c.prenom} ${c.nom}`).join(" · ")}
                    </p>
                    {!hasPeriod ? (
                      <Badge tone="warning" className="mt-1.5">
                        À planifier
                      </Badge>
                    ) : (
                      <p className="mt-1 text-[10px] text-ink-faint">
                        {ctrls[0]?.releve_depuis}
                        {ctrls[0]?.releve_jusque
                          ? ` → ${ctrls[0].releve_jusque}`
                          : ""}
                      </p>
                    )}
                    {canPlanifier ? (
                      <Link
                        href={`/planning-controleurs/planifier?zone_id=${zone.id}`}
                        className="mt-1.5 inline-block text-xs font-medium text-teal hover:underline"
                      >
                        Modifier
                      </Link>
                    ) : null}
                  </td>
                  {dates.map((d) => {
                    const on = onDutyForDay(zone, d);
                    const isToday = d === today;
                    if (!hasPeriod) {
                      return (
                        <td
                          key={d}
                          className={cn(
                            "px-1.5 py-2 align-top",
                            isToday && "bg-teal/5",
                          )}
                        >
                          <div className="min-h-[3.25rem] rounded-md bg-paper-muted/50 px-1.5 py-1.5 text-center text-[10px] text-ink-faint">
                            —
                          </div>
                        </td>
                      );
                    }
                    const resting = ctrls.filter((c) => c.id !== on?.id);
                    return (
                      <td
                        key={d}
                        className={cn(
                          "px-1.5 py-2 align-top",
                          isToday && "bg-teal/5",
                        )}
                      >
                        <div className="space-y-1">
                          {on ? (
                            <div className="rounded-md bg-teal px-1.5 py-1.5 text-white shadow-sm">
                              <p className="truncate text-[11px] font-medium leading-tight">
                                {on.prenom} {on.nom}
                              </p>
                              <p className="text-[9px] text-white/75">
                                En service
                              </p>
                            </div>
                          ) : (
                            <div className="rounded-md bg-paper-muted/60 px-1.5 py-1.5 text-center text-[10px] text-ink-faint">
                              Hors période
                            </div>
                          )}
                          {on
                            ? resting.map((c) => (
                                <div
                                  key={c.id}
                                  className="rounded-md border border-dashed border-amber-300/80 bg-amber-50 px-1.5 py-1 text-[10px] text-amber-900"
                                >
                                  <span className="font-semibold">
                                    {c.prenom} {c.nom}
                                  </span>
                                  <span className="ml-1 opacity-80">Repos</span>
                                </div>
                              ))
                            : null}
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
    </div>
  );
}
