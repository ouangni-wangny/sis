"use client";

import { useCallback, useMemo, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { type ColumnDef } from "@tanstack/react-table";
import { Plus } from "lucide-react";
import { useDebouncedValue } from "@/application/hooks/useDebouncedValue";
import {
  usePrimesQualification,
  useDeletePrimeQualification,
  useDeleteQualification,
  useSavePrimeQualification,
  useSaveQualification,
  useQualifications,
} from "@/application/hooks/useResources";
import {
  primeQualificationSchema,
  qualificationSchema,
  type PrimeQualificationFormValues,
  type QualificationFormValues,
} from "@/domain/schemas/qualification";
import type { PrimeQualification, Qualification } from "@/domain/types/entities";
import { DataTable } from "@/presentation/components/tables/DataTable";
import { TableActions } from "@/presentation/components/tables/TableActions";
import { Alert } from "@/presentation/components/ui/Alert";
import { Badge } from "@/presentation/components/ui/Badge";
import { Button } from "@/presentation/components/ui/Button";
import { Card, CardBody, CardHeader } from "@/presentation/components/ui/Card";
import { ConfirmDialog } from "@/presentation/components/ui/ConfirmDialog";
import { RequiredFieldsLegend } from "@/presentation/components/ui/FieldLabel";
import { Input } from "@/presentation/components/ui/Input";
import { Modal } from "@/presentation/components/ui/Modal";
import { Select } from "@/presentation/components/ui/Select";
import { useToast } from "@/presentation/providers/ToastProvider";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { formatFcfa } from "@/shared/lib/format";

const UNITE_LABEL: Record<PrimeQualification["unite"], string> = {
  mois: "par mois",
  jour: "par jour",
};

const ACTIF_OPTIONS = [
  { value: "1", label: "Actif" },
  { value: "0", label: "Inactif" },
];

const emptyType: QualificationFormValues = {
  libelle: "",
  abreviation: "",
  description: "",
  actif: "1",
};

const emptyBareme: PrimeQualificationFormValues = {
  qualification_id: "",
  montant: "",
  unite: "mois",
  actif: "1",
};

/**
 * Paramètres → Primes :
 * 1. Qualifications des agents (Chef de poste – CP, Garde armée – GA, Maître-chien – MC…)
 * 2. Primes : montant que donne chaque qualification, par mois ou par jour.
 */
export function QualificationsTab({ canManage }: { canManage: boolean }) {
  return (
    <div className="space-y-6">
      <QualificationsSection canManage={canManage} />
      <PrimesQualificationSection canManage={canManage} />
    </div>
  );
}

function QualificationsSection({ canManage }: { canManage: boolean }) {
  const { toast } = useToast();
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [q, setQ] = useState("");
  const search = useDebouncedValue(q);
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<Qualification | null>(null);
  const [toDelete, setToDelete] = useState<Qualification | null>(null);
  const [formError, setFormError] = useState<string | null>(null);

  const { data, isLoading } = useQualifications({
    page,
    per_page: perPage,
    q: search || undefined,
  });
  const save = useSaveQualification();
  const remove = useDeleteQualification();

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<QualificationFormValues>({
    resolver: zodResolver(qualificationSchema),
    mode: "onBlur",
    defaultValues: emptyType,
  });
  const busy = isSubmitting || save.isPending;

  const close = () => {
    if (busy) return;
    setOpen(false);
    setEditing(null);
    setFormError(null);
    reset(emptyType);
  };

  const openCreate = () => {
    setEditing(null);
    reset(emptyType);
    setFormError(null);
    setOpen(true);
  };

  const openEdit = useCallback(
    (t: Qualification) => {
      setEditing(t);
      reset({
        libelle: t.libelle,
        abreviation: t.abreviation,
        description: t.description ?? "",
        actif: t.actif ? "1" : "0",
      });
      setFormError(null);
      setOpen(true);
    },
    [reset],
  );

  const columns = useMemo<ColumnDef<Qualification>[]>(
    () => [
      {
        accessorKey: "abreviation",
        header: "Abréviation",
        cell: ({ row }) => (
          <span className="inline-flex min-w-10 justify-center rounded bg-teal/10 px-2 py-0.5 font-mono text-sm font-semibold text-teal-dark">
            {row.original.abreviation}
          </span>
        ),
      },
      {
        accessorKey: "libelle",
        header: "Qualification",
        cell: ({ row }) => (
          <span className="font-medium">{row.original.libelle}</span>
        ),
      },
      {
        accessorKey: "description",
        header: "Description",
        cell: ({ getValue }) => (getValue() as string | null) || "—",
      },
      {
        id: "baremes",
        header: "Primes",
        cell: ({ row }) => row.original.primes_count ?? 0,
      },
      {
        accessorKey: "actif",
        header: "Statut",
        cell: ({ row }) => (
          <Badge tone={row.original.actif ? "success" : "neutral"}>
            {row.original.actif ? "Actif" : "Inactif"}
          </Badge>
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
    <Card className="min-w-0 overflow-hidden">
      <CardHeader
        title="Qualifications"
        description="Qualifications des agents et leur abréviation (ex. CP = Chef de poste). Elles se cochent sur la fiche de chaque agent."
      />
      <CardBody>
        <DataTable
          data={data?.data ?? []}
          columns={columns}
          isLoading={isLoading}
          selectable={false}
          search={{
            value: q,
            onChange: (v) => {
              setQ(v);
              setPage(1);
            },
            placeholder: "Rechercher une qualification…",
          }}
          toolbar={
            canManage ? (
              <Button onClick={openCreate}>
                <Plus className="size-4" />
                Nouvelle qualification
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
          emptyTitle="Aucune qualification"
          emptyDescription="Ajoutez par exemple Maître-chien (MC)."
        />
      </CardBody>

      <Modal
        open={open}
        onClose={close}
        preventClose={busy}
        title={editing ? "Modifier la qualification" : "Nouvelle qualification"}
        description="Le nom complet et son abréviation, utilisée sur les fiches et bulletins."
        footer={
          <>
            <Button variant="secondary" onClick={close} disabled={busy}>
              Annuler
            </Button>
            <Button type="submit" form="qualification-form" loading={busy}>
              {busy ? "Enregistrement…" : "Enregistrer"}
            </Button>
          </>
        }
      >
        <form
          id="qualification-form"
          className="space-y-3"
          onSubmit={handleSubmit(async (v) => {
            setFormError(null);
            try {
              await save.mutateAsync({
                id: editing?.id,
                payload: {
                  libelle: v.libelle,
                  abreviation: v.abreviation.toUpperCase(),
                  description: v.description || null,
                  actif: v.actif === "1",
                },
              });
              toast(editing ? "Qualification mise à jour." : "Qualification créée.");
              close();
            } catch (err) {
              setFormError(getApiErrorMessage(err, "Échec de l’enregistrement."));
            }
          })}
        >
          <RequiredFieldsLegend />
          {formError ? <Alert tone="danger">{formError}</Alert> : null}
          <Input
            label="Nom de la qualification"
            requiredMark
            placeholder="Chef de poste"
            error={errors.libelle?.message}
            {...register("libelle")}
          />
          <Input
            label="Abréviation"
            requiredMark
            placeholder="CP"
            className="uppercase"
            error={errors.abreviation?.message}
            {...register("abreviation")}
          />
          <Input
            label="Description"
            optionalMark
            error={errors.description?.message}
            {...register("description")}
          />
          <Select label="Statut" options={ACTIF_OPTIONS} {...register("actif")} />
        </form>
      </Modal>

      <ConfirmDialog
        open={!!toDelete}
        onClose={() => {
          if (remove.isPending) return;
          setToDelete(null);
        }}
        loading={remove.isPending}
        title="Supprimer la qualification"
        description={
          toDelete
            ? `Confirmer la suppression de « ${toDelete.libelle} (${toDelete.abreviation}) » ?`
            : ""
        }
        onConfirm={async () => {
          if (!toDelete) return;
          try {
            await remove.mutateAsync(toDelete.id);
            toast("Qualification supprimée.");
            setToDelete(null);
          } catch (err) {
            toast(getApiErrorMessage(err, "Échec de la suppression."), "danger");
          }
        }}
      />
    </Card>
  );
}

function PrimesQualificationSection({ canManage }: { canManage: boolean }) {
  const { toast } = useToast();
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [q, setQ] = useState("");
  const search = useDebouncedValue(q);
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<PrimeQualification | null>(null);
  const [toDelete, setToDelete] = useState<PrimeQualification | null>(null);
  const [formError, setFormError] = useState<string | null>(null);

  const { data, isLoading } = usePrimesQualification({
    page,
    per_page: perPage,
    q: search || undefined,
  });
  const { data: typesData } = useQualifications({ all: true });
  const save = useSavePrimeQualification();
  const remove = useDeletePrimeQualification();

  const typeOptions = (typesData?.data ?? [])
    .filter((t) => t.actif || t.id === editing?.qualification_id)
    .map((t) => ({ value: t.id, label: `${t.abreviation} — ${t.libelle}` }));

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<PrimeQualificationFormValues>({
    resolver: zodResolver(primeQualificationSchema),
    mode: "onBlur",
    defaultValues: emptyBareme,
  });
  const busy = isSubmitting || save.isPending;

  const close = () => {
    if (busy) return;
    setOpen(false);
    setEditing(null);
    setFormError(null);
    reset(emptyBareme);
  };

  const openCreate = () => {
    setEditing(null);
    reset(emptyBareme);
    setFormError(null);
    setOpen(true);
  };

  const openEdit = useCallback(
    (b: PrimeQualification) => {
      setEditing(b);
      reset({
        qualification_id: b.qualification_id,
        montant: String(b.montant),
        unite: b.unite,
        actif: b.actif ? "1" : "0",
      });
      setFormError(null);
      setOpen(true);
    },
    [reset],
  );

  const columns = useMemo<ColumnDef<PrimeQualification>[]>(
    () => [
      {
        id: "abreviation",
        header: "Code",
        cell: ({ row }) => (
          <span className="inline-flex min-w-10 justify-center rounded bg-teal/10 px-2 py-0.5 font-mono text-sm font-semibold text-teal-dark">
            {row.original.qualification?.abreviation ?? "—"}
          </span>
        ),
      },
      {
        id: "qualification",
        header: "Qualification",
        cell: ({ row }) => (
          <span className="font-medium">
            {row.original.qualification?.libelle ?? "—"}
          </span>
        ),
      },
      {
        accessorKey: "montant",
        header: "Prime",
        cell: ({ row }) => (
          <span className="font-semibold">
            {formatFcfa(row.original.montant)}
          </span>
        ),
      },
      {
        accessorKey: "unite",
        header: "Unité",
        cell: ({ row }) => UNITE_LABEL[row.original.unite] ?? row.original.unite,
      },
      {
        accessorKey: "actif",
        header: "Statut",
        cell: ({ row }) => (
          <Badge tone={row.original.actif ? "success" : "neutral"}>
            {row.original.actif ? "Actif" : "Inactif"}
          </Badge>
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
    <Card className="min-w-0 overflow-hidden">
      <CardHeader
        title="Primes par qualification"
        description="Montant que donne chaque qualification, par mois ou par jour (ex. MC — 500 FCFA par jour). Ajoutée au bulletin de l’agent."
      />
      <CardBody>
        <DataTable
          data={data?.data ?? []}
          columns={columns}
          isLoading={isLoading}
          selectable={false}
          search={{
            value: q,
            onChange: (v) => {
              setQ(v);
              setPage(1);
            },
            placeholder: "Rechercher par qualification ou code…",
          }}
          toolbar={
            canManage ? (
              <Button onClick={openCreate}>
                <Plus className="size-4" />
                Nouvelle prime
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
          emptyTitle="Aucune prime"
          emptyDescription="Fixez la prime de chaque qualification (par mois ou par jour)."
        />
      </CardBody>

      <Modal
        open={open}
        onClose={close}
        preventClose={busy}
        title={editing ? "Modifier la prime" : "Nouvelle prime"}
        description="Une seule prime par qualification et par unité."
        footer={
          <>
            <Button variant="secondary" onClick={close} disabled={busy}>
              Annuler
            </Button>
            <Button type="submit" form="prime-qualification-form" loading={busy}>
              {busy ? "Enregistrement…" : "Enregistrer"}
            </Button>
          </>
        }
      >
        <form
          id="prime-qualification-form"
          className="space-y-3"
          onSubmit={handleSubmit(async (v) => {
            setFormError(null);
            try {
              await save.mutateAsync({
                id: editing?.id,
                payload: {
                  qualification_id: v.qualification_id,
                  montant: Number(v.montant),
                  unite: v.unite,
                  actif: v.actif === "1",
                },
              });
              toast(editing ? "Prime mise à jour." : "Prime enregistrée.");
              close();
            } catch (err) {
              setFormError(getApiErrorMessage(err, "Échec de l’enregistrement."));
            }
          })}
        >
          <RequiredFieldsLegend />
          {formError ? <Alert tone="danger">{formError}</Alert> : null}
          <Select
            label="Qualification"
            requiredMark
            placeholder="Choisir la qualification"
            options={typeOptions}
            error={errors.qualification_id?.message}
            {...register("qualification_id")}
          />
          <div className="grid gap-3 sm:grid-cols-2">
            <Input
              label="Montant (FCFA)"
              requiredMark
              type="number"
              min={0}
              step={1}
              placeholder="1000"
              error={errors.montant?.message}
              {...register("montant")}
            />
            <Select
              label="Unité"
              requiredMark
              options={[
                { value: "mois", label: "Par mois" },
                { value: "jour", label: "Par jour" },
              ]}
              error={errors.unite?.message}
              {...register("unite")}
            />
          </div>
          <Select label="Statut" options={ACTIF_OPTIONS} {...register("actif")} />
        </form>
      </Modal>

      <ConfirmDialog
        open={!!toDelete}
        onClose={() => {
          if (remove.isPending) return;
          setToDelete(null);
        }}
        loading={remove.isPending}
        title="Supprimer la prime"
        description={
          toDelete
            ? `Supprimer ${toDelete.qualification?.abreviation ?? ""} — ${formatFcfa(toDelete.montant)} ${UNITE_LABEL[toDelete.unite]} ?`
            : ""
        }
        onConfirm={async () => {
          if (!toDelete) return;
          try {
            await remove.mutateAsync(toDelete.id);
            toast("Prime supprimée.");
            setToDelete(null);
          } catch (err) {
            toast(getApiErrorMessage(err, "Échec de la suppression."), "danger");
          }
        }}
      />
    </Card>
  );
}
