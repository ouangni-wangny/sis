"use client";

import Link from "next/link";
import { useCallback, useEffect, useMemo, useState } from "react";
import { useForm, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { type ColumnDef } from "@tanstack/react-table";
import {
  AlertTriangle,
  CalendarOff,
  FileDown,
  FileText,
  Plus,
  Settings2,
  Wallet,
} from "lucide-react";
import { useDebouncedValue } from "@/application/hooks/useDebouncedValue";
import {
  useAbsences,
  useAgents,
  useContratAlerts,
  useContrats,
  useCreateAbsence,
  useCreateContrat,
  useDeleteAbsence,
  useDeleteContrat,
  useExportContratsPdf,
  useUpdateAbsence,
  useUpdateContrat,
  useVilles,
} from "@/application/hooks/useResources";
import {
  absenceSchema,
  absenceSideEffectMessage,
  formatAbsenceDuration,
  isTerminalAbsenceStatut,
  labelSourceAbsence,
  labelTypeAbsence,
  sourceAbsenceTone,
  statutAbsenceOptions,
  TYPE_ABSENCE_OPTIONS,
  STATUT_ABSENCE_FILTER_OPTIONS,
  type AbsenceFormValues,
  type StatutAbsence,
} from "@/domain/schemas/absence";
import {
  contratSchema,
  previewRemunerationCi,
  STATUT_CONTRAT_FILTER_OPTIONS,
  TYPE_CONTRAT_FILTER_OPTIONS,
  type ContratFormValues,
} from "@/domain/schemas/contrat";
import {
  getContratReminders,
  getReglesTypeContrat,
  formatContratDuree,
  hintDateFin,
  hintPeriodeEssai,
  hintPeriodeEssaiVerrouillee,
  type TypeContrat,
} from "@/domain/schemas/contrat-rules";
import type { Absence, Contrat } from "@/domain/types/entities";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { DataTable } from "@/presentation/components/tables/DataTable";
import { TableActions, tableActionIcons } from "@/presentation/components/tables/TableActions";
import { Alert } from "@/presentation/components/ui/Alert";
import { Badge, statusTone } from "@/presentation/components/ui/Badge";
import { Button } from "@/presentation/components/ui/Button";
import { Card, CardBody, CardHeader } from "@/presentation/components/ui/Card";
import { ConfirmDialog } from "@/presentation/components/ui/ConfirmDialog";
import { RequiredFieldsLegend } from "@/presentation/components/ui/FieldLabel";
import { DatePicker } from "@/presentation/components/ui/DatePicker";
import { Input } from "@/presentation/components/ui/Input";
import { Modal } from "@/presentation/components/ui/Modal";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { Select } from "@/presentation/components/ui/Select";
import { StatCard } from "@/presentation/components/ui/StatCard";
import { TabPanel, Tabs } from "@/presentation/components/ui/Tabs";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { useToast } from "@/presentation/providers/ToastProvider";
import { getApiErrorMessage, getApiErrorMessageAsync } from "@/shared/lib/api-error";
import {
  can,
  canManageAbsences,
  canManageContrats,
  canViewAbsences,
  canViewContrats,
  canViewPaie,
} from "@/shared/lib/can";
import { formatDate, formatSalaire, labelize } from "@/shared/lib/format";

const ALERTE_LABELS: Record<string, string> = {
  fin_essai: "Fin période d’essai",
  fin_contrat: "Fin de contrat",
};

const emptyAbsenceDefaults: AbsenceFormValues = {
  agent_id: "",
  type: "conge",
  date_debut: new Date().toISOString().slice(0, 10),
  date_fin: new Date().toISOString().slice(0, 10),
  motif: "",
  statut: "en_attente",
};

function toastAbsenceSideEffects(
  toast: ReturnType<typeof useToast>["toast"],
  data: Absence,
  baseMessage: string,
) {
  toast(baseMessage);
  const sideEffect = absenceSideEffectMessage(data);
  if (sideEffect) {
    toast(sideEffect, "info");
  }
}

const emptyContratDefaults: ContratFormValues = {
  agent_id: "",
  type: "cdi",
  reference: "",
  date_debut: new Date().toISOString().slice(0, 10),
  date_fin: "",
  periode_essai_mois: "3",
  salaire_base: "",
  indemnite_fonction: "",
  prime_responsabilite: "",
  prime_transport: "",
  prime_entretien_tenue: "",
  sursalaire: "",
  parts_igr: "",
  statut: "actif",
};

function toNumberOrNull(value: string | number | undefined | null) {
  if (value === "" || value == null) return null;
  const n = Number(value);
  return Number.isFinite(n) ? n : null;
}

export default function RhPage() {
  const { toast } = useToast();
  const { user } = useAuth();
  const canAbsences = canManageAbsences(user);
  const canViewAbs = canViewAbsences(user);
  const canContrats = canManageContrats(user);
  const canViewCont = canViewContrats(user);
  const canGrades = can(user, "grades.manage");
  const [rhTab, setRhTab] = useState<"contrats" | "absences">(
    canViewCont ? "contrats" : "absences",
  );

  const { data: alertsData } = useContratAlerts({ enabled: canViewCont });
  const contratAlertes = alertsData?.data ?? [];

  const { data: agentsData } = useAgents({ all: true });
  const agentOptions = useMemo(
    () =>
      (agentsData?.data ?? []).map((a) => ({
        value: a.id,
        label: `${a.prenom} ${a.nom} (${a.matricule})`,
      })),
    [agentsData],
  );

  // --- Absences ---
  const [absencePage, setAbsencePage] = useState(1);
  const [absencePerPage, setAbsencePerPage] = useState(15);
  const [absenceQ, setAbsenceQ] = useState("");
  const [absenceStatutFilter, setAbsenceStatutFilter] = useState("");
  const absenceSearch = useDebouncedValue(absenceQ);
  const [absenceOpen, setAbsenceOpen] = useState(false);
  const [editingAbsence, setEditingAbsence] = useState<Absence | null>(null);
  const [absenceToDelete, setAbsenceToDelete] = useState<Absence | null>(null);
  const [absenceFormError, setAbsenceFormError] = useState<string | null>(null);

  const absences = useAbsences({
    page: absencePage,
    per_page: absencePerPage,
    q: absenceSearch || undefined,
    statut: absenceStatutFilter || undefined,
  });
  const absencesEnAttente = useAbsences(
    { statut: "en_attente", per_page: 1 },
    { enabled: canViewAbs },
  );
  const createAbsence = useCreateAbsence();
  const updateAbsence = useUpdateAbsence();
  const deleteAbsence = useDeleteAbsence();

  const {
    register: registerAbsence,
    handleSubmit: handleSubmitAbsence,
    reset: resetAbsence,
    formState: { errors: absenceErrors, isSubmitting: absenceSubmitting },
  } = useForm<AbsenceFormValues>({
    resolver: zodResolver(absenceSchema),
    mode: "onBlur",
    reValidateMode: "onChange",
    defaultValues: emptyAbsenceDefaults,
  });

  const absenceBusy =
    absenceSubmitting || createAbsence.isPending || updateAbsence.isPending;

  const absenceStatutSelectOptions = useMemo(
    () =>
      statutAbsenceOptions(
        (editingAbsence?.statut as StatutAbsence | undefined) ?? null,
        !editingAbsence,
      ),
    [editingAbsence],
  );
  const absenceFormReadOnly =
    !!editingAbsence && isTerminalAbsenceStatut(editingAbsence.statut);

  const handleAbsenceTransition = useCallback(
    async (row: Absence, statut: StatutAbsence, baseMessage: string) => {
      if (!canAbsences) {
        toast("Vous n’avez pas le droit de modifier une absence.", "danger");
        return;
      }
      try {
        const result = await updateAbsence.mutateAsync({
          id: row.id,
          payload: { statut },
        });
        toastAbsenceSideEffects(toast, result.data, baseMessage);
      } catch (err) {
        toast(getApiErrorMessage(err, "Échec de la mise à jour."), "danger");
      }
    },
    [canAbsences, toast, updateAbsence],
  );

  const closeAbsenceModal = () => {
    if (absenceBusy) return;
    setAbsenceOpen(false);
    setEditingAbsence(null);
    setAbsenceFormError(null);
    resetAbsence(emptyAbsenceDefaults);
  };

  const openCreateAbsence = () => {
    if (!canAbsences) {
      toast("Vous n’avez pas le droit de créer une absence.", "danger");
      return;
    }
    setEditingAbsence(null);
    resetAbsence(emptyAbsenceDefaults);
    setAbsenceFormError(null);
    setAbsenceOpen(true);
  };

  const openEditAbsence = useCallback(
    (row: Absence) => {
      if (!canAbsences) {
        toast("Vous n’avez pas le droit de modifier une absence.", "danger");
        return;
      }
      setEditingAbsence(row);
      resetAbsence({
        agent_id: row.agent_id,
        type: (row.type as AbsenceFormValues["type"]) || "autre",
        date_debut: row.date_debut?.slice(0, 10) ?? "",
        date_fin: row.date_fin?.slice(0, 10) ?? "",
        motif: row.motif ?? "",
        statut: (row.statut as AbsenceFormValues["statut"]) || "en_attente",
      });
      setAbsenceFormError(null);
      setAbsenceOpen(true);
    },
    [canAbsences, resetAbsence, toast],
  );

  const absenceColumns = useMemo<ColumnDef<Absence>[]>(
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
        accessorKey: "type",
        header: "Type",
        cell: ({ getValue }) => labelTypeAbsence(String(getValue())),
      },
      {
        id: "source",
        header: "Origine",
        cell: ({ row }) => (
          <Badge tone={sourceAbsenceTone(row.original.source)}>
            {labelSourceAbsence(row.original.source)}
          </Badge>
        ),
      },
      {
        accessorKey: "date_debut",
        header: "Du",
        cell: ({ getValue }) => formatDate(String(getValue())),
      },
      {
        accessorKey: "date_fin",
        header: "Au",
        cell: ({ getValue }) =>
          formatDate(getValue() as string | null | undefined),
      },
      {
        id: "duree",
        header: "Durée",
        cell: ({ row }) =>
          formatAbsenceDuration(row.original.date_debut, row.original.date_fin),
      },
      {
        accessorKey: "motif",
        header: "Motif",
        cell: ({ getValue }) => (getValue() as string | null) || "—",
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
        cell: ({ row }) => {
          const statut = row.original.statut;
          const terminal = isTerminalAbsenceStatut(statut);

          return (
            <TableActions
              canEdit={canAbsences && !terminal}
              canDelete={canAbsences}
              onEdit={() => openEditAbsence(row.original)}
              onDelete={() => setAbsenceToDelete(row.original)}
              items={[
                {
                  key: "approve",
                  label: "Approuver",
                  icon: tableActionIcons.resolve,
                  tone: "success",
                  hidden: !canAbsences || statut !== "en_attente",
                  onClick: () =>
                    void handleAbsenceTransition(
                      row.original,
                      "approuvee",
                      "Absence approuvée.",
                    ),
                },
                {
                  key: "reject",
                  label: "Refuser",
                  tone: "danger",
                  hidden: !canAbsences || statut !== "en_attente",
                  onClick: () =>
                    void handleAbsenceTransition(
                      row.original,
                      "refusee",
                      "Absence refusée.",
                    ),
                },
                {
                  key: "cancel",
                  label: "Annuler",
                  tone: "warning",
                  hidden: !canAbsences || statut !== "approuvee",
                  onClick: () =>
                    void handleAbsenceTransition(
                      row.original,
                      "annulee",
                      "Absence annulée.",
                    ),
                },
              ]}
            />
          );
        },
      },
    ],
    [canAbsences, handleAbsenceTransition, openEditAbsence],
  );

  // --- Contrats ---
  const [contratPage, setContratPage] = useState(1);
  const [contratPerPage, setContratPerPage] = useState(15);
  const [contratQ, setContratQ] = useState("");
  const [contratTypeFilter, setContratTypeFilter] = useState("");
  const [contratStatutFilter, setContratStatutFilter] = useState("");
  const [contratVilleFilter, setContratVilleFilter] = useState("");
  const contratSearch = useDebouncedValue(contratQ);
  const [contratOpen, setContratOpen] = useState(false);
  const [editingContrat, setEditingContrat] = useState<Contrat | null>(null);
  const [contratToDelete, setContratToDelete] = useState<Contrat | null>(null);
  const [contratFormError, setContratFormError] = useState<string | null>(null);
  const [contratSurveillanceOnly, setContratSurveillanceOnly] = useState(false);
  const [contratPdfLoading, setContratPdfLoading] = useState(false);

  const { data: villesData } = useVilles({ all: true });
  const villeFilterOptions = useMemo(
    () => [
      { value: "", label: "Toutes les villes" },
      ...(villesData?.data ?? []).map((v) => ({
        value: v.id,
        label: v.libelle,
      })),
    ],
    [villesData],
  );

  const contratListParams = {
    page: contratPage,
    per_page: contratPerPage,
    q: contratSearch || undefined,
    type: contratTypeFilter || undefined,
    statut: contratStatutFilter || undefined,
    ville_id: contratVilleFilter || undefined,
    surveillance: contratSurveillanceOnly || undefined,
  };

  const contrats = useContrats(contratListParams, { enabled: canViewCont });

  const contratsCdi = useContrats(
    { type: "cdi", per_page: 1, valide: true },
    { enabled: canViewCont },
  );
  const contratsCdd = useContrats(
    { type: "cdd", per_page: 1, valide: true },
    { enabled: canViewCont },
  );
  const contratsPrestation = useContrats(
    { type: "prestation", per_page: 1, valide: true },
    { enabled: canViewCont },
  );
  const contratsStage = useContrats(
    { type: "stage", per_page: 1, valide: true },
    { enabled: canViewCont },
  );

  const createContrat = useCreateContrat();
  const updateContrat = useUpdateContrat();
  const deleteContrat = useDeleteContrat();
  const exportContratsPdf = useExportContratsPdf();

  const focusContratsByType = (type: string) => {
    setRhTab("contrats");
    setContratQ("");
    setContratTypeFilter(type);
    setContratStatutFilter("actif");
    setContratVilleFilter("");
    setContratSurveillanceOnly(false);
    setContratPage(1);
  };

  const handleExportContratsPdf = async () => {
    if (!canViewCont) {
      toast("Vous n’avez pas le droit d’exporter la liste.", "danger");
      return;
    }
    setContratPdfLoading(true);
    try {
      const data = await exportContratsPdf.mutateAsync({
        q: contratSearch || undefined,
        type: contratTypeFilter || undefined,
        statut: contratStatutFilter || undefined,
        ville_id: contratVilleFilter || undefined,
        surveillance: contratSurveillanceOnly || undefined,
      });
      const blob =
        data instanceof Blob
          ? data
          : new Blob([data as BlobPart], { type: "application/pdf" });
      const objectUrl = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = objectUrl;
      const suffix =
        [
          contratTypeFilter,
          contratStatutFilter,
          contratVilleFilter ? "ville" : "",
          contratSurveillanceOnly ? "alertes" : "",
        ]
          .filter(Boolean)
          .join("-") || "tous";
      link.download = `contrats-${suffix}-${new Date().toISOString().slice(0, 10)}.pdf`;
      link.rel = "noopener";
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(objectUrl);
      toast("PDF téléchargé.", "success");
    } catch (err) {
      toast(
        await getApiErrorMessageAsync(err, "Échec de la génération du PDF."),
        "danger",
      );
    } finally {
      setContratPdfLoading(false);
    }
  };

  const {
    register: registerContrat,
    handleSubmit: handleSubmitContrat,
    reset: resetContrat,
    control: controlContrat,
    setValue: setValueContrat,
    formState: { errors: contratErrors, isSubmitting: contratSubmitting },
  } = useForm<ContratFormValues>({
    resolver: zodResolver(contratSchema),
    mode: "onBlur",
    reValidateMode: "onChange",
    defaultValues: emptyContratDefaults,
  });

  const contratType = useWatch({ control: controlContrat, name: "type" }) as TypeContrat;
  const contratAgentId = useWatch({ control: controlContrat, name: "agent_id" });
  const remuWatch = useWatch({
    control: controlContrat,
    name: [
      "salaire_base",
      "indemnite_fonction",
      "prime_responsabilite",
      "prime_transport",
      "prime_entretien_tenue",
      "sursalaire",
      "parts_igr",
    ],
  });
  const contratRegles = useMemo(
    () => getReglesTypeContrat(contratType ?? "cdi"),
    [contratType],
  );

  useEffect(() => {
    if (!contratOpen || editingContrat) return;
    if (!contratRegles.essaiVisible) {
      setValueContrat("periode_essai_mois", "");
    } else if (contratRegles.essaiDefautMois != null) {
      setValueContrat(
        "periode_essai_mois",
        String(contratRegles.essaiDefautMois),
      );
    }
    if (!contratRegles.needsDateFin) {
      setValueContrat("date_fin", "");
    }
  }, [
    contratOpen,
    editingContrat,
    contratRegles.essaiVisible,
    contratRegles.essaiDefautMois,
    contratRegles.needsDateFin,
    setValueContrat,
  ]);

  const contratsRows = contrats.data?.data ?? [];

  const selectedContratAgent = useMemo(
    () => (agentsData?.data ?? []).find((a) => a.id === contratAgentId),
    [agentsData, contratAgentId],
  );
  const remuPreview = useMemo(
    () =>
      previewRemunerationCi({
        salaire_base: remuWatch[0],
        indemnite_fonction: remuWatch[1],
        prime_responsabilite: remuWatch[2],
        prime_transport: remuWatch[3],
        prime_entretien_tenue: remuWatch[4],
        sursalaire: remuWatch[5],
        parts_igr: remuWatch[6],
        nombre_enfants: selectedContratAgent?.nombre_enfants ?? 0,
        situation_matrimoniale: selectedContratAgent?.situation_matrimoniale,
      }),
    [remuWatch, selectedContratAgent],
  );
  const contratBusy =
    contratSubmitting || createContrat.isPending || updateContrat.isPending;

  const closeContratModal = () => {
    if (contratBusy) return;
    setContratOpen(false);
    setEditingContrat(null);
    setContratFormError(null);
    resetContrat(emptyContratDefaults);
  };

  const openCreateContrat = () => {
    if (!canContrats) {
      toast("Vous n’avez pas le droit de créer un contrat.", "danger");
      return;
    }
    setEditingContrat(null);
    resetContrat(emptyContratDefaults);
    setContratFormError(null);
    setContratOpen(true);
  };

  const openEditContrat = useCallback(
    (row: Contrat) => {
      if (!canContrats) {
        toast("Vous n’avez pas le droit de modifier un contrat.", "danger");
        return;
      }
      setEditingContrat(row);
      resetContrat({
        agent_id: row.agent_id,
        type: row.type as ContratFormValues["type"],
        reference: row.reference ?? "",
        date_debut: String(row.date_debut).slice(0, 10),
        date_fin: row.date_fin ? String(row.date_fin).slice(0, 10) : "",
        periode_essai_mois:
          row.periode_essai_mois != null ? String(row.periode_essai_mois) : "",
        salaire_base:
          row.salaire_base != null
            ? String(row.salaire_base)
            : row.salaire_brut != null
              ? String(row.salaire_brut)
              : "",
        indemnite_fonction:
          row.indemnite_fonction != null ? String(row.indemnite_fonction) : "",
        prime_responsabilite:
          row.prime_responsabilite != null
            ? String(row.prime_responsabilite)
            : "",
        prime_transport:
          row.prime_transport != null ? String(row.prime_transport) : "",
        prime_entretien_tenue:
          row.prime_entretien_tenue != null
            ? String(row.prime_entretien_tenue)
            : "",
        sursalaire: row.sursalaire != null ? String(row.sursalaire) : "",
        parts_igr: row.parts_igr != null ? String(row.parts_igr) : "",
        statut: (row.statut as ContratFormValues["statut"]) || "actif",
      });
      setContratFormError(null);
      setContratOpen(true);
    },
    [canContrats, resetContrat, toast],
  );

  const contratColumns = useMemo<ColumnDef<Contrat>[]>(
    () => [
      {
        accessorKey: "reference",
        header: "Référence",
        cell: ({ getValue }) => (
          <span className="font-mono text-xs">
            {(getValue() as string | null) || "—"}
          </span>
        ),
      },
      {
        id: "agent",
        header: "Agent",
        cell: ({ row }) =>
          row.original.agent
            ? `${row.original.agent.prenom} ${row.original.agent.nom}`
            : "—",
      },
      {
        accessorKey: "type",
        header: "Type",
        cell: ({ getValue }) => labelize(String(getValue())),
      },
      {
        accessorKey: "date_debut",
        header: "Début",
        cell: ({ getValue }) => formatDate(String(getValue())),
      },
      {
        accessorKey: "date_fin",
        header: "Fin",
        cell: ({ getValue }) =>
          formatDate(getValue() as string | null | undefined),
      },
      {
        accessorKey: "duree_mois",
        header: "Durée",
        cell: ({ getValue }) => formatContratDuree(getValue() as number | null),
      },
      {
        id: "alertes",
        header: "Alertes",
        cell: ({ row }) => {
          const reminders = getContratReminders({
            statut: row.original.statut,
            date_debut: String(row.original.date_debut),
            date_fin: row.original.date_fin
              ? String(row.original.date_fin)
              : null,
            periode_essai_mois: row.original.periode_essai_mois,
          });
          if (reminders.length === 0) {
            return <span className="text-xs text-ink-faint">—</span>;
          }
          return (
            <div className="flex flex-wrap gap-1">
              {reminders.map((r) => (
                <Badge key={r.key} tone={r.tone}>
                  {r.label}
                </Badge>
              ))}
            </div>
          );
        },
      },
      {
        accessorKey: "salaire_brut",
        header: "Salaire brut",
        cell: ({ row }) =>
          formatSalaire(
            (row.original.salaire_brut ?? row.original.salaire) as
              | string
              | number
              | null,
            user,
          ),
      },
      {
        accessorKey: "salaire_net",
        header: "Salaire net",
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
        id: "actions",
        header: "Actions",
        enableSorting: false,
        cell: ({ row }) => (
          <TableActions
            canEdit={canContrats}
            canDelete={canContrats}
            onEdit={() => openEditContrat(row.original)}
            onDelete={() => setContratToDelete(row.original)}
          />
        ),
      },
    ],
    [canContrats, openEditContrat, user],
  );

  const rhTabs = useMemo(
    () =>
      [
        canViewCont
          ? {
              id: "contrats" as const,
              label: "Contrats",
              count: contrats.data?.meta.total ?? 0,
            }
          : null,
        canViewAbs
          ? {
              id: "absences" as const,
              label: "Absences",
              count: absences.data?.meta.total ?? 0,
            }
          : null,
      ].filter(Boolean) as Array<{
        id: "contrats" | "absences";
        label: string;
        count: number;
      }>,
    [
      canViewCont,
      canViewAbs,
      contrats.data?.meta.total,
      absences.data?.meta.total,
    ],
  );

  const canPaie = canViewPaie(user);
  const pendingAbsencesCount = absencesEnAttente.data?.meta.total ?? 0;
  const contratsTotal = contrats.data?.meta.total ?? 0;
  const aSurveillerCount = useMemo(
    () => new Set(contratAlertes.map((a) => a.contrat_id)).size,
    [contratAlertes],
  );

  const toggleContratsSurveillance = () => {
    setRhTab("contrats");
    setContratSurveillanceOnly((v) => !v);
    setContratPage(1);
  };

  const focusAbsencesPending = () => {
    setRhTab("absences");
    setAbsenceStatutFilter("en_attente");
    setAbsencePage(1);
  };

  return (
    <PermissionGate
      permission={[
        "contrats.manage",
        "contrats.view",
        "contrats.alerts",
        "absences.manage",
        "absences.view",
      ]}
      title="Ressources humaines"
    >
      <div className="space-y-6">
        <PageHeader
          title="Ressources humaines"
          description="Pilotage des contrats, absences et alertes d’échéance."
          actions={
            <div className="flex flex-wrap gap-2">
              {canPaie ? (
                <Link href="/rh/paie">
                  <Button variant="secondary" size="sm">
                    <Wallet className="size-4" />
                    Paie
                  </Button>
                </Link>
              ) : null}
              {canGrades ? (
                <Link href="/parametres">
                  <Button variant="secondary" size="sm">
                    <Settings2 className="size-4" />
                    Grades
                  </Button>
                </Link>
              ) : null}
              {canAbsences ? (
                <Button size="sm" variant="secondary" onClick={openCreateAbsence}>
                  <Plus className="size-4" />
                  Absence
                </Button>
              ) : null}
              {canContrats ? (
                <Button size="sm" onClick={openCreateContrat}>
                  <Plus className="size-4" />
                  Contrat
                </Button>
              ) : null}
            </div>
          }
        />

        {/* Synthèse */}
        <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          {canViewCont ? (
            <button
              type="button"
              className="text-left transition hover:opacity-90"
              onClick={() => {
                setRhTab("contrats");
                setContratSurveillanceOnly(false);
                setContratTypeFilter("");
                setContratStatutFilter("");
                setContratVilleFilter("");
                setContratQ("");
                setContratPage(1);
              }}
            >
              <StatCard
                label="Contrats"
                value={contratsTotal}
                hint={
                  aSurveillerCount > 0
                    ? `${aSurveillerCount} à surveiller`
                    : "Tous statuts"
                }
                icon={<FileText className="size-4" />}
              />
            </button>
          ) : null}
          {canViewCont ? (
            <button
              type="button"
              className={`text-left transition hover:opacity-90 ${
                contratSurveillanceOnly
                  ? "rounded-xl ring-2 ring-brand/40 ring-offset-2 ring-offset-paper"
                  : ""
              }`}
              onClick={toggleContratsSurveillance}
            >
              <StatCard
                label="Alertes échéances"
                value={contratAlertes.length}
                hint={
                  contratSurveillanceOnly
                    ? "Filtre actif — cliquer pour désactiver"
                    : contratAlertes.length > 0
                      ? "Essai ou fin de contrat ≤ 30 j"
                      : "Aucune échéance proche"
                }
                icon={<AlertTriangle className="size-4" />}
              />
            </button>
          ) : null}
          {canViewAbs ? (
            <button
              type="button"
              className="text-left transition hover:opacity-90"
              onClick={focusAbsencesPending}
            >
              <StatCard
                label="Absences en attente"
                value={pendingAbsencesCount}
                hint="À valider ou refuser"
                icon={<CalendarOff className="size-4" />}
              />
            </button>
          ) : null}
          {canPaie ? (
            <Link href="/rh/paie" className="block transition hover:opacity-90">
              <StatCard
                label="Paie"
                value="Ouvrir"
                hint="Périodes & bulletins"
                icon={<Wallet className="size-4" />}
              />
            </Link>
          ) : null}
        </section>

        {/* Répartition par type de contrat */}
        {canViewCont ? (
          <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <button
              type="button"
              className="text-left transition hover:opacity-90"
              onClick={() => focusContratsByType("cdi")}
            >
              <StatCard
                label="CDI"
                value={contratsCdi.data?.meta.total ?? "—"}
                hint="Effectif en cours"
                icon={<FileText className="size-4" />}
              />
            </button>
            <button
              type="button"
              className="text-left transition hover:opacity-90"
              onClick={() => focusContratsByType("cdd")}
            >
              <StatCard
                label="CDD"
                value={contratsCdd.data?.meta.total ?? "—"}
                hint="Effectif en cours"
                icon={<FileText className="size-4" />}
              />
            </button>
            <button
              type="button"
              className="text-left transition hover:opacity-90"
              onClick={() => focusContratsByType("prestation")}
            >
              <StatCard
                label="Prestation"
                value={contratsPrestation.data?.meta.total ?? "—"}
                hint="Effectif en cours"
                icon={<FileText className="size-4" />}
              />
            </button>
            <button
              type="button"
              className="text-left transition hover:opacity-90"
              onClick={() => focusContratsByType("stage")}
            >
              <StatCard
                label="Stage"
                value={contratsStage.data?.meta.total ?? "—"}
                hint="Conventions en cours"
                icon={<FileText className="size-4" />}
              />
            </button>
          </section>
        ) : null}

        {/* Contenu principal + panneau latéral */}
        <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
          <Card className="min-w-0 overflow-hidden">
            <div className="border-b border-border px-4 pt-2 sm:px-5">
              <Tabs
                items={rhTabs}
                value={rhTab}
                onChange={(id) => setRhTab(id as "contrats" | "absences")}
              />
            </div>

            <CardBody className="pt-4">
              <TabPanel when="contrats" active={rhTab} className="pt-0">
                {canViewCont ? (
                  <DataTable
                    data={contratsRows}
                    columns={contratColumns}
                    isLoading={contrats.isLoading}
                    search={{
                      value: contratQ,
                      onChange: (value) => {
                        setContratQ(value);
                        setContratPage(1);
                      },
                      placeholder: "Agent, matricule, référence, type…",
                    }}
                    toolbar={
                      <div className="flex flex-wrap items-center gap-2">
                        <Select
                          className="min-w-[10rem]"
                          value={contratTypeFilter}
                          options={TYPE_CONTRAT_FILTER_OPTIONS.map((o) => ({
                            value: o.value,
                            label: o.label,
                          }))}
                          onChange={(event) => {
                            setContratTypeFilter(event.target.value);
                            setContratPage(1);
                          }}
                        />
                        <Select
                          className="min-w-[11rem]"
                          value={contratStatutFilter}
                          options={STATUT_CONTRAT_FILTER_OPTIONS.map((o) => ({
                            value: o.value,
                            label: o.label,
                          }))}
                          onChange={(event) => {
                            setContratStatutFilter(event.target.value);
                            setContratPage(1);
                          }}
                        />
                        <Select
                          className="min-w-[11rem]"
                          aria-label="Filtrer par ville"
                          value={contratVilleFilter}
                          options={villeFilterOptions}
                          onChange={(event) => {
                            setContratVilleFilter(event.target.value);
                            setContratPage(1);
                          }}
                        />
                        <Button
                          type="button"
                          variant={
                            contratSurveillanceOnly ? "primary" : "secondary"
                          }
                          size="sm"
                          aria-pressed={contratSurveillanceOnly}
                          onClick={toggleContratsSurveillance}
                        >
                          <AlertTriangle className="size-4" />
                          Alertes contrats
                          {contratSurveillanceOnly ? " (actif)" : ""}
                        </Button>
                        {canViewCont ? (
                          <Button
                            type="button"
                            variant="secondary"
                            onClick={() => void handleExportContratsPdf()}
                            loading={
                              contratPdfLoading || exportContratsPdf.isPending
                            }
                          >
                            <FileDown className="size-4" />
                            Exporter PDF
                          </Button>
                        ) : null}
                        {canContrats ? (
                          <Button onClick={openCreateContrat}>
                            <Plus className="size-4" />
                            Nouveau contrat
                          </Button>
                        ) : null}
                      </div>
                    }
                    pagination={{
                      page: contratPage,
                      perPage: contrats.data?.meta.per_page ?? contratPerPage,
                      total: contrats.data?.meta.total ?? 0,
                      onPageChange: setContratPage,
                      onPerPageChange: (n) => {
                        setContratPerPage(n);
                        setContratPage(1);
                      },
                    }}
                    emptyTitle="Aucun contrat"
                    emptyAction={
                      canContrats ? (
                        <Button size="sm" onClick={openCreateContrat}>
                          <Plus className="size-4" />
                          Nouveau contrat
                        </Button>
                      ) : undefined
                    }
                  />
                ) : (
                  <Alert tone="warning">
                    Vous n’avez pas accès à la gestion des contrats.
                  </Alert>
                )}
              </TabPanel>

              <TabPanel when="absences" active={rhTab} className="pt-0">
                {canViewAbs ? (
                  <DataTable
                    data={absences.data?.data ?? []}
                    columns={absenceColumns}
                    isLoading={absences.isLoading}
                    search={{
                      value: absenceQ,
                      onChange: (value) => {
                        setAbsenceQ(value);
                        setAbsencePage(1);
                      },
                      placeholder: "Agent, matricule, motif, statut…",
                    }}
                    toolbar={
                      <div className="flex flex-wrap items-center gap-2">
                        <Select
                          className="min-w-[11rem]"
                          value={absenceStatutFilter}
                          options={STATUT_ABSENCE_FILTER_OPTIONS.map((o) => ({
                            value: o.value,
                            label: o.label,
                          }))}
                          onChange={(event) => {
                            setAbsenceStatutFilter(event.target.value);
                            setAbsencePage(1);
                          }}
                        />
                        {canAbsences ? (
                          <Button onClick={openCreateAbsence}>
                            <Plus className="size-4" />
                            Nouvelle absence
                          </Button>
                        ) : null}
                      </div>
                    }
                    pagination={{
                      page: absencePage,
                      perPage: absences.data?.meta.per_page ?? absencePerPage,
                      total: absences.data?.meta.total ?? 0,
                      onPageChange: setAbsencePage,
                      onPerPageChange: (n) => {
                        setAbsencePerPage(n);
                        setAbsencePage(1);
                      },
                    }}
                    emptyTitle="Aucune absence"
                    emptyAction={
                      canAbsences ? (
                        <Button size="sm" onClick={openCreateAbsence}>
                          <Plus className="size-4" />
                          Nouvelle absence
                        </Button>
                      ) : undefined
                    }
                  />
                ) : (
                  <Alert tone="warning">
                    Vous n’avez pas accès à la gestion des absences.
                  </Alert>
                )}
              </TabPanel>
            </CardBody>
          </Card>

          <aside className="space-y-4 xl:sticky xl:top-4 xl:self-start">
            {canViewCont ? (
              <Card>
                <CardHeader
                  title="Alertes contrats"
                  description={
                    contratAlertes.length > 0
                      ? `${contratAlertes.length} échéance(s) à traiter`
                      : "Rien à signaler"
                  }
                  action={
                    contratAlertes.length > 0 ? (
                      <Button
                        size="sm"
                        variant={
                          contratSurveillanceOnly ? "primary" : "secondary"
                        }
                        aria-pressed={contratSurveillanceOnly}
                        onClick={toggleContratsSurveillance}
                      >
                        {contratSurveillanceOnly ? "Masquer" : "Voir"}
                      </Button>
                    ) : undefined
                  }
                />
                <CardBody className="space-y-2 pt-3">
                  {contratAlertes.length === 0 ? (
                    <p className="text-sm text-ink-faint">
                      Aucune fin d’essai ou de contrat dans les 30 prochains
                      jours.
                    </p>
                  ) : (
                    contratAlertes.slice(0, 8).map((a) => (
                      <div
                        key={`${a.contrat_id}-${a.alerte}`}
                        className="flex items-start justify-between gap-2 rounded-md border border-border/70 bg-paper/60 px-3 py-2"
                      >
                        <div className="min-w-0">
                          <p className="truncate text-sm font-medium text-ink">
                            {a.agent_nom}
                          </p>
                          <p className="truncate text-xs text-ink-muted">
                            {a.matricule} ·{" "}
                            {ALERTE_LABELS[a.alerte] ?? labelize(a.alerte)}
                          </p>
                          <p className="mt-0.5 text-[11px] text-ink-faint">
                            {formatDate(a.date_reference)}
                          </p>
                        </div>
                        <Badge
                          tone={a.jours_restants <= 7 ? "danger" : "warning"}
                        >
                          {a.jours_restants} j
                        </Badge>
                      </div>
                    ))
                  )}
                  {contratAlertes.length > 8 ? (
                    <p className="text-xs text-ink-faint">
                      +{contratAlertes.length - 8} autre(s)
                    </p>
                  ) : null}
                </CardBody>
              </Card>
            ) : null}

            {canViewAbs ? (
              <Card>
                <CardHeader
                  title="File d’absences"
                  description="Demandes en attente de décision"
                />
                <CardBody className="space-y-3">
                  <div className="flex items-center justify-between rounded-md bg-paper px-3 py-2">
                    <span className="text-sm text-ink-muted">En attente</span>
                    <span className="font-mono text-lg font-semibold tabular-nums text-ink">
                      {pendingAbsencesCount}
                    </span>
                  </div>
                  <Button
                    size="sm"
                    variant="secondary"
                    className="w-full"
                    onClick={focusAbsencesPending}
                  >
                    Traiter les demandes
                  </Button>
                </CardBody>
              </Card>
            ) : null}

            {canPaie ? (
              <Card className="border-teal/20 bg-teal/[0.03]">
                <CardBody className="space-y-2">
                  <p className="text-sm font-semibold text-ink">Module paie</p>
                  <p className="text-xs text-ink-muted">
                    Périodes, génération des bulletins et exports PDF.
                  </p>
                  <Link href="/rh/paie" className="block">
                    <Button size="sm" className="w-full">
                      <Wallet className="size-4" />
                      Aller à la paie
                    </Button>
                  </Link>
                </CardBody>
              </Card>
            ) : null}
          </aside>
        </div>

        <Modal
          open={absenceOpen}
          onClose={closeAbsenceModal}
          preventClose={absenceBusy}
          size="2xl"
          title={editingAbsence ? "Modifier l’absence" : "Nouvelle absence"}
          description="Déclarez une période d’absence pour un agent."
          footer={
            <>
              <Button
                variant="secondary"
                onClick={closeAbsenceModal}
                disabled={absenceBusy}
              >
                {absenceFormReadOnly ? "Fermer" : "Annuler"}
              </Button>
              {!absenceFormReadOnly ? (
                <Button type="submit" form="absence-form" loading={absenceBusy}>
                  {absenceBusy ? "Enregistrement…" : "Enregistrer"}
                </Button>
              ) : null}
            </>
          }
        >
          <form
            id="absence-form"
            className="space-y-3"
            onSubmit={handleSubmitAbsence(async (values) => {
              setAbsenceFormError(null);
              try {
                if (editingAbsence) {
                  const result = await updateAbsence.mutateAsync({
                    id: editingAbsence.id,
                    payload: {
                      type: values.type,
                      date_debut: values.date_debut,
                      date_fin: values.date_fin,
                      motif: values.motif || null,
                      statut: values.statut,
                    },
                  });
                  toastAbsenceSideEffects(
                    toast,
                    result.data,
                    "Absence mise à jour.",
                  );
                } else {
                  const result = await createAbsence.mutateAsync({
                    agent_id: values.agent_id,
                    type: values.type,
                    date_debut: values.date_debut,
                    date_fin: values.date_fin,
                    motif: values.motif || null,
                    statut: values.statut,
                  });
                  toastAbsenceSideEffects(
                    toast,
                    result.data,
                    "Absence créée.",
                  );
                }
                closeAbsenceModal();
              } catch (err) {
                setAbsenceFormError(
                  getApiErrorMessage(err, "Échec de l’enregistrement."),
                );
              }
            })}
          >
            <RequiredFieldsLegend />
            {absenceFormError ? (
              <Alert tone="danger">{absenceFormError}</Alert>
            ) : null}
            {absenceFormReadOnly ? (
              <Alert tone="info">
                Cette absence est {labelize(editingAbsence?.statut ?? "")} et ne
                peut plus être modifiée.
              </Alert>
            ) : null}
            <Select
              label="Agent"
              requiredMark
              disabled={!!editingAbsence || absenceFormReadOnly}
              options={agentOptions}
              placeholder="Choisir un agent"
              error={absenceErrors.agent_id?.message}
              {...registerAbsence("agent_id")}
            />
            <Select
              label="Type"
              requiredMark
              disabled={absenceFormReadOnly}
              options={TYPE_ABSENCE_OPTIONS.map((o) => ({
                value: o.value,
                label: o.label,
              }))}
              error={absenceErrors.type?.message}
              {...registerAbsence("type")}
            />
            <div className="grid gap-3 sm:grid-cols-2">
              <DatePicker
                label="Date début"
                requiredMark
                disabled={absenceFormReadOnly}
                error={absenceErrors.date_debut?.message}
                {...registerAbsence("date_debut")}
              />
              <DatePicker
                label="Date fin"
                requiredMark
                disabled={absenceFormReadOnly}
                error={absenceErrors.date_fin?.message}
                {...registerAbsence("date_fin")}
              />
            </div>
            <Input
              label="Motif"
              optionalMark
              disabled={absenceFormReadOnly}
              error={absenceErrors.motif?.message}
              {...registerAbsence("motif")}
            />
            <Select
              label="Statut"
              requiredMark
              disabled={absenceFormReadOnly}
              options={absenceStatutSelectOptions}
              error={absenceErrors.statut?.message}
              {...registerAbsence("statut")}
            />
          </form>
        </Modal>

        <ConfirmDialog
          open={!!absenceToDelete}
          onClose={() => {
            if (deleteAbsence.isPending) return;
            setAbsenceToDelete(null);
          }}
          loading={deleteAbsence.isPending}
          title="Supprimer l’absence"
          description="Confirmer la suppression de cette absence ?"
          onConfirm={async () => {
            if (!absenceToDelete) return;
            try {
              const result = await deleteAbsence.mutateAsync(absenceToDelete.id);
              toastAbsenceSideEffects(toast, result.data, "Absence supprimée.");
              setAbsenceToDelete(null);
            } catch (err) {
              toast(
                getApiErrorMessage(err, "Échec de la suppression."),
                "danger",
              );
            }
          }}
        />

        <Modal
          open={contratOpen}
          onClose={closeContratModal}
          preventClose={contratBusy}
          size="2xl"
          title={editingContrat ? "Modifier le contrat" : "Nouveau contrat"}
          description={
            editingContrat
              ? "Agent et type sont figés. Ajustez dates, primes et situation familiale IGR."
              : "Rémunération = base + primes. Brut, parts IGR, CNPS et net sont calculés automatiquement."
          }
          footer={
            <>
              <Button
                variant="secondary"
                onClick={closeContratModal}
                disabled={contratBusy}
              >
                Annuler
              </Button>
              <Button type="submit" form="contrat-form" loading={contratBusy}>
                {contratBusy ? "Enregistrement…" : "Enregistrer"}
              </Button>
            </>
          }
        >
          <form
            id="contrat-form"
            className="space-y-4"
            onSubmit={handleSubmitContrat(async (values) => {
              setContratFormError(null);
              const regles = getReglesTypeContrat(values.type);
              const moneyPayload = {
                salaire_base: toNumberOrNull(values.salaire_base),
                indemnite_fonction: toNumberOrNull(values.indemnite_fonction),
                prime_responsabilite: toNumberOrNull(
                  values.prime_responsabilite,
                ),
                prime_transport: toNumberOrNull(values.prime_transport),
                prime_entretien_tenue: toNumberOrNull(
                  values.prime_entretien_tenue,
                ),
                sursalaire: toNumberOrNull(values.sursalaire),
                parts_igr: toNumberOrNull(values.parts_igr),
              };
              const periodeEssai = regles.essaiVisible
                ? toNumberOrNull(values.periode_essai_mois)
                : null;
              try {
                if (editingContrat) {
                  await updateContrat.mutateAsync({
                    id: editingContrat.id,
                    payload: {
                      date_debut: values.date_debut,
                      date_fin: values.date_fin || null,
                      periode_essai_mois: periodeEssai,
                      ...moneyPayload,
                      statut: values.statut,
                    },
                  });
                  toast("Contrat mis à jour.");
                } else {
                  await createContrat.mutateAsync({
                    agent_id: values.agent_id,
                    type: values.type,
                    date_debut: values.date_debut,
                    date_fin: values.date_fin || null,
                    periode_essai_mois: periodeEssai,
                    ...moneyPayload,
                    statut: values.statut,
                  });
                  toast("Contrat créé.");
                }
                closeContratModal();
              } catch (err) {
                setContratFormError(
                  getApiErrorMessage(err, "Échec de l’enregistrement."),
                );
              }
            })}
          >
            <RequiredFieldsLegend />
            {contratFormError ? (
              <Alert tone="danger">{contratFormError}</Alert>
            ) : null}
            <div className="grid gap-3 sm:grid-cols-3">
              <Select
                label="Agent"
                requiredMark
                disabled={!!editingContrat}
                options={agentOptions}
                placeholder="Choisir un agent"
                error={contratErrors.agent_id?.message}
                {...registerContrat("agent_id")}
              />
              <Select
                label="Type de contrat"
                requiredMark
                disabled={!!editingContrat}
                options={[
                  { value: "cdi", label: "CDI" },
                  { value: "cdd", label: "CDD" },
                  { value: "prestation", label: "Prestation" },
                  { value: "stage", label: "Stage" },
                ]}
                error={contratErrors.type?.message}
                {...registerContrat("type")}
              />
              <Input
                label="Période d’essai (mois)"
                optionalMark
                type="number"
                min={0}
                max={contratRegles.essaiMaxMois ?? undefined}
                inputMode="numeric"
                disabled={!contratRegles.essaiVisible}
                hint={
                  contratRegles.essaiVisible
                    ? (hintPeriodeEssai(contratType) ?? undefined)
                    : hintPeriodeEssaiVerrouillee(contratType)
                }
                error={contratErrors.periode_essai_mois?.message}
                {...registerContrat("periode_essai_mois")}
              />
            </div>
            <div className="grid gap-3 sm:grid-cols-3">
              <DatePicker
                label="Date début"
                requiredMark
                error={contratErrors.date_debut?.message}
                {...registerContrat("date_debut")}
              />
              <DatePicker
                label="Date fin"
                requiredMark={contratRegles.needsDateFin}
                optionalMark={!contratRegles.needsDateFin}
                hint={hintDateFin(contratType)}
                error={contratErrors.date_fin?.message}
                {...registerContrat("date_fin")}
              />
              <Select
                label="Statut"
                requiredMark
                options={[
                  { value: "actif", label: "Actif" },
                  { value: "suspendu", label: "Suspendu" },
                  { value: "termine", label: "Terminé" },
                  { value: "resilie", label: "Résilié" },
                ]}
                error={contratErrors.statut?.message}
                {...registerContrat("statut")}
              />
            </div>

            <p className="text-xs font-semibold uppercase tracking-wide text-ink-muted">
              Rémunération
            </p>
            <div className="grid gap-3 sm:grid-cols-3">
              <Input
                label="Salaire de base (FCFA)"
                optionalMark
                type="number"
                min={0}
                inputMode="numeric"
                error={contratErrors.salaire_base?.message}
                {...registerContrat("salaire_base")}
              />
              <Input
                label="Indemnité de fonction"
                optionalMark
                type="number"
                min={0}
                inputMode="numeric"
                error={contratErrors.indemnite_fonction?.message}
                {...registerContrat("indemnite_fonction")}
              />
              <Input
                label="Prime de responsabilité"
                optionalMark
                type="number"
                min={0}
                inputMode="numeric"
                error={contratErrors.prime_responsabilite?.message}
                {...registerContrat("prime_responsabilite")}
              />
            </div>
            <div className="grid gap-3 sm:grid-cols-3">
              <Input
                label="Prime de transport"
                optionalMark
                type="number"
                min={0}
                inputMode="numeric"
                error={contratErrors.prime_transport?.message}
                {...registerContrat("prime_transport")}
              />
              <Input
                label="Prime d’entretien de tenue"
                optionalMark
                type="number"
                min={0}
                inputMode="numeric"
                error={contratErrors.prime_entretien_tenue?.message}
                {...registerContrat("prime_entretien_tenue")}
              />
              <Input
                label="Sursalaire"
                optionalMark
                type="number"
                min={0}
                inputMode="numeric"
                error={contratErrors.sursalaire?.message}
                {...registerContrat("sursalaire")}
              />
            </div>

            <p className="text-xs font-semibold uppercase tracking-wide text-ink-muted">
              Quotient familial / IGR
            </p>
            <div className="grid gap-3 sm:grid-cols-3">
              <Input
                label="Situation matrimoniale"
                disabled
                value={
                  selectedContratAgent?.situation_matrimoniale
                    ? labelize(selectedContratAgent.situation_matrimoniale)
                    : "— (fiche agent)"
                }
                hint="Issu de la fiche Agent."
              />
              <Input
                label="Nombre d’enfants"
                disabled
                value={
                  selectedContratAgent
                    ? String(selectedContratAgent.nombre_enfants ?? 0)
                    : "— (fiche agent)"
                }
                hint="Issu de la fiche Agent — sert au calcul des parts IGR."
              />
              <Input
                label="Part IGR (override)"
                optionalMark
                type="number"
                min={1}
                max={5}
                step={0.5}
                inputMode="decimal"
                hint="Laisser vide = calcul auto depuis la fiche agent."
                error={contratErrors.parts_igr?.message}
                {...registerContrat("parts_igr")}
              />
            </div>

            <div className="rounded-lg border border-border bg-paper-muted/40 p-3">
              <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-muted">
                Calcul automatique (aperçu)
              </p>
              <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-5 text-sm">
                <div>
                  <p className="text-[11px] text-ink-faint">Salaire brut</p>
                  <p className="font-medium">
                    {formatSalaire(remuPreview.salaire_brut, user)}
                  </p>
                </div>
                <div>
                  <p className="text-[11px] text-ink-faint">Parts IGR</p>
                  <p className="font-medium">{remuPreview.parts_igr}</p>
                </div>
                <div>
                  <p className="text-[11px] text-ink-faint">Retenue CNPS</p>
                  <p className="font-medium">
                    {formatSalaire(remuPreview.retenue_cnps, user)}
                  </p>
                </div>
                <div>
                  <p className="text-[11px] text-ink-faint">Montant IGR</p>
                  <p className="font-medium">
                    {formatSalaire(remuPreview.montant_igr, user)}
                  </p>
                </div>
                <div>
                  <p className="text-[11px] text-ink-faint">Salaire net</p>
                  <p className="font-semibold text-ink">
                    {formatSalaire(remuPreview.salaire_net, user)}
                  </p>
                </div>
              </div>
            </div>
          </form>
        </Modal>

        <ConfirmDialog
          open={!!contratToDelete}
          onClose={() => {
            if (deleteContrat.isPending) return;
            setContratToDelete(null);
          }}
          loading={deleteContrat.isPending}
          title="Supprimer le contrat"
          description="Confirmer la suppression de ce contrat ?"
          onConfirm={async () => {
            if (!contratToDelete) return;
            try {
              await deleteContrat.mutateAsync(contratToDelete.id);
              toast("Contrat supprimé.");
              setContratToDelete(null);
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
