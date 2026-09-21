"use client";

import { useCallback, useEffect, useMemo, useState, Suspense } from "react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { type ColumnDef } from "@tanstack/react-table";
import { AlertTriangle, CalendarDays, ClipboardCheck, Plus, UserCog } from "lucide-react";
import { useDebouncedValue } from "@/application/hooks/useDebouncedValue";
import {
  useAgents,
  useAbsences,
  useCreateVacation,
  useDeleteVacation,
  useDeleteVacationsBulk,
  usePostes,
  usePosteCoverage,
  useRecouvrirVacation,
  useSites,
  useUpdateVacation,
  useVacations,
} from "@/application/hooks/useResources";
import {
  vacationSchema,
  type VacationFormValues,
} from "@/domain/schemas/vacation";
import type { Vacation } from "@/domain/types/entities";
import {
  dayNightHalvesFromPoste,
  hasInsufficientReposMs,
  inferQuartFromHours,
  isCycle24h,
  isOvernight,
  rangesOverlapMs,
  vacationRangeMs,
} from "@/domain/time/shift-interval";
import { DataTable } from "@/presentation/components/tables/DataTable";
import { TableActions } from "@/presentation/components/tables/TableActions";
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
import { Spinner } from "@/presentation/components/ui/Spinner";
import { StatCard } from "@/presentation/components/ui/StatCard";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { useToast } from "@/presentation/providers/ToastProvider";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { can } from "@/shared/lib/can";
import { cn } from "@/shared/lib/cn";
import { formatDate } from "@/shared/lib/format";
import { WeekGrid, type WeekCoverageStats, type CreateForDayContext } from "./WeekGrid";

const emptyDefaults: VacationFormValues = {
  agent_id: "",
  site_id: "",
  poste_id: "",
  date_debut: "",
  date_fin: "",
  heure_debut: "",
  heure_fin: "",
  repeat_frequency: "aucune",
  repeat_count: "1",
};

const STEP_LABELS = ["Agent", "Site & poste", "Horaires", "Récapitulatif"];
const TOTAL_STEPS = STEP_LABELS.length;
const STEP_FIELDS: Record<number, (keyof VacationFormValues)[]> = {
  1: ["agent_id"],
  2: ["site_id", "poste_id"],
  3: ["date_debut", "date_fin", "heure_debut", "heure_fin"],
};

function PlanifierLink({ size = "md" }: { size?: "sm" | "md" }) {
  return (
    <Link
      href="/vacations/planifier"
      title="Planifier l’effectif d’un poste (tous les agents requis)"
      className={`inline-flex items-center justify-center gap-2 rounded-md bg-teal font-medium text-white shadow-sm transition-colors hover:bg-teal-dark ${
        size === "sm" ? "h-8 px-3 text-xs" : "h-10 px-4 text-sm"
      }`}
    >
      <Plus className="size-4" />
      Planifier le poste
    </Link>
  );
}

const statutLabel: Record<string, string> = {
  planifiee: "Planifiée",
  en_cours: "En cours",
  terminee: "Terminée",
  annulee: "Annulée",
  a_recouvrir: "À recouvrir",
};

function toDateInput(value: string | null | undefined) {
  if (!value) return "";
  return value.slice(0, 10);
}

function toTimeInput(value: string | null | undefined) {
  if (!value) return "";
  return value.slice(0, 5);
}

function todayIso() {
  return new Date().toISOString().slice(0, 10);
}

const JOURS: readonly string[] = [
  "dimanche",
  "lundi",
  "mardi",
  "mercredi",
  "jeudi",
  "vendredi",
  "samedi",
];

function frenchWeekday(dateIso: string): string {
  return JOURS[new Date(`${dateIso}T00:00:00`).getDay()];
}

/** Propose les horaires pour couvrir ce jour (cycle complet ou quart manquant). */
function suggestHoursForDay(ctx: CreateForDayContext): {
  heure_debut: string;
  heure_fin: string;
  quart: "jour" | "nuit";
} {
  const hd = ctx.heure_debut?.slice(0, 5) || "07:00";
  const hf = ctx.heure_fin?.slice(0, 5) || "19:00";
  const hdn = ctx.heure_debut_nuit?.slice(0, 5) || "";
  const hfn = ctx.heure_fin_nuit?.slice(0, 5) || "";
  const hasQuarts = Boolean(hdn && hfn);
  const cycle24 = isCycle24h(hd, hf);
  const needSplit = ctx.agentsRequis >= 2;

  // 1 agent sur cycle 24h → toute la relève (ex. 18:30→18:30), pas une moitié.
  if (cycle24 && !hasQuarts && !needSplit) {
    return { heure_debut: hd, heure_fin: hf, quart: "jour" };
  }

  const halves = dayNightHalvesFromPoste({
    heure_debut: hd,
    heure_fin: hf,
    heure_debut_nuit: hdn || null,
    heure_fin_nuit: hfn || null,
  });
  const jour = halves?.jour ?? { heure_debut: hd, heure_fin: hf };
  const nuit = halves?.nuit ?? { heure_debut: hdn, heure_fin: hfn };

  const hasJourOcc = ctx.occupied.some(
    (o) =>
      inferQuartFromHours(o.heure_debut, o.heure_fin, halves) === "jour",
  );
  const hasNuitOcc = ctx.occupied.some(
    (o) =>
      inferQuartFromHours(o.heure_debut, o.heure_fin, halves) === "nuit",
  );

  if (needSplit && nuit.heure_debut && nuit.heure_fin) {
    if (hasNuitOcc && !hasJourOcc) {
      return { ...jour, quart: "jour" };
    }
    if (hasJourOcc && !hasNuitOcc) {
      return { ...nuit, quart: "nuit" };
    }
    return { ...jour, quart: "jour" };
  }

  return { heure_debut: hd, heure_fin: hf, quart: "jour" };
}

export default function VacationsPage() {
  return (
    <Suspense
      fallback={
        <div className="flex justify-center py-16">
          <Spinner className="size-6" />
        </div>
      }
    >
      <VacationsPageContent />
    </Suspense>
  );
}

function VacationsPageContent() {
  const searchParams = useSearchParams();
  const initialAgentId = searchParams.get("agent_id") ?? "";
  const initialSiteId = searchParams.get("site_id") ?? "";
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(50);
  const [q, setQ] = useState("");
  const search = useDebouncedValue(q);
  const [filterAgentId, setFilterAgentId] = useState(initialAgentId);
  const [filterSiteId, setFilterSiteId] = useState(initialSiteId);
  const [filterStatut, setFilterStatut] = useState("");
  const [selectedIds, setSelectedIds] = useState<string[]>([]);
  const [bulkDeleteOpen, setBulkDeleteOpen] = useState(false);
  const [view, setView] = useState<"liste" | "grille">(
    initialAgentId ? "liste" : "grille",
  );
  const [gridSiteId, setGridSiteId] = useState(initialSiteId);
  const [weekStats, setWeekStats] = useState<WeekCoverageStats | null>(null);
  const [open, setOpen] = useState(false);
  const [creating, setCreating] = useState(false);
  const [createContext, setCreateContext] = useState<CreateForDayContext | null>(
    null,
  );
  const [step, setStep] = useState(1);
  const [quart, setQuart] = useState<"jour" | "nuit">("jour");
  const [editing, setEditing] = useState<Vacation | null>(null);
  const [toDelete, setToDelete] = useState<Vacation | null>(null);
  const [toRecouvrir, setToRecouvrir] = useState<Vacation | null>(null);
  const [remplacantId, setRemplacantId] = useState("");
  const [formError, setFormError] = useState<string | null>(null);
  const { toast } = useToast();
  const { user } = useAuth();
  const canCreate = can(user, "vacations.create");
  const canUpdate = can(user, "vacations.update");
  const canDelete = can(user, "vacations.delete");

  const { data, isLoading } = useVacations({
    page,
    q: search || undefined,
    agent_id: filterAgentId || undefined,
    site_id: filterSiteId || undefined,
    statut: filterStatut || undefined,
    per_page: perPage,
  });
  const vacationsARecouvrir = useVacations({
    statut: "a_recouvrir",
    per_page: 1,
  });
  const vacationsPlanifiees = useVacations({
    statut: "planifiee",
    per_page: 1,
  });
  const vacationsEnCours = useVacations({
    statut: "en_cours",
    per_page: 1,
  });
  const { data: coverageTodayData } = usePosteCoverage({
    date: todayIso(),
  });
  const { data: agentsData } = useAgents({
    type: "agent",
    all: true,
    contrat_valide: true,
  });
  const { data: sitesData } = useSites({ all: true });
  const updateVacation = useUpdateVacation();
  const createVacation = useCreateVacation();
  const recouvrirVacation = useRecouvrirVacation();
  const deleteVacation = useDeleteVacation();
  const deleteBulk = useDeleteVacationsBulk();

  const focusListeStatut = (statut: string) => {
    setView("liste");
    setFilterStatut((current) => (current === statut ? "" : statut));
    setPage(1);
  };

  const postesProblemesToday = useMemo(
    () =>
      (coverageTodayData?.data ?? []).filter((c) => c.statut !== "ok").length,
    [coverageTodayData?.data],
  );
  const casesAPlanifierSemaine = useMemo(() => {
    if (!weekStats) return 0;
    return weekStats.sous + weekStats.non;
  }, [weekStats]);
  const countARecouvrir = vacationsARecouvrir.data?.meta.total ?? 0;
  const countPlanifiees = vacationsPlanifiees.data?.meta.total ?? 0;
  const countEnCours = vacationsEnCours.data?.meta.total ?? 0;
  const countTotal = data?.meta.total ?? 0;

  const {
    register,
    handleSubmit,
    reset,
    watch,
    trigger,
    setValue,
    getValues,
    formState: { errors, isSubmitting },
  } = useForm<VacationFormValues>({
    resolver: zodResolver(vacationSchema),
    mode: "onBlur",
    reValidateMode: "onChange",
    defaultValues: emptyDefaults,
  });

  const agentId = watch("agent_id");
  const siteId = watch("site_id");
  const posteId = watch("poste_id");
  const dateDebut = watch("date_debut");
  const dateFin = watch("date_fin");
  const heureDebut = watch("heure_debut");
  const heureFin = watch("heure_fin");

  /** Vacations déjà actives ce jour (tous sites) — pour filtrer les agents libres. */
  const { data: busyDayVacations } = useVacations(
    creating && dateDebut
      ? { en_poste: 1, date: dateDebut, per_page: 200 }
      : undefined,
    { enabled: creating && !!dateDebut },
  );
  const { data: absencesApprouvees } = useAbsences(
    creating ? { statut: "approuvee", per_page: 200 } : undefined,
    { enabled: creating },
  );
  const { data: postesData } = usePostes(
    siteId ? { site_id: siteId, all: true } : undefined,
  );
  const { data: coverageData } = usePosteCoverage(
    { site_id: siteId, date: dateDebut || todayIso() },
    { enabled: !!siteId },
  );
  const posteCoverage = useMemo(
    () => coverageData?.data.find((c) => c.poste_id === posteId),
    [coverageData, posteId],
  );
  const selectedPoste = useMemo(
    () => (postesData?.data ?? []).find((p) => p.id === posteId),
    [postesData, posteId],
  );
  const posteEst24h = !!(
    selectedPoste?.heure_debut_nuit && selectedPoste?.heure_fin_nuit
  );
  const posteCycle24h = Boolean(
    selectedPoste?.heure_debut &&
      selectedPoste?.heure_fin &&
      selectedPoste.heure_debut.slice(0, 5) ===
        selectedPoste.heure_fin.slice(0, 5) &&
      !posteEst24h,
  );
  const showQuartSelect =
    posteEst24h ||
    (creating &&
      posteCycle24h &&
      (createContext?.agentsRequis ?? 1) >= 2);
  const selectedAgentJourRepos = useMemo(
    () => (agentsData?.data ?? []).find((a) => a.id === agentId)?.jour_repos,
    [agentsData, agentId],
  );
  const restDayConflict =
    !!selectedAgentJourRepos &&
    !!dateDebut &&
    frenchWeekday(dateDebut) === selectedAgentJourRepos;

  const busy =
    isSubmitting || updateVacation.isPending || createVacation.isPending;

  const agentOptions = useMemo(() => {
    const assignable = new Set(["disponible", "en_activite"]);
    const weekday = dateDebut ? frenchWeekday(dateDebut) : null;
    const proposed =
      creating && dateDebut && heureDebut && heureFin
        ? vacationRangeMs(
            dateDebut,
            heureDebut,
            heureFin,
            dateFin || dateDebut,
          )
        : null;

    const busyAgentIds = new Set<string>();
    const reposBlockedIds = new Set<string>();
    if (creating && proposed) {
      const proposedNight = isOvernight(heureDebut, heureFin);
      const proposedCycle = isCycle24h(heureDebut, heureFin);
      for (const v of busyDayVacations?.data ?? []) {
        if (!v.agent_id) continue;
        const other = vacationRangeMs(
          v.date_debut,
          v.heure_debut,
          v.heure_fin,
          v.date_fin,
        );
        if (rangesOverlapMs(proposed, other)) {
          busyAgentIds.add(v.agent_id);
          continue;
        }
        const otherNight = isOvernight(v.heure_debut, v.heure_fin);
        const otherCycle = isCycle24h(v.heure_debut, v.heure_fin);
        if (
          hasInsufficientReposMs(proposed, other, {
            samePoste: !!posteId && v.poste_id === posteId,
            proposedOvernight: proposedNight,
            otherOvernight: otherNight,
            proposedCycle,
            otherCycle,
          })
        ) {
          reposBlockedIds.add(v.agent_id);
        }
      }
      for (const a of absencesApprouvees?.data ?? []) {
        if (!a.agent_id || !dateDebut) continue;
        const aStart = a.date_debut.slice(0, 10);
        const aEnd = (a.date_fin ?? a.date_debut).slice(0, 10);
        const day = dateDebut.slice(0, 10);
        const next =
          isOvernight(heureDebut, heureFin) || isCycle24h(heureDebut, heureFin)
            ? (() => {
                const d = new Date(`${day}T12:00:00`);
                d.setDate(d.getDate() + 1);
                return d.toISOString().slice(0, 10);
              })()
            : day;
        if (aStart <= next && aEnd >= day) busyAgentIds.add(a.agent_id);
      }
      for (const o of createContext?.occupied ?? []) {
        if (o.agent_id) busyAgentIds.add(o.agent_id);
      }
    }

    const options = (agentsData?.data ?? [])
      .filter((a) => {
        if (!creating) return true;
        if (!assignable.has(a.statut)) return false;
        if (weekday && a.jour_repos === weekday) return false;
        if (busyAgentIds.has(a.id)) return false;
        if (reposBlockedIds.has(a.id)) return false;
        return true;
      })
      .map((a) => ({
        value: a.id,
        label: `${a.prenom} ${a.nom} (${a.matricule})${
          a.grade?.libelle ? ` — ${a.grade.libelle}` : ""
        }${a.jour_repos ? ` · repos ${a.jour_repos}` : ""}`,
      }));

    if (editing?.agent && !options.some((o) => o.value === editing.agent!.id)) {
      options.unshift({
        value: editing.agent.id,
        label: `${editing.agent.prenom} ${editing.agent.nom} (${editing.agent.matricule})`,
      });
    }
    return options;
  }, [
    agentsData,
    editing,
    creating,
    createContext,
    dateDebut,
    dateFin,
    heureDebut,
    heureFin,
    busyDayVacations,
    absencesApprouvees,
    posteId,
  ]);

  // Si l’horaire change (quart), retirer un agent devenu indisponible.
  useEffect(() => {
    if (!creating || !agentId) return;
    if (agentOptions.some((o) => o.value === agentId)) return;
    setValue("agent_id", "", { shouldValidate: true });
  }, [creating, agentId, agentOptions, setValue]);

  /**
   * Remplaçants : mêmes règles que l’API (disponible / en activité), hors
   * agent du trou. Les agents du pool siège sont mis en avant en premier —
   * ce sont les candidats naturels au remplacement (leur vacation siège du
   * jour est libérée automatiquement s’ils sont choisis).
   */
  const remplacantOptions = useMemo(() => {
    const assignable = new Set(["disponible", "en_activite"]);
    return (agentsData?.data ?? [])
      .filter(
        (a) =>
          assignable.has(a.statut) && a.id !== toRecouvrir?.agent_id,
      )
      .map((a) => ({
        value: a.id,
        label: `${a.pool_siege ? "★ " : ""}${a.prenom} ${a.nom} (${a.matricule})${
          a.grade?.libelle ? ` — ${a.grade.libelle}` : ""
        }${a.pool_siege ? " — Pool siège" : ""}`,
        poolSiege: Boolean(a.pool_siege),
      }))
      .sort((a, b) =>
        a.poolSiege === b.poolSiege
          ? a.label.localeCompare(b.label)
          : a.poolSiege
            ? -1
            : 1,
      );
  }, [agentsData, toRecouvrir?.agent_id]);

  const siteOptions = useMemo(
    () => (sitesData?.data ?? []).map((s) => ({ value: s.id, label: s.nom })),
    [sitesData],
  );
  const posteOptions = useMemo(
    () => (postesData?.data ?? []).map((p) => ({ value: p.id, label: p.nom })),
    [postesData],
  );

  const closeModal = () => {
    if (busy) return;
    setOpen(false);
    setCreating(false);
    setCreateContext(null);
    setStep(1);
    setQuart("jour");
    setEditing(null);
    setFormError(null);
    reset(emptyDefaults);
    updateVacation.reset();
    createVacation.reset();
  };

  const openCreateFor = useCallback(
    (ctx: CreateForDayContext) => {
      if (!canCreate) {
        toast("Vous n’avez pas le droit de créer une affectation.", "danger");
        return;
      }
      const suggested = suggestHoursForDay(ctx);
      setEditing(null);
      setCreating(true);
      setCreateContext(ctx);
      setFormError(null);
      setQuart(suggested.quart);
      setStep(1);
      reset({
        agent_id: "",
        site_id: ctx.siteId,
        poste_id: ctx.posteId,
        date_debut: ctx.date,
        // Même jour : couverture ponctuelle (nuit → API pousse date_fin +1).
        date_fin: ctx.date,
        heure_debut: suggested.heure_debut,
        heure_fin: suggested.heure_fin,
        repeat_frequency: "aucune",
        repeat_count: "1",
      });
      setOpen(true);
    },
    [canCreate, reset, toast],
  );

  const openRecouvrir = useCallback(
    (vacation: Vacation) => {
      if (!canUpdate) {
        toast(
          "Vous n’avez pas le droit de recouvrir une affectation.",
          "danger",
        );
        return;
      }
      setToRecouvrir(vacation);
      setRemplacantId("");
    },
    [canUpdate, toast],
  );

  const openEdit = useCallback(
    (vacation: Vacation) => {
      if (!canUpdate) {
        toast(
          "Vous n’avez pas le droit de modifier une affectation.",
          "danger",
        );
        return;
      }
      if (vacation.statut === "a_recouvrir") {
        openRecouvrir(vacation);
        return;
      }
      setEditing(vacation);
      setCreating(false);
      setCreateContext(null);
      setFormError(null);
      reset({
        agent_id: vacation.agent_id,
        site_id: vacation.site_id,
        poste_id: vacation.poste_id ?? "",
        date_debut: toDateInput(vacation.date_debut),
        date_fin: toDateInput(vacation.date_fin),
        heure_debut: toTimeInput(vacation.heure_debut),
        heure_fin: toTimeInput(vacation.heure_fin),
      });
      setStep(1);
      setQuart("jour");
      setOpen(true);
    },
    [canUpdate, openRecouvrir, reset, toast],
  );

  const goNext = async () => {
    const fields = STEP_FIELDS[step] ?? [];
    const ok = fields.length === 0 || (await trigger(fields));
    if (ok) setStep((s) => Math.min(TOTAL_STEPS, s + 1));
  };

  const goBack = () => setStep((s) => Math.max(1, s - 1));

  const columns = useMemo<ColumnDef<Vacation>[]>(
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
        id: "site",
        header: "Site",
        cell: ({ row }) => row.original.site?.nom ?? "—",
      },
      {
        id: "poste",
        header: "Poste",
        cell: ({ row }) => row.original.poste?.nom ?? "—",
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
        id: "horaires",
        header: "Horaires",
        cell: ({ row }) => (
          <span className="font-mono text-xs">
            {row.original.heure_debut?.slice(0, 5)} –{" "}
            {row.original.heure_fin?.slice(0, 5)}
          </span>
        ),
      },
      {
        accessorKey: "statut",
        header: "Statut",
        cell: ({ getValue }) => {
          const v = String(getValue());
          return <Badge tone={statusTone(v)}>{statutLabel[v] ?? v}</Badge>;
        },
      },
      {
        id: "actions",
        header: "Actions",
        enableSorting: false,
        cell: ({ row }) => {
          const v = row.original;
          const isHole = v.statut === "a_recouvrir";
          const canMarkHole =
            v.statut === "planifiee" || v.statut === "en_cours";
          return (
            <TableActions
              canEdit={canUpdate && !isHole}
              canDelete={canDelete}
              onEdit={() => openEdit(v)}
              onDelete={() => setToDelete(v)}
              items={[
                {
                  key: "recouvrir",
                  label: "Recouvrir",
                  icon: UserCog,
                  tone: "warning",
                  hidden: !canUpdate || !isHole,
                  onClick: () => openRecouvrir(v),
                },
                {
                  key: "marquer-trou",
                  label: "Marquer à recouvrir",
                  icon: AlertTriangle,
                  tone: "warning",
                  hidden: !canUpdate || !canMarkHole,
                  onClick: async () => {
                    try {
                      await updateVacation.mutateAsync({
                        id: v.id,
                        payload: { statut: "a_recouvrir" },
                      });
                      toast("Vacation marquée à recouvrir.");
                    } catch (err) {
                      toast(
                        getApiErrorMessage(
                          err,
                          "Impossible de marquer cette vacation.",
                        ),
                        "danger",
                      );
                    }
                  },
                },
              ]}
            />
          );
        },
      },
    ],
    [canUpdate, canDelete, openEdit, openRecouvrir, updateVacation, toast],
  );

  return (
    <PermissionGate permission="vacations.view" title="Planning postes">
      <div className="space-y-6">
        <PageHeader
          title="Planning des postes"
          description="Affectez les agents aux postes selon l’effectif et les horaires définis."
          actions={
            <div className="flex flex-col items-stretch gap-2 sm:items-end">
              <div className="flex flex-wrap items-center justify-end gap-2">
                {canCreate ? <PlanifierLink /> : null}
              </div>
              <div className="inline-flex self-end rounded-md border border-border bg-white p-0.5">
                <button
                  type="button"
                  onClick={() => setView("grille")}
                  className={cn(
                    "rounded px-3 py-1.5 text-sm font-medium transition",
                    view === "grille"
                      ? "bg-ink text-white"
                      : "text-ink-muted hover:text-ink",
                  )}
                >
                  Grille site
                </button>
                <button
                  type="button"
                  onClick={() => setView("liste")}
                  className={cn(
                    "rounded px-3 py-1.5 text-sm font-medium transition",
                    view === "liste"
                      ? "bg-ink text-white"
                      : "text-ink-muted hover:text-ink",
                  )}
                >
                  Détail jours
                </button>
              </div>
            </div>
          }
        />

        <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <button
            type="button"
            className="text-left transition hover:opacity-90"
            onClick={() => {
              setView("liste");
              setFilterStatut("");
              setPage(1);
            }}
          >
            <StatCard
              label="Vacations (filtre)"
              value={countTotal}
              hint={
                filterStatut
                  ? `Statut : ${statutLabel[filterStatut] ?? filterStatut}`
                  : "Liste filtrée actuelle"
              }
              icon={<CalendarDays className="size-4" />}
              className={cn(view === "liste" && !filterStatut && "ring-1 ring-teal/30")}
            />
          </button>
          <button
            type="button"
            className="text-left transition hover:opacity-90"
            onClick={() => {
              setView("grille");
            }}
          >
            <StatCard
              label="À planifier"
              value={
                view === "grille" && gridSiteId
                  ? casesAPlanifierSemaine
                  : postesProblemesToday
              }
              hint={
                view === "grille" && gridSiteId
                  ? weekStats
                    ? `${weekStats.non} vide(s) · ${weekStats.sous} sous-effectif${
                        weekStats.sur > 0
                          ? ` · ${weekStats.sur} sur-effectif`
                          : ""
                      }`
                    : "Couverture de la semaine"
                  : postesProblemesToday > 0
                    ? "Postes non couverts aujourd’hui"
                    : "Couverture du jour"
              }
              icon={<UserCog className="size-4" />}
              className={cn(view === "grille" && "ring-1 ring-teal/30")}
            />
          </button>
          <button
            type="button"
            className="text-left transition hover:opacity-90"
            onClick={() => focusListeStatut("a_recouvrir")}
          >
            <StatCard
              label="À recouvrir"
              value={countARecouvrir}
              hint="Après absence / indispo"
              icon={<AlertTriangle className="size-4" />}
              className={cn(
                filterStatut === "a_recouvrir" && "ring-1 ring-teal/30",
              )}
            />
          </button>
          <button
            type="button"
            className="text-left transition hover:opacity-90"
            onClick={() => focusListeStatut("en_cours")}
          >
            <StatCard
              label="En cours"
              value={countEnCours}
              hint={`${countPlanifiees} planifiée(s)`}
              icon={<ClipboardCheck className="size-4" />}
              className={cn(filterStatut === "en_cours" && "ring-1 ring-teal/30")}
            />
          </button>
        </section>

        <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
          <Card className="min-w-0 overflow-hidden">
            <CardHeader
              title={view === "grille" ? "Grille site" : "Détail des vacations"}
              description={
                view === "grille"
                  ? "Vue hebdomadaire par poste — cliquez une case pour planifier."
                  : "Liste filtrable des affectations jour par jour."
              }
              action={canCreate ? <PlanifierLink size="sm" /> : undefined}
            />
            <CardBody className="space-y-4">
              {view === "liste" ? (
                <div className="flex flex-wrap items-end gap-3">
                  <div className="w-56">
                    <Select
                      label="Filtrer par agent"
                      optionalMark
                      placeholder="Tous les agents"
                      options={[
                        { value: "", label: "Tous les agents" },
                        ...agentOptions,
                      ]}
                      value={filterAgentId}
                      onChange={(e) => {
                        setFilterAgentId(e.target.value);
                        setPage(1);
                      }}
                    />
                  </div>
                  <div className="w-56">
                    <Select
                      label="Filtrer par site"
                      optionalMark
                      placeholder="Tous les sites"
                      options={[
                        { value: "", label: "Tous les sites" },
                        ...siteOptions,
                      ]}
                      value={filterSiteId}
                      onChange={(e) => {
                        setFilterSiteId(e.target.value);
                        setPage(1);
                      }}
                    />
                  </div>
                  <div className="w-56">
                    <Select
                      label="Filtrer par statut"
                      optionalMark
                      placeholder="Tous les statuts"
                      options={[
                        { value: "", label: "Tous les statuts" },
                        { value: "a_recouvrir", label: "À recouvrir" },
                        { value: "planifiee", label: "Planifiée" },
                        { value: "en_cours", label: "En cours" },
                        { value: "terminee", label: "Terminée" },
                        { value: "annulee", label: "Annulée" },
                      ]}
                      value={filterStatut}
                      onChange={(e) => {
                        setFilterStatut(e.target.value);
                        setPage(1);
                      }}
                    />
                  </div>
                  {filterAgentId && canCreate ? (
                    <Link
                      href={`/vacations/planifier?agent_id=${filterAgentId}${
                        filterSiteId ? `&site_id=${filterSiteId}` : ""
                      }`}
                      className="inline-flex h-10 items-center rounded-md border border-border bg-white px-3 text-sm font-medium text-ink hover:bg-paper-muted"
                    >
                      Modifier son planning
                    </Link>
                  ) : null}
                </div>
              ) : null}

              {view === "grille" ? (
                <WeekGrid
                  siteId={gridSiteId}
                  siteOptions={siteOptions}
                  onSiteChange={setGridSiteId}
                  onCreateFor={openCreateFor}
                  onEditVacation={openEdit}
                  onWeekStats={setWeekStats}
                />
              ) : (
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
                    placeholder: "Rechercher agent, site, poste…",
                  }}
                  toolbar={
                    <div className="flex flex-wrap items-center gap-2">
                      {canDelete && selectedIds.length > 0 ? (
                        <Button
                          variant="danger"
                          size="sm"
                          onClick={() => setBulkDeleteOpen(true)}
                        >
                          Supprimer ({selectedIds.length})
                        </Button>
                      ) : null}
                    </div>
                  }
                  onSelectionChange={setSelectedIds}
                  getRowId={(row) => row.id}
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
                  emptyTitle="Aucun détail de vacation"
                  emptyAction={
                    canCreate ? <PlanifierLink size="sm" /> : undefined
                  }
                />
              )}
            </CardBody>
          </Card>

          <aside className="space-y-4 xl:sticky xl:top-4 xl:self-start">
            <Card>
              <CardHeader
                title="À traiter"
                description={
                  view === "grille" && gridSiteId
                    ? "Priorités de la semaine affichée"
                    : "Priorités opérationnelles du jour"
                }
              />
              <CardBody className="space-y-3">
                <div className="flex items-center justify-between rounded-md bg-paper px-3 py-2">
                  <span className="text-sm text-ink-muted">
                    Cases à planifier
                  </span>
                  <span className="font-mono text-lg font-semibold tabular-nums text-ink">
                    {view === "grille" && gridSiteId
                      ? casesAPlanifierSemaine
                      : postesProblemesToday}
                  </span>
                </div>
                {view === "grille" && weekStats ? (
                  <div className="flex flex-wrap gap-2 px-1 text-[11px] text-ink-muted">
                    <span>
                      <strong className="text-rose-600">{weekStats.non}</strong>{" "}
                      vide(s)
                    </span>
                    <span>·</span>
                    <span>
                      <strong className="text-amber-600">{weekStats.sous}</strong>{" "}
                      sous-effectif
                    </span>
                    {weekStats.sur > 0 ? (
                      <>
                        <span>·</span>
                        <span>
                          <strong className="text-violet-600">
                            {weekStats.sur}
                          </strong>{" "}
                          sur-effectif
                        </span>
                      </>
                    ) : null}
                  </div>
                ) : null}
                <div className="flex items-center justify-between rounded-md bg-paper px-3 py-2">
                  <span className="text-sm text-ink-muted">
                    Trous après absence
                  </span>
                  <span className="font-mono text-lg font-semibold tabular-nums text-ink">
                    {countARecouvrir}
                  </span>
                </div>
                <Button
                  size="sm"
                  variant="secondary"
                  className="w-full"
                  onClick={() => setView("grille")}
                >
                  Voir la grille
                </Button>
                <Link href="/postes" className="block">
                  <Button size="sm" variant="secondary" className="w-full">
                    Couverture postes
                  </Button>
                </Link>
              </CardBody>
            </Card>

            <Card>
              <CardHeader
                title="Comment piloter"
                description="Raccourcis métier"
              />
              <CardBody>
                <ol className="space-y-2 text-xs text-ink-muted">
                  <li className="flex gap-2">
                    <span className="font-mono font-semibold text-teal">1</span>
                    Grille site → repérer les cases vides
                  </li>
                  <li className="flex gap-2">
                    <span className="font-mono font-semibold text-teal">2</span>
                    <span>
                      <strong className="text-ink">Planifier le poste</strong> —
                      effectif complet du poste
                    </span>
                  </li>
                  <li className="flex gap-2">
                    <span className="font-mono font-semibold text-teal">3</span>
                    Traiter les « À recouvrir » (remplaçant)
                  </li>
                </ol>
              </CardBody>
            </Card>

            {canCreate ? (
              <Card className="border-teal/20 bg-teal/[0.03]">
                <CardBody className="space-y-3">
                  <div>
                    <p className="text-sm font-semibold text-ink">
                      Planifier le poste
                    </p>
                    <p className="mt-0.5 text-xs text-ink-muted">
                      Choisir un poste et planifier tous les agents requis en
                      une fois.
                    </p>
                    <div className="mt-2">
                      <PlanifierLink size="sm" />
                    </div>
                  </div>
                </CardBody>
              </Card>
            ) : null}
          </aside>
        </div>

        <Modal
          open={open}
          onClose={closeModal}
          preventClose={busy}
          size="lg"
          title={
            creating
              ? "Ajouter un agent"
              : "Modifier l’affectation"
          }
          description={
            creating && createContext
              ? `${createContext.posteNom} · ${formatDate(createContext.date)} — couverture de ce jour uniquement (${createContext.occupied.length}/${createContext.agentsRequis} déjà planifié${createContext.occupied.length > 1 ? "s" : ""}).`
              : "Ajustez le site, le poste ou les horaires de cette vacation."
          }
          footer={
            <>
              {step > 1 ? (
                <Button variant="secondary" onClick={goBack} disabled={busy}>
                  Précédent
                </Button>
              ) : (
                <Button
                  variant="secondary"
                  onClick={closeModal}
                  disabled={busy}
                >
                  Annuler
                </Button>
              )}
              {step < TOTAL_STEPS ? (
                <Button
                  type="button"
                  onClick={() => void goNext()}
                  disabled={busy}
                >
                  Suivant
                </Button>
              ) : (
                <Button type="submit" form="vacation-form" loading={busy}>
                  {busy
                    ? "Enregistrement…"
                    : creating
                      ? "Ajouter pour ce jour"
                      : "Enregistrer"}
                </Button>
              )}
            </>
          }
        >
          <div className="mb-4 flex items-center gap-2">
            {STEP_LABELS.map((label, i) => {
              const n = i + 1;
              const active = n === step;
              const done = n < step;
              return (
                <div key={label} className="flex flex-1 items-center gap-2">
                  <div className="flex items-center gap-2">
                    <span
                      className={`flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold ${
                        active
                          ? "bg-ink text-white"
                          : done
                            ? "bg-teal text-white"
                            : "bg-paper-muted text-ink-faint"
                      }`}
                    >
                      {n}
                    </span>
                    <span
                      className={`hidden text-xs font-medium sm:inline ${
                        active ? "text-ink" : "text-ink-faint"
                      }`}
                    >
                      {label}
                    </span>
                  </div>
                  {n < TOTAL_STEPS ? (
                    <div className="h-px flex-1 bg-border" />
                  ) : null}
                </div>
              );
            })}
          </div>

          <form
            id="vacation-form"
            className="space-y-3"
            noValidate
            onSubmit={handleSubmit(async (values) => {
              setFormError(null);
              try {
                if (creating) {
                  if (
                    posteCoverage?.statut === "ok" ||
                    posteCoverage?.statut === "sur_effectif"
                  ) {
                    setFormError(
                      posteCoverage.statut === "sur_effectif"
                        ? "Sur-effectif : retirez un agent avant d’en ajouter."
                        : "Effectif déjà complet pour ce poste à cette date.",
                    );
                    return;
                  }
                  if (
                    posteCoverage?.couverture_24h &&
                    posteCoverage.jour &&
                    posteCoverage.nuit
                  ) {
                    const night = isOvernight(
                      values.heure_debut,
                      values.heure_fin,
                    );
                    const slot = night
                      ? posteCoverage.nuit
                      : posteCoverage.jour;
                    if ((slot.manquant ?? 0) <= 0) {
                      setFormError(
                        night
                          ? "Le quart nuit est déjà couvert pour ce jour."
                          : "Le quart jour est déjà couvert pour ce jour.",
                      );
                      return;
                    }
                  }
                  await createVacation.mutateAsync({
                    agent_id: values.agent_id,
                    site_id: values.site_id,
                    poste_id: values.poste_id || null,
                    heure_debut: values.heure_debut,
                    heure_fin: values.heure_fin,
                    date_debut: values.date_debut,
                    date_fin: values.date_fin || values.date_debut,
                  });
                  toast("Agent ajouté pour ce jour.");
                  reset(emptyDefaults);
                  setCreating(false);
                  setCreateContext(null);
                  setOpen(false);
                  return;
                }
                if (!editing) return;
                await updateVacation.mutateAsync({
                  id: editing.id,
                  payload: {
                    agent_id: values.agent_id,
                    site_id: values.site_id,
                    poste_id: values.poste_id || null,
                    heure_debut: values.heure_debut,
                    heure_fin: values.heure_fin,
                    date_debut: values.date_debut,
                    date_fin: values.date_fin || null,
                  },
                });
                toast("Affectation mise à jour avec succès.");
                reset(emptyDefaults);
                setEditing(null);
                setOpen(false);
              } catch (err) {
                const message = getApiErrorMessage(
                  err,
                  creating
                    ? "Échec de l’ajout de l’agent."
                    : "Échec de la modification de l’affectation.",
                );
                setFormError(message);
                toast(message, "danger");
              }
            })}
          >
            <RequiredFieldsLegend className="mb-1" />

            {step === 1 ? (
              <div className="space-y-2">
                <Select
                  label="Agent"
                  requiredMark
                  searchable
                  placeholder={
                    creating
                      ? "Agents libres sur cet horaire"
                      : "Sélectionnez un agent"
                  }
                  hint={
                    creating
                      ? heureDebut && heureFin
                        ? `Disponibles pour ${heureDebut}–${heureFin} le ${formatDate(dateDebut)} (hors repos, conflit, absence).`
                        : "Choisissez d’abord les horaires si besoin."
                      : undefined
                  }
                  options={agentOptions}
                  error={errors.agent_id?.message}
                  {...register("agent_id")}
                />
                {creating && agentOptions.length === 0 ? (
                  <Alert tone="warning">
                    Aucun agent libre pour cet horaire — conflit de planning,
                    jour de repos ou absence approuvée.
                  </Alert>
                ) : null}
              </div>
            ) : null}

            {step === 2 ? (
              <div className="grid gap-3 sm:grid-cols-2">
                <Select
                  label="Site"
                  requiredMark
                  placeholder="Sélectionnez un site"
                  options={siteOptions}
                  error={errors.site_id?.message}
                  {...register("site_id")}
                />
                <Select
                  label="Poste"
                  optionalMark
                  hint="L’horaire de la vacation sera pré-rempli depuis celui du poste, si défini."
                  placeholder={
                    siteId
                      ? "Sélectionnez un poste"
                      : "Choisissez d’abord un site"
                  }
                  options={posteOptions}
                  disabled={!siteId}
                  error={errors.poste_id?.message}
                  {...register("poste_id", {
                    onChange: (e) => {
                      setQuart("jour");
                      const poste = (postesData?.data ?? []).find(
                        (p) => p.id === e.target.value,
                      );
                      if (!poste) return;
                      // Ne pré-remplit que si l'utilisateur n'a pas déjà
                      // saisi ses propres heures pour cette vacation.
                      if (poste.heure_debut && !getValues("heure_debut")) {
                        setValue("heure_debut", poste.heure_debut.slice(0, 5));
                      }
                      if (poste.heure_fin && !getValues("heure_fin")) {
                        setValue("heure_fin", poste.heure_fin.slice(0, 5));
                      }
                    },
                  })}
                />
              </div>
            ) : null}

            {step === 3 ? (
              <>
                <div className="grid gap-3 sm:grid-cols-2">
                  <DatePicker
                    label="Date de début"
                    requiredMark
                    error={errors.date_debut?.message}
                    {...register("date_debut")}
                  />
                  <DatePicker
                    label="Date de fin"
                    optionalMark={!creating}
                    hint={
                      creating
                        ? "Par défaut = ce jour uniquement (couverture jour par jour)."
                        : "Laissez vide pour une affectation récurrente sans fin définie."
                    }
                    error={errors.date_fin?.message}
                    {...register("date_fin")}
                  />
                </div>

                {restDayConflict ? (
                  <Alert tone="warning">
                    <span className="flex items-start gap-2">
                      <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                      <span>
                        Cet agent a son jour de repos le{" "}
                        {selectedAgentJourRepos}. Vous pouvez continuer si
                        c’est une exception voulue.
                      </span>
                    </span>
                  </Alert>
                ) : null}

                {posteCoverage &&
                (posteCoverage.statut === "ok" ||
                  posteCoverage.statut === "sur_effectif") ? (
                  <Alert
                    tone={
                      posteCoverage.statut === "sur_effectif"
                        ? "danger"
                        : "warning"
                    }
                  >
                    <span className="flex items-start gap-2">
                      <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                      <span>
                        {posteCoverage.statut === "sur_effectif" ? (
                          <>
                            Anomalie : ce poste a déjà{" "}
                            {posteCoverage.planifies} agent(s) pour{" "}
                            {posteCoverage.agents_requis} requis. Retirez un
                            agent en trop — un ajout est refusé.
                          </>
                        ) : posteCoverage.couverture_24h &&
                          posteCoverage.jour &&
                          posteCoverage.nuit ? (
                          <>
                            Effectif complet ({posteCoverage.planifies}/
                            {posteCoverage.agents_requis} — jour{" "}
                            {posteCoverage.jour.planifies} · nuit{" "}
                            {posteCoverage.nuit.planifies}). Ajout refusé.
                          </>
                        ) : (
                          <>
                            Effectif complet ({posteCoverage.planifies}/
                            {posteCoverage.agents_requis}) à cette date. Ajout
                            refusé.
                          </>
                        )}
                      </span>
                    </span>
                  </Alert>
                ) : null}

                {showQuartSelect ? (
                  <Select
                    label="Quart"
                    hint={
                      posteEst24h
                        ? "Ce poste est couvert jour + nuit — choisissez le quart de cet agent."
                        : "Poste 24h à 2 agents : Jour = plage diurne, Nuit = plage nocturne."
                    }
                    value={quart}
                    onChange={(e) => {
                      const q = e.target.value as "jour" | "nuit";
                      setQuart(q);
                      if (!selectedPoste) return;
                      if (posteEst24h) {
                        if (
                          q === "jour" &&
                          selectedPoste.heure_debut &&
                          selectedPoste.heure_fin
                        ) {
                          setValue(
                            "heure_debut",
                            selectedPoste.heure_debut.slice(0, 5),
                          );
                          setValue(
                            "heure_fin",
                            selectedPoste.heure_fin.slice(0, 5),
                          );
                        } else if (
                          q === "nuit" &&
                          selectedPoste.heure_debut_nuit &&
                          selectedPoste.heure_fin_nuit
                        ) {
                          setValue(
                            "heure_debut",
                            selectedPoste.heure_debut_nuit.slice(0, 5),
                          );
                          setValue(
                            "heure_fin",
                            selectedPoste.heure_fin_nuit.slice(0, 5),
                          );
                        }
                        return;
                      }
                      const halves = dayNightHalvesFromPoste({
                        heure_debut: selectedPoste.heure_debut?.slice(0, 5) ?? "",
                        heure_fin: selectedPoste.heure_fin?.slice(0, 5) ?? "",
                        heure_debut_nuit:
                          selectedPoste.heure_debut_nuit?.slice(0, 5) ?? null,
                        heure_fin_nuit:
                          selectedPoste.heure_fin_nuit?.slice(0, 5) ?? null,
                      });
                      if (!halves) return;
                      const half = q === "jour" ? halves.jour : halves.nuit;
                      setValue("heure_debut", half.heure_debut);
                      setValue("heure_fin", half.heure_fin);
                    }}
                    options={(() => {
                      if (posteEst24h) {
                        return [
                          { value: "jour", label: "Jour" },
                          { value: "nuit", label: "Nuit" },
                        ];
                      }
                      const halves = selectedPoste
                        ? dayNightHalvesFromPoste({
                            heure_debut:
                              selectedPoste.heure_debut?.slice(0, 5) ?? "",
                            heure_fin:
                              selectedPoste.heure_fin?.slice(0, 5) ?? "",
                            heure_debut_nuit:
                              selectedPoste.heure_debut_nuit?.slice(0, 5) ??
                              null,
                            heure_fin_nuit:
                              selectedPoste.heure_fin_nuit?.slice(0, 5) ?? null,
                          })
                        : null;
                      return [
                        {
                          value: "jour",
                          label: halves
                            ? `Jour (${halves.jour.heure_debut}–${halves.jour.heure_fin})`
                            : "Jour",
                        },
                        {
                          value: "nuit",
                          label: halves
                            ? `Nuit (${halves.nuit.heure_debut}–${halves.nuit.heure_fin})`
                            : "Nuit",
                        },
                      ];
                    })()}
                  />
                ) : creating && posteCycle24h ? (
                  <Alert tone="info">
                    Poste 24h · 1 agent : cycle complet{" "}
                    <strong>
                      {heureDebut} → {heureFin}
                    </strong>{" "}
                    (relève à {heureDebut}).
                  </Alert>
                ) : null}

                <p className="text-xs text-ink-muted">
                  Horaire réel de <strong>cet agent</strong> sur cette
                  affectation — peut différer de l’horaire par défaut du poste
                  (relève partielle, heures sup…).
                </p>
                <div className="grid gap-3 sm:grid-cols-2">
                  <Input
                    label="Heure de début"
                    type="time"
                    requiredMark
                    error={errors.heure_debut?.message}
                    {...register("heure_debut")}
                  />
                  <Input
                    label="Heure de fin"
                    type="time"
                    requiredMark
                    error={errors.heure_fin?.message}
                    {...register("heure_fin")}
                  />
                </div>
              </>
            ) : null}

            {step === 4 ? (
              <div className="space-y-3">
                <p className="text-sm text-ink-muted">
                  Vérifiez les informations avant d’enregistrer.
                </p>
                <dl className="divide-y divide-border rounded-md border border-border">
                  {[
                    [
                      "Agent",
                      agentOptions.find((o) => o.value === agentId)?.label ??
                        "—",
                    ],
                    [
                      "Site",
                      siteOptions.find((o) => o.value === siteId)?.label ?? "—",
                    ],
                    [
                      "Poste",
                      posteOptions.find((o) => o.value === posteId)?.label ??
                        "Non précisé",
                    ],
                    [
                      "Dates",
                      dateFin
                        ? `Du ${formatDate(dateDebut)} au ${formatDate(dateFin)}`
                        : dateDebut
                          ? `À partir du ${formatDate(dateDebut)} — sans fin définie`
                          : "—",
                    ],
                    ["Horaires", `${heureDebut} – ${heureFin}`],
                  ].map(([label, value]) => (
                    <div
                      key={label}
                      className="flex items-center justify-between gap-4 px-3 py-2 text-sm"
                    >
                      <span className="text-ink-muted">{label}</span>
                      <span className="text-right font-medium text-ink">
                        {value}
                      </span>
                    </div>
                  ))}
                </dl>
                {restDayConflict ? (
                  <Alert tone="warning">
                    Jour de repos habituel de l’agent ({selectedAgentJourRepos}
                    ).
                  </Alert>
                ) : null}
                {posteCoverage &&
                (posteCoverage.statut === "ok" ||
                  posteCoverage.statut === "sur_effectif") ? (
                  <Alert
                    tone={
                      posteCoverage.statut === "sur_effectif"
                        ? "danger"
                        : "warning"
                    }
                  >
                    {posteCoverage.statut === "sur_effectif"
                      ? "Sur-effectif — ajout impossible. Corrigez d’abord le planning."
                      : "Effectif déjà complet — ajout impossible."}
                  </Alert>
                ) : null}
              </div>
            ) : null}

            {formError ? <Alert tone="danger">{formError}</Alert> : null}
          </form>
        </Modal>

        <Modal
          open={!!toRecouvrir}
          onClose={() => {
            if (recouvrirVacation.isPending) return;
            setToRecouvrir(null);
            setRemplacantId("");
          }}
          title="Recouvrir le poste"
          description={
            toRecouvrir
              ? `Remplacer ${toRecouvrir.agent?.prenom ?? ""} ${toRecouvrir.agent?.nom ?? "l’agent"} le ${formatDate(toRecouvrir.date_debut)} (${toRecouvrir.heure_debut?.slice(0, 5)}–${toRecouvrir.heure_fin?.slice(0, 5)}) sur ${toRecouvrir.poste?.nom ?? toRecouvrir.site?.nom ?? "le poste"}.`
              : undefined
          }
        >
          <div className="space-y-4">
            <Select
              label="Agent remplaçant"
              placeholder="Choisir un agent disponible"
              options={remplacantOptions}
              value={remplacantId}
              onChange={(e) => setRemplacantId(e.target.value)}
            />
            {remplacantOptions.length === 0 ? (
              <p className="text-sm text-ink-muted">
                Aucun agent disponible ou en activité pour ce remplacement.
              </p>
            ) : null}
            <div className="flex justify-end gap-2">
              <Button
                variant="secondary"
                type="button"
                disabled={recouvrirVacation.isPending}
                onClick={() => {
                  setToRecouvrir(null);
                  setRemplacantId("");
                }}
              >
                Annuler
              </Button>
              <Button
                type="button"
                disabled={!remplacantId || recouvrirVacation.isPending}
                loading={recouvrirVacation.isPending}
                onClick={async () => {
                  if (!toRecouvrir || !remplacantId) return;
                  try {
                    await recouvrirVacation.mutateAsync({
                      id: toRecouvrir.id,
                      agent_id: remplacantId,
                    });
                    toast("Poste recouvert avec le remplaçant.");
                    setToRecouvrir(null);
                    setRemplacantId("");
                  } catch (err) {
                    toast(
                      getApiErrorMessage(
                        err,
                        "Échec du recouvrement.",
                      ),
                      "danger",
                    );
                  }
                }}
              >
                Confirmer le remplacement
              </Button>
            </div>
          </div>
        </Modal>

        <ConfirmDialog
          open={!!toDelete}
          onClose={() => {
            if (deleteVacation.isPending) return;
            setToDelete(null);
          }}
          loading={deleteVacation.isPending}
          title="Supprimer l’affectation"
          description={
            toDelete
              ? `Confirmer la suppression de l’affectation du ${formatDate(toDelete.date_debut)} ?`
              : ""
          }
          onConfirm={async () => {
            if (!toDelete) return;
            try {
              await deleteVacation.mutateAsync(toDelete.id);
              toast("Affectation supprimée.");
              setToDelete(null);
            } catch (err) {
              toast(
                getApiErrorMessage(
                  err,
                  "Échec de la suppression de l’affectation.",
                ),
                "danger",
              );
            }
          }}
        />

        <ConfirmDialog
          open={bulkDeleteOpen}
          onClose={() => {
            if (deleteBulk.isPending) return;
            setBulkDeleteOpen(false);
          }}
          loading={deleteBulk.isPending}
          title="Supprimer les affectations"
          description={`Supprimer ${selectedIds.length} ligne(s) sélectionnée(s) ? Utile pour refaire le planning ensuite.`}
          onConfirm={async () => {
            try {
              const n = await deleteBulk.mutateAsync(selectedIds);
              toast(`${n} affectation(s) supprimée(s).`);
              setSelectedIds([]);
              setBulkDeleteOpen(false);
            } catch (err) {
              toast(
                getApiErrorMessage(err, "Échec de la suppression groupée."),
                "danger",
              );
            }
          }}
        />
      </div>
    </PermissionGate>
  );
}
