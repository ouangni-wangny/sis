"use client";

import { useCallback, useEffect, useMemo, useState, Suspense } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { type ColumnDef } from "@tanstack/react-table";
import {
  CheckCircle2,
  FileDown,
  Plus,
  Wallet,
  XCircle,
  AlertTriangle,
} from "lucide-react";
import { useDebouncedValue } from "@/application/hooks/useDebouncedValue";
import { useClients } from "@/application/hooks/useClients";
import {
  PERIODICITE_FACTURE_FILTER_OPTIONS,
  STATUT_PAIEMENT_FACTURE_FILTER_OPTIONS,
} from "@/domain/schemas/facture";
import {
  useFactures,
  useGenererFacturePdf,
  useUpdateFactureStatut,
} from "@/application/hooks/useResources";
import type { Facture, StatutFacture } from "@/domain/types/entities";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { DataTable } from "@/presentation/components/tables/DataTable";
import { TableActions } from "@/presentation/components/tables/TableActions";
import { Badge, statusTone } from "@/presentation/components/ui/Badge";
import { Button } from "@/presentation/components/ui/Button";
import { ConfirmDialog } from "@/presentation/components/ui/ConfirmDialog";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { Select } from "@/presentation/components/ui/Select";
import { Spinner } from "@/presentation/components/ui/Spinner";
import { TabPanel, Tabs } from "@/presentation/components/ui/Tabs";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { useToast } from "@/presentation/providers/ToastProvider";
import { apiClient } from "@/infrastructure/http/apiClient";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { can } from "@/shared/lib/can";
import { formatDate, formatFcfa, labelize } from "@/shared/lib/format";

type FactureTab = "proformas" | "factures" | "annulees";

const TAB_STATUT: Record<FactureTab, StatutFacture> = {
  proformas: "en_attente",
  factures: "valide",
  annulees: "annule",
};

function isFactureTab(value: string | null): value is FactureTab {
  return value === "proformas" || value === "factures" || value === "annulees";
}

const STATUT_LABELS: Record<string, string> = {
  en_attente: "Proforma",
  valide: "Validée",
  annule: "Rejetée",
};

const STATUT_PAIEMENT_LABELS: Record<string, string> = {
  non_payee: "Non payée",
  partiel: "Partiel",
  soldee: "Soldée",
};

function labelFactureStatut(statut: string) {
  return STATUT_LABELS[statut] ?? labelize(statut);
}

async function downloadFacturePdfFile(facture: Facture) {
  const response = await apiClient.get(`/factures/${facture.id}/pdf`, {
    responseType: "blob",
  });
  const blob = new Blob([response.data], { type: "application/pdf" });
  const objectUrl = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = objectUrl;
  link.download = `${(facture.numero || "facture").replace(/[/\\]/g, "-")}.pdf`;
  link.rel = "noopener";
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(objectUrl);
}

export default function FacturesPage() {
  return (
    <PermissionGate permission="factures.view" title="Factures">
      <Suspense
        fallback={
          <div className="flex justify-center py-16">
            <Spinner className="size-6" />
          </div>
        }
      >
        <FacturesPageContent />
      </Suspense>
    </PermissionGate>
  );
}

function FacturesPageContent() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const tabParam = searchParams.get("tab");
  const [tab, setTab] = useState<FactureTab>(() =>
    isFactureTab(tabParam) ? tabParam : "factures",
  );
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [q, setQ] = useState("");
  const [clientFilter, setClientFilter] = useState("");
  const [periodiciteFilter, setPeriodiciteFilter] = useState("");
  const [statutPaiementFilter, setStatutPaiementFilter] = useState("");
  const [echeance30jOnly, setEcheance30jOnly] = useState(false);
  const search = useDebouncedValue(q);
  const [pdfLoadingId, setPdfLoadingId] = useState<string | null>(null);
  const [statutUpdatingId, setStatutUpdatingId] = useState<string | null>(null);
  const [toValidate, setToValidate] = useState<Facture | null>(null);
  const [toReject, setToReject] = useState<Facture | null>(null);
  const { toast } = useToast();
  const { user } = useAuth();
  const canCreate = can(user, "factures.create");
  const canUpdate = can(user, "factures.update");
  const canView = can(user, "factures.view");
  const canPay = can(user, "paiements.create");

  const statut = TAB_STATUT[tab];

  const resetPage = () => setPage(1);

  const toggleEcheance30j = () => {
    setEcheance30jOnly((v) => !v);
    resetPage();
  };

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

  const { data, isLoading } = useFactures({
    page,
    per_page: perPage,
    q: search || undefined,
    statut,
    client_id: clientFilter || undefined,
    periodicite: periodiciteFilter || undefined,
    statut_paiement:
      tab === "factures" && statutPaiementFilter
        ? statutPaiementFilter
        : undefined,
    echeance_30j: echeance30jOnly || undefined,
  });

  const goCreateProforma = () => {
    if (!canCreate) {
      toast("Vous n’avez pas le droit de créer une facture proforma.", "danger");
      return;
    }
    router.push("/factures/nouvelle");
  };

  const handleTabChange = (id: string) => {
    const next = id as FactureTab;
    setTab(next);
    setPage(1);
    router.replace(`/factures?tab=${next}`, { scroll: false });
  };

  useEffect(() => {
    if (isFactureTab(tabParam) && tabParam !== tab) {
      setTab(tabParam);
      setPage(1);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- sync URL → tab only
  }, [tabParam]);

  const countProformas = useFactures({
    page: 1,
    per_page: 1,
    statut: "en_attente",
  });
  const countFactures = useFactures({
    page: 1,
    per_page: 1,
    statut: "valide",
  });
  const countAnnulees = useFactures({
    page: 1,
    per_page: 1,
    statut: "annule",
  });

  const genererFacturePdf = useGenererFacturePdf();
  const updateStatut = useUpdateFactureStatut();

  const factureTabs = useMemo(
    () => [
      {
        id: "proformas" as const,
        label: "Proformas",
        count: countProformas.data?.meta.total ?? 0,
      },
      {
        id: "factures" as const,
        label: "Factures",
        count: countFactures.data?.meta.total ?? 0,
      },
      {
        id: "annulees" as const,
        label: "Rejetées",
        count: countAnnulees.data?.meta.total ?? 0,
      },
    ],
    [
      countProformas.data?.meta.total,
      countFactures.data?.meta.total,
      countAnnulees.data?.meta.total,
    ],
  );

  const handleStatutChange = useCallback(
    async (facture: Facture, next: StatutFacture) => {
      if (!canUpdate || facture.statut === next) return;
      setStatutUpdatingId(facture.id);
      try {
        await updateStatut.mutateAsync({ id: facture.id, statut: next });
        toast(
          next === "valide"
            ? !facture.client_id
              ? "Proforma validée — client créé depuis la proforma, puis abonnement créé."
              : "Proforma validée — abonnement créé."
            : next === "annule"
              ? "Proforma rejetée."
              : `Statut mis à jour : ${labelFactureStatut(next)}.`,
        );
        setToValidate(null);
        setToReject(null);
      } catch (err) {
        toast(
          getApiErrorMessage(err, "Échec de la mise à jour du statut."),
          "danger",
        );
      } finally {
        setStatutUpdatingId(null);
      }
    },
    [canUpdate, toast, updateStatut],
  );

  const handleGenererProforma = useCallback(
    async (facture: Facture) => {
      if (!canView) {
        toast("Vous n’avez pas le droit de générer la facture.", "danger");
        return;
      }
      setPdfLoadingId(facture.id);
      try {
        if (canUpdate) {
          await genererFacturePdf.mutateAsync(facture.id);
        } else if (!facture.pdf?.url) {
          toast("Le PDF n’existe pas encore.", "danger");
          return;
        }
        await downloadFacturePdfFile(facture);
        toast(
          facture.statut === "en_attente"
            ? "Proforma générée — téléchargement démarré."
            : "Facture générée — téléchargement démarré.",
        );
      } catch (err) {
        toast(
          getApiErrorMessage(err, "Échec de la génération du PDF."),
          "danger",
        );
      } finally {
        setPdfLoadingId(null);
      }
    },
    [canUpdate, canView, genererFacturePdf, toast],
  );

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
        cell: ({ getValue }) =>
          formatDate(getValue() as string | null | undefined),
      },
      {
        accessorKey: "montant_ttc",
        header: "Montant TTC",
        cell: ({ getValue }) => (
          <span className="font-mono text-xs tabular-nums">
            {formatFcfa(getValue() as string | number)}
          </span>
        ),
      },
      {
        id: "paiement",
        header: "Paiement",
        cell: ({ row }) => {
          const f = row.original;
          if (f.statut === "en_attente" || f.statut === "annule") {
            return <span className="text-xs text-ink-faint">—</span>;
          }
          const sp = f.statut_paiement ?? "non_payee";
          return (
            <div className="space-y-1">
              <Badge tone={statusTone(sp)}>
                {STATUT_PAIEMENT_LABELS[sp] ?? labelize(sp)}
              </Badge>
              <p className="text-[11px] tabular-nums text-ink-muted">
                Solde {formatFcfa(f.solde ?? f.montant_ttc)}
              </p>
            </div>
          );
        },
      },
      {
        accessorKey: "statut",
        header: "Statut",
        cell: ({ row }) => {
          const facture = row.original;
          return (
            <Badge tone={statusTone(facture.statut)}>
              {labelFactureStatut(facture.statut)}
            </Badge>
          );
        },
      },
      {
        id: "actions",
        header: "Actions",
        enableSorting: false,
        cell: ({ row }) => {
          const facture = row.original;
          const isProforma = facture.statut === "en_attente";
          const isValidee = facture.statut === "valide";
          const busy = statutUpdatingId === facture.id;
          const canEditRow = canUpdate && facture.statut !== "annule";
          const canGenerate =
            canView && (canUpdate || Boolean(facture.pdf?.url));
          const canRecordPay =
            canPay && isValidee && Number(facture.solde ?? 1) > 0;

          const items = [
            ...(canUpdate && isProforma
              ? [
                  {
                    key: "validate",
                    label: "Valider la proforma",
                    icon: CheckCircle2,
                    tone: "success" as const,
                    onClick: () => {
                      if (!facture.periodicite) {
                        toast(
                          "Indiquez une périodicité sur la proforma avant de la valider (Modifier → Périodicité).",
                          "danger",
                        );
                        return;
                      }
                      if (!facture.client_id && !facture.client_nom?.trim()) {
                        toast(
                          "Indiquez le nom du destinataire sur la proforma avant de valider.",
                          "danger",
                        );
                        return;
                      }
                      setToValidate(facture);
                    },
                    disabled: busy,
                  },
                  {
                    key: "reject",
                    label: "Rejeter la proforma",
                    icon: XCircle,
                    tone: "danger" as const,
                    onClick: () => setToReject(facture),
                    disabled: busy,
                  },
                ]
              : []),
            ...(canGenerate
              ? [
                  {
                    key: "pdf",
                    label:
                      facture.statut === "en_attente"
                        ? "Générer / télécharger PDF"
                        : "Télécharger le PDF",
                    icon: FileDown,
                    tone: "accent" as const,
                    onClick: () => void handleGenererProforma(facture),
                    disabled: pdfLoadingId === facture.id,
                  },
                ]
              : []),
            ...(canRecordPay
              ? [
                  {
                    key: "pay",
                    label: "Enregistrer un paiement",
                    icon: Wallet,
                    tone: "success" as const,
                    onClick: () =>
                      router.push(`/paiements?facture_id=${facture.id}`),
                  },
                ]
              : []),
          ];

          return (
            <TableActions
              canEdit={canEditRow}
              canDelete={false}
              editLabel={isProforma ? "Modifier la proforma" : "Modifier"}
              onEdit={() => router.push(`/factures/${facture.id}/edit`)}
              items={items.length ? items : undefined}
            />
          );
        },
      },
    ],
    [
      canUpdate,
      canView,
      canPay,
      pdfLoadingId,
      statutUpdatingId,
      handleGenererProforma,
      router,
      toast,
    ],
  );

  const emptyByTab: Record<FactureTab, string> = {
    proformas: "Aucune proforma",
    factures: "Aucune facture",
    annulees: "Aucune proforma rejetée",
  };

  const statutBusy = statutUpdatingId != null;

  return (
      <div className="space-y-6">
        <PageHeader
          title="Factures"
          description="Proformas (devis) puis factures validées — montants en FCFA (XOF)."
        />

        <div>
          <Tabs items={factureTabs} value={tab} onChange={handleTabChange} />

          <TabPanel when={tab} active={tab}>
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
                    value={periodiciteFilter}
                    options={PERIODICITE_FACTURE_FILTER_OPTIONS.map((o) => ({
                      value: o.value,
                      label: o.label,
                    }))}
                    onChange={(event) => {
                      setPeriodiciteFilter(event.target.value);
                      resetPage();
                    }}
                  />
                  <Select
                    className="min-w-[11rem]"
                    value={statutPaiementFilter}
                    disabled={tab !== "factures"}
                    title={
                      tab !== "factures"
                        ? "Filtre paiement disponible sur l’onglet Factures"
                        : undefined
                    }
                    options={STATUT_PAIEMENT_FACTURE_FILTER_OPTIONS.map((o) => ({
                      value: o.value,
                      label: o.label,
                    }))}
                    onChange={(event) => {
                      setStatutPaiementFilter(event.target.value);
                      resetPage();
                    }}
                  />
                  <Button
                    type="button"
                    variant={echeance30jOnly ? "primary" : "secondary"}
                    size="sm"
                    aria-pressed={echeance30jOnly}
                    onClick={toggleEcheance30j}
                  >
                    <AlertTriangle className="size-4" />
                    Échéance ≤ 30 j
                    {echeance30jOnly ? " (actif)" : ""}
                  </Button>
                  {canCreate ? (
                    <Button onClick={goCreateProforma}>
                      <Plus className="size-4" />
                      Nouvelle proforma
                    </Button>
                  ) : null}
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
              emptyTitle={emptyByTab[tab]}
              emptyAction={
                canCreate && tab === "proformas" ? (
                  <Button size="sm" onClick={goCreateProforma}>
                    <Plus className="size-4" />
                    Nouvelle proforma
                  </Button>
                ) : undefined
              }
            />
          </TabPanel>
        </div>

        <ConfirmDialog
          open={!!toValidate}
          onClose={() => {
            if (statutBusy) return;
            setToValidate(null);
          }}
          loading={statutBusy}
          title="Valider la proforma"
          description={
            toValidate
              ? toValidate.client_id
                ? `Confirmer l’acceptation de « ${toValidate.numero} » ? Elle passera en facture validée et un abonnement sera créé.`
                : `Confirmer l’acceptation de « ${toValidate.numero} » ? Attention : le destinataire « ${toValidate.client_nom ?? "—"} » n’est pas encore un client SIS. Les informations de la proforma (nom, adresse, téléphone, email) seront utilisées pour créer le client, puis un abonnement sera créé.`
              : ""
          }
          confirmLabel={
            toValidate && !toValidate.client_id
              ? "Créer le client et valider"
              : "Valider"
          }
          confirmVariant="primary"
          onConfirm={() => {
            if (!toValidate) return;
            void handleStatutChange(toValidate, "valide");
          }}
        />

        <ConfirmDialog
          open={!!toReject}
          onClose={() => {
            if (statutBusy) return;
            setToReject(null);
          }}
          loading={statutBusy}
          title="Rejeter la proforma"
          description={
            toReject
              ? `Confirmer le rejet de « ${toReject.numero} » ? Elle sera classée comme rejetée.`
              : ""
          }
          confirmLabel="Rejeter"
          confirmVariant="danger"
          onConfirm={() => {
            if (!toReject) return;
            void handleStatutChange(toReject, "annule");
          }}
        />
      </div>
  );
}
