"use client";

import { useCallback, useMemo, useState } from "react";
import { type ColumnDef } from "@tanstack/react-table";
import { AlertTriangle, ArrowRightCircle, CalendarRange, CheckCircle2 } from "lucide-react";
import { useDebouncedValue } from "@/application/hooks/useDebouncedValue";
import {
  useAnomalies,
  useSites,
  useUpdateAnomalieStatut,
} from "@/application/hooks/useResources";
import {
  GRAVITE_ANOMALIE_FILTER_OPTIONS,
  STATUT_ANOMALIE_FILTER_OPTIONS,
  TYPE_ANOMALIE_FILTER_OPTIONS,
} from "@/domain/schemas/anomalie";
import type { Anomalie, StatutAnomalie } from "@/domain/types/entities";
import { DataTable } from "@/presentation/components/tables/DataTable";
import { TableActions } from "@/presentation/components/tables/TableActions";
import { Badge, statusTone } from "@/presentation/components/ui/Badge";
import { Button } from "@/presentation/components/ui/Button";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { Select } from "@/presentation/components/ui/Select";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { useToast } from "@/presentation/providers/ToastProvider";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { can } from "@/shared/lib/can";
import { formatDateTime, labelize } from "@/shared/lib/format";

const nextStatut: Record<StatutAnomalie, StatutAnomalie | null> = {
  ouverte: "en_cours",
  en_cours: "resolue",
  resolue: null,
};

const nextActionLabel: Record<StatutAnomalie, string> = {
  ouverte: "Prendre en charge",
  en_cours: "Marquer résolue",
  resolue: "",
};

export default function AnomaliesPage() {
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [q, setQ] = useState("");
  const [statutFilter, setStatutFilter] = useState("");
  const [graviteFilter, setGraviteFilter] = useState("");
  const [typeFilter, setTypeFilter] = useState("");
  const [siteFilter, setSiteFilter] = useState("");
  const [aTraiterOnly, setATraiterOnly] = useState(false);
  const [recent30jOnly, setRecent30jOnly] = useState(false);
  const search = useDebouncedValue(q);
  const { toast } = useToast();
  const { user } = useAuth();
  const canUpdate = can(user, "anomalies.update");

  const resetPage = () => setPage(1);

  const toggleATraiter = () => {
    setATraiterOnly((v) => !v);
    resetPage();
  };

  const toggleRecent30j = () => {
    setRecent30jOnly((v) => !v);
    resetPage();
  };

  const { data: sitesData } = useSites({ all: true });

  const siteFilterOptions = useMemo(
    () => [
      { value: "", label: "Tous les sites" },
      ...(sitesData?.data ?? []).map((s) => ({
        value: s.id,
        label: s.nom,
      })),
    ],
    [sitesData],
  );

  const { data, isLoading } = useAnomalies({
    page,
    per_page: perPage,
    q: search || undefined,
    statut: aTraiterOnly ? undefined : statutFilter || undefined,
    gravite: graviteFilter || undefined,
    type: typeFilter || undefined,
    site_id: siteFilter || undefined,
    a_traiter: aTraiterOnly || undefined,
    recent_30j: recent30jOnly || undefined,
  });
  const updateStatut = useUpdateAnomalieStatut();
  const [busyId, setBusyId] = useState<string | null>(null);

  const advance = useCallback(
    async (anomalie: Anomalie) => {
      const target = nextStatut[anomalie.statut];
      if (!target) return;
      setBusyId(anomalie.id);
      try {
        const payload: { statut: StatutAnomalie; assigne_a_id?: string | null } = {
          statut: target,
        };
        if (target === "en_cours" && !anomalie.assigne_a_id && user?.id) {
          payload.assigne_a_id = user.id;
        }
        await updateStatut.mutateAsync({ id: anomalie.id, payload });
        toast(
          target === "resolue"
            ? "Anomalie marquée résolue."
            : "Anomalie prise en charge.",
        );
      } catch (err) {
        toast(
          getApiErrorMessage(err, "Échec de la mise à jour du statut."),
          "danger",
        );
      } finally {
        setBusyId(null);
      }
    },
    [toast, updateStatut, user?.id],
  );

  const columns = useMemo<ColumnDef<Anomalie>[]>(
    () => [
      {
        accessorKey: "signale_at",
        header: "Signalée le",
        cell: ({ getValue }) => formatDateTime(String(getValue())),
      },
      {
        id: "site",
        header: "Site",
        cell: ({ row }) => row.original.site?.nom ?? "—",
      },
      {
        accessorKey: "type",
        header: "Type",
        cell: ({ getValue }) => (
          <span className="capitalize">{labelize(String(getValue()))}</span>
        ),
      },
      {
        accessorKey: "gravite",
        header: "Gravité",
        cell: ({ getValue }) => {
          const v = String(getValue());
          return <Badge tone={statusTone(v)}>{labelize(v)}</Badge>;
        },
      },
      {
        accessorKey: "statut",
        header: "Statut",
        cell: ({ getValue }) => {
          const v = String(getValue());
          return <Badge tone={statusTone(v)}>{labelize(v)}</Badge>;
        },
      },
      {
        accessorKey: "commentaire",
        header: "Commentaire",
        cell: ({ getValue }) => (
          <span className="line-clamp-2 max-w-md text-ink-muted">
            {(getValue() as string | null) || "—"}
          </span>
        ),
      },
      {
        id: "actions",
        header: "Actions",
        enableSorting: false,
        cell: ({ row }) => {
          const anomalie = row.original;
          const target = nextStatut[anomalie.statut];
          if (!canUpdate || !target) {
            return <span className="text-xs text-ink-faint">—</span>;
          }
          const isResolve = anomalie.statut === "en_cours";
          return (
            <TableActions
              canEdit={false}
              canDelete={false}
              items={[
                {
                  key: "advance",
                  label: nextActionLabel[anomalie.statut],
                  icon: isResolve ? CheckCircle2 : ArrowRightCircle,
                  tone: isResolve ? "success" : "primary",
                  disabled: busyId === anomalie.id,
                  onClick: () => void advance(anomalie),
                },
              ]}
            />
          );
        },
      },
    ],
    [advance, busyId, canUpdate],
  );

  return (
    <PermissionGate permission="anomalies.view" title="Anomalies">
    <div>
      <PageHeader
        title="Anomalies"
        description="Signalements terrain — gravité et suivi de statut."
      />
      <DataTable
        data={data?.data ?? []}
        columns={columns}
        isLoading={isLoading}
        search={{
          value: q,
          onChange: (value) => {
            setQ(value);
            setPage(1);
          },
          placeholder: "Rechercher site, type, gravité, statut, commentaire…",
        }}
        toolbar={
          <div className="flex flex-wrap items-center gap-2">
            <Select
              className="min-w-[11rem]"
              value={statutFilter}
              disabled={aTraiterOnly}
              title={
                aTraiterOnly
                  ? "Désactivez « À traiter » pour filtrer par statut"
                  : undefined
              }
              options={STATUT_ANOMALIE_FILTER_OPTIONS.map((o) => ({
                value: o.value,
                label: o.label,
              }))}
              onChange={(event) => {
                setStatutFilter(event.target.value);
                resetPage();
              }}
            />
            <Select
              className="min-w-[11rem]"
              value={graviteFilter}
              options={GRAVITE_ANOMALIE_FILTER_OPTIONS.map((o) => ({
                value: o.value,
                label: o.label,
              }))}
              onChange={(event) => {
                setGraviteFilter(event.target.value);
                resetPage();
              }}
            />
            <Select
              className="min-w-[11rem]"
              value={typeFilter}
              options={TYPE_ANOMALIE_FILTER_OPTIONS.map((o) => ({
                value: o.value,
                label: o.label,
              }))}
              onChange={(event) => {
                setTypeFilter(event.target.value);
                resetPage();
              }}
            />
            <Select
              className="min-w-[12rem]"
              value={siteFilter}
              options={siteFilterOptions}
              onChange={(event) => {
                setSiteFilter(event.target.value);
                resetPage();
              }}
            />
            <Button
              type="button"
              variant={aTraiterOnly ? "primary" : "secondary"}
              size="sm"
              aria-pressed={aTraiterOnly}
              onClick={toggleATraiter}
            >
              <AlertTriangle className="size-4" />
              À traiter
              {aTraiterOnly ? " (actif)" : ""}
            </Button>
            <Button
              type="button"
              variant={recent30jOnly ? "primary" : "secondary"}
              size="sm"
              aria-pressed={recent30jOnly}
              onClick={toggleRecent30j}
            >
              <CalendarRange className="size-4" />
              30 derniers jours
              {recent30jOnly ? " (actif)" : ""}
            </Button>
          </div>
        }
        pagination={{
          page,
          perPage: data?.meta.per_page ?? perPage,
          total: data?.meta.total ?? 0,
          onPageChange: setPage,
          onPerPageChange: (n) => {
            setPerPage(n);
            setPage(1);
          },
        }}
        emptyTitle="Aucune anomalie"
      />
    </div>
    </PermissionGate>
  );
}
