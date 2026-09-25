"use client";

import { useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { type ColumnDef } from "@tanstack/react-table";
import {
  AlertTriangle,
  Clock3,
  HandCoins,
  Wallet,
} from "lucide-react";
import { useDebouncedValue } from "@/application/hooks/useDebouncedValue";
import { useClients } from "@/application/hooks/useClients";
import { useFactures } from "@/application/hooks/useResources";
import type { Facture, StatutPaiementFacture } from "@/domain/types/entities";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { DataTable } from "@/presentation/components/tables/DataTable";
import { TableActions } from "@/presentation/components/tables/TableActions";
import { Badge, statusTone } from "@/presentation/components/ui/Badge";
import { Button } from "@/presentation/components/ui/Button";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { Select } from "@/presentation/components/ui/Select";
import { StatCard } from "@/presentation/components/ui/StatCard";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { can } from "@/shared/lib/can";
import { formatDate, formatFcfa, labelize } from "@/shared/lib/format";

const STATUT_PAIEMENT_LABELS: Record<StatutPaiementFacture, string> = {
  non_payee: "Non payée",
  partiel: "Partiel",
  soldee: "Soldée",
};

const STATUT_FILTER_OPTIONS = [
  { value: "", label: "Tous (non soldées)" },
  { value: "non_payee", label: "Non payée" },
  { value: "partiel", label: "Partiel" },
];

function startOfDay(d: Date) {
  const x = new Date(d);
  x.setHours(0, 0, 0, 0);
  return x;
}

function daysOverdue(echeance: string | null | undefined): number | null {
  if (!echeance) return null;
  const due = startOfDay(new Date(echeance));
  if (Number.isNaN(due.getTime())) return null;
  const today = startOfDay(new Date());
  const diff = Math.floor((today.getTime() - due.getTime()) / 86_400_000);
  return diff > 0 ? diff : 0;
}

export default function RecouvrementPage() {
  return (
    <PermissionGate permission="factures.view">
      <RecouvrementPageContent />
    </PermissionGate>
  );
}

function RecouvrementPageContent() {
  const router = useRouter();
  const { user } = useAuth();
  const canPay = can(user, "paiements.create");

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [q, setQ] = useState("");
  const [clientFilter, setClientFilter] = useState("");
  const [statutPaiementFilter, setStatutPaiementFilter] = useState("");
  const [retardOnly, setRetardOnly] = useState(false);
  const [echeance30jOnly, setEcheance30jOnly] = useState(false);
  const search = useDebouncedValue(q);

  const resetPage = () => setPage(1);

  const { data: clientsData } = useClients({ all: true });
  const clientFilterOptions = useMemo(
    () => [
      { value: "", label: "Tous les clients" },
      ...(clientsData?.data ?? []).map((c) => ({
        value: c.id,
        label: c.raison_sociale,
      })),
    ],
    [clientsData],
  );

  const listParams = {
    a_recouvrer: true as const,
    page,
    per_page: perPage,
    q: search || undefined,
    client_id: clientFilter || undefined,
    statut_paiement: statutPaiementFilter || undefined,
    retard: retardOnly || undefined,
    echeance_30j: echeance30jOnly || undefined,
    statut: echeance30jOnly ? "valide" : undefined,
  };

  const { data, isLoading } = useFactures(listParams);

  const { data: statsRes, isLoading: statsLoading } = useFactures({
    a_recouvrer: true,
    all: true,
  });

  const stats = useMemo(() => {
    const rows = statsRes?.data ?? [];
    const today = startOfDay(new Date());
    const in30 = new Date(today);
    in30.setDate(in30.getDate() + 30);

    let totalSolde = 0;
    let enRetard = 0;
    let echeance30 = 0;

    for (const f of rows) {
      totalSolde += Number(f.solde ?? 0);
      if (!f.date_echeance) continue;
      const due = startOfDay(new Date(f.date_echeance));
      if (Number.isNaN(due.getTime())) continue;
      if (due < today) enRetard += 1;
      if (due <= in30) echeance30 += 1;
    }

    return {
      count: rows.length,
      totalSolde,
      enRetard,
      echeance30,
    };
  }, [statsRes]);

  const columns = useMemo<ColumnDef<Facture>[]>(
    () => [
      {
        accessorKey: "numero",
        header: "Numéro",
        cell: ({ getValue }) => (
          <span className="font-mono text-xs font-medium">
            {String(getValue())}
          </span>
        ),
      },
      {
        id: "client",
        header: "Client",
        cell: ({ row }) =>
          row.original.client?.raison_sociale
          ?? row.original.client_nom
          ?? "—",
      },
      {
        accessorKey: "date_emission",
        header: "Émission",
        cell: ({ getValue }) => formatDate(String(getValue())),
      },
      {
        accessorKey: "date_echeance",
        header: "Échéance",
        cell: ({ row }) => {
          const f = row.original;
          const overdue = daysOverdue(f.date_echeance);
          return (
            <div className="space-y-0.5">
              <p>{formatDate(f.date_echeance)}</p>
              {overdue != null && overdue > 0 ? (
                <p className="text-[11px] font-medium text-danger">
                  Retard {overdue} j
                </p>
              ) : null}
            </div>
          );
        },
      },
      {
        accessorKey: "montant_ttc",
        header: "TTC",
        cell: ({ getValue }) => (
          <span className="font-mono text-xs tabular-nums">
            {formatFcfa(getValue() as string | number)}
          </span>
        ),
      },
      {
        id: "solde",
        header: "Solde dû",
        cell: ({ row }) => (
          <span className="font-mono text-xs font-semibold tabular-nums">
            {formatFcfa(row.original.solde ?? row.original.montant_ttc)}
          </span>
        ),
      },
      {
        id: "paiement",
        header: "Statut",
        cell: ({ row }) => {
          const sp = (row.original.statut_paiement ?? "non_payee") as StatutPaiementFacture;
          return (
            <Badge tone={statusTone(sp)}>
              {STATUT_PAIEMENT_LABELS[sp] ?? labelize(sp)}
            </Badge>
          );
        },
      },
      {
        id: "actions",
        header: "Actions",
        enableSorting: false,
        cell: ({ row }) => {
          if (!canPay) {
            return <span className="text-xs text-ink-faint">—</span>;
          }
          const facture = row.original;
          return (
            <TableActions
              items={[
                {
                  key: "payer",
                  label: "Enregistrer un paiement",
                  icon: Wallet,
                  tone: "success",
                  onClick: () =>
                    router.push(`/paiements?facture_id=${facture.id}`),
                },
              ]}
            />
          );
        },
      },
    ],
    [canPay, router],
  );

  return (
    <div className="space-y-6">
      <PageHeader
        title="À recouvrer"
        description="Créances clients (factures validées non soldées) — l’encaissement se fait via Paiements, puis entre en Trésorerie."
      />

      <div className="grid gap-3 sm:grid-cols-3">
        <StatCard
          label="Total à recouvrer"
          value={statsLoading ? "…" : formatFcfa(stats.totalSolde)}
          hint={statsLoading ? undefined : `${stats.count} facture${stats.count > 1 ? "s" : ""}`}
          icon={<HandCoins className="size-5 text-ink-muted" />}
        />
        <StatCard
          label="En retard"
          value={statsLoading ? "…" : stats.enRetard}
          hint="Échéance dépassée"
          icon={<Clock3 className="size-5 text-danger" />}
        />
        <StatCard
          label="Échéance ≤ 30 j"
          value={statsLoading ? "…" : stats.echeance30}
          hint="Dont retards inclus"
          icon={<AlertTriangle className="size-5 text-warning" />}
        />
      </div>

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
          placeholder: "Rechercher n° facture, client…",
        }}
        toolbar={
          <div className="flex flex-wrap items-center gap-2">
            <Select
              className="min-w-[12rem]"
              value={clientFilter}
              options={clientFilterOptions}
              onChange={(event) => {
                setClientFilter(event.target.value);
                resetPage();
              }}
            />
            <Select
              className="min-w-[11rem]"
              value={statutPaiementFilter}
              options={STATUT_FILTER_OPTIONS}
              onChange={(event) => {
                setStatutPaiementFilter(event.target.value);
                resetPage();
              }}
            />
            <Button
              type="button"
              variant={retardOnly ? "primary" : "secondary"}
              size="sm"
              aria-pressed={retardOnly}
              onClick={() => {
                setRetardOnly((v) => !v);
                if (!retardOnly) setEcheance30jOnly(false);
                resetPage();
              }}
            >
              <Clock3 className="size-4" />
              En retard
              {retardOnly ? " (actif)" : ""}
            </Button>
            <Button
              type="button"
              variant={echeance30jOnly ? "primary" : "secondary"}
              size="sm"
              aria-pressed={echeance30jOnly}
              onClick={() => {
                setEcheance30jOnly((v) => !v);
                if (!echeance30jOnly) setRetardOnly(false);
                resetPage();
              }}
            >
              <AlertTriangle className="size-4" />
              Échéance ≤ 30 j
              {echeance30jOnly ? " (actif)" : ""}
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
        emptyTitle="Aucune créance à recouvrer"
      />
    </div>
  );
}
