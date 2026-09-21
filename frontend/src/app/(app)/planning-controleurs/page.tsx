"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import { CalendarDays, MapPinned, Plus, Repeat, UserRound } from "lucide-react";
import { useZones } from "@/application/hooks/useZones";
import {
  estControleurEnService,
  indiceEnService,
} from "@/domain/controleur-releve-48h";
import type { Zone } from "@/domain/types/entities";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { Badge } from "@/presentation/components/ui/Badge";
import { Card, CardBody, CardHeader } from "@/presentation/components/ui/Card";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { StatCard } from "@/presentation/components/ui/StatCard";
import { DataTable } from "@/presentation/components/tables/DataTable";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { can } from "@/shared/lib/can";
import { cn } from "@/shared/lib/cn";
import { type ColumnDef } from "@tanstack/react-table";
import { ZoneWeekGrid } from "./ZoneWeekGrid";

function todayIso() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}

function sortedControleurs(zone: Zone) {
  return [...(zone.controleurs ?? [])].sort(
    (a, b) => (a.indice_releve ?? 99) - (b.indice_releve ?? 99),
  );
}

function PlanifierLink({
  size = "md",
  zoneId,
}: {
  size?: "sm" | "md";
  zoneId?: string;
}) {
  const href = zoneId
    ? `/planning-controleurs/planifier?zone_id=${zoneId}`
    : "/planning-controleurs/planifier";
  return (
    <Link
      href={href}
      className={`inline-flex items-center justify-center gap-2 rounded-md bg-teal font-medium text-white shadow-sm transition-colors hover:bg-teal-dark ${
        size === "sm" ? "h-8 px-3 text-xs" : "h-10 px-4 text-sm"
      }`}
    >
      <Plus className="size-4" />
      Planifier la relève
    </Link>
  );
}

export default function PlanningControleursPage() {
  const { data, isLoading } = useZones({ all: true });
  const { user } = useAuth();
  const canManage = can(user, ["zones.update", "zones.manage"]);
  const [view, setView] = useState<"grille" | "liste">("grille");

  const zones = data?.data ?? [];
  const today = todayIso();

  const stats = useMemo(() => {
    const ready = zones.filter((z) => (z.controleurs?.length ?? 0) >= 2);
    const incomplete = zones.filter((z) => (z.controleurs?.length ?? 0) < 2);
    const planned = ready.filter((z) =>
      sortedControleurs(z).some((c) => c.releve_depuis),
    );
    const aPlanifier = ready.length - planned.length;

    let enServiceToday = 0;
    for (const z of planned) {
      const ctrls = sortedControleurs(z);
      const depuis = ctrls[0]?.releve_depuis;
      const jusque = ctrls[0]?.releve_jusque;
      if (!depuis) continue;
      if (today < depuis || (jusque && today > jusque)) continue;
      const idx = indiceEnService(depuis, today);
      if (idx >= 0) enServiceToday += 1;
    }

    return {
      ready: ready.length,
      incomplete: incomplete.length,
      planned: planned.length,
      aPlanifier,
      enServiceToday,
      readyZones: ready,
    };
  }, [zones, today]);

  const columns = useMemo<ColumnDef<Zone>[]>(
    () => [
      {
        accessorKey: "nom",
        header: "Zone",
        cell: ({ row }) => (
          <span className="font-medium">{row.original.nom}</span>
        ),
      },
      {
        id: "binome",
        header: "Binôme",
        cell: ({ row }) => {
          const ctrls = sortedControleurs(row.original);
          if (ctrls.length === 0) {
            return <span className="text-ink-faint">Aucun</span>;
          }
          return (
            <div className="space-y-0.5">
              {ctrls.map((c) => {
                const on =
                  c.releve_depuis != null &&
                  estControleurEnService(
                    c.indice_releve,
                    c.releve_depuis,
                    today,
                  ) &&
                  (!c.releve_jusque || today <= c.releve_jusque) &&
                  today >= (c.releve_depuis ?? "");
                return (
                  <div key={c.id} className="flex flex-wrap items-center gap-1.5">
                    <span className="text-sm">
                      {c.prenom} {c.nom}
                    </span>
                    {c.releve_depuis ? (
                      <Badge tone={on ? "success" : "neutral"}>
                        {on ? "En service" : "Repos"}
                      </Badge>
                    ) : null}
                  </div>
                );
              })}
            </div>
          );
        },
      },
      {
        id: "periode",
        header: "Période",
        cell: ({ row }) => {
          const c = sortedControleurs(row.original)[0];
          if (!c?.releve_depuis) {
            return <Badge tone="warning">À planifier</Badge>;
          }
          return (
            <span className="font-mono text-xs tabular-nums text-ink-muted">
              {c.releve_depuis}
              {c.releve_jusque ? ` → ${c.releve_jusque}` : ""}
            </span>
          );
        },
      },
      {
        id: "actions",
        header: "Actions",
        enableSorting: false,
        cell: ({ row }) =>
          canManage && (row.original.controleurs?.length ?? 0) >= 2 ? (
            <Link
              href={`/planning-controleurs/planifier?zone_id=${row.original.id}`}
              className="text-sm font-medium text-teal hover:underline"
            >
              Planifier
            </Link>
          ) : (
            <span className="text-xs text-ink-faint">—</span>
          ),
      },
    ],
    [canManage, today],
  );

  return (
    <PermissionGate
      permission={["zones.view", "perimetres.view", "zones.manage"]}
      title="Planning contrôleurs"
    >
      <div className="space-y-6">
        <PageHeader
          title="Planning des contrôleurs"
          description="Relève 2 jours / 2 jours par zone — qui est en service cette semaine."
          actions={
            <div className="flex flex-col items-stretch gap-2 sm:items-end">
              <div className="flex flex-wrap items-center justify-end gap-2">
                {canManage ? <PlanifierLink /> : null}
              </div>
              <div className="inline-flex self-end rounded-md border border-border bg-white p-0.5">
                <button
                  type="button"
                  onClick={() => setView("grille")}
                  className={cn(
                    "rounded px-3 py-1.5 text-sm font-medium transition",
                    view === "grille"
                      ? "bg-ink text-white"
                      : "text-ink-muted hover:text-ink",
                  )}
                >
                  Grille zones
                </button>
                <button
                  type="button"
                  onClick={() => setView("liste")}
                  className={cn(
                    "rounded px-3 py-1.5 text-sm font-medium transition",
                    view === "liste"
                      ? "bg-ink text-white"
                      : "text-ink-muted hover:text-ink",
                  )}
                >
                  Liste zones
                </button>
              </div>
            </div>
          }
        />

        <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <StatCard
            label="Binômes prêts"
            value={stats.ready}
            hint="Zones avec 2 contrôleurs"
            icon={<UserRound className="size-4" />}
          />
          <StatCard
            label="À planifier"
            value={stats.aPlanifier}
            hint="Binômes sans période de relève"
            icon={<Repeat className="size-4" />}
            className={cn(stats.aPlanifier > 0 && "ring-1 ring-amber-300/50")}
          />
          <StatCard
            label="En service aujourd’hui"
            value={stats.enServiceToday}
            hint="Zones couvertes ce jour"
            icon={<CalendarDays className="size-4" />}
          />
          <StatCard
            label="Zones incomplètes"
            value={stats.incomplete}
            hint="Moins de 2 contrôleurs"
            icon={<MapPinned className="size-4" />}
          />
        </section>

        <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
          <Card className="min-w-0 overflow-hidden">
            <CardHeader
              title={view === "grille" ? "Grille zones" : "Liste des zones"}
              description={
                view === "grille"
                  ? "Semaine par zone — qui est en service / en repos."
                  : "Binômes, période planifiée et statut du jour."
              }
              action={canManage ? <PlanifierLink size="sm" /> : undefined}
            />
            <CardBody>
              {isLoading ? (
                <p className="py-8 text-center text-sm text-ink-muted">
                  Chargement…
                </p>
              ) : view === "grille" ? (
                <ZoneWeekGrid
                  zones={zones}
                  canPlanifier={canManage}
                />
              ) : (
                <DataTable
                  data={stats.readyZones}
                  columns={columns}
                  isLoading={isLoading}
                  emptyTitle="Aucun binôme"
                  emptyDescription="Assignez 2 contrôleurs par zone, puis planifiez."
                  emptyAction={
                    canManage ? <PlanifierLink size="sm" /> : undefined
                  }
                />
              )}
            </CardBody>
          </Card>

          <aside className="space-y-4 xl:sticky xl:top-4 xl:self-start">
            <Card>
              <CardHeader
                title="À traiter"
                description="Priorités du planning contrôleurs"
              />
              <CardBody className="space-y-3">
                <div className="flex items-center justify-between rounded-md bg-paper px-3 py-2">
                  <span className="text-sm text-ink-muted">
                    Relèves à définir
                  </span>
                  <span className="font-mono text-lg font-semibold tabular-nums text-ink">
                    {stats.aPlanifier}
                  </span>
                </div>
                <div className="flex items-center justify-between rounded-md bg-paper px-3 py-2">
                  <span className="text-sm text-ink-muted">
                    Zones sans binôme
                  </span>
                  <span className="font-mono text-lg font-semibold tabular-nums text-ink">
                    {stats.incomplete}
                  </span>
                </div>
                {canManage ? (
                  <PlanifierLink size="sm" />
                ) : null}
                {stats.incomplete > 0 ? (
                  <Link
                    href="/zones"
                    className="inline-flex h-8 w-full items-center justify-center rounded-md border border-border bg-white px-3 text-xs font-medium text-ink hover:bg-paper"
                  >
                    Compléter dans Zones
                  </Link>
                ) : null}
              </CardBody>
            </Card>

            <Card>
              <CardHeader title="Comment piloter" />
              <CardBody className="space-y-2 text-sm text-ink-muted">
                <p>
                  1. Affectez 2 contrôleurs dans{" "}
                  <Link href="/zones" className="text-teal hover:underline">
                    Zones
                  </Link>
                  .
                </p>
                <p>
                  2. Cliquez{" "}
                  <strong className="text-ink">Planifier la relève</strong> —
                  période début/fin + qui commence.
                </p>
                <p>
                  3. Suivez la grille semaine : service (vert) / repos (jaune).
                </p>
              </CardBody>
            </Card>
          </aside>
        </div>
      </div>
    </PermissionGate>
  );
}
