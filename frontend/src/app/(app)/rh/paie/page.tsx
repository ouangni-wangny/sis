"use client";

import Link from "next/link";
import { useCallback, useMemo, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { type ColumnDef } from "@tanstack/react-table";
import {
  ArrowLeft,
  CalendarRange,
  CheckCircle2,
  Download,
  FileText,
  Lock,
  Plus,
  Wallet,
} from "lucide-react";
import { useDebouncedValue } from "@/application/hooks/useDebouncedValue";
import {
  useBulletinsPaie,
  useCloturerPeriodePaie,
  useCreatePeriodePaie,
  useGenererBulletinPdf,
  useGenererBulletinsPaie,
  useMarquerBulletinPaye,
  usePeriodesPaie,
  useValiderPeriodePaie,
} from "@/application/hooks/useResources";
import type { BulletinPaie, PeriodePaie } from "@/domain/types/entities";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { DataTable } from "@/presentation/components/tables/DataTable";
import { TableActions } from "@/presentation/components/tables/TableActions";
import { Alert } from "@/presentation/components/ui/Alert";
import { Badge, statusTone } from "@/presentation/components/ui/Badge";
import { Button } from "@/presentation/components/ui/Button";
import { Card, CardBody, CardHeader } from "@/presentation/components/ui/Card";
import { RequiredFieldsLegend } from "@/presentation/components/ui/FieldLabel";
import { Input } from "@/presentation/components/ui/Input";
import { Modal } from "@/presentation/components/ui/Modal";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { Select } from "@/presentation/components/ui/Select";
import { StatCard } from "@/presentation/components/ui/StatCard";
import { Textarea } from "@/presentation/components/ui/Textarea";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { useToast } from "@/presentation/providers/ToastProvider";
import { apiClient } from "@/infrastructure/http/apiClient";
import { bulletinsPaieApi } from "@/infrastructure/http/resources";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { canManagePaie } from "@/shared/lib/can";
import {
  formatDate,
  formatSalaire,
  labelize,
  labelMoisAnnee,
  MOIS_LABELS,
} from "@/shared/lib/format";
import { cn } from "@/shared/lib/cn";

const periodeSchema = z.object({
  mois: z.string().min(1, "Mois requis"),
  annee: z.string().min(4, "Année requise"),
  commentaire: z.string().optional(),
});

type PeriodeFormValues = z.infer<typeof periodeSchema>;

const now = new Date();

const emptyPeriodeDefaults: PeriodeFormValues = {
  mois: String(now.getMonth() + 1),
  annee: String(now.getFullYear()),
  commentaire: "",
};

const STATUT_PERIODE_OPTIONS = [
  { value: "", label: "Tous les statuts" },
  { value: "brouillon", label: "Brouillon" },
  { value: "validee", label: "Validée" },
  { value: "cloturee", label: "Clôturée" },
];

async function downloadBulletinPdf(bulletinId: string) {
  const response = await apiClient.get(bulletinsPaieApi.downloadPdf(bulletinId), {
    responseType: "blob",
  });
  const blob = new Blob([response.data], { type: "application/pdf" });
  const objectUrl = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = objectUrl;
  link.download = `bulletin-${bulletinId}.pdf`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(objectUrl);
}

export default function PaiePage() {
  const { toast } = useToast();
  const { user } = useAuth();
  const canManage = canManagePaie(user);

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [periodeQ, setPeriodeQ] = useState("");
  const [filterAnnee, setFilterAnnee] = useState("");
  const [filterMois, setFilterMois] = useState("");
  const [filterStatut, setFilterStatut] = useState("");
  const periodeSearch = useDebouncedValue(periodeQ);

  const [selectedPeriodeId, setSelectedPeriodeId] = useState<string | null>(null);
  const [bulletinsPage, setBulletinsPage] = useState(1);
  const [bulletinsPerPage, setBulletinsPerPage] = useState(50);
  const [bulletinsQ, setBulletinsQ] = useState("");
  const bulletinsSearch = useDebouncedValue(bulletinsQ);
  const [periodeOpen, setPeriodeOpen] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

  const anneeOptions = useMemo(() => {
    const current = now.getFullYear();
    return [
      { value: "", label: "Toutes les années" },
      ...Array.from({ length: 6 }, (_, i) => {
        const year = String(current - i);
        return { value: year, label: year };
      }),
    ];
  }, []);

  const moisFilterOptions = useMemo(
    () => [
      { value: "", label: "Tous les mois" },
      ...MOIS_LABELS.map((label, i) => ({
        value: String(i + 1),
        label,
      })),
    ],
    [],
  );

  const periodes = usePeriodesPaie({
    page,
    per_page: perPage,
    q: periodeSearch || undefined,
    annee: filterAnnee || undefined,
    mois: filterMois || undefined,
    statut: filterStatut || undefined,
  });
  const periodesBrouillon = usePeriodesPaie({ statut: "brouillon", per_page: 1 });
  const periodesValidees = usePeriodesPaie({ statut: "validee", per_page: 1 });
  const periodesCloturees = usePeriodesPaie({ statut: "cloturee", per_page: 1 });
  const bulletins = useBulletinsPaie(selectedPeriodeId ?? undefined, {
    page: bulletinsPage,
    per_page: bulletinsPerPage,
    q: bulletinsSearch || undefined,
  });
  const createPeriode = useCreatePeriodePaie();
  const genererBulletins = useGenererBulletinsPaie();
  const validerPeriode = useValiderPeriodePaie();
  const cloturerPeriode = useCloturerPeriodePaie();
  const genererPdf = useGenererBulletinPdf();
  const marquerPaye = useMarquerBulletinPaye();

  const selectedPeriode = useMemo(
    () =>
      (periodes.data?.data ?? []).find((p) => p.id === selectedPeriodeId) ??
      null,
    [periodes.data?.data, selectedPeriodeId],
  );

  const setStatutFilter = (statut: string) => {
    setFilterStatut((current) => (current === statut ? "" : statut));
    setPage(1);
  };

  const selectPeriode = (id: string) => {
    setSelectedPeriodeId(id);
    setBulletinsPage(1);
    setBulletinsQ("");
  };

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<PeriodeFormValues>({
    resolver: zodResolver(periodeSchema),
    defaultValues: emptyPeriodeDefaults,
  });

  const periodeBusy = isSubmitting || createPeriode.isPending;

  const closePeriodeModal = () => {
    if (periodeBusy) return;
    setPeriodeOpen(false);
    setFormError(null);
    reset(emptyPeriodeDefaults);
  };

  const runPeriodeAction = useCallback(
    async (action: () => Promise<unknown>, successMessage: string) => {
      try {
        await action();
        toast(successMessage);
      } catch (err) {
        toast(getApiErrorMessage(err, "Échec de l’opération."), "danger");
      }
    },
    [toast],
  );

  const periodeColumns = useMemo<ColumnDef<PeriodePaie>[]>(
    () => [
      {
        id: "periode",
        header: "Période",
        cell: ({ row }) => labelMoisAnnee(row.original.mois, row.original.annee),
      },
      {
        accessorKey: "date_debut",
        header: "Du",
        cell: ({ getValue }) => formatDate(String(getValue())),
      },
      {
        accessorKey: "date_fin",
        header: "Au",
        cell: ({ getValue }) => formatDate(String(getValue())),
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
        accessorKey: "bulletins_count",
        header: "Bulletins",
        cell: ({ getValue }) => String(getValue() ?? 0),
      },
      {
        id: "actions",
        header: "",
        enableSorting: false,
        cell: ({ row }) => (
          <Button
            size="sm"
            variant={
              selectedPeriodeId === row.original.id ? "primary" : "secondary"
            }
            onClick={() => selectPeriode(row.original.id)}
          >
            Voir bulletins
          </Button>
        ),
      },
    ],
    [selectedPeriodeId],
  );

  const bulletinColumns = useMemo<ColumnDef<BulletinPaie>[]>(
    () => [
      {
        id: "agent",
        header: "Agent",
        cell: ({ row }) =>
          row.original.agent
            ? `${row.original.agent.prenom} ${row.original.agent.nom}`
            : "—",
      },
      {
        id: "matricule",
        header: "Matricule",
        cell: ({ row }) => row.original.agent?.matricule ?? "—",
      },
      {
        accessorKey: "salaire_brut",
        header: "Brut",
        cell: ({ getValue }) =>
          formatSalaire(getValue() as string | number | null, user),
      },
      {
        accessorKey: "salaire_net",
        header: "Net",
        cell: ({ getValue }) =>
          formatSalaire(getValue() as string | number | null, user),
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
        accessorKey: "paye_le",
        header: "Payé le",
        cell: ({ getValue }) => formatDate(getValue() as string | null),
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
                key: "pdf",
                label: "Générer PDF",
                icon: FileText,
                hidden: !canManage,
                onClick: () =>
                  void runPeriodeAction(
                    () => genererPdf.mutateAsync(row.original.id),
                    "PDF généré.",
                  ),
              },
              {
                key: "download",
                label: "Télécharger PDF",
                icon: Download,
                onClick: async () => {
                  try {
                    await downloadBulletinPdf(row.original.id);
                  } catch (err) {
                    toast(
                      getApiErrorMessage(err, "Téléchargement impossible."),
                      "danger",
                    );
                  }
                },
              },
              {
                key: "paye",
                label: "Marquer payé",
                tone: "success",
                hidden: !canManage || row.original.statut === "paye",
                onClick: () =>
                  void runPeriodeAction(
                    () => marquerPaye.mutateAsync(row.original.id),
                    "Bulletin marqué payé.",
                  ),
              },
            ]}
          />
        ),
      },
    ],
    [canManage, genererPdf, marquerPaye, runPeriodeAction, toast, user],
  );

  const moisOptions = MOIS_LABELS.map((label, i) => ({
    value: String(i + 1),
    label,
  }));

  const totalPeriodes = periodes.data?.meta.total ?? 0;
  const countBrouillon = periodesBrouillon.data?.meta.total ?? 0;
  const countValidee = periodesValidees.data?.meta.total ?? 0;
  const countCloturee = periodesCloturees.data?.meta.total ?? 0;
  const bulletinsTotal = bulletins.data?.meta.total ?? 0;
  const bulletinsPayes = useMemo(
    () =>
      (bulletins.data?.data ?? []).filter((b) => b.statut === "paye").length,
    [bulletins.data?.data],
  );

  return (
    <PermissionGate permission={["paie.manage", "paie.view"]} title="Paie">
      <div className="space-y-6">
        <PageHeader
          title="Paie"
          description="Périodes mensuelles, génération des bulletins et suivi des paiements."
          actions={
            <div className="flex flex-wrap gap-2">
              <Link href="/rh">
                <Button variant="secondary" size="sm">
                  <ArrowLeft className="size-4" />
                  Contrats &amp; Absences
                </Button>
              </Link>
              {canManage ? (
                <Button size="sm" onClick={() => setPeriodeOpen(true)}>
                  <Plus className="size-4" />
                  Nouvelle période
                </Button>
              ) : null}
            </div>
          }
        />

        {/* Synthèse */}
        <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <button
            type="button"
            className="text-left transition hover:opacity-90"
            onClick={() => {
              setFilterStatut("");
              setPage(1);
            }}
          >
            <StatCard
              label="Périodes"
              value={totalPeriodes}
              hint={filterStatut ? `Filtre : ${labelize(filterStatut)}` : "Toutes"}
              icon={<CalendarRange className="size-4" />}
              className={cn(!filterStatut && "ring-1 ring-teal/30")}
            />
          </button>
          <button
            type="button"
            className="text-left transition hover:opacity-90"
            onClick={() => setStatutFilter("brouillon")}
          >
            <StatCard
              label="Brouillon"
              value={countBrouillon}
              hint="À générer / valider"
              icon={<FileText className="size-4" />}
              className={cn(filterStatut === "brouillon" && "ring-1 ring-teal/30")}
            />
          </button>
          <button
            type="button"
            className="text-left transition hover:opacity-90"
            onClick={() => setStatutFilter("validee")}
          >
            <StatCard
              label="Validées"
              value={countValidee}
              hint="Prêtes à clôturer"
              icon={<CheckCircle2 className="size-4" />}
              className={cn(filterStatut === "validee" && "ring-1 ring-teal/30")}
            />
          </button>
          <button
            type="button"
            className="text-left transition hover:opacity-90"
            onClick={() => setStatutFilter("cloturee")}
          >
            <StatCard
              label="Clôturées"
              value={countCloturee}
              hint="Verrouillées"
              icon={<Lock className="size-4" />}
              className={cn(filterStatut === "cloturee" && "ring-1 ring-teal/30")}
            />
          </button>
        </section>

        {/* Périodes + panneau latéral */}
        <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
          <Card className="min-w-0 overflow-hidden">
            <CardHeader
              title="Périodes de paie"
              description="Sélectionnez une période pour afficher ses bulletins."
              action={
                canManage ? (
                  <Button size="sm" onClick={() => setPeriodeOpen(true)}>
                    <Plus className="size-4" />
                    Nouvelle
                  </Button>
                ) : undefined
              }
            />
            <CardBody>
              <DataTable
                data={periodes.data?.data ?? []}
                columns={periodeColumns}
                isLoading={periodes.isLoading}
                search={{
                  value: periodeQ,
                  onChange: (value) => {
                    setPeriodeQ(value);
                    setPage(1);
                  },
                  placeholder: "Rechercher une période…",
                }}
                toolbar={
                  <div className="flex flex-wrap items-center gap-2">
                    <Select
                      className="min-w-[8.5rem]"
                      value={filterAnnee}
                      options={anneeOptions}
                      onChange={(event) => {
                        setFilterAnnee(event.target.value);
                        setPage(1);
                      }}
                    />
                    <Select
                      className="min-w-[9rem]"
                      value={filterMois}
                      options={moisFilterOptions}
                      onChange={(event) => {
                        setFilterMois(event.target.value);
                        setPage(1);
                      }}
                    />
                    <Select
                      className="min-w-[10rem]"
                      value={filterStatut}
                      options={STATUT_PERIODE_OPTIONS}
                      onChange={(event) => {
                        setFilterStatut(event.target.value);
                        setPage(1);
                      }}
                    />
                  </div>
                }
                pagination={{
                  page,
                  perPage: periodes.data?.meta.per_page ?? perPage,
                  total: periodes.data?.meta.total ?? 0,
                  onPageChange: setPage,
                  onPerPageChange: (n) => {
                    setPerPage(n);
                    setPage(1);
                  },
                }}
                emptyTitle="Aucune période de paie"
                emptyAction={
                  canManage ? (
                    <Button size="sm" onClick={() => setPeriodeOpen(true)}>
                      <Plus className="size-4" />
                      Nouvelle période
                    </Button>
                  ) : undefined
                }
              />
            </CardBody>
          </Card>

          <aside className="space-y-4 xl:sticky xl:top-4 xl:self-start">
            <Card>
              <CardHeader
                title="Période sélectionnée"
                description={
                  selectedPeriode
                    ? labelMoisAnnee(selectedPeriode.mois, selectedPeriode.annee)
                    : "Aucune sélection"
                }
              />
              <CardBody className="space-y-3">
                {!selectedPeriode ? (
                  <p className="text-sm text-ink-faint">
                    Cliquez sur « Voir bulletins » dans le tableau pour ouvrir
                    une période.
                  </p>
                ) : (
                  <>
                    <div className="space-y-1 text-sm">
                      <p className="text-ink-muted">
                        {formatDate(selectedPeriode.date_debut)} →{" "}
                        {formatDate(selectedPeriode.date_fin)}
                      </p>
                      <Badge tone={statusTone(selectedPeriode.statut)}>
                        {labelize(selectedPeriode.statut)}
                      </Badge>
                      <p className="pt-1 text-xs text-ink-faint">
                        {bulletinsTotal} bulletin(s)
                        {bulletinsPayes > 0
                          ? ` · ${bulletinsPayes} payé(s) (page)`
                          : ""}
                      </p>
                    </div>

                    {canManage ? (
                      <div className="flex flex-col gap-2">
                        {selectedPeriode.statut !== "cloturee" ? (
                          <Button
                            size="sm"
                            variant="secondary"
                            loading={genererBulletins.isPending}
                            onClick={() =>
                              void runPeriodeAction(
                                () =>
                                  genererBulletins.mutateAsync(
                                    selectedPeriode.id,
                                  ),
                                "Bulletins générés.",
                              )
                            }
                          >
                            Générer bulletins
                          </Button>
                        ) : (
                          <Alert tone="info">
                            Période clôturée — génération verrouillée.
                          </Alert>
                        )}
                        {selectedPeriode.statut === "brouillon" ? (
                          <Button
                            size="sm"
                            loading={validerPeriode.isPending}
                            onClick={() =>
                              void runPeriodeAction(
                                () =>
                                  validerPeriode.mutateAsync(
                                    selectedPeriode.id,
                                  ),
                                "Période validée.",
                              )
                            }
                          >
                            Valider la période
                          </Button>
                        ) : null}
                        {selectedPeriode.statut === "validee" ? (
                          <Button
                            size="sm"
                            loading={cloturerPeriode.isPending}
                            onClick={() =>
                              void runPeriodeAction(
                                () =>
                                  cloturerPeriode.mutateAsync(
                                    selectedPeriode.id,
                                  ),
                                "Période clôturée.",
                              )
                            }
                          >
                            Clôturer
                          </Button>
                        ) : null}
                      </div>
                    ) : null}
                  </>
                )}
              </CardBody>
            </Card>

            <Card>
              <CardHeader
                title="Circuit paie"
                description="Ordre recommandé des étapes"
              />
              <CardBody>
                <ol className="space-y-2 text-xs text-ink-muted">
                  <li className="flex gap-2">
                    <span className="font-mono font-semibold text-teal">1</span>
                    Créer une période (brouillon)
                  </li>
                  <li className="flex gap-2">
                    <span className="font-mono font-semibold text-teal">2</span>
                    Générer les bulletins
                  </li>
                  <li className="flex gap-2">
                    <span className="font-mono font-semibold text-teal">3</span>
                    Valider la période
                  </li>
                  <li className="flex gap-2">
                    <span className="font-mono font-semibold text-teal">4</span>
                    Marquer payé / PDF, puis clôturer
                  </li>
                </ol>
              </CardBody>
            </Card>

            <Card className="border-teal/20 bg-teal/[0.03]">
              <CardBody className="space-y-2">
                <p className="flex items-center gap-2 text-sm font-semibold text-ink">
                  <Wallet className="size-4 text-teal" />
                  Module RH
                </p>
                <p className="text-xs text-ink-muted">
                  Contrats, absences et alertes d’échéance.
                </p>
                <Link href="/rh" className="block">
                  <Button size="sm" variant="secondary" className="w-full">
                    Retour RH
                  </Button>
                </Link>
              </CardBody>
            </Card>
          </aside>
        </div>

        {/* Bulletins */}
        <Card className="min-w-0 overflow-hidden">
          <CardHeader
            title={
              selectedPeriode
                ? `Bulletins — ${labelMoisAnnee(selectedPeriode.mois, selectedPeriode.annee)}`
                : "Bulletins"
            }
            description={
              selectedPeriode
                ? `${formatDate(selectedPeriode.date_debut)} → ${formatDate(selectedPeriode.date_fin)}`
                : "Sélectionnez une période pour afficher les bulletins."
            }
            action={
              selectedPeriode ? (
                <Badge tone={statusTone(selectedPeriode.statut)}>
                  {labelize(selectedPeriode.statut)}
                </Badge>
              ) : undefined
            }
          />
          <CardBody>
            {selectedPeriode ? (
              <DataTable
                data={bulletins.data?.data ?? []}
                columns={bulletinColumns}
                isLoading={bulletins.isLoading}
                search={{
                  value: bulletinsQ,
                  onChange: (value) => {
                    setBulletinsQ(value);
                    setBulletinsPage(1);
                  },
                  placeholder: "Agent, matricule, statut…",
                }}
                pagination={{
                  page: bulletinsPage,
                  perPage: bulletins.data?.meta.per_page ?? bulletinsPerPage,
                  total: bulletins.data?.meta.total ?? 0,
                  onPageChange: setBulletinsPage,
                  onPerPageChange: (n) => {
                    setBulletinsPerPage(n);
                    setBulletinsPage(1);
                  },
                }}
                emptyTitle="Aucun bulletin pour cette période"
              />
            ) : (
              <Alert tone="info">
                Sélectionnez une période dans le tableau ci-dessus pour afficher
                et gérer les bulletins.
              </Alert>
            )}
          </CardBody>
        </Card>

        <Modal
          open={periodeOpen}
          onClose={closePeriodeModal}
          preventClose={periodeBusy}
          title="Nouvelle période de paie"
          description="Créez une période mensuelle pour générer les bulletins."
          footer={
            <>
              <Button
                variant="secondary"
                onClick={closePeriodeModal}
                disabled={periodeBusy}
              >
                Annuler
              </Button>
              <Button type="submit" form="periode-form" loading={periodeBusy}>
                Créer
              </Button>
            </>
          }
        >
          <form
            id="periode-form"
            className="space-y-3"
            onSubmit={handleSubmit(async (values) => {
              setFormError(null);
              try {
                const result = await createPeriode.mutateAsync({
                  mois: Number(values.mois),
                  annee: Number(values.annee),
                  commentaire: values.commentaire || null,
                });
                toast("Période créée.");
                selectPeriode(result.data.id);
                closePeriodeModal();
              } catch (err) {
                setFormError(
                  getApiErrorMessage(err, "Échec de la création."),
                );
              }
            })}
          >
            <RequiredFieldsLegend />
            {formError ? <Alert tone="danger">{formError}</Alert> : null}
            <div className="grid gap-3 sm:grid-cols-2">
              <Select
                label="Mois"
                requiredMark
                options={moisOptions}
                error={errors.mois?.message}
                {...register("mois")}
              />
              <Input
                label="Année"
                requiredMark
                type="number"
                min={2000}
                max={2100}
                error={errors.annee?.message}
                {...register("annee")}
              />
            </div>
            <Textarea
              label="Commentaire"
              optionalMark
              error={errors.commentaire?.message}
              {...register("commentaire")}
            />
          </form>
        </Modal>
      </div>
    </PermissionGate>
  );
}
