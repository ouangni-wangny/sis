"use client";

import { useCallback, useMemo, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { type ColumnDef } from "@tanstack/react-table";
import { Plus } from "lucide-react";
import { useDebouncedValue } from "@/application/hooks/useDebouncedValue";
import {
  useCreateClient,
  useClients,
  useDeleteClient,
  useUpdateClient,
} from "@/application/hooks/useClients";
import {
  clientIdentityHint,
  clientIdentityLabel,
  clientIdentityPlaceholder,
  clientSchema,
  type ClientFormValues,
} from "@/domain/schemas/client";
import type { Client } from "@/domain/types/entities";
import { DataTable } from "@/presentation/components/tables/DataTable";
import { TableActions } from "@/presentation/components/tables/TableActions";
import { Alert } from "@/presentation/components/ui/Alert";
import { Badge, statusTone } from "@/presentation/components/ui/Badge";
import { Button } from "@/presentation/components/ui/Button";
import { ConfirmDialog } from "@/presentation/components/ui/ConfirmDialog";
import { RequiredFieldsLegend } from "@/presentation/components/ui/FieldLabel";
import { Input } from "@/presentation/components/ui/Input";
import { Modal } from "@/presentation/components/ui/Modal";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { Select } from "@/presentation/components/ui/Select";
import { Textarea } from "@/presentation/components/ui/Textarea";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { useToast } from "@/presentation/providers/ToastProvider";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { can } from "@/shared/lib/can";
import { labelize } from "@/shared/lib/format";

const emptyDefaults: ClientFormValues = {
  type: "entreprise",
  raison_sociale: "",
  personne_contact: "",
  telephone: "",
  email: "",
  adresse: "",
  statut: "actif",
};

export default function ClientsPage() {
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [q, setQ] = useState("");
  const search = useDebouncedValue(q);
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<Client | null>(null);
  const [toDelete, setToDelete] = useState<Client | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const { toast } = useToast();
  const { user } = useAuth();
  const canCreate = can(user, "clients.create");
  const canUpdate = can(user, "clients.update");
  const canDelete = can(user, "clients.delete");

  const { data, isLoading } = useClients({
    page,
    per_page: perPage,
    q: search || undefined,
  });
  const createClient = useCreateClient();
  const updateClient = useUpdateClient();
  const deleteClient = useDeleteClient();

  const {
    register,
    handleSubmit,
    reset,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<ClientFormValues>({
    resolver: zodResolver(clientSchema),
    mode: "onBlur",
    reValidateMode: "onChange",
    defaultValues: emptyDefaults,
  });

  const clientType = watch("type");
  const busy =
    isSubmitting || createClient.isPending || updateClient.isPending;

  const openEdit = useCallback(
    (client: Client) => {
      if (!canUpdate) {
        toast("Vous n’avez pas le droit de modifier un client.", "danger");
        return;
      }
      setEditing(client);
      setFormError(null);
      reset({
        type: client.type,
        raison_sociale: client.raison_sociale,
        personne_contact: client.personne_contact ?? "",
        telephone: client.telephone ?? "",
        email: client.email ?? "",
        adresse: client.adresse ?? "",
        statut: client.statut,
      });
      setOpen(true);
    },
    [canUpdate, reset, toast],
  );

  const columns = useMemo<ColumnDef<Client>[]>(
    () => [
      {
        accessorKey: "raison_sociale",
        header: "Nom / Raison sociale",
        cell: ({ row }) => (
          <div>
            <p className="font-medium">{row.original.raison_sociale}</p>
            {row.original.personne_contact ? (
              <p className="text-xs text-ink-faint">
                Contact : {row.original.personne_contact}
              </p>
            ) : null}
          </div>
        ),
      },
      {
        accessorKey: "type",
        header: "Type",
        cell: ({ getValue }) => (
          <span className="capitalize">{labelize(String(getValue()))}</span>
        ),
      },
      {
        accessorKey: "adresse",
        header: "Adresse",
        cell: ({ getValue }) => (
          <span className="line-clamp-2 max-w-[220px] text-ink-muted">
            {(getValue() as string | null) || "—"}
          </span>
        ),
      },
      {
        accessorKey: "telephone",
        header: "Téléphone",
        cell: ({ getValue }) => getValue() || "—",
      },
      {
        accessorKey: "email",
        header: "Email",
        cell: ({ getValue }) => getValue() || "—",
      },
      {
        accessorKey: "sites_count",
        header: "Sites",
        cell: ({ getValue }) => (
          <span className="font-mono tabular-nums">
            {Number(getValue() ?? 0)}
          </span>
        ),
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

  const closeModal = () => {
    if (busy) return;
    setOpen(false);
    setEditing(null);
    setFormError(null);
    reset(emptyDefaults);
    createClient.reset();
    updateClient.reset();
  };

  const openCreate = () => {
    if (!canCreate) {
      toast("Vous n’avez pas le droit de créer un client.", "danger");
      return;
    }
    setEditing(null);
    reset(emptyDefaults);
    setFormError(null);
    setOpen(true);
  };

  return (
    <PermissionGate permission="clients.view" title="Clients">
    <div>
      <PageHeader
        title="Clients"
        description="Référentiel clients entreprises et particuliers."
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
          placeholder: "Rechercher un client…",
        }}
        toolbar={
          canCreate ? (
            <Button onClick={openCreate}>
              <Plus className="size-4" />
              Nouveau client
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
        emptyTitle="Aucun client"
        emptyDescription="Créez votre premier client pour rattacher des sites."
        emptyAction={
          canCreate ? (
            <Button size="sm" onClick={openCreate}>
              <Plus className="size-4" />
              Nouveau client
            </Button>
          ) : undefined
        }
      />

      <Modal
        open={open}
        onClose={closeModal}
        preventClose={busy}
        size="xl"
        title={editing ? "Modifier le client" : "Nouveau client"}
        description="Choisissez d’abord le type : entreprise ou particulier."
        footer={
          <>
            <Button variant="secondary" onClick={closeModal} disabled={busy}>
              Annuler
            </Button>
            <Button type="submit" form="client-form" loading={busy}>
              {busy ? "Enregistrement…" : "Enregistrer"}
            </Button>
          </>
        }
      >
        <form
          id="client-form"
          className="space-y-3"
          noValidate
          onSubmit={handleSubmit(async (values) => {
            setFormError(null);
            const payload = {
              ...values,
              personne_contact: values.personne_contact || null,
              telephone: values.telephone || null,
              email: values.email || null,
              adresse: values.adresse || null,
            };
            try {
              if (editing) {
                await updateClient.mutateAsync({
                  id: editing.id,
                  payload,
                });
                toast("Client mis à jour avec succès.");
              } else {
                await createClient.mutateAsync(payload);
                toast("Client créé avec succès.");
              }
              reset(emptyDefaults);
              setEditing(null);
              setOpen(false);
            } catch (err) {
              const message = getApiErrorMessage(
                err,
                editing
                  ? "Échec de la modification du client."
                  : "Échec de la création du client.",
              );
              setFormError(message);
              toast(message, "danger");
            }
          })}
        >
          <RequiredFieldsLegend className="mb-1" />

          <Select
            label="Type de client"
            requiredMark
            options={[
              { value: "entreprise", label: "Entreprise" },
              { value: "particulier", label: "Particulier" },
            ]}
            hint="Le libellé du nom change selon le type choisi."
            error={errors.type?.message}
            {...register("type")}
          />

          <div className="grid gap-3 sm:grid-cols-2">
            <Input
              label={clientIdentityLabel(clientType)}
              requiredMark
              placeholder={clientIdentityPlaceholder(clientType)}
              hint={clientIdentityHint(clientType)}
              error={errors.raison_sociale?.message}
              {...register("raison_sociale")}
            />
            <Input
              label="Personne contact"
              optionalMark={clientType === "particulier"}
              requiredMark={clientType === "entreprise"}
              placeholder={
                clientType === "entreprise"
                  ? "ex. Responsable sécurité"
                  : "ex. Personne joignable (si différente)"
              }
              hint={
                clientType === "entreprise"
                  ? "Interlocuteur opérationnel côté client."
                  : "Laissez vide si c’est la même personne."
              }
              error={errors.personne_contact?.message}
              {...register("personne_contact")}
            />
          </div>

          <Textarea
            label="Adresse"
            optionalMark
            rows={2}
            placeholder="Quartier, commune, ville…"
            hint="Adresse postale ou de localisation principale."
            error={errors.adresse?.message}
            {...register("adresse")}
          />

          <div className="grid gap-3 sm:grid-cols-2">
            <Input
              label="Téléphone"
              optionalMark
              type="tel"
              inputMode="tel"
              placeholder="07 XX XX XX XX"
              error={errors.telephone?.message}
              {...register("telephone")}
            />
            <Input
              label="Email"
              optionalMark
              type="email"
              autoComplete="email"
              placeholder="contact@entreprise.ci"
              error={errors.email?.message}
              {...register("email")}
            />
          </div>

          <Select
            label="Statut"
            requiredMark
            options={[
              { value: "actif", label: "Actif" },
              { value: "suspendu", label: "Suspendu" },
              { value: "resilie", label: "Résilié" },
            ]}
            error={errors.statut?.message}
            {...register("statut")}
          />

          {formError ? <Alert tone="danger">{formError}</Alert> : null}
        </form>
      </Modal>

      <ConfirmDialog
        open={!!toDelete}
        onClose={() => {
          if (deleteClient.isPending) return;
          setToDelete(null);
        }}
        loading={deleteClient.isPending}
        title="Supprimer le client"
        description={
          toDelete
            ? `Supprimer « ${toDelete.raison_sociale} » ? Ses sites, postes, plannings, contrôles, abonnements et factures seront aussi archivés (soft delete).`
            : ""
        }
        onConfirm={async () => {
          if (!toDelete) return;
          try {
            await deleteClient.mutateAsync(toDelete.id);
            toast("Client supprimé.");
            setToDelete(null);
          } catch (err) {
            toast(
              getApiErrorMessage(err, "Échec de la suppression du client."),
              "danger",
            );
          }
        }}
      />
    </div>
    </PermissionGate>
  );
}
