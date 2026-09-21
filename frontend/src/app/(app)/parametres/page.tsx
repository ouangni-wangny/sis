"use client";

import { useCallback, useMemo, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { type ColumnDef } from "@tanstack/react-table";
import { Plus } from "lucide-react";
import { useDebouncedValue } from "@/application/hooks/useDebouncedValue";
import {
  useCreateGrade,
  useCreateVille,
  useDeleteGrade,
  useDeleteVille,
  useGrades,
  useUpdateGrade,
  useUpdateVille,
  useVilles,
} from "@/application/hooks/useResources";
import { gradeSchema, type GradeFormValues } from "@/domain/schemas/grade";
import { villeSchema, type VilleFormValues } from "@/domain/schemas/ville";
import type { Grade, Ville } from "@/domain/types/entities";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { DataTable } from "@/presentation/components/tables/DataTable";
import { TableActions } from "@/presentation/components/tables/TableActions";
import { Alert } from "@/presentation/components/ui/Alert";
import { Button } from "@/presentation/components/ui/Button";
import { ConfirmDialog } from "@/presentation/components/ui/ConfirmDialog";
import { RequiredFieldsLegend } from "@/presentation/components/ui/FieldLabel";
import { Input } from "@/presentation/components/ui/Input";
import { Modal } from "@/presentation/components/ui/Modal";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { Select } from "@/presentation/components/ui/Select";
import { TabPanel, Tabs } from "@/presentation/components/ui/Tabs";
import { Textarea } from "@/presentation/components/ui/Textarea";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { useToast } from "@/presentation/providers/ToastProvider";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { can } from "@/shared/lib/can";
import { labelTypeAgent } from "@/shared/lib/format";

const emptyGradeDefaults: GradeFormValues = {
  libelle: "",
  type_agent: "agent",
  description: "",
};

const emptyVilleDefaults: VilleFormValues = {
  libelle: "",
};

function GradesTab({ canManage }: { canManage: boolean }) {
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [q, setQ] = useState("");
  const search = useDebouncedValue(q);
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<Grade | null>(null);
  const [toDelete, setToDelete] = useState<Grade | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const { toast } = useToast();

  const { data, isLoading } = useGrades({
    page,
    per_page: perPage,
    q: search || undefined,
  });
  const createGrade = useCreateGrade();
  const updateGrade = useUpdateGrade();
  const deleteGrade = useDeleteGrade();

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<GradeFormValues>({
    resolver: zodResolver(gradeSchema),
    mode: "onBlur",
    reValidateMode: "onChange",
    defaultValues: emptyGradeDefaults,
  });

  const busy = isSubmitting || createGrade.isPending || updateGrade.isPending;

  const closeModal = () => {
    if (busy) return;
    setOpen(false);
    setEditing(null);
    setFormError(null);
    reset(emptyGradeDefaults);
  };

  const openCreate = () => {
    if (!canManage) {
      toast("Vous n’avez pas le droit de créer un grade.", "danger");
      return;
    }
    setEditing(null);
    reset(emptyGradeDefaults);
    setFormError(null);
    setOpen(true);
  };

  const openEdit = useCallback(
    (grade: Grade) => {
      if (!canManage) {
        toast("Vous n’avez pas le droit de modifier un grade.", "danger");
        return;
      }
      setEditing(grade);
      reset({
        libelle: grade.libelle,
        type_agent: grade.type_agent,
        description: grade.description ?? "",
      });
      setFormError(null);
      setOpen(true);
    },
    [canManage, reset, toast],
  );

  const columns = useMemo<ColumnDef<Grade>[]>(
    () => [
      {
        accessorKey: "libelle",
        header: "Grade",
        cell: ({ row }) => (
          <span className="font-medium">{row.original.libelle}</span>
        ),
      },
      {
        accessorKey: "type_agent",
        header: "Type",
        cell: ({ getValue }) => labelTypeAgent(String(getValue())),
      },
      {
        accessorKey: "description",
        header: "Description",
        cell: ({ getValue }) =>
          (getValue() as string | null | undefined) || "—",
      },
      {
        id: "actions",
        header: "Actions",
        enableSorting: false,
        cell: ({ row }) => (
          <TableActions
            canEdit={canManage}
            canDelete={canManage}
            onEdit={() => openEdit(row.original)}
            onDelete={() => setToDelete(row.original)}
          />
        ),
      },
    ],
    [canManage, openEdit],
  );

  return (
    <>
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
          placeholder: "Rechercher un grade…",
        }}
        toolbar={
          canManage ? (
            <Button onClick={openCreate}>
              <Plus className="size-4" />
              Nouveau grade
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
        emptyTitle="Aucun grade"
        emptyAction={
          canManage ? (
            <Button size="sm" onClick={openCreate}>
              <Plus className="size-4" />
              Nouveau grade
            </Button>
          ) : undefined
        }
      />

      <Modal
        open={open}
        onClose={closeModal}
        preventClose={busy}
        title={editing ? "Modifier le grade" : "Nouveau grade"}
        description="Le type détermine Agent posté, Contrôleur ou Administration."
        footer={
          <>
            <Button variant="secondary" onClick={closeModal} disabled={busy}>
              Annuler
            </Button>
            <Button type="submit" form="grade-form" loading={busy}>
              {busy ? "Enregistrement…" : "Enregistrer"}
            </Button>
          </>
        }
      >
        <form
          id="grade-form"
          className="space-y-3"
          onSubmit={handleSubmit(async (values) => {
            setFormError(null);
            try {
              const payload = {
                libelle: values.libelle,
                type_agent: values.type_agent,
                description: values.description || null,
              };
              if (editing) {
                await updateGrade.mutateAsync({
                  id: editing.id,
                  payload,
                });
                toast("Grade mis à jour.");
              } else {
                await createGrade.mutateAsync(payload);
                toast("Grade créé.");
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
            error={errors.libelle?.message}
            {...register("libelle")}
          />
          <Select
            label="Type"
            requiredMark
            error={errors.type_agent?.message}
            options={[
              { value: "agent", label: "Agent de sécurité" },
              { value: "controleur", label: "Contrôleur" },
              { value: "administration", label: "Administration" },
            ]}
            {...register("type_agent")}
          />
          <Textarea
            label="Description"
            optionalMark
            error={errors.description?.message}
            {...register("description")}
          />
        </form>
      </Modal>

      <ConfirmDialog
        open={!!toDelete}
        onClose={() => {
          if (deleteGrade.isPending) return;
          setToDelete(null);
        }}
        loading={deleteGrade.isPending}
        title="Supprimer le grade"
        description={
          toDelete
            ? `Confirmer la suppression de « ${toDelete.libelle} » ?`
            : ""
        }
        onConfirm={async () => {
          if (!toDelete) return;
          try {
            await deleteGrade.mutateAsync(toDelete.id);
            toast("Grade supprimé.");
            setToDelete(null);
          } catch (err) {
            toast(
              getApiErrorMessage(err, "Échec de la suppression."),
              "danger",
            );
          }
        }}
      />
    </>
  );
}

function VillesTab({ canManage }: { canManage: boolean }) {
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [q, setQ] = useState("");
  const search = useDebouncedValue(q);
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<Ville | null>(null);
  const [toDelete, setToDelete] = useState<Ville | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const { toast } = useToast();

  const { data, isLoading } = useVilles({
    page,
    per_page: perPage,
    q: search || undefined,
  });
  const createVille = useCreateVille();
  const updateVille = useUpdateVille();
  const deleteVille = useDeleteVille();

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<VilleFormValues>({
    resolver: zodResolver(villeSchema),
    mode: "onBlur",
    reValidateMode: "onChange",
    defaultValues: emptyVilleDefaults,
  });

  const busy = isSubmitting || createVille.isPending || updateVille.isPending;

  const closeModal = () => {
    if (busy) return;
    setOpen(false);
    setEditing(null);
    setFormError(null);
    reset(emptyVilleDefaults);
  };

  const openCreate = () => {
    if (!canManage) {
      toast("Vous n’avez pas le droit de créer une ville.", "danger");
      return;
    }
    setEditing(null);
    reset(emptyVilleDefaults);
    setFormError(null);
    setOpen(true);
  };

  const openEdit = useCallback(
    (ville: Ville) => {
      if (!canManage) {
        toast("Vous n’avez pas le droit de modifier une ville.", "danger");
        return;
      }
      setEditing(ville);
      reset({ libelle: ville.libelle });
      setFormError(null);
      setOpen(true);
    },
    [canManage, reset, toast],
  );

  const columns = useMemo<ColumnDef<Ville>[]>(
    () => [
      {
        accessorKey: "libelle",
        header: "Ville",
        cell: ({ row }) => (
          <span className="font-medium">{row.original.libelle}</span>
        ),
      },
      {
        id: "actions",
        header: "Actions",
        enableSorting: false,
        cell: ({ row }) => (
          <TableActions
            canEdit={canManage}
            canDelete={canManage}
            onEdit={() => openEdit(row.original)}
            onDelete={() => setToDelete(row.original)}
          />
        ),
      },
    ],
    [canManage, openEdit],
  );

  return (
    <>
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
          placeholder: "Rechercher une ville…",
        }}
        toolbar={
          canManage ? (
            <Button onClick={openCreate}>
              <Plus className="size-4" />
              Nouvelle ville
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
        emptyTitle="Aucune ville"
        emptyDescription="Ajoutez les villes pour les proposer dans les fiches agents."
        emptyAction={
          canManage ? (
            <Button size="sm" onClick={openCreate}>
              <Plus className="size-4" />
              Nouvelle ville
            </Button>
          ) : undefined
        }
      />

      <Modal
        open={open}
        onClose={closeModal}
        preventClose={busy}
        title={editing ? "Modifier la ville" : "Nouvelle ville"}
        description="Référentiel utilisé dans les fiches agents."
        footer={
          <>
            <Button variant="secondary" onClick={closeModal} disabled={busy}>
              Annuler
            </Button>
            <Button type="submit" form="ville-form" loading={busy}>
              {busy ? "Enregistrement…" : "Enregistrer"}
            </Button>
          </>
        }
      >
        <form
          id="ville-form"
          className="space-y-3"
          onSubmit={handleSubmit(async (values) => {
            setFormError(null);
            try {
              if (editing) {
                await updateVille.mutateAsync({
                  id: editing.id,
                  payload: { libelle: values.libelle },
                });
                toast("Ville mise à jour.");
              } else {
                await createVille.mutateAsync({ libelle: values.libelle });
                toast("Ville créée.");
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
            placeholder="Abidjan"
            error={errors.libelle?.message}
            {...register("libelle")}
          />
        </form>
      </Modal>

      <ConfirmDialog
        open={!!toDelete}
        onClose={() => {
          if (deleteVille.isPending) return;
          setToDelete(null);
        }}
        loading={deleteVille.isPending}
        title="Supprimer la ville"
        description={
          toDelete
            ? `Confirmer la suppression de « ${toDelete.libelle} » ?`
            : ""
        }
        onConfirm={async () => {
          if (!toDelete) return;
          try {
            await deleteVille.mutateAsync(toDelete.id);
            toast("Ville supprimée.");
            setToDelete(null);
          } catch (err) {
            toast(
              getApiErrorMessage(err, "Échec de la suppression."),
              "danger",
            );
          }
        }}
      />
    </>
  );
}

export default function ParametresPage() {
  const [tab, setTab] = useState("grades");
  const { user } = useAuth();
  const canManage = can(user, "grades.manage");

  return (
    <PermissionGate permission="grades.manage" title="Paramètres">
      <div className="space-y-4">
        <PageHeader
          title="Paramètres"
          description="Grades, villes et référentiels système."
        />
        <Tabs
          items={[
            { id: "grades", label: "Grades" },
            { id: "villes", label: "Villes" },
          ]}
          value={tab}
          onChange={setTab}
        />
        <TabPanel when="grades" active={tab}>
          <GradesTab canManage={canManage} />
        </TabPanel>
        <TabPanel when="villes" active={tab}>
          <VillesTab canManage={canManage} />
        </TabPanel>
      </div>
    </PermissionGate>
  );
}
