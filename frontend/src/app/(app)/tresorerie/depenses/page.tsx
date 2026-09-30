"use client";

import { useMemo, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { type ColumnDef } from "@tanstack/react-table";
import { Plus, Wallet } from "lucide-react";
import {
  useCategoriesDepense,
  useComptesTresorerieOptions,
  useCreateDepense,
  useDepenses,
  useModesPaiementOptions,
} from "@/application/hooks/useResources";
import type { Depense } from "@/domain/types/entities";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { DataTable } from "@/presentation/components/tables/DataTable";
import { Alert } from "@/presentation/components/ui/Alert";
import { Badge, statusTone } from "@/presentation/components/ui/Badge";
import { Button } from "@/presentation/components/ui/Button";
import { Input } from "@/presentation/components/ui/Input";
import { Modal } from "@/presentation/components/ui/Modal";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { Select } from "@/presentation/components/ui/Select";
import { StatCard } from "@/presentation/components/ui/StatCard";
import { Textarea } from "@/presentation/components/ui/Textarea";
import { useToast } from "@/presentation/providers/ToastProvider";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import {
  formatDate,
  formatFcfa,
  labelize,
  labelMoisAnnee,
  MOIS_LABELS,
} from "@/shared/lib/format";

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
  const now = new Date();
  const [page, setPage] = useState(1);
  const [statutFilter, setStatutFilter] = useState("");
  const [filterMois, setFilterMois] = useState(String(now.getMonth() + 1));
  const [filterAnnee, setFilterAnnee] = useState(String(now.getFullYear()));
  const [open, setOpen] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const { toast } = useToast();

  const { data, isLoading } = useDepenses({
    page,
    per_page: 15,
    statut: statutFilter || undefined,
    mois: filterMois || undefined,
    annee: filterAnnee || undefined,
  });
  const { data: categoriesRes } = useCategoriesDepense();
  const { data: comptesRes } = useComptesTresorerieOptions();
  const { data: modesRes } = useModesPaiementOptions();
  const createDepense = useCreateDepense();

  const categories = categoriesRes?.data ?? [];
  const comptes = comptesRes?.data ?? [];
  const modeOptions = useMemo(() => {
    const modes = modesRes?.data ?? [];
    return modes.map((m) => ({ value: m.code, label: m.libelle }));
  }, [modesRes]);

  const anneeOptions = useMemo(() => {
    const current = now.getFullYear();
    return Array.from({ length: 6 }, (_, i) => {
      const year = String(current - i);
      return { value: year, label: year };
    });
  }, [now]);

  const moisOptions = useMemo(
    () =>
      MOIS_LABELS.map((label, i) => ({
        value: String(i + 1),
        label,
      })),
    [],
  );

  const totalMontant = Number(data?.summary?.total_montant ?? 0);
  const periodeLabel =
    filterMois && filterAnnee
      ? labelMoisAnnee(Number(filterMois), Number(filterAnnee))
      : "période sélectionnée";

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
      <div className="space-y-4">
        <PageHeader
          title="Dépenses"
          description="Dépenses d’entreprise (hors salaires). Chaque saisie crée une sortie de trésorerie."
          actions={
            <Button
              type="button"
              onClick={() => {
                reset({
                  ...emptyDefaults,
                  compte_tresorerie_id: comptes[0]?.id ?? "",
                  categorie_depense_id: categories[0]?.id ?? "",
                  mode: modeOptions[0]?.value ?? "especes",
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

        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <Select
            label="Mois"
            value={filterMois}
            onChange={(e) => {
              setFilterMois(e.target.value);
              setPage(1);
            }}
            options={moisOptions}
          />
          <Select
            label="Année"
            value={filterAnnee}
            onChange={(e) => {
              setFilterAnnee(e.target.value);
              setPage(1);
            }}
            options={anneeOptions}
          />
          <Select
            label="Statut"
            value={statutFilter}
            onChange={(e) => {
              setStatutFilter(e.target.value);
              setPage(1);
            }}
            options={[...STATUT_FILTER_OPTIONS]}
          />
          <StatCard
            label="Total du mois"
            value={formatFcfa(totalMontant)}
            hint={
              statutFilter
                ? `${periodeLabel} · ${STATUT_LABELS[statutFilter] ?? labelize(statutFilter)}`
                : `${periodeLabel} · dépenses validées`
            }
            icon={<Wallet className="size-4" />}
            className="border-teal/25 bg-gradient-to-br from-teal/[0.08] to-white sm:col-span-2 lg:col-span-1"
          />
        </div>

        <DataTable
          columns={columns}
          data={data?.data ?? []}
          isLoading={isLoading}
          page={page}
          pageCount={data?.meta?.last_page ?? 1}
          onPageChange={setPage}
          perPage={15}
          total={data?.meta?.total}
        />
      </div>

      <Modal
        open={open}
        onClose={() => !isSubmitting && setOpen(false)}
        title="Nouvelle dépense"
      >
        <form className="space-y-3" onSubmit={onSubmit} noValidate>
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
            label="Montant"
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
              label: c.libelle,
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
              Fermer
            </Button>
            <Button type="submit" disabled={isSubmitting}>
              Enregistrer
            </Button>
          </div>
        </form>
      </Modal>
    </PermissionGate>
  );
}
