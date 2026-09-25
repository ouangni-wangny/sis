"use client";

import { useMemo, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { type ColumnDef } from "@tanstack/react-table";
import { Plus } from "lucide-react";
import {
  useCategoriesDepense,
  useComptesTresorerieOptions,
  useCreateDepense,
  useDeleteDepense,
  useDepenses,
  useModesPaiementOptions,
} from "@/application/hooks/useResources";
import type { Depense } from "@/domain/types/entities";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { DataTable } from "@/presentation/components/tables/DataTable";
import { TableActions } from "@/presentation/components/tables/TableActions";
import { Alert } from "@/presentation/components/ui/Alert";
import { Badge, statusTone } from "@/presentation/components/ui/Badge";
import { Button } from "@/presentation/components/ui/Button";
import { ConfirmDialog } from "@/presentation/components/ui/ConfirmDialog";
import { Input } from "@/presentation/components/ui/Input";
import { Modal } from "@/presentation/components/ui/Modal";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { Select } from "@/presentation/components/ui/Select";
import { Textarea } from "@/presentation/components/ui/Textarea";
import { useToast } from "@/presentation/providers/ToastProvider";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { formatDate, formatFcfa, labelize } from "@/shared/lib/format";

const depenseSchema = z.object({
  categorie_depense_id: z.string().uuid("Catégorie requise"),
  libelle: z.string().min(1, "Libellé requis"),
  montant: z.coerce.number().positive("Montant > 0"),
  date_depense: z.string().min(1, "Date requise"),
  compte_tresorerie_id: z.string().uuid("Compte requis"),
  mode: z.string().min(1, "Mode requis"),
  reference: z.string().optional().or(z.literal("")),
  notes: z.string().optional().or(z.literal("")),
});

type DepenseForm = z.infer<typeof depenseSchema>;
type DepenseFormInput = z.input<typeof depenseSchema>;

const emptyDefaults: DepenseForm = {
  categorie_depense_id: "",
  libelle: "",
  montant: 0,
  date_depense: new Date().toISOString().slice(0, 10),
  compte_tresorerie_id: "",
  mode: "especes",
  reference: "",
  notes: "",
};

const STATUT_LABELS: Record<string, string> = {
  validee: "Validée",
  annulee: "Annulée",
};

const STATUT_FILTER_OPTIONS = [
  { value: "", label: "Tous les statuts" },
  { value: "validee", label: "Validées" },
  { value: "annulee", label: "Annulées" },
];

export default function DepensesPage() {
  const [page, setPage] = useState(1);
  const [statutFilter, setStatutFilter] = useState("");
  const [open, setOpen] = useState(false);
  const [toDelete, setToDelete] = useState<Depense | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const { toast } = useToast();

  const { data, isLoading } = useDepenses({
    page,
    per_page: 15,
    statut: statutFilter || undefined,
  });
  const { data: categoriesRes } = useCategoriesDepense();
  const { data: comptesRes } = useComptesTresorerieOptions();
  const { data: modesRes } = useModesPaiementOptions();
  const createDepense = useCreateDepense();
  const deleteDepense = useDeleteDepense();

  const categories = categoriesRes?.data ?? [];
  const comptes = comptesRes?.data ?? [];
  const modeOptions = useMemo(() => {
    const modes = modesRes?.data ?? [];
    return modes.map((m) => ({ value: m.code, label: m.libelle }));
  }, [modesRes]);

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<DepenseFormInput, unknown, DepenseForm>({
    resolver: zodResolver(depenseSchema),
    defaultValues: emptyDefaults,
  });

  const columns = useMemo<ColumnDef<Depense>[]>(
    () => [
      {
        accessorKey: "date_depense",
        header: "Date",
        cell: ({ getValue }) => formatDate(getValue() as string),
      },
      {
        accessorKey: "libelle",
        header: "Libellé",
      },
      {
        id: "categorie",
        header: "Catégorie",
        cell: ({ row }) => row.original.categorie?.libelle ?? "—",
      },
      {
        accessorKey: "montant",
        header: "Montant",
        cell: ({ getValue }) => formatFcfa(getValue() as number),
      },
      {
        id: "compte",
        header: "Compte",
        cell: ({ row }) => row.original.compte?.libelle ?? "—",
      },
      {
        accessorKey: "mode",
        header: "Mode",
        cell: ({ getValue }) => String(getValue() ?? "—").toUpperCase(),
      },
      {
        accessorKey: "statut",
        header: "Statut",
        cell: ({ row }) => {
          const statut = row.original.statut ?? "validee";
          return (
            <Badge tone={statusTone(statut)}>
              {STATUT_LABELS[statut] ?? labelize(statut)}
            </Badge>
          );
        },
      },
      {
        id: "actions",
        header: "Actions",
        enableSorting: false,
        cell: ({ row }) => {
          const isAnnulee = (row.original.statut ?? "validee") === "annulee";
          if (isAnnulee) {
            return <span className="text-xs text-ink-faint">—</span>;
          }
          return (
            <TableActions
              canEdit={false}
              canDelete
              deleteLabel="Annuler"
              onDelete={() => setToDelete(row.original)}
            />
          );
        },
      },
    ],
    [],
  );

  const onSubmit = handleSubmit(async (values) => {
    setFormError(null);
    try {
      await createDepense.mutateAsync({
        ...values,
        reference: values.reference || null,
        notes: values.notes || null,
      });
      toast("Dépense enregistrée.", "success");
      setOpen(false);
      reset(emptyDefaults);
    } catch (err) {
      setFormError(getApiErrorMessage(err, "Échec de l’enregistrement."));
    }
  });

  return (
    <PermissionGate
      permission={[
        "depenses.view",
        "depenses.manage",
        "tresorerie.view",
        "tresorerie.manage",
      ]}
      title="Dépenses"
    >
      <div className="space-y-6">
        <PageHeader
          title="Dépenses"
          description="Enregistrez les sorties d’argent de l’entreprise (impact immédiat sur le solde)."
          actions={
            <Button
              type="button"
              onClick={() => {
                reset({
                  ...emptyDefaults,
                  compte_tresorerie_id: comptes[0]?.id ?? "",
                  categorie_depense_id: categories[0]?.id ?? "",
                });
                setFormError(null);
                setOpen(true);
              }}
            >
              <Plus className="size-4" />
              Nouvelle dépense
            </Button>
          }
        />

        <DataTable
          columns={columns}
          data={data?.data ?? []}
          isLoading={isLoading}
          selectable={false}
          toolbar={
            <Select
              className="min-w-[11rem]"
              value={statutFilter}
              options={STATUT_FILTER_OPTIONS}
              onChange={(event) => {
                setStatutFilter(event.target.value);
                setPage(1);
              }}
            />
          }
          pagination={{
            page,
            perPage: 15,
            total: data?.meta.total ?? 0,
            onPageChange: setPage,
          }}
        />
      </div>

      <Modal
        open={open}
        onClose={() => !isSubmitting && setOpen(false)}
        title="Nouvelle dépense"
      >
        <form className="space-y-3" onSubmit={onSubmit}>
          {formError ? <Alert tone="danger">{formError}</Alert> : null}
          <Select
            label="Catégorie"
            error={errors.categorie_depense_id?.message}
            options={categories.map((c) => ({
              value: c.id,
              label: c.libelle,
            }))}
            {...register("categorie_depense_id")}
          />
          <Input
            label="Libellé"
            error={errors.libelle?.message}
            {...register("libelle")}
          />
          <Input
            label="Montant (FCFA)"
            type="number"
            error={errors.montant?.message}
            {...register("montant")}
          />
          <Input
            label="Date"
            type="date"
            error={errors.date_depense?.message}
            {...register("date_depense")}
          />
          <Select
            label="Compte"
            error={errors.compte_tresorerie_id?.message}
            options={comptes.map((c) => ({
              value: c.id,
              label: `${c.libelle} (${formatFcfa(c.solde ?? 0)})`,
            }))}
            {...register("compte_tresorerie_id")}
          />
          <Select
            label="Mode"
            error={errors.mode?.message}
            options={modeOptions}
            {...register("mode")}
          />
          <Input
            label="Référence"
            hint="Laissée vide → générée automatiquement (ex. DEP-20260925-A3F2K)."
            placeholder="Auto si vide"
            {...register("reference")}
          />
          <Textarea label="Notes" {...register("notes")} />
          <div className="flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="ghost"
              onClick={() => setOpen(false)}
              disabled={isSubmitting}
            >
              Annuler
            </Button>
            <Button type="submit" disabled={isSubmitting}>
              Enregistrer
            </Button>
          </div>
        </form>
      </Modal>

      <ConfirmDialog
        open={!!toDelete}
        title="Annuler cette dépense ?"
        description="La dépense restera visible avec le statut Annulée. Un mouvement inverse sera créé en trésorerie."
        confirmLabel="Annuler la dépense"
        onConfirm={async () => {
          if (!toDelete) return;
          try {
            await deleteDepense.mutateAsync(toDelete.id);
            toast("Dépense annulée.", "success");
            setToDelete(null);
          } catch (err) {
            toast(getApiErrorMessage(err, "Échec."), "danger");
          }
        }}
        onClose={() => setToDelete(null)}
      />
    </PermissionGate>
  );
}
