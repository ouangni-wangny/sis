"use client";

import { useCallback, useMemo, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { type ColumnDef } from "@tanstack/react-table";
import { Plus } from "lucide-react";
import { useDebouncedValue } from "@/application/hooks/useDebouncedValue";
import {
  useCategoriesDepenseList,
  useComptesTresorerie,
  useCreateCategorieDepense,
  useCreateCompteTresorerie,
  useCreateGrade,
  useCreateModePaiement,
  useCreateVille,
  useDeleteCategorieDepense,
  useDeleteCompteTresorerie,
  useDeleteGrade,
  useDeleteModePaiement,
  useDeleteVille,
  useGrades,
  useModesPaiement,
  useUpdateCategorieDepense,
  useUpdateCompteTresorerie,
  useUpdateGrade,
  useUpdateModePaiement,
  useUpdateVille,
  useVilles,
} from "@/application/hooks/useResources";
import { gradeSchema, type GradeFormValues } from "@/domain/schemas/grade";
import { villeSchema, type VilleFormValues } from "@/domain/schemas/ville";
import type {
  CategorieDepense,
  CompteTresorerie,
  Grade,
  ModePaiementParam,
  Ville,
} from "@/domain/types/entities";
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
import { formatFcfa, labelTypeAgent, labelize } from "@/shared/lib/format";

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

const TYPE_COMPTE_OPTIONS = [
  { value: "banque", label: "Banque" },
  { value: "caisse", label: "Caisse" },
  { value: "mobile_money", label: "Mobile Money" },
];

const ACTIF_OPTIONS = [
  { value: "1", label: "Actif" },
  { value: "0", label: "Inactif" },
];

function CategoriesTab({ canManage }: { canManage: boolean }) {
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [q, setQ] = useState("");
  const search = useDebouncedValue(q);
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<CategorieDepense | null>(null);
  const [toDelete, setToDelete] = useState<CategorieDepense | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const [libelle, setLibelle] = useState("");
  const [actif, setActif] = useState("1");
  const { toast } = useToast();

  const { data, isLoading } = useCategoriesDepenseList({
    page,
    per_page: perPage,
    q: search || undefined,
  });
  const createCat = useCreateCategorieDepense();
  const updateCat = useUpdateCategorieDepense();
  const deleteCat = useDeleteCategorieDepense();
  const busy = createCat.isPending || updateCat.isPending;

  const closeModal = () => {
    if (busy) return;
    setOpen(false);
    setEditing(null);
    setFormError(null);
    setLibelle("");
    setActif("1");
  };

  const openCreate = () => {
    setEditing(null);
    setLibelle("");
    setActif("1");
    setFormError(null);
    setOpen(true);
  };

  const openEdit = useCallback(
    (row: CategorieDepense) => {
      setEditing(row);
      setLibelle(row.libelle);
      setActif(row.actif ? "1" : "0");
      setFormError(null);
      setOpen(true);
    },
    [],
  );

  const columns = useMemo<ColumnDef<CategorieDepense>[]>(
    () => [
      { accessorKey: "libelle", header: "Libellé" },
      {
        accessorKey: "actif",
        header: "Statut",
        cell: ({ getValue }) => (getValue() ? "Actif" : "Inactif"),
      },
      {
        id: "actions",
        header: "Actions",
        enableSorting: false,
        cell: ({ row }) =>
          canManage ? (
            <TableActions
              canEdit
              canDelete
              onEdit={() => openEdit(row.original)}
              onDelete={() => setToDelete(row.original)}
            />
          ) : null,
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
          placeholder: "Rechercher une catégorie…",
        }}
        toolbar={
          canManage ? (
            <Button onClick={openCreate}>
              <Plus className="size-4" />
              Nouvelle catégorie
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
        emptyTitle="Aucune catégorie"
        emptyDescription="Ajoutez les catégories utilisées pour les dépenses."
        emptyAction={
          canManage ? (
            <Button size="sm" onClick={openCreate}>
              <Plus className="size-4" />
              Nouvelle catégorie
            </Button>
          ) : undefined
        }
      />

      <Modal
        open={open}
        onClose={closeModal}
        preventClose={busy}
        title={editing ? "Modifier la catégorie" : "Nouvelle catégorie"}
        description="Référentiel des catégories de dépense."
        footer={
          <>
            <Button variant="secondary" onClick={closeModal} disabled={busy}>
              Annuler
            </Button>
            <Button
              loading={busy}
              onClick={async () => {
                setFormError(null);
                if (!libelle.trim()) {
                  setFormError("Libellé requis.");
                  return;
                }
                try {
                  if (editing) {
                    await updateCat.mutateAsync({
                      id: editing.id,
                      payload: {
                        libelle: libelle.trim(),
                        actif: actif === "1",
                      },
                    });
                    toast("Catégorie mise à jour.");
                  } else {
                    await createCat.mutateAsync({
                      libelle: libelle.trim(),
                      actif: actif === "1",
                    });
                    toast("Catégorie créée.");
                  }
                  closeModal();
                } catch (err) {
                  setFormError(
                    getApiErrorMessage(err, "Échec de l’enregistrement."),
                  );
                }
              }}
            >
              {busy ? "Enregistrement…" : "Enregistrer"}
            </Button>
          </>
        }
      >
        <div className="space-y-3">
          {formError ? <Alert tone="danger">{formError}</Alert> : null}
          <Input
            label="Libellé"
            requiredMark
            value={libelle}
            onChange={(e) => setLibelle(e.target.value)}
            placeholder="Fournitures bureau"
          />
          <Select
            label="Statut"
            value={actif}
            onChange={(e) => setActif(e.target.value)}
            options={ACTIF_OPTIONS}
          />
        </div>
      </Modal>

      <ConfirmDialog
        open={!!toDelete}
        onClose={() => {
          if (deleteCat.isPending) return;
          setToDelete(null);
        }}
        loading={deleteCat.isPending}
        title="Supprimer la catégorie"
        description={
          toDelete
            ? `Confirmer la suppression de « ${toDelete.libelle} » ?`
            : ""
        }
        onConfirm={async () => {
          if (!toDelete) return;
          try {
            await deleteCat.mutateAsync(toDelete.id);
            toast("Catégorie supprimée.");
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

function ComptesTab({ canManage }: { canManage: boolean }) {
  const [q, setQ] = useState("");
  const search = useDebouncedValue(q);
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<CompteTresorerie | null>(null);
  const [toDelete, setToDelete] = useState<CompteTresorerie | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const [libelle, setLibelle] = useState("");
  const [type, setType] = useState("caisse");
  const [soldeOuverture, setSoldeOuverture] = useState("0");
  const [actif, setActif] = useState("1");
  const { toast } = useToast();

  const { data, isLoading } = useComptesTresorerie();
  const createCompte = useCreateCompteTresorerie();
  const updateCompte = useUpdateCompteTresorerie();
  const deleteCompte = useDeleteCompteTresorerie();
  const busy = createCompte.isPending || updateCompte.isPending;

  const rows = useMemo(() => {
    const all = data?.data ?? [];
    const term = search.trim().toLowerCase();
    if (!term) return all;
    return all.filter(
      (c) =>
        c.libelle.toLowerCase().includes(term) ||
        c.type.toLowerCase().includes(term),
    );
  }, [data, search]);

  const closeModal = () => {
    if (busy) return;
    setOpen(false);
    setEditing(null);
    setFormError(null);
    setLibelle("");
    setType("caisse");
    setSoldeOuverture("0");
    setActif("1");
  };

  const openCreate = () => {
    setEditing(null);
    setLibelle("");
    setType("caisse");
    setSoldeOuverture("0");
    setActif("1");
    setFormError(null);
    setOpen(true);
  };

  const openEdit = useCallback((row: CompteTresorerie) => {
    setEditing(row);
    setLibelle(row.libelle);
    setType(row.type);
    setSoldeOuverture(String(row.solde_ouverture ?? 0));
    setActif(row.actif ? "1" : "0");
    setFormError(null);
    setOpen(true);
  }, []);

  const columns = useMemo<ColumnDef<CompteTresorerie>[]>(
    () => [
      { accessorKey: "libelle", header: "Libellé" },
      {
        accessorKey: "type",
        header: "Type",
        cell: ({ getValue }) => labelize(String(getValue())),
      },
      {
        accessorKey: "solde_ouverture",
        header: "Solde d’ouverture",
        cell: ({ getValue }) => formatFcfa(getValue() as number),
      },
      {
        id: "solde",
        header: "Solde actuel",
        cell: ({ row }) =>
          row.original.solde != null
            ? formatFcfa(row.original.solde as number)
            : "—",
      },
      {
        accessorKey: "actif",
        header: "Statut",
        cell: ({ getValue }) => (getValue() ? "Actif" : "Inactif"),
      },
      {
        id: "actions",
        header: "Actions",
        enableSorting: false,
        cell: ({ row }) =>
          canManage ? (
            <TableActions
              canEdit
              canDelete
              onEdit={() => openEdit(row.original)}
              onDelete={() => setToDelete(row.original)}
            />
          ) : null,
      },
    ],
    [canManage, openEdit],
  );

  return (
    <>
      <DataTable
        data={rows}
        columns={columns}
        isLoading={isLoading}
        search={{
          value: q,
          onChange: setQ,
          placeholder: "Rechercher un compte…",
        }}
        toolbar={
          canManage ? (
            <Button onClick={openCreate}>
              <Plus className="size-4" />
              Nouveau compte
            </Button>
          ) : null
        }
        emptyTitle="Aucun compte"
        emptyDescription="Créez les comptes caisse / banque / mobile money."
        emptyAction={
          canManage ? (
            <Button size="sm" onClick={openCreate}>
              <Plus className="size-4" />
              Nouveau compte
            </Button>
          ) : undefined
        }
      />

      <Modal
        open={open}
        onClose={closeModal}
        preventClose={busy}
        title={editing ? "Modifier le compte" : "Nouveau compte"}
        description="Compte de trésorerie (caisse, banque, mobile money)."
        footer={
          <>
            <Button variant="secondary" onClick={closeModal} disabled={busy}>
              Annuler
            </Button>
            <Button
              loading={busy}
              onClick={async () => {
                setFormError(null);
                if (!libelle.trim()) {
                  setFormError("Libellé requis.");
                  return;
                }
                try {
                  const solde = Number(soldeOuverture);
                  if (editing) {
                    await updateCompte.mutateAsync({
                      id: editing.id,
                      payload: {
                        libelle: libelle.trim(),
                        type,
                        solde_ouverture: Number.isFinite(solde) ? solde : 0,
                        actif: actif === "1",
                      },
                    });
                    toast("Compte mis à jour.");
                  } else {
                    await createCompte.mutateAsync({
                      libelle: libelle.trim(),
                      type,
                      solde_ouverture: Number.isFinite(solde) ? solde : 0,
                      actif: actif === "1",
                    });
                    toast("Compte créé.");
                  }
                  closeModal();
                } catch (err) {
                  setFormError(
                    getApiErrorMessage(err, "Échec de l’enregistrement."),
                  );
                }
              }}
            >
              {busy ? "Enregistrement…" : "Enregistrer"}
            </Button>
          </>
        }
      >
        <div className="space-y-3">
          {formError ? <Alert tone="danger">{formError}</Alert> : null}
          <Input
            label="Libellé"
            requiredMark
            value={libelle}
            onChange={(e) => setLibelle(e.target.value)}
            placeholder="Caisse principale"
          />
          <Select
            label="Type"
            requiredMark
            value={type}
            onChange={(e) => setType(e.target.value)}
            options={TYPE_COMPTE_OPTIONS}
          />
          <Input
            label="Solde d’ouverture"
            type="number"
            value={soldeOuverture}
            onChange={(e) => setSoldeOuverture(e.target.value)}
          />
          <Select
            label="Statut"
            value={actif}
            onChange={(e) => setActif(e.target.value)}
            options={ACTIF_OPTIONS}
          />
        </div>
      </Modal>

      <ConfirmDialog
        open={!!toDelete}
        onClose={() => {
          if (deleteCompte.isPending) return;
          setToDelete(null);
        }}
        loading={deleteCompte.isPending}
        title="Supprimer le compte"
        description={
          toDelete
            ? `Confirmer la suppression de « ${toDelete.libelle} » ?`
            : ""
        }
        onConfirm={async () => {
          if (!toDelete) return;
          try {
            await deleteCompte.mutateAsync(toDelete.id);
            toast("Compte supprimé.");
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

function ModesTab({ canManage }: { canManage: boolean }) {
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [q, setQ] = useState("");
  const search = useDebouncedValue(q);
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<ModePaiementParam | null>(null);
  const [toDelete, setToDelete] = useState<ModePaiementParam | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const [libelle, setLibelle] = useState("");
  const [code, setCode] = useState("");
  const [ordre, setOrdre] = useState("0");
  const [actif, setActif] = useState("1");
  const { toast } = useToast();

  const { data, isLoading } = useModesPaiement({
    page,
    per_page: perPage,
    q: search || undefined,
  });
  const createMode = useCreateModePaiement();
  const updateMode = useUpdateModePaiement();
  const deleteMode = useDeleteModePaiement();
  const busy = createMode.isPending || updateMode.isPending;

  const closeModal = () => {
    if (busy) return;
    setOpen(false);
    setEditing(null);
    setFormError(null);
    setLibelle("");
    setCode("");
    setOrdre("0");
    setActif("1");
  };

  const openCreate = () => {
    setEditing(null);
    setLibelle("");
    setCode("");
    setOrdre("0");
    setActif("1");
    setFormError(null);
    setOpen(true);
  };

  const openEdit = useCallback((row: ModePaiementParam) => {
    setEditing(row);
    setLibelle(row.libelle);
    setCode(row.code);
    setOrdre(String(row.ordre ?? 0));
    setActif(row.actif ? "1" : "0");
    setFormError(null);
    setOpen(true);
  }, []);

  const columns = useMemo<ColumnDef<ModePaiementParam>[]>(
    () => [
      { accessorKey: "libelle", header: "Libellé" },
      { accessorKey: "code", header: "Code" },
      { accessorKey: "ordre", header: "Ordre" },
      {
        accessorKey: "actif",
        header: "Statut",
        cell: ({ getValue }) => (getValue() ? "Actif" : "Inactif"),
      },
      {
        id: "actions",
        header: "Actions",
        enableSorting: false,
        cell: ({ row }) =>
          canManage ? (
            <TableActions
              canEdit
              canDelete
              onEdit={() => openEdit(row.original)}
              onDelete={() => setToDelete(row.original)}
            />
          ) : null,
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
          placeholder: "Rechercher un mode…",
        }}
        toolbar={
          canManage ? (
            <Button onClick={openCreate}>
              <Plus className="size-4" />
              Nouveau mode
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
        emptyTitle="Aucun mode de paiement"
        emptyDescription="Ajoutez les modes proposés dans les formulaires."
        emptyAction={
          canManage ? (
            <Button size="sm" onClick={openCreate}>
              <Plus className="size-4" />
              Nouveau mode
            </Button>
          ) : undefined
        }
      />

      <Modal
        open={open}
        onClose={closeModal}
        preventClose={busy}
        title={editing ? "Modifier le mode" : "Nouveau mode"}
        description="Mode de paiement (espèces, virement, etc.)."
        footer={
          <>
            <Button variant="secondary" onClick={closeModal} disabled={busy}>
              Annuler
            </Button>
            <Button
              loading={busy}
              onClick={async () => {
                setFormError(null);
                if (!libelle.trim()) {
                  setFormError("Libellé requis.");
                  return;
                }
                try {
                  const ordreNum = Number(ordre);
                  if (editing) {
                    await updateMode.mutateAsync({
                      id: editing.id,
                      payload: {
                        libelle: libelle.trim(),
                        actif: actif === "1",
                        ordre: Number.isFinite(ordreNum) ? ordreNum : 0,
                      },
                    });
                    toast("Mode mis à jour.");
                  } else {
                    await createMode.mutateAsync({
                      libelle: libelle.trim(),
                      code: code.trim() || undefined,
                      actif: actif === "1",
                      ordre: Number.isFinite(ordreNum) ? ordreNum : undefined,
                    });
                    toast("Mode créé.");
                  }
                  closeModal();
                } catch (err) {
                  setFormError(
                    getApiErrorMessage(err, "Échec de l’enregistrement."),
                  );
                }
              }}
            >
              {busy ? "Enregistrement…" : "Enregistrer"}
            </Button>
          </>
        }
      >
        <div className="space-y-3">
          {formError ? <Alert tone="danger">{formError}</Alert> : null}
          <Input
            label="Libellé"
            requiredMark
            value={libelle}
            onChange={(e) => setLibelle(e.target.value)}
            placeholder="Espèces"
          />
          <Input
            label="Code"
            value={code}
            onChange={(e) => setCode(e.target.value)}
            disabled={!!editing}
            hint={
              editing
                ? "Le code n’est pas modifiable (historique)."
                : "Optionnel — dérivé du libellé si vide."
            }
            placeholder="especes"
          />
          <Input
            label="Ordre"
            type="number"
            value={ordre}
            onChange={(e) => setOrdre(e.target.value)}
          />
          <Select
            label="Statut"
            value={actif}
            onChange={(e) => setActif(e.target.value)}
            options={ACTIF_OPTIONS}
          />
        </div>
      </Modal>

      <ConfirmDialog
        open={!!toDelete}
        onClose={() => {
          if (deleteMode.isPending) return;
          setToDelete(null);
        }}
        loading={deleteMode.isPending}
        title="Supprimer le mode"
        description={
          toDelete
            ? `Confirmer la suppression de « ${toDelete.libelle} » ?`
            : ""
        }
        onConfirm={async () => {
          if (!toDelete) return;
          try {
            await deleteMode.mutateAsync(toDelete.id);
            toast("Mode supprimé.");
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
          description="Grades, villes, catégories, comptes et modes de paiement."
        />
        <Tabs
          items={[
            { id: "grades", label: "Grades" },
            { id: "villes", label: "Villes" },
            { id: "categories", label: "Catégories" },
            { id: "comptes", label: "Comptes" },
            { id: "modes", label: "Modes" },
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
        <TabPanel when="categories" active={tab}>
          <CategoriesTab canManage={canManage} />
        </TabPanel>
        <TabPanel when="comptes" active={tab}>
          <ComptesTab canManage={canManage} />
        </TabPanel>
        <TabPanel when="modes" active={tab}>
          <ModesTab canManage={canManage} />
        </TabPanel>
      </div>
    </PermissionGate>
  );
}
