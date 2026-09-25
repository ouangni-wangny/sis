"use client";

import { useMemo, useState } from "react";
import { type ColumnDef } from "@tanstack/react-table";
import {
  ArrowDownLeft,
  ArrowUpRight,
  Landmark,
  Receipt,
  Scale,
  Wallet,
} from "lucide-react";
import {
  useComptesTresorerie,
  useCreateAjustementTresorerie,
  useModesPaiementOptions,
  useMouvementsTresorerie,
  useTresorerieStats,
} from "@/application/hooks/useResources";
import type { MouvementTresorerie } from "@/domain/types/entities";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { DataTable } from "@/presentation/components/tables/DataTable";
import { Alert } from "@/presentation/components/ui/Alert";
import { Badge } from "@/presentation/components/ui/Badge";
import { Button } from "@/presentation/components/ui/Button";
import { Input } from "@/presentation/components/ui/Input";
import { Modal } from "@/presentation/components/ui/Modal";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { Select } from "@/presentation/components/ui/Select";
import { StatCard } from "@/presentation/components/ui/StatCard";
import { Textarea } from "@/presentation/components/ui/Textarea";
import { useToast } from "@/presentation/providers/ToastProvider";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { cn } from "@/shared/lib/cn";
import { formatDate, formatFcfa, labelize } from "@/shared/lib/format";

const MOIS = [
  "Janvier", "Février", "Mars", "Avril", "Mai", "Juin",
  "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre",
];

export default function TresoreriePage() {
  const now = new Date();
  const [mois, setMois] = useState(now.getMonth() + 1);
  const [annee, setAnnee] = useState(now.getFullYear());
  const [page, setPage] = useState(1);
  const [compteFilter, setCompteFilter] = useState("");
  const [ajustOpen, setAjustOpen] = useState(false);
  const [ajustError, setAjustError] = useState<string | null>(null);
  const [ajustForm, setAjustForm] = useState({
    compte_tresorerie_id: "",
    direction: "entree" as "entree" | "sortie",
    montant: "",
    date_mouvement: now.toISOString().slice(0, 10),
    mode: "virement",
    reference: "",
    notes: "",
  });
  const { toast } = useToast();

  const { data: statsRes, isLoading: statsLoading } = useTresorerieStats({
    mois,
    annee,
  });
  const stats = statsRes?.data;
  const { data: comptesRes } = useComptesTresorerie({ actif_only: 1 });
  const comptes = comptesRes?.data ?? [];
  const { data: modesRes } = useModesPaiementOptions();
  const modeOptions = useMemo(() => {
    const modes = modesRes?.data ?? [];
    return modes.map((m) => ({ value: m.code, label: m.libelle }));
  }, [modesRes]);
  const { data: mouvRes, isLoading: mouvLoading } = useMouvementsTresorerie({
    page,
    per_page: 15,
    compte_id: compteFilter || undefined,
  });
  const createAjustement = useCreateAjustementTresorerie();

  const compteOptions = useMemo(
    () => [
      { value: "", label: "Tous les comptes" },
      ...comptes.map((c) => ({ value: c.id, label: c.libelle })),
    ],
    [comptes],
  );

  const columns = useMemo<ColumnDef<MouvementTresorerie>[]>(
    () => [
      {
        accessorKey: "date_mouvement",
        header: "Date",
        cell: ({ getValue }) => formatDate(getValue() as string),
      },
      {
        id: "compte",
        header: "Compte",
        cell: ({ row }) => row.original.compte?.libelle ?? "—",
      },
      {
        accessorKey: "direction",
        header: "Sens",
        cell: ({ getValue }) => {
          const d = getValue() as string;
          return (
            <Badge tone={d === "entree" ? "success" : "danger"}>
              {d === "entree" ? "Entrée" : "Sortie"}
            </Badge>
          );
        },
      },
      {
        accessorKey: "montant",
        header: "Montant",
        cell: ({ getValue }) => formatFcfa(getValue() as number),
      },
      {
        accessorKey: "mode",
        header: "Mode",
        cell: ({ getValue }) => String(getValue() ?? "—").toUpperCase(),
      },
      {
        accessorKey: "source_type",
        header: "Source",
        cell: ({ getValue }) => labelize(String(getValue())),
      },
      {
        accessorKey: "notes",
        header: "Notes",
        cell: ({ getValue }) => (getValue() as string) || "—",
      },
    ],
    [],
  );

  const submitAjustement = async () => {
    setAjustError(null);
    try {
      await createAjustement.mutateAsync({
        compte_tresorerie_id: ajustForm.compte_tresorerie_id,
        direction: ajustForm.direction,
        montant: Number(ajustForm.montant),
        date_mouvement: ajustForm.date_mouvement,
        mode: ajustForm.mode,
        reference: ajustForm.reference || undefined,
        notes: ajustForm.notes || undefined,
      });
      toast("Ajustement enregistré.", "success");
      setAjustOpen(false);
    } catch (err) {
      setAjustError(getApiErrorMessage(err, "Échec de l’ajustement."));
    }
  };

  const soldeNegatif = (stats?.solde_consolide ?? 0) < 0;
  const paieReglements = stats?.paie_mois.par_mode.reduce(
    (n, r) => n + r.count,
    0,
  ) ?? 0;

  return (
    <PermissionGate
      permission={["tresorerie.view", "tresorerie.manage"]}
      title="Trésorerie"
    >
      <div className="space-y-6">
        <PageHeader
          title="Trésorerie"
          description="Soldes des comptes, journal des mouvements et masse salariale payée."
          actions={
            <Button
              type="button"
              onClick={() => {
                setAjustForm((f) => ({
                  ...f,
                  compte_tresorerie_id: comptes[0]?.id ?? "",
                }));
                setAjustError(null);
                setAjustOpen(true);
              }}
            >
              Ajustement
            </Button>
          }
        />

        <div className="flex flex-wrap gap-3">
          <Select
            label="Mois"
            value={String(mois)}
            onChange={(e) => setMois(Number(e.target.value))}
            options={MOIS.map((label, i) => ({
              value: String(i + 1),
              label,
            }))}
          />
          <Input
            label="Année"
            type="number"
            value={annee}
            onChange={(e) => setAnnee(Number(e.target.value))}
          />
        </div>

        {statsLoading ? (
          <p className="text-sm text-ink-muted">Chargement des stats…</p>
        ) : stats ? (
          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <StatCard
              label="Solde consolidé"
              value={formatFcfa(stats.solde_consolide)}
              hint={`${stats.comptes.length} compte${stats.comptes.length > 1 ? "s" : ""} actif${stats.comptes.length > 1 ? "s" : ""}`}
              icon={<Scale className="size-4" />}
              className={cn(
                soldeNegatif
                  ? "border-rose-200 bg-gradient-to-br from-rose-50 to-white"
                  : "border-teal/25 bg-gradient-to-br from-teal/[0.08] to-white",
              )}
            />
            <StatCard
              label="Entrées du mois"
              value={formatFcfa(stats.entrees_mois?.total ?? 0)}
              hint={`${stats.entrees_mois?.count ?? 0} mouvement${(stats.entrees_mois?.count ?? 0) > 1 ? "s" : ""} · encaissements ${formatFcfa(stats.entrees_mois?.encaissements ?? 0)}`}
              icon={<ArrowDownLeft className="size-4" />}
              className="border-emerald-200/80 bg-gradient-to-br from-emerald-50/90 to-white"
            />
            <StatCard
              label="Sorties du mois"
              value={formatFcfa(stats.sorties_mois?.total ?? 0)}
              hint={`${stats.sorties_mois?.count ?? 0} mouvement${(stats.sorties_mois?.count ?? 0) > 1 ? "s" : ""}`}
              icon={<ArrowUpRight className="size-4" />}
              className="border-amber-200/80 bg-gradient-to-br from-amber-50/80 to-white"
            />
            <StatCard
              label="Dépenses du mois"
              value={formatFcfa(stats.depenses_mois.total)}
              hint="Hors salaires"
              icon={<Receipt className="size-4" />}
            />
            <StatCard
              label="Salaires payés"
              value={formatFcfa(stats.paie_mois.total_paye)}
              hint={`${paieReglements} règlement${paieReglements > 1 ? "s" : ""}`}
              icon={<Wallet className="size-4" />}
            />
            <StatCard
              label="Comptes actifs"
              value={stats.comptes.length}
              hint="Banque · Caisse · Mobile Money"
              icon={<Landmark className="size-4" />}
            />
          </div>
        ) : null}

        {stats?.comptes?.length ? (
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {stats.comptes.map((c) => {
              const negatif = Number(c.solde) < 0;
              return (
                <div
                  key={c.id}
                  className={cn(
                    "rounded-xl border bg-white p-4 shadow-sm",
                    negatif ? "border-rose-200" : "border-border",
                  )}
                >
                  <div className="flex items-start justify-between gap-2">
                    <div>
                      <p className="text-[11px] font-medium uppercase tracking-wide text-ink-faint">
                        {labelize(c.type)}
                      </p>
                      <p className="mt-0.5 font-semibold text-ink">{c.libelle}</p>
                    </div>
                    <span className="rounded-md bg-ink/[0.04] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-ink-muted">
                      {String(c.type).replaceAll("_", " ")}
                    </span>
                  </div>
                  <p
                    className={cn(
                      "mt-3 font-mono text-xl font-semibold tabular-nums",
                      negatif ? "text-rose-700" : "text-teal",
                    )}
                  >
                    {formatFcfa(c.solde)}
                  </p>
                </div>
              );
            })}
          </div>
        ) : null}

        {stats?.paie_mois?.par_mode?.length ? (
          <div className="rounded-xl border border-border bg-white p-4 shadow-sm">
            <p className="mb-3 text-sm font-semibold text-ink">
              Paie du mois par mode
            </p>
            <ul className="divide-y divide-border/70">
              {stats.paie_mois.par_mode.map((row) => (
                <li
                  key={row.mode}
                  className="flex items-center justify-between gap-4 py-2.5 text-sm"
                >
                  <span className="font-medium uppercase tracking-wide text-ink">
                    {String(row.mode).toUpperCase()}
                  </span>
                  <span className="tabular-nums text-ink-muted">
                    {formatFcfa(row.montant)}
                    <span className="ml-2 text-ink-faint">
                      · {row.count} règlement{row.count > 1 ? "s" : ""}
                    </span>
                  </span>
                </li>
              ))}
            </ul>
          </div>
        ) : null}

        <div className="space-y-3">
          <div className="flex flex-wrap items-end gap-3">
            <Select
              label="Filtrer par compte"
              value={compteFilter}
              onChange={(e) => {
                setCompteFilter(e.target.value);
                setPage(1);
              }}
              options={compteOptions}
            />
          </div>
          <DataTable
            columns={columns}
            data={mouvRes?.data ?? []}
            isLoading={mouvLoading}
            selectable={false}
            pagination={{
              page,
              perPage: 15,
              total: mouvRes?.meta.total ?? 0,
              onPageChange: setPage,
            }}
          />
        </div>
      </div>

      <Modal
        open={ajustOpen}
        onClose={() => !createAjustement.isPending && setAjustOpen(false)}
        title="Ajustement de trésorerie"
      >
        <div className="space-y-3">
          {ajustError ? <Alert tone="danger">{ajustError}</Alert> : null}
          <Select
            label="Compte"
            value={ajustForm.compte_tresorerie_id}
            onChange={(e) =>
              setAjustForm((f) => ({
                ...f,
                compte_tresorerie_id: e.target.value,
              }))
            }
            options={comptes.map((c) => ({ value: c.id, label: c.libelle }))}
          />
          <Select
            label="Sens"
            value={ajustForm.direction}
            onChange={(e) =>
              setAjustForm((f) => ({
                ...f,
                direction: e.target.value as "entree" | "sortie",
              }))
            }
            options={[
              { value: "entree", label: "Entrée" },
              { value: "sortie", label: "Sortie" },
            ]}
          />
          <Input
            label="Montant"
            type="number"
            value={ajustForm.montant}
            onChange={(e) =>
              setAjustForm((f) => ({ ...f, montant: e.target.value }))
            }
          />
          <Input
            label="Date"
            type="date"
            value={ajustForm.date_mouvement}
            onChange={(e) =>
              setAjustForm((f) => ({ ...f, date_mouvement: e.target.value }))
            }
          />
          <Select
            label="Mode"
            value={ajustForm.mode}
            onChange={(e) =>
              setAjustForm((f) => ({ ...f, mode: e.target.value }))
            }
            options={modeOptions}
          />
          <Input
            label="Référence"
            value={ajustForm.reference}
            onChange={(e) =>
              setAjustForm((f) => ({ ...f, reference: e.target.value }))
            }
          />
          <Textarea
            label="Notes"
            value={ajustForm.notes}
            onChange={(e) =>
              setAjustForm((f) => ({ ...f, notes: e.target.value }))
            }
          />
          <div className="flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="ghost"
              onClick={() => setAjustOpen(false)}
              disabled={createAjustement.isPending}
            >
              Annuler
            </Button>
            <Button
              type="button"
              onClick={() => void submitAjustement()}
              disabled={createAjustement.isPending}
            >
              Enregistrer
            </Button>
          </div>
        </div>
      </Modal>
    </PermissionGate>
  );
}
