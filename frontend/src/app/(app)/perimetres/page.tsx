"use client";

import { useMemo, useState } from "react";
import { type ColumnDef } from "@tanstack/react-table";
import { MapPin, MapPinOff, ShieldCheck } from "lucide-react";
import { useDebouncedValue } from "@/application/hooks/useDebouncedValue";
import { usePerimetres } from "@/application/hooks/useResources";
import { useZones } from "@/application/hooks/useZones";
import { STATUT_CONTROLEUR_FILTER_OPTIONS } from "@/domain/schemas/perimetre";
import type { Agent } from "@/domain/types/entities";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { DataTable } from "@/presentation/components/tables/DataTable";
import { Badge, statusTone } from "@/presentation/components/ui/Badge";
import { Button } from "@/presentation/components/ui/Button";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { Select } from "@/presentation/components/ui/Select";
import { labelAgentStatut } from "@/shared/lib/format";

export default function PerimetresPage() {
  const [q, setQ] = useState("");
  const [statutFilter, setStatutFilter] = useState("");
  const [zoneFilter, setZoneFilter] = useState("");
  const [sansZoneOnly, setSansZoneOnly] = useState(false);
  const search = useDebouncedValue(q);
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);

  const resetPage = () => setPage(1);

  const toggleSansZone = () => {
    setSansZoneOnly((v) => !v);
    resetPage();
  };

  const { data: zonesData } = useZones({ all: true });

  const zoneFilterOptions = useMemo(
    () => [
      { value: "", label: "Toutes les zones" },
      ...(zonesData?.data ?? []).map((z) => ({
        value: z.id,
        label: z.nom,
      })),
    ],
    [zonesData],
  );

  const { data, isLoading } = usePerimetres({
    q: search || undefined,
    statut: statutFilter || undefined,
    zone_id: sansZoneOnly ? undefined : zoneFilter || undefined,
    sans_zone: sansZoneOnly || undefined,
  });

  const filtered = data?.data ?? [];

  const paginated = useMemo(() => {
    const start = (page - 1) * perPage;
    return filtered.slice(start, start + perPage);
  }, [filtered, page, perPage]);

  const columns = useMemo<ColumnDef<Agent>[]>(
    () => [
      {
        id: "controleur",
        header: "Contrôleur",
        cell: ({ row }) => (
          <span className="font-medium">
            {row.original.prenom} {row.original.nom}
          </span>
        ),
      },
      {
        accessorKey: "matricule",
        header: "Matricule",
        cell: ({ getValue }) => (
          <span className="font-mono text-xs">{String(getValue())}</span>
        ),
      },
      {
        id: "grade",
        header: "Grade",
        cell: ({ row }) => row.original.grade?.libelle ?? "—",
      },
      {
        accessorKey: "statut",
        header: "Statut",
        cell: ({ getValue }) => {
          const v = String(getValue());
          return <Badge tone={statusTone(v)}>{labelAgentStatut(v)}</Badge>;
        },
      },
      {
        id: "perimetre",
        header: "Zone",
        cell: ({ row }) => {
          const zone = row.original.perimetres?.[0]?.zone?.nom;
          return zone ? (
            <span className="text-sm text-ink-muted">{zone}</span>
          ) : (
            <span className="text-ink-faint">Aucune</span>
          );
        },
      },
      {
        id: "sites_count",
        header: "Sites",
        cell: ({ row }) => (
          <span className="inline-flex items-center gap-1.5 font-mono text-sm tabular-nums text-ink">
            <MapPin className="size-3.5 text-ink-faint" />
            {row.original.perimetre_sites_count ?? 0}
          </span>
        ),
      },
      {
        id: "agents_count",
        header: "Agents sous charge",
        cell: ({ row }) => (
          <span className="inline-flex items-center gap-1.5 font-mono text-sm tabular-nums text-ink">
            <ShieldCheck className="size-3.5 text-ink-faint" />
            {row.original.perimetre_agents_count ?? 0}
          </span>
        ),
      },
    ],
    [],
  );

  return (
    <PermissionGate
      permission={["perimetres.view", "agents.view"]}
      title="Périmètre contrôleurs"
    >
      <div>
        <PageHeader
          title="Périmètre contrôleurs"
          description="Vue des affectations par zone. Assignation : Zones. Planning : Planning contrôleurs."
        />
        <DataTable
          data={paginated}
          columns={columns}
          isLoading={isLoading}
          search={{
            value: q,
            onChange: (value) => {
              setQ(value);
              resetPage();
            },
            placeholder: "Nom, prénom, matricule, zone…",
          }}
          toolbar={
            <div className="flex flex-wrap items-center gap-2">
              <Select
                className="min-w-[11rem]"
                value={statutFilter}
                options={STATUT_CONTROLEUR_FILTER_OPTIONS.map((o) => ({
                  value: o.value,
                  label: o.label,
                }))}
                onChange={(event) => {
                  setStatutFilter(event.target.value);
                  resetPage();
                }}
              />
              <Select
                className="min-w-[12rem]"
                value={zoneFilter}
                disabled={sansZoneOnly}
                title={
                  sansZoneOnly
                    ? "Désactivez « Sans zone » pour filtrer par zone"
                    : undefined
                }
                options={zoneFilterOptions}
                onChange={(event) => {
                  setZoneFilter(event.target.value);
                  resetPage();
                }}
              />
              <Button
                type="button"
                variant={sansZoneOnly ? "primary" : "secondary"}
                size="sm"
                aria-pressed={sansZoneOnly}
                onClick={toggleSansZone}
              >
                <MapPinOff className="size-4" />
                Sans zone
                {sansZoneOnly ? " (actif)" : ""}
              </Button>
            </div>
          }
          pagination={{
            page,
            perPage,
            total: filtered.length,
            onPageChange: setPage,
            onPerPageChange: (n) => {
              setPerPage(n);
              resetPage();
            },
          }}
          emptyTitle="Aucun contrôleur"
        />
      </div>
    </PermissionGate>
  );
}
