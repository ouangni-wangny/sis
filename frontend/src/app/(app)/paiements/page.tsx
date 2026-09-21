"use client";

import { Suspense, useCallback, useEffect, useMemo, useState } from "react";
import { useSearchParams } from "next/navigation";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { type ColumnDef } from "@tanstack/react-table";
import { Plus, CalendarRange } from "lucide-react";
import { useDebouncedValue } from "@/application/hooks/useDebouncedValue";
import { useClients } from "@/application/hooks/useClients";
import {
  useCreatePaiement,
  useDeletePaiement,
  useFactures,
  usePaiements,
  useUpdatePaiement,
} from "@/application/hooks/useResources";
import {
  paiementSchema,
  MODE_PAIEMENT_FILTER_OPTIONS,
  MODE_PAIEMENT_FORM_OPTIONS,
  type PaiementFormInput,
  type PaiementFormValues,
} from "@/domain/schemas/paiement";
import type { Paiement } from "@/domain/types/entities";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { DataTable } from "@/presentation/components/tables/DataTable";
import { TableActions } from "@/presentation/components/tables/TableActions";
import { Alert } from "@/presentation/components/ui/Alert";
import { Badge, statusTone } from "@/presentation/components/ui/Badge";
import { Button } from "@/presentation/components/ui/Button";
import { ConfirmDialog } from "@/presentation/components/ui/ConfirmDialog";
import { DatePicker } from "@/presentation/components/ui/DatePicker";
import { RequiredFieldsLegend } from "@/presentation/components/ui/FieldLabel";
import { Input } from "@/presentation/components/ui/Input";
import { Modal } from "@/presentation/components/ui/Modal";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { Select } from "@/presentation/components/ui/Select";
import { Textarea } from "@/presentation/components/ui/Textarea";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { useToast } from "@/presentation/providers/ToastProvider";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { can } from "@/shared/lib/can";
import { formatDate, formatFcfa, labelize } from "@/shared/lib/format";

const modeOptions = MODE_PAIEMENT_FORM_OPTIONS.map((o) => ({
  value: o.value,
  label: o.label,
}));

const emptyDefaults: PaiementFormValues = {
  facture_id: "",
  montant: 0,
  date_paiement: new Date().toISOString().slice(0, 10),
  mode: "virement",
  reference: "",
  notes: "",
};

export default function PaiementsPage() {
  return (
    <Suspense
      fallback={
        <div className="p-6 text-sm text-ink-muted">Chargement…</div>
      }
    >
      <PaiementsPageContent />
    </Suspense>
  );
}

function PaiementsPageContent() {
  const searchParams = useSearchParams();
  const prefillFactureId = searchParams.get("facture_id") ?? "";

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [q, setQ] = useState("");
  const [clientFilter, setClientFilter] = useState("");
  const [modeFilter, setModeFilter] = useState("");
  const [recent30jOnly, setRecent30jOnly] = useState(false);
  const search = useDebouncedValue(q);
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<Paiement | null>(null);
  const [toDelete, setToDelete] = useState<Paiement | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const { toast } = useToast();
  const { user } = useAuth();
  const canCreate = can(user, "paiements.create");
  const canUpdate = can(user, "paiements.update");
  const canDelete = can(user, "paiements.delete");

  const resetPage = () => setPage(1);

  const toggleRecent30j = () => {
    setRecent30jOnly((v) => !v);
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

  const { data, isLoading } = usePaiements({
    page,
    per_page: perPage,
    q: search || undefined,
    client_id: clientFilter || undefined,
    mode: modeFilter || undefined,
    recent_30j: recent30jOnly || undefined,
    facture_id: prefillFactureId || undefined,
  });
  const { data: facturesData } = useFactures({ all: true });
  const createPaiement = useCreatePaiement();
  const updatePaiement = useUpdatePaiement();
  const deletePaiement = useDeletePaiement();

  const {
    register,
    handleSubmit,
    reset,
    watch,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<PaiementFormInput, unknown, PaiementFormValues>({
    resolver: zodResolver(paiementSchema),
    mode: "onBlur",
    reValidateMode: "onChange",
    defaultValues: emptyDefaults,
  });

  const factureId = watch("facture_id");
  const busy =
    isSubmitting || createPaiement.isPending || updatePaiement.isPending;

  const factureOptions = useMemo(() => {
    const rows = (facturesData?.data ?? []).filter((f) => f.statut !== "annule");
    return rows.map((f) => {
      const solde =
        f.solde != null
          ? Number(f.solde)
          : Math.max(0, Number(f.montant_ttc) - Number(f.montant_paye ?? 0));
      const periode =
        f.periode_debut && f.periode_fin
          ? ` · ${formatDate(f.periode_debut)} → ${formatDate(f.periode_fin)}`
          : "";
      return {
        value: f.id,
        label: `${f.numero} · ${f.client?.raison_sociale ?? "Client"}${periode} · solde ${formatFcfa(solde)}`,
      };
    });
  }, [facturesData]);

  const selectedFacture = useMemo(
    () => (facturesData?.data ?? []).find((f) => f.id === factureId),
    [facturesData, factureId],
  );

  const closeModal = () => {
    if (busy) return;
    setOpen(false);
    setEditing(null);
    setFormError(null);
    reset(emptyDefaults);
  };

  const openCreate = useCallback(
    (facturePrefill?: string) => {
      if (!canCreate) {
        toast("Vous n’avez pas le droit d’enregistrer un paiement.", "danger");
        return;
      }
      setEditing(null);
      const fid = facturePrefill || "";
      const facture = (facturesData?.data ?? []).find((f) => f.id === fid);
      const solde = facture
        ? facture.solde != null
          ? Number(facture.solde)
          : Math.max(
              0,
              Number(facture.montant_ttc) - Number(facture.montant_paye ?? 0),
            )
        : 0;
      reset({
        ...emptyDefaults,
        facture_id: fid,
        montant: solde > 0 ? solde : 0,
      });
      setFormError(null);
      setOpen(true);
    },
    [canCreate, facturesData, reset, toast],
  );

  useEffect(() => {
    if (!prefillFactureId || !canCreate) return;
    openCreate(prefillFactureId);
  }, [prefillFactureId, canCreate, openCreate]);

  useEffect(() => {
    if (editing || !selectedFacture || !open) return;
    const solde =
      selectedFacture.solde != null
        ? Number(selectedFacture.solde)
        : Math.max(
            0,
            Number(selectedFacture.montant_ttc) -
              Number(selectedFacture.montant_paye ?? 0),
          );
    if (solde > 0) {
      setValue("montant", solde);
    }
  }, [factureId, selectedFacture, editing, open, setValue]);

  const openEdit = useCallback(
    (row: Paiement) => {
      if (!canUpdate) {
        toast("Vous n’avez pas le droit de modifier un paiement.", "danger");
        return;
      }
      setEditing(row);
      reset({
        facture_id: row.facture_id,
        montant: Number(row.montant),
        date_paiement: row.date_paiement?.slice(0, 10) ?? "",
        mode: row.mode as PaiementFormValues["mode"],
        reference: row.reference ?? "",
        notes: row.notes ?? "",
      });
      setFormError(null);
      setOpen(true);
    },
    [canUpdate, reset, toast],
  );

  const columns = useMemo<ColumnDef<Paiement>[]>(
    () => [
      {
        id: "client",
        header: "Client",
        cell: ({ row }) =>
          row.original.facture?.client?.raison_sociale ?? "—",
      },
      {
        id: "facture",
        header: "Facture",
        cell: ({ row }) => (
          <span className="font-medium tabular-nums">
            {row.original.facture?.numero ?? "—"}
          </span>
        ),
      },
      {
        accessorKey: "date_paiement",
        header: "Date",
        cell: ({ getValue }) => formatDate(String(getValue())),
      },
      {
        accessorKey: "mode",
        header: "Mode",
        cell: ({ getValue }) => labelize(String(getValue())),
      },
      {
        accessorKey: "montant",
        header: "Montant",
        cell: ({ getValue }) => (
          <span className="font-mono text-xs font-medium tabular-nums">
            {formatFcfa(getValue() as string | number)}
          </span>
        ),
      },
      {
        accessorKey: "reference",
        header: "Référence",
        cell: ({ getValue }) => (getValue() as string | null) || "—",
      },
      {
        id: "actions",
        header: "Actions",
        enableSorting: false,
        cell: ({ row }) => (
          <TableActions
            canEdit={canUpdate}
            canDelete={canDelete}
            onEdit={() => openEdit(row.original)}
            onDelete={() => setToDelete(row.original)}
          />
        ),
      },
    ],
    [canUpdate, canDelete, openEdit],
  );

  return (
    <PermissionGate permission="paiements.view" title="Paiements">
      <div>
        <PageHeader
          title="Paiements"
          description="Encaissements clients liés aux factures (acomptes, partiels ou solde)."
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
            placeholder: "Rechercher facture, client, référence…",
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
                value={modeFilter}
                options={MODE_PAIEMENT_FILTER_OPTIONS.map((o) => ({
                  value: o.value,
                  label: o.label,
                }))}
                onChange={(event) => {
                  setModeFilter(event.target.value);
                  resetPage();
                }}
              />
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
              {canCreate ? (
                <Button onClick={() => openCreate()}>
                  <Plus className="size-4" />
                  Enregistrer un paiement
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
          emptyTitle="Aucun paiement"
          emptyAction={
            canCreate ? (
              <Button onClick={() => openCreate()}>
                <Plus className="size-4" />
                Enregistrer un paiement
              </Button>
            ) : undefined
          }
        />

        <Modal
          open={open}
          onClose={closeModal}
          preventClose={busy}
          title={editing ? "Modifier le paiement" : "Nouveau paiement"}
          description="Le montant ne peut pas dépasser le solde restant de la facture."
          footer={
            <>
              <Button
                type="button"
                variant="secondary"
                disabled={busy}
                onClick={closeModal}
              >
                Annuler
              </Button>
              <Button type="submit" form="paiement-form" loading={busy}>
                Enregistrer
              </Button>
            </>
          }
        >
          <form
            id="paiement-form"
            className="space-y-4"
            noValidate
            onSubmit={handleSubmit(async (values) => {
              setFormError(null);
              try {
                const payload = {
                  facture_id: values.facture_id,
                  montant: Number(values.montant),
                  date_paiement: values.date_paiement,
                  mode: values.mode,
                  reference: values.reference || null,
                  notes: values.notes || null,
                };
                if (editing) {
                  await updatePaiement.mutateAsync({
                    id: editing.id,
                    payload,
                  });
                  toast("Paiement mis à jour.");
                } else {
                  await createPaiement.mutateAsync(payload);
                  toast("Paiement enregistré.");
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
              label="Facture"
              requiredMark
              options={factureOptions}
              placeholder="Choisir une facture"
              error={errors.facture_id?.message}
              disabled={Boolean(editing)}
              {...register("facture_id")}
            />
            {selectedFacture ? (
              <div className="rounded-lg border border-border bg-paper/50 px-3 py-2 text-xs text-ink-muted">
                {selectedFacture.periode_debut &&
                selectedFacture.periode_fin ? (
                  <>
                    Période {formatDate(selectedFacture.periode_debut)} →{" "}
                    {formatDate(selectedFacture.periode_fin)}
                    {" · "}
                  </>
                ) : null}
                TTC {formatFcfa(selectedFacture.montant_ttc)}
                {" · "}
                Payé {formatFcfa(selectedFacture.montant_paye ?? 0)}
                {" · "}
                Solde{" "}
                <span className="font-semibold text-ink">
                  {formatFcfa(selectedFacture.solde ?? 0)}
                </span>
                {selectedFacture.statut_paiement ? (
                  <>
                    {" · "}
                    <Badge
                      tone={statusTone(selectedFacture.statut_paiement)}
                      className="align-middle"
                    >
                      {labelize(selectedFacture.statut_paiement)}
                    </Badge>
                  </>
                ) : null}
              </div>
            ) : null}
            <div className="grid gap-4 sm:grid-cols-2">
              <Input
                label="Montant"
                requiredMark
                type="number"
                step="1"
                min={0}
                error={errors.montant?.message}
                {...register("montant", { valueAsNumber: true })}
              />
              <DatePicker
                label="Date de paiement"
                requiredMark
                error={errors.date_paiement?.message}
                {...register("date_paiement")}
              />
              <Select
                label="Mode"
                requiredMark
                options={modeOptions}
                error={errors.mode?.message}
                {...register("mode")}
              />
              <Input
                label="Référence"
                optionalMark
                placeholder="N° chèque, réf. virement…"
                error={errors.reference?.message}
                {...register("reference")}
              />
            </div>
            <Textarea
              label="Notes"
              optionalMark
              rows={2}
              error={errors.notes?.message}
              {...register("notes")}
            />
          </form>
        </Modal>

        <ConfirmDialog
          open={Boolean(toDelete)}
          onClose={() => setToDelete(null)}
          title="Supprimer le paiement"
          description="Confirmer la suppression de cet encaissement ?"
          confirmLabel="Supprimer"
          loading={deletePaiement.isPending}
          onConfirm={async () => {
            if (!toDelete) return;
            try {
              await deletePaiement.mutateAsync(toDelete.id);
              toast("Paiement supprimé.");
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
