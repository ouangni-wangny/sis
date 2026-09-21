"use client";

import { useCallback, useMemo, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { type ColumnDef } from "@tanstack/react-table";
import { Plus } from "lucide-react";
import { useDebouncedValue } from "@/application/hooks/useDebouncedValue";
import {
  useCreateOffre,
  useDeleteOffre,
  useOffres,
  useUpdateOffre,
} from "@/application/hooks/useResources";
import { offreSchema, type OffreFormValues } from "@/domain/schemas/offre";
import type { Offre } from "@/domain/types/entities";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { DataTable } from "@/presentation/components/tables/DataTable";
import { TableActions } from "@/presentation/components/tables/TableActions";
import { Alert } from "@/presentation/components/ui/Alert";
import { Badge } from "@/presentation/components/ui/Badge";
import { Button } from "@/presentation/components/ui/Button";
import { ConfirmDialog } from "@/presentation/components/ui/ConfirmDialog";
import { RequiredFieldsLegend } from "@/presentation/components/ui/FieldLabel";
import { Input } from "@/presentation/components/ui/Input";
import { Modal } from "@/presentation/components/ui/Modal";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { Textarea } from "@/presentation/components/ui/Textarea";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { useToast } from "@/presentation/providers/ToastProvider";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { can } from "@/shared/lib/can";
import { formatFcfa } from "@/shared/lib/format";

const emptyDefaults: OffreFormValues = {
  libelle: "",
  description: "",
  prix_mensuel: "",
  actif: true,
};

export default function OffresPage() {
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [q, setQ] = useState("");
  const search = useDebouncedValue(q);
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<Offre | null>(null);
  const [toDelete, setToDelete] = useState<Offre | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const { toast } = useToast();
  const { user } = useAuth();
  const canCreate = can(user, "offres.create");
  const canUpdate = can(user, "offres.update");
  const canDelete = can(user, "offres.delete");

  const { data, isLoading } = useOffres({
    page,
    per_page: perPage,
    q: search || undefined,
  });
  const createOffre = useCreateOffre();
  const updateOffre = useUpdateOffre();
  const deleteOffre = useDeleteOffre();

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<OffreFormValues>({
    resolver: zodResolver(offreSchema),
    mode: "onBlur",
    reValidateMode: "onChange",
    defaultValues: emptyDefaults,
  });

  const busy = isSubmitting || createOffre.isPending || updateOffre.isPending;

  const closeModal = () => {
    if (busy) return;
    setOpen(false);
    setEditing(null);
    setFormError(null);
    reset(emptyDefaults);
  };

  const openCreate = () => {
    if (!canCreate) {
      toast("Vous n’avez pas le droit de créer une offre.", "danger");
      return;
    }
    setEditing(null);
    reset(emptyDefaults);
    setFormError(null);
    setOpen(true);
  };

  const openEdit = useCallback(
    (row: Offre) => {
      if (!canUpdate) {
        toast("Vous n’avez pas le droit de modifier une offre.", "danger");
        return;
      }
      setEditing(row);
      reset({
        libelle: row.libelle,
        description: row.description ?? "",
        prix_mensuel: row.prix_mensuel,
        actif: row.actif,
      });
      setFormError(null);
      setOpen(true);
    },
    [canUpdate, reset, toast],
  );

  const columns = useMemo<ColumnDef<Offre>[]>(
    () => [
      {
        accessorKey: "libelle",
        header: "Offre",
        cell: ({ row }) => (
          <span className="font-medium">{row.original.libelle}</span>
        ),
      },
      {
        accessorKey: "description",
        header: "Description",
        cell: ({ getValue }) => (
          <span className="line-clamp-2 max-w-md text-ink-muted">
            {(getValue() as string | null) || "—"}
          </span>
        ),
      },
      {
        accessorKey: "prix_mensuel",
        header: "Prix mensuel",
        cell: ({ getValue }) => (
          <span className="font-mono text-xs tabular-nums">
            {formatFcfa(getValue() as string | number)}
          </span>
        ),
      },
      {
        accessorKey: "actif",
        header: "Statut",
        cell: ({ getValue }) =>
          getValue() ? (
            <Badge tone="success">Actif</Badge>
          ) : (
            <Badge tone="neutral">Inactif</Badge>
          ),
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
    <PermissionGate permission="offres.view" title="Offres">
      <div>
        <PageHeader
          title="Offres"
          description="Catalogue de tarifs (prix mensuel de référence). Les abonnements et proformas s’appuient sur ces offres."
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
            placeholder: "Rechercher une offre…",
          }}
          toolbar={
            canCreate ? (
              <Button onClick={openCreate}>
                <Plus className="size-4" />
                Nouvelle offre
              </Button>
            ) : null
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
          emptyTitle="Aucune offre"
          emptyAction={
            canCreate ? (
              <Button size="sm" onClick={openCreate}>
                <Plus className="size-4" />
                Nouvelle offre
              </Button>
            ) : undefined
          }
        />

        <Modal
          open={open}
          onClose={closeModal}
          preventClose={busy}
          title={editing ? "Modifier l’offre" : "Nouvelle offre"}
          description="Catalogue de prestations proposées aux clients."
          footer={
            <>
              <Button variant="secondary" onClick={closeModal} disabled={busy}>
                Annuler
              </Button>
              <Button type="submit" form="offre-form" loading={busy}>
                {busy ? "Enregistrement…" : "Enregistrer"}
              </Button>
            </>
          }
        >
          <form
            id="offre-form"
            className="space-y-3"
            onSubmit={handleSubmit(async (values) => {
              setFormError(null);
              try {
                const payload = {
                  libelle: values.libelle,
                  description: values.description || null,
                  prix_mensuel: values.prix_mensuel,
                  actif: values.actif,
                };
                if (editing) {
                  await updateOffre.mutateAsync({ id: editing.id, payload });
                  toast("Offre mise à jour.");
                } else {
                  await createOffre.mutateAsync(payload);
                  toast("Offre créée.");
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
            <Input
              label="Libellé"
              requiredMark
              placeholder="ex. Gardiennage de nuit"
              error={errors.libelle?.message}
              {...register("libelle")}
            />
            <Textarea
              label="Description"
              optionalMark
              rows={3}
              error={errors.description?.message}
              {...register("description")}
            />
            <Input
              label="Prix mensuel (FCFA)"
              requiredMark
              type="number"
              min={0}
              inputMode="numeric"
              error={errors.prix_mensuel?.message}
              {...register("prix_mensuel")}
            />
            <label className="flex items-center gap-2 text-sm text-ink">
              <input
                type="checkbox"
                className="size-4 rounded border-border text-teal focus:ring-teal/40"
                {...register("actif")}
              />
              Offre active
            </label>
          </form>
        </Modal>

        <ConfirmDialog
          open={!!toDelete}
          onClose={() => {
            if (deleteOffre.isPending) return;
            setToDelete(null);
          }}
          loading={deleteOffre.isPending}
          title="Supprimer l’offre"
          description={
            toDelete
              ? `Confirmer la suppression de « ${toDelete.libelle} » ?`
              : ""
          }
          onConfirm={async () => {
            if (!toDelete) return;
            try {
              await deleteOffre.mutateAsync(toDelete.id);
              toast("Offre supprimée.");
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
