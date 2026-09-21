"use client";

import { useCallback, useMemo, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { type ColumnDef } from "@tanstack/react-table";
import { Eye, Pencil, Plus, Trash2, AlertTriangle } from "lucide-react";
import { useDebouncedValue } from "@/application/hooks/useDebouncedValue";
import { useClients } from "@/application/hooks/useClients";
import {
  useAbonnements,
  useCreateAbonnement,
  useDeleteAbonnement,
  useOffres,
  useSites,
  useUpdateAbonnement,
} from "@/application/hooks/useResources";
import {
  abonnementSchema,
  PERIODICITE_ABONNEMENT_FILTER_OPTIONS,
  STATUT_ABONNEMENT_FILTER_OPTIONS,
  type AbonnementFormValues,
} from "@/domain/schemas/abonnement";
import type { Abonnement } from "@/domain/types/entities";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { DataTable } from "@/presentation/components/tables/DataTable";
import { TableActions } from "@/presentation/components/tables/TableActions";
import { Alert } from "@/presentation/components/ui/Alert";
import { Badge, statusTone } from "@/presentation/components/ui/Badge";
import { Button } from "@/presentation/components/ui/Button";
import { ConfirmDialog } from "@/presentation/components/ui/ConfirmDialog";
import { RequiredFieldsLegend } from "@/presentation/components/ui/FieldLabel";
import { DatePicker } from "@/presentation/components/ui/DatePicker";
import { Input } from "@/presentation/components/ui/Input";
import { Modal } from "@/presentation/components/ui/Modal";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { Select } from "@/presentation/components/ui/Select";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { useToast } from "@/presentation/providers/ToastProvider";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { can } from "@/shared/lib/can";
import { formatDate, labelize } from "@/shared/lib/format";

/** Masquer temporairement la création manuelle (abonnements créés via proforma validée). */
const SHOW_NOUVEL_ABONNEMENT = false;

function splitPrestations(raw: string | null | undefined): string[] {
  if (!raw?.trim()) return [];
  return raw
    .split(/\s*\+\s*/)
    .map((s) => s.trim())
    .filter(Boolean);
}

function PrestationsCell({
  abonnementId,
  designation,
  offreLibelle,
}: {
  abonnementId: string;
  designation?: string | null;
  offreLibelle?: string | null;
}) {
  const items = splitPrestations(designation ?? offreLibelle ?? "");

  if (items.length === 0) return <span>—</span>;
  if (items.length === 1) {
    return (
      <Link
        href={`/abonnements/${abonnementId}`}
        className="line-clamp-2 max-w-[280px] font-medium text-ink hover:text-teal hover:underline"
      >
        {items[0]}
      </Link>
    );
  }

  const rest = items.length - 1;

  return (
    <div className="max-w-[280px]">
      <Link
        href={`/abonnements/${abonnementId}`}
        className="line-clamp-1 text-sm font-medium text-ink hover:text-teal hover:underline"
      >
        {items[0]}
      </Link>
      <Link
        href={`/abonnements/${abonnementId}`}
        className="mt-0.5 inline-block text-xs font-medium text-teal hover:underline"
      >
        +{rest} autre{rest > 1 ? "s" : ""} — voir le détail
      </Link>
    </div>
  );
}

const emptyDefaults: AbonnementFormValues = {
  client_id: "",
  offre_id: "",
  site_id: "",
  periodicite: "mensuel",
  date_debut: new Date().toISOString().slice(0, 10),
  date_fin: "",
  statut: "actif",
};

export default function AbonnementsPage() {
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [q, setQ] = useState("");
  const [statutFilter, setStatutFilter] = useState("");
  const [periodiciteFilter, setPeriodiciteFilter] = useState("");
  const [clientFilter, setClientFilter] = useState("");
  const [echeance30jOnly, setEcheance30jOnly] = useState(false);
  const search = useDebouncedValue(q);
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<Abonnement | null>(null);
  const [toDelete, setToDelete] = useState<Abonnement | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const { toast } = useToast();
  const { user } = useAuth();
  const canCreate = can(user, "abonnements.create");
  const canUpdate = can(user, "abonnements.update");
  const canDelete = can(user, "abonnements.delete");
  const router = useRouter();

  const { data, isLoading } = useAbonnements({
    page,
    per_page: perPage,
    q: search || undefined,
    statut: statutFilter || undefined,
    periodicite: periodiciteFilter || undefined,
    client_id: clientFilter || undefined,
    echeance_30j: echeance30jOnly || undefined,
  });
  const { data: clientsData } = useClients({ all: true });
  const { data: offresData } = useOffres({ all: true });
  const { data: sitesData } = useSites({ all: true });
  const createAbonnement = useCreateAbonnement();
  const updateAbonnement = useUpdateAbonnement();
  const deleteAbonnement = useDeleteAbonnement();

  const {
    register,
    handleSubmit,
    reset,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<AbonnementFormValues>({
    resolver: zodResolver(abonnementSchema),
    mode: "onBlur",
    reValidateMode: "onChange",
    defaultValues: emptyDefaults,
  });

  const clientId = watch("client_id");
  const busy =
    isSubmitting || createAbonnement.isPending || updateAbonnement.isPending;

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

  const resetPage = () => setPage(1);

  const toggleEcheance30j = () => {
    setEcheance30jOnly((v) => !v);
    resetPage();
  };

  const siteOptions = useMemo(() => {
    const sites = sitesData?.data ?? [];
    const filtered = clientId
      ? sites.filter((s) => s.client_id === clientId)
      : sites;
    return [
      { value: "", label: "— Aucun site —" },
      ...filtered.map((s) => ({ value: s.id, label: s.nom })),
    ];
  }, [sitesData, clientId]);

  const closeModal = () => {
    if (busy) return;
    setOpen(false);
    setEditing(null);
    setFormError(null);
    reset(emptyDefaults);
  };

  const openCreate = () => {
    if (!canCreate) {
      toast("Vous n’avez pas le droit de créer un abonnement.", "danger");
      return;
    }
    setEditing(null);
    reset(emptyDefaults);
    setFormError(null);
    setOpen(true);
  };

  const openEdit = useCallback(
    (row: Abonnement) => {
      if (!canUpdate) {
        toast("Vous n’avez pas le droit de modifier un abonnement.", "danger");
        return;
      }
      setEditing(row);
      reset({
        client_id: row.client_id,
        offre_id: row.offre_id ?? "",
        site_id: row.site_id ?? "",
        periodicite:
          (row.periodicite as AbonnementFormValues["periodicite"]) || "mensuel",
        date_debut: row.date_debut?.slice(0, 10) ?? "",
        date_fin: row.date_fin?.slice(0, 10) ?? "",
        statut: (row.statut as AbonnementFormValues["statut"]) || "actif",
      });
      setFormError(null);
      setOpen(true);
    },
    [canUpdate, reset, toast],
  );

  const columns = useMemo<ColumnDef<Abonnement>[]>(
    () => [
      {
        id: "client",
        header: "Client",
        cell: ({ row }) => {
          const client = row.original.client;
          if (!client) return "—";
          return (
            <Link
              href={`/clients/${client.id}`}
              className="font-medium text-teal hover:underline"
            >
              {client.raison_sociale}
            </Link>
          );
        },
      },
      {
        id: "offre",
        header: "Prestation",
        cell: ({ row }) => (
          <PrestationsCell
            abonnementId={row.original.id}
            designation={row.original.designation}
            offreLibelle={row.original.offre?.libelle}
          />
        ),
      },
      {
        accessorKey: "periodicite",
        header: "Périodicité",
        cell: ({ getValue }) => labelize(String(getValue() ?? "—")),
      },
      {
        id: "site",
        header: "Site",
        cell: ({ row }) => row.original.site?.nom ?? "—",
      },
      {
        accessorKey: "date_debut",
        header: "Début",
        cell: ({ getValue }) => formatDate(String(getValue())),
      },
      {
        accessorKey: "prochaine_facture_le",
        header: "Proch. facture",
        cell: ({ getValue }) =>
          formatDate(getValue() as string | null | undefined),
      },
      {
        accessorKey: "date_fin",
        header: "Fin",
        cell: ({ getValue }) =>
          formatDate(getValue() as string | null | undefined),
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
        id: "actions",
        header: "Actions",
        enableSorting: false,
        cell: ({ row }) => (
          <TableActions
            canEdit={false}
            canDelete={false}
            items={[
              {
                key: "view",
                label: "Voir le détail",
                icon: Eye,
                tone: "accent",
                onClick: () => router.push(`/abonnements/${row.original.id}`),
              },
              ...(canUpdate
                ? [
                    {
                      key: "edit",
                      label: "Modifier",
                      icon: Pencil,
                      tone: "primary" as const,
                      onClick: () => openEdit(row.original),
                    },
                  ]
                : []),
              ...(canDelete
                ? [
                    {
                      key: "delete",
                      label: "Supprimer",
                      icon: Trash2,
                      tone: "danger" as const,
                      onClick: () => setToDelete(row.original),
                    },
                  ]
                : []),
            ]}
          />
        ),
      },
    ],
    [canUpdate, canDelete, openEdit, router],
  );

  return (
    <PermissionGate permission="abonnements.view" title="Abonnements">
      <div>
        <PageHeader
          title="Abonnements"
          description="Contrats clients (après acceptation du devis). Un devis multi-offres = un seul abonnement combiné."
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
            placeholder: "Rechercher client, offre, site…",
          }}
          toolbar={
            <div className="flex flex-wrap items-center gap-2">
              <Select
                className="min-w-[11rem]"
                value={statutFilter}
                options={STATUT_ABONNEMENT_FILTER_OPTIONS.map((o) => ({
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
                value={periodiciteFilter}
                options={PERIODICITE_ABONNEMENT_FILTER_OPTIONS.map((o) => ({
                  value: o.value,
                  label: o.label,
                }))}
                onChange={(event) => {
                  setPeriodiciteFilter(event.target.value);
                  resetPage();
                }}
              />
              <Select
                className="min-w-[12rem]"
                value={clientFilter}
                options={clientFilterOptions}
                onChange={(event) => {
                  setClientFilter(event.target.value);
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
                Facture ≤ 30 j
                {echeance30jOnly ? " (actif)" : ""}
              </Button>
              {SHOW_NOUVEL_ABONNEMENT && canCreate ? (
                <Button onClick={openCreate}>
                  <Plus className="size-4" />
                  Nouvel abonnement
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
          emptyTitle="Aucun abonnement"
          emptyAction={
            SHOW_NOUVEL_ABONNEMENT && canCreate ? (
              <Button size="sm" onClick={openCreate}>
                <Plus className="size-4" />
                Nouvel abonnement
              </Button>
            ) : undefined
          }
        />

        <Modal
          open={open}
          onClose={closeModal}
          preventClose={busy}
          title={editing ? "Modifier l’abonnement" : "Nouvel abonnement"}
          description="L’offre (ou le combiné issu du devis) + la périodicité et les dates. Préférer la validation d’une proforma pour un devis multi-lignes."
          footer={
            <>
              <Button variant="secondary" onClick={closeModal} disabled={busy}>
                Annuler
              </Button>
              <Button type="submit" form="abonnement-form" loading={busy}>
                {busy ? "Enregistrement…" : "Enregistrer"}
              </Button>
            </>
          }
        >
          <form
            id="abonnement-form"
            className="space-y-3"
            onSubmit={handleSubmit(async (values) => {
              setFormError(null);
              try {
                const payload = {
                  client_id: values.client_id,
                  offre_id: values.offre_id,
                  site_id: values.site_id || null,
                  periodicite: values.periodicite,
                  date_debut: values.date_debut,
                  date_fin: values.date_fin || null,
                  statut: values.statut,
                };
                if (editing) {
                  await updateAbonnement.mutateAsync({
                    id: editing.id,
                    payload,
                  });
                  toast("Abonnement mis à jour.");
                } else {
                  await createAbonnement.mutateAsync(payload);
                  toast("Abonnement créé.");
                }
                closeModal();
              } catch (err) {
                setFormError(
                  getApiErrorMessage(err, "Échec de l’enregistrement."),
                );
              }
            })}
          >
            <RequiredFieldsLegend />
            {formError ? <Alert tone="danger">{formError}</Alert> : null}
            <Select
              label="Client"
              requiredMark
              options={(clientsData?.data ?? []).map((c) => ({
                value: c.id,
                label: c.raison_sociale,
              }))}
              placeholder="Choisir un client"
              error={errors.client_id?.message}
              {...register("client_id")}
            />
            <Select
              label="Offre catalogue"
              requiredMark
              options={(offresData?.data ?? []).map((o) => ({
                value: o.id,
                label: `${o.libelle} — ${o.prix_mensuel} FCFA/mois`,
              }))}
              placeholder="Choisir une offre"
              hint="Tarif de référence. Le montant de période = prix mensuel × 1, 3 ou 12."
              error={errors.offre_id?.message}
              {...register("offre_id")}
            />
            <Select
              label="Site"
              optionalMark
              options={siteOptions}
              error={errors.site_id?.message}
              {...register("site_id")}
            />
            <Select
              label="Périodicité de facturation"
              requiredMark
              options={[
                { value: "mensuel", label: "Mensuel" },
                { value: "trimestriel", label: "Trimestriel" },
                { value: "annuel", label: "Annuel" },
              ]}
              error={errors.periodicite?.message}
              {...register("periodicite")}
            />
            <div className="grid gap-3 sm:grid-cols-2">
              <DatePicker
                label="Date début"
                requiredMark
                hint="Début du contrat / service."
                error={errors.date_debut?.message}
                {...register("date_debut")}
              />
              <DatePicker
                label="Date fin (expiration)"
                optionalMark
                hint="Laisser vide = tacite reconduction."
                error={errors.date_fin?.message}
                {...register("date_fin")}
              />
            </div>
            <Select
              label="Statut"
              requiredMark
              options={[
                { value: "actif", label: "Actif" },
                { value: "suspendu", label: "Suspendu" },
                { value: "resilie", label: "Résilié" },
                { value: "expire", label: "Expiré" },
              ]}
              error={errors.statut?.message}
              {...register("statut")}
            />
          </form>
        </Modal>

        <ConfirmDialog
          open={!!toDelete}
          onClose={() => {
            if (deleteAbonnement.isPending) return;
            setToDelete(null);
          }}
          loading={deleteAbonnement.isPending}
          title="Supprimer l’abonnement"
          description="Confirmer la suppression de cet abonnement ?"
          onConfirm={async () => {
            if (!toDelete) return;
            try {
              await deleteAbonnement.mutateAsync(toDelete.id);
              toast("Abonnement supprimé.");
              setToDelete(null);
            } catch (err) {
              toast(
                getApiErrorMessage(err, "Échec de la suppression."),
                "danger",
              );
            }
          }}
        />
      </div>
    </PermissionGate>
  );
}
