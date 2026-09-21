"use client";

import { useCallback, useMemo, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { type ColumnDef } from "@tanstack/react-table";
import { Plus } from "lucide-react";
import { useDebouncedValue } from "@/application/hooks/useDebouncedValue";
import {
  useCreateUser,
  useDeleteUser,
  useUpdateUser,
  useUsers,
} from "@/application/hooks/useResources";
import {
  BASE_ROLE_OPTIONS,
  DEVELOPPEUR_ROLE_OPTION,
  userSchema,
  type UserFormValues,
} from "@/domain/schemas/user";
import type { User } from "@/domain/types/entities";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
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
import { PasswordInput } from "@/presentation/components/ui/PasswordInput";
import { Select } from "@/presentation/components/ui/Select";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { useToast } from "@/presentation/providers/ToastProvider";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { can, isDeveloppeurUser } from "@/shared/lib/can";
import { formatDateTime, labelize, labelRole } from "@/shared/lib/format";

function resolveBackofficeRole(
  role: string | undefined,
): UserFormValues["role"] {
  if (role === "superviseur" || role === "operation") return "operation";
  if (role === "developpeur") return "developpeur";
  if (role === "super-admin" || role === "rh" || role === "commercial") {
    return role;
  }
  return "operation";
}

const emptyDefaults: UserFormValues = {
  nom: "",
  prenom: "",
  email: "",
  password: "",
  role: "operation",
  statut: "actif",
};

export default function UsersPage() {
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [q, setQ] = useState("");
  const search = useDebouncedValue(q);
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<User | null>(null);
  const [toDelete, setToDelete] = useState<User | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const { toast } = useToast();
  const { user } = useAuth();
  const canCreate = can(user, "users.create");
  const canUpdate = can(user, "users.update");
  const canDelete = can(user, "users.delete");
  const roleOptions = useMemo(
    () =>
      isDeveloppeurUser(user)
        ? [...BASE_ROLE_OPTIONS, DEVELOPPEUR_ROLE_OPTION]
        : BASE_ROLE_OPTIONS,
    [user],
  );

  const { data, isLoading } = useUsers({
    page,
    per_page: perPage,
    q: search || undefined,
  });
  const createUser = useCreateUser();
  const updateUser = useUpdateUser();
  const deleteUser = useDeleteUser();

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<UserFormValues>({
    resolver: zodResolver(userSchema),
    mode: "onBlur",
    reValidateMode: "onChange",
    defaultValues: emptyDefaults,
  });

  const busy = isSubmitting || createUser.isPending || updateUser.isPending;

  const closeModal = () => {
    if (busy) return;
    setOpen(false);
    setEditing(null);
    setFormError(null);
    reset(emptyDefaults);
  };

  const openCreate = () => {
    if (!canCreate) {
      toast("Vous n’avez pas le droit de créer un utilisateur.", "danger");
      return;
    }
    setEditing(null);
    reset(emptyDefaults);
    setFormError(null);
    setOpen(true);
  };

  const openEdit = useCallback(
    (row: User) => {
      if (!canUpdate) {
        toast("Vous n’avez pas le droit de modifier un utilisateur.", "danger");
        return;
      }
      setEditing(row);
      reset({
        nom: row.nom,
        prenom: row.prenom,
        email: row.email ?? "",
        password: "",
        role: resolveBackofficeRole(row.roles?.[0]),
        statut: (row.statut as UserFormValues["statut"]) || "actif",
      });
      setFormError(null);
      setOpen(true);
    },
    [canUpdate, reset, toast],
  );

  const columns = useMemo<ColumnDef<User>[]>(
    () => [
      {
        id: "nom",
        header: "Utilisateur",
        cell: ({ row }) => (
          <span className="font-medium">
            {row.original.prenom} {row.original.nom}
          </span>
        ),
      },
      {
        accessorKey: "email",
        header: "Email",
        cell: ({ getValue }) => (
          <span className="font-mono text-xs">{String(getValue())}</span>
        ),
      },
      {
        id: "roles",
        header: "Rôles",
        cell: ({ row }) =>
          row.original.roles?.length
            ? row.original.roles.map((r) => labelRole(r)).join(", ")
            : "—",
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
        accessorKey: "last_login_at",
        header: "Dernière connexion",
        cell: ({ getValue }) =>
          formatDateTime(getValue() as string | null | undefined),
      },
      {
        id: "actions",
        header: "Actions",
        enableSorting: false,
        cell: ({ row }) => (
          <TableActions
            canEdit={canUpdate}
            canDelete={canDelete && row.original.id !== user?.id}
            onEdit={() => openEdit(row.original)}
            onDelete={() => setToDelete(row.original)}
          />
        ),
      },
    ],
    [canUpdate, canDelete, openEdit, user?.id],
  );

  return (
    <PermissionGate permission="users.view" title="Utilisateurs">
      <div>
        <PageHeader
          title="Utilisateurs"
          description="Comptes back-office et rôles."
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
            placeholder: "Rechercher nom, email…",
          }}
          toolbar={
            canCreate ? (
              <Button onClick={openCreate}>
                <Plus className="size-4" />
                Nouvel utilisateur
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
          emptyTitle="Aucun utilisateur"
          emptyAction={
            canCreate ? (
              <Button size="sm" onClick={openCreate}>
                <Plus className="size-4" />
                Nouvel utilisateur
              </Button>
            ) : undefined
          }
        />

        <Modal
          open={open}
          onClose={closeModal}
          preventClose={busy}
          title={editing ? "Modifier l’utilisateur" : "Nouvel utilisateur"}
          description="Comptes d’accès au back-office (hors agents mobile)."
          footer={
            <>
              <Button variant="secondary" onClick={closeModal} disabled={busy}>
                Annuler
              </Button>
              <Button type="submit" form="user-form" loading={busy}>
                {busy ? "Enregistrement…" : "Enregistrer"}
              </Button>
            </>
          }
        >
          <form
            id="user-form"
            className="space-y-3"
            onSubmit={handleSubmit(async (values) => {
              setFormError(null);
              if (!editing && (!values.password || values.password.length < 8)) {
                setFormError("Mot de passe requis (8 caractères min.).");
                return;
              }
              try {
                if (editing) {
                  const payload: Parameters<typeof updateUser.mutateAsync>[0]["payload"] =
                    {
                      nom: values.nom,
                      prenom: values.prenom,
                      email: values.email,
                      role: values.role,
                      statut: values.statut,
                    };
                  if (values.password) payload.password = values.password;
                  await updateUser.mutateAsync({ id: editing.id, payload });
                  toast("Utilisateur mis à jour.");
                } else {
                  await createUser.mutateAsync({
                    nom: values.nom,
                    prenom: values.prenom,
                    email: values.email,
                    password: values.password!,
                    role: values.role,
                    statut: values.statut,
                  });
                  toast("Utilisateur créé.");
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
            <div className="grid gap-3 sm:grid-cols-2">
              <Input
                label="Prénom"
                requiredMark
                error={errors.prenom?.message}
                {...register("prenom")}
              />
              <Input
                label="Nom"
                requiredMark
                error={errors.nom?.message}
                {...register("nom")}
              />
            </div>
            <Input
              label="Email"
              type="email"
              requiredMark
              error={errors.email?.message}
              {...register("email")}
            />
            <PasswordInput
              label={editing ? "Nouveau mot de passe" : "Mot de passe"}
              requiredMark={!editing}
              hint={editing ? "Laisser vide pour conserver l’actuel." : undefined}
              error={errors.password?.message}
              {...register("password")}
            />
            <Select
              label="Rôle"
              requiredMark
              options={roleOptions}
              error={errors.role?.message}
              {...register("role")}
            />
            <Select
              label="Statut"
              requiredMark
              options={[
                { value: "actif", label: "Actif" },
                { value: "inactif", label: "Inactif" },
                { value: "bloque", label: "Bloqué" },
              ]}
              error={errors.statut?.message}
              {...register("statut")}
            />
          </form>
        </Modal>

        <ConfirmDialog
          open={!!toDelete}
          onClose={() => {
            if (deleteUser.isPending) return;
            setToDelete(null);
          }}
          loading={deleteUser.isPending}
          title="Supprimer l’utilisateur"
          description={
            toDelete
              ? `Confirmer la suppression de « ${toDelete.prenom} ${toDelete.nom} » ?`
              : ""
          }
          onConfirm={async () => {
            if (!toDelete) return;
            try {
              await deleteUser.mutateAsync(toDelete.id);
              toast("Utilisateur supprimé.");
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
