"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { useForm, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import {
  CalendarDays,
} from "lucide-react";
import {
  useAgents,
  useCreateVacationsBulk,
  usePostes,
  useSites,
  useVacations,
} from "@/application/hooks/useResources";
import {
  agentPlanningSchema,
  buildAlternanceMotifs,
  cellulesDepuisCycle,
  cellulesDepuisSemaine,
  agentsEnPlus,
  cellulesEnregistrees,
  celluleSuivante,
  completerCellules,
  creneauxDepuisCellules,
  datesDePeriode,
  emptyPlanningSlot,
  hoursForAgentSlot,
  joursVerrouilles,
  needsAlternanceMotifPrefill,
  periodeEnregistree,
  titulairesDuPoste,
  pickAlternanceReferenceWeekStart,
  JOURS_SEMAINE,
  slotFromAgentRepos,
  type AgentPlanningFormValues,
  type AgentPlanningSlot,
  type CellulesPlanning,
  type PlannedShift,
} from "@/domain/schemas/agent-planning";
import type { JourSemaine } from "@/domain/types/entities";
import { Alert } from "@/presentation/components/ui/Alert";
import { Badge } from "@/presentation/components/ui/Badge";
import { Button } from "@/presentation/components/ui/Button";
import { Card, CardBody, CardHeader } from "@/presentation/components/ui/Card";
import { DatePicker } from "@/presentation/components/ui/DatePicker";
import {
  FieldHint,
  FieldLabel,
} from "@/presentation/components/ui/FieldLabel";
import { Select } from "@/presentation/components/ui/Select";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { useToast } from "@/presentation/providers/ToastProvider";
import { getApiErrorMessage, getBlockingVacationIds } from "@/shared/lib/api-error";
import { can } from "@/shared/lib/can";
import { cn } from "@/shared/lib/cn";
import { BlockingVacationAlert } from "../BlockingVacationAlert";
import { PlanningPeriodeGrille } from "./PlanningPeriodeGrille";
import {
  AgentMonthCalendar,
  PlanningCalendarLegend,
} from "./AgentMonthCalendar";
import {
  derivePlanningRepartition,
  hasDecoupageQuarts,
  isCycle24hInterval,
  normalizePosteHoraires,
  planningCouvertureHint,
  planningCouvertureResume,
} from "@/domain/schemas/poste-horaires";
import {
  dayNightHalvesFromPoste,
  inferQuartFromHours,
} from "@/domain/time/shift-interval";
import {
  PlanningWorkspaceShell,
  planningTwoColClassName,
} from "../PlanningWorkspaceShell";

function todayIso() {
  return new Date().toISOString().slice(0, 10);
}

function addDaysIso(iso: string, days: number) {
  const d = new Date(`${iso}T12:00:00`);
  d.setDate(d.getDate() + days);
  return d.toISOString().slice(0, 10);
}

function monthOf(iso: string) {
  return iso.slice(0, 7);
}

/** Garantit 1 jour + 1 nuit quand couverture en moitiés (2 agents). */
function ensureDistinctQuarts(
  slots: AgentPlanningSlot[],
): AgentPlanningSlot[] {
  if (slots.length < 2) return slots;
  const q0 = slots[0]?.quart ?? "jour";
  const q1 = slots[1]?.quart ?? "nuit";
  if (q0 !== q1) return slots;
  return slots.map((s, i) => ({
    ...s,
    quart: (i === 0 ? "jour" : "nuit") as "jour" | "nuit",
  }));
}

/** Un titulaire en congé aujourd’hui reste sur le planning des jours où il revient. */
const STATUTS_PLANIFIABLES = ["disponible", "en_activite", "conge", "malade"];

/** Motifs semaine 0 = jours saisis de chaque slot (alternance N agents). */
function alternanceMotifsFromSlots(
  slots: AgentPlanningSlot[],
  modeEffectif: "ensemble" | "alternance",
): JourSemaine[][] | undefined {
  if (modeEffectif !== "alternance" || slots.length < 2) return undefined;
  const motifs = slots.map((s) => (s.jours_travailles ?? []) as JourSemaine[]);
  if (motifs.some((m) => m.length === 0)) return undefined;
  return motifs;
}

const emptyDefaults: AgentPlanningFormValues = {
  slots: [],
  site_id: "",
  poste_id: "",
  date_debut: todayIso(),
  date_fin: addDaysIso(todayIso(), 27),
  repartition: "jour_entier",
  mode_effectif: "ensemble",
  quart: "jour",
  heure_debut: "",
  heure_fin: "",
  heure_debut_nuit: "",
  heure_fin_nuit: "",
};

function SectionTitle({
  step,
  title,
  hint,
}: {
  step: string;
  title: string;
  hint?: string;
}) {
  return (
    <div className="mb-3 flex items-start gap-2.5">
      <span className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded bg-teal/10 text-[10px] font-semibold tabular-nums text-teal">
        {step}
      </span>
      <div>
        <h2 className="text-sm font-semibold tracking-tight text-ink">
          {title}
        </h2>
        {hint ? (
          <p className="mt-0.5 text-xs text-ink-muted">{hint}</p>
        ) : null}
      </div>
    </div>
  );
}

export function PlanifierAgentForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { toast } = useToast();
  const { user } = useAuth();
  const canCreate = can(user, "vacations.create");
  const createBulk = useCreateVacationsBulk();
  const [formError, setFormError] = useState<{
    message: string;
    blockingVacationIds: string[];
  } | null>(null);
  const [progress, setProgress] = useState(false);
  const [calendarMonth, setCalendarMonth] = useState(monthOf(todayIso()));
  const [agentsVusParPoste, setAgentsVusParPoste] = useState<Record<string, string[]>>({});
  const [aRetirerParPoste, setARetirerParPoste] = useState<Record<string, string[]>>({});

  const urlAgentId = searchParams.get("agent_id") ?? "";
  const urlSiteId = searchParams.get("site_id") ?? "";
  const urlPosteId = searchParams.get("poste_id") ?? "";
  const urlDate = searchParams.get("date") ?? "";

  const {
    register,
    handleSubmit,
    control,
    setValue,
    getValues,
    clearErrors,
    formState: { errors, isSubmitting },
  } = useForm<AgentPlanningFormValues>({
    resolver: zodResolver(agentPlanningSchema),
    mode: "onBlur",
    // Recalcule les erreurs à chaque setValue (évite message jours figé).
    reValidateMode: "onChange",
    defaultValues: {
      ...emptyDefaults,
      slots: urlAgentId ? [emptyPlanningSlot(urlAgentId)] : [],
      site_id: urlSiteId,
      poste_id: urlPosteId,
      date_debut: urlDate || emptyDefaults.date_debut,
    },
  });

  const slots = useWatch({ control, name: "slots" }) ?? [];
  const siteId = useWatch({ control, name: "site_id" });
  const posteId = useWatch({ control, name: "poste_id" });
  const dateDebut = useWatch({ control, name: "date_debut" });
  const dateFin = useWatch({ control, name: "date_fin" });
  const repartition =
    useWatch({ control, name: "repartition" }) ?? "jour_entier";
  const heureDebut = useWatch({ control, name: "heure_debut" });
  const heureFin = useWatch({ control, name: "heure_fin" });
  const heureDebutNuit = useWatch({ control, name: "heure_debut_nuit" });
  const heureFinNuit = useWatch({ control, name: "heure_fin_nuit" });

  const { data: agentsData, isLoading: agentsLoading } = useAgents(
    { type: "agent", all: true, contrat_valide: true },
    { refetchOnMount: "always" },
  );
  const { data: sitesData } = useSites({ all: true });
  const { data: postesData, isFetching: postesFetching } = usePostes(
    siteId ? { site_id: siteId, all: true } : undefined,
    { enabled: !!siteId, refetchOnMount: "always" },
  );
  const { data: posteVacations, isFetching: posteVacationsLoading } =
    useVacations(
      // Le passé lointain n’est plus modifiable : inutile de tout recharger.
      {
        poste_id: posteId,
        all: true,
        date_from:
          dateDebut && dateDebut < addDaysIso(todayIso(), -7)
            ? dateDebut
            : addDaysIso(todayIso(), -7),
      },
      { enabled: !!posteId },
    );

  const vacationsDuPoste = useMemo(
    () => posteVacations?.data ?? [],
    [posteVacations],
  );

  const pendingPosteRef = useRef(urlPosteId);
  const prefilledPosteRef = useRef("");
  /** Poste pour lequel horaires ont déjà été appliqués. */
  const hoursAppliedPosteRef = useRef("");

  /** Agents planifiables : disponibles, ou déjà en activité (autres jours / postes). */
  const assignableAgents = useMemo(
    () =>
      (agentsData?.data ?? []).filter((a) =>
        ["disponible", "en_activite"].includes(a.statut),
      ),
    [agentsData],
  );

  const selectedPoste = useMemo(
    () => (postesData?.data ?? []).find((p) => p.id === posteId),
    [postesData, posteId],
  );

  const agentsRequis = Math.max(1, selectedPoste?.agents_requis ?? 1);
  const posteHoursNormalized = useMemo(
    () =>
      normalizePosteHoraires({
        heure_debut: heureDebut || "",
        heure_fin: heureFin || "",
        heure_debut_nuit: heureDebutNuit || "",
        heure_fin_nuit: heureFinNuit || "",
      }),
    [heureDebut, heureFin, heureDebutNuit, heureFinNuit],
  );
  const posteHasDecoupage = hasDecoupageQuarts(posteHoursNormalized);
  const posteCycle24h = isCycle24hInterval(posteHoursNormalized);
  const posteModeEffectif =
    !posteHasDecoupage &&
    agentsRequis >= 2 &&
    selectedPoste?.mode_effectif === "alternance"
      ? "alternance"
      : "ensemble";
  const posteReady = !!posteId && !!selectedPoste;

  // Répartition = règle du poste (pas de select figé).
  const derivedRepartition = useMemo(
    () => derivePlanningRepartition(posteHoursNormalized, agentsRequis),
    [posteHoursNormalized, agentsRequis],
  );

  const posteHours = {
    heure_debut: posteHoursNormalized.heure_debut,
    heure_fin: posteHoursNormalized.heure_fin,
    heure_debut_nuit: posteHoursNormalized.heure_debut_nuit || undefined,
    heure_fin_nuit: posteHoursNormalized.heure_fin_nuit || undefined,
    repartition: derivedRepartition,
  };
  const posteHalves = useMemo(() => {
    // Halves uniquement si le poste a un découpage enregistré.
    if (!posteHasDecoupage || !heureDebut || !heureFin) return null;
    return dayNightHalvesFromPoste(posteHoursNormalized);
  }, [
    heureDebut,
    heureFin,
    posteHasDecoupage,
    posteHoursNormalized,
  ]);

  const quartDeVacation = useCallback(
    (v: { heure_debut: string; heure_fin: string }) =>
      inferQuartFromHours(v.heure_debut, v.heure_fin, posteHalves),
    [posteHalves],
  );

  const posteHorairesLabel = useMemo(() => {
    if (!heureDebut || !heureFin) return "—";
    return planningCouvertureResume(posteHoursNormalized, agentsRequis);
  }, [posteHoursNormalized, agentsRequis, heureDebut, heureFin]);

  const posteCouvertureHint = useMemo(
    () =>
      planningCouvertureHint(
        posteHoursNormalized,
        agentsRequis,
        posteModeEffectif,
      ),
    [posteHoursNormalized, agentsRequis, posteModeEffectif],
  );

  // Réappliquer le poste une fois la liste des postes du site chargée.
  useEffect(() => {
    const wanted = pendingPosteRef.current || getValues("poste_id");
    if (!wanted || !siteId || !postesData?.data) return;
    const exists = postesData.data.some((p) => p.id === wanted);
    if (exists && getValues("poste_id") !== wanted) {
      setValue("poste_id", wanted, { shouldValidate: true });
    }
    if (exists) {
      pendingPosteRef.current = "";
    }
  }, [siteId, postesData, getValues, setValue]);

  // Ajuster slots à agents_requis quand le poste / effectif change.
  useEffect(() => {
    if (!selectedPoste) return;
    const n = Math.max(1, selectedPoste.agents_requis ?? 1);
    const current = getValues("slots") ?? [];
    const next = Array.from({ length: n }, (_, i) => {
      const existing = current[i];
      if (existing) {
        return {
          ...existing,
          quart: existing.quart ?? (i === 0 ? "jour" : "nuit"),
        };
      }
      return emptyPlanningSlot("", i === 0 ? "jour" : "nuit");
    });
    const same =
      current.length === n &&
      current.every(
        (s, i) =>
          s.quart === next[i].quart &&
          s.agent_id === next[i].agent_id &&
          (s.jours_travailles?.length ?? 0) ===
            (next[i].jours_travailles?.length ?? 0),
      );
    if (same && current.length === n && current.every((s) => s.quart)) return;
    setValue("slots", next, { shouldValidate: true });
  }, [selectedPoste?.id, selectedPoste?.agents_requis, getValues, setValue]);

  // Retour sur un poste planifié : on recharge ce qui est enregistré, sans rien deviner.
  useEffect(() => {
    if (!posteId || !selectedPoste || !posteVacations) return;
    if (posteVacationsLoading || postesFetching || agentsLoading) return;
    if (hoursAppliedPosteRef.current !== posteId) return;
    if (prefilledPosteRef.current === posteId) return;

    const n = Math.max(1, selectedPoste.agents_requis ?? 1);
    const periode = urlDate
      ? null
      : periodeEnregistree(selectedPoste, todayIso());
    if (periode) {
      setValue("date_debut", periode.debut);
      setValue("date_fin", periode.fin);
    }

    const titulaires = titulairesDuPoste(
      posteVacations.data,
      periode?.debut ?? getValues("date_debut"),
      periode?.fin ?? getValues("date_fin"),
      quartDeVacation,
    ).filter((t) =>
      (agentsData?.data ?? []).some(
        (a) => a.id === t.id && STATUTS_PLANIFIABLES.includes(a.statut),
      ),
    );
    const referenceWeek =
      n >= 2 && !posteHasDecoupage && selectedPoste.mode_effectif === "alternance"
        ? pickAlternanceReferenceWeekStart(titulaires.flatMap((t) => t.dates))
        : null;

    const current = getValues("slots") ?? [];
    const pris = new Set(current.map((s) => s.agent_id).filter(Boolean));
    const pool = titulaires.filter((t) => !pris.has(t.id));
    const prendre = (quart: "jour" | "nuit") => {
      const i = pool.findIndex((t) => t.quart === quart);
      return pool.splice(Math.max(i, 0), 1)[0] ?? null;
    };

    let next = Array.from({ length: n }, (_, i) => {
      const defaut = i === 0 ? "jour" : "nuit";
      const existant = current[i];
      const titulaire = existant?.agent_id
        ? (titulaires.find((t) => t.id === existant.agent_id) ?? null)
        : prendre(defaut);
      if (!titulaire) {
        return existant ?? emptyPlanningSlot("", defaut);
      }
      return slotFromAgentRepos(
        titulaire.id,
        null,
        titulaire.dates,
        titulaire.quart,
        referenceWeek,
      );
    });
    if (n === 2 && getValues("repartition") === "moities") {
      next = ensureDistinctQuarts(next);
    }

    setValue("slots", next, { shouldValidate: true });
    prefilledPosteRef.current = posteId;
  }, [
    posteId,
    selectedPoste,
    posteVacations,
    posteVacationsLoading,
    postesFetching,
    agentsLoading,
    agentsData,
    quartDeVacation,
    posteHasDecoupage,
    urlDate,
    getValues,
    setValue,
  ]);

  // Changement de poste → réautoriser sync horaires / préremplissage.
  useEffect(() => {
    prefilledPosteRef.current = "";
    hoursAppliedPosteRef.current = "";
  }, [posteId]);

  // Horaires depuis le poste — une seule fois par poste (ne pas écraser le planning).
  useEffect(() => {
    if (!selectedPoste) return;
    if (hoursAppliedPosteRef.current === selectedPoste.id) return;
    hoursAppliedPosteRef.current = selectedPoste.id;

    const hours = normalizePosteHoraires({
      heure_debut: selectedPoste.heure_debut?.slice(0, 5) ?? "",
      heure_fin: selectedPoste.heure_fin?.slice(0, 5) ?? "",
      heure_debut_nuit: selectedPoste.heure_debut_nuit?.slice(0, 5) ?? "",
      heure_fin_nuit: selectedPoste.heure_fin_nuit?.slice(0, 5) ?? "",
    });

    setValue("heure_debut", hours.heure_debut);
    setValue("heure_fin", hours.heure_fin);
    setValue("heure_debut_nuit", hours.heure_debut_nuit);
    setValue("heure_fin_nuit", hours.heure_fin_nuit);

    const n = Math.max(1, selectedPoste.agents_requis ?? 1);
    const decoupage = hasDecoupageQuarts(hours);
    if (!decoupage) {
      setValue("quart", "jour");
    } else if (n > 1) {
      setValue("quart", "les_deux");
    }

    const nextRepartition = derivePlanningRepartition(hours, n);
    setValue("repartition", nextRepartition);
    const mode =
      !decoupage && n >= 2 && selectedPoste.mode_effectif === "alternance"
        ? "alternance"
        : "ensemble";
    setValue("mode_effectif", mode);
    if (nextRepartition === "moities" && n >= 2) {
      const current = getValues("slots") ?? [];
      if (current.length >= 2) {
        setValue("slots", ensureDistinctQuarts(current), {
          shouldValidate: true,
        });
      }
    }
  }, [selectedPoste, getValues, setValue]);

  // Aligne mode_effectif sur le poste sélectionné (effectif / découpage).
  useEffect(() => {
    if (!posteReady) return;
    setValue("mode_effectif", posteModeEffectif, { shouldValidate: true });
  }, [posteReady, posteModeEffectif, setValue]);

  // Alternance N agents : préremplir une répartition équilibrée si jours au défaut.
  useEffect(() => {
    if (!posteReady || posteModeEffectif !== "alternance") return;
    if (agentsRequis < 2) return;
    const current = getValues("slots") ?? [];
    if (!needsAlternanceMotifPrefill(current, agentsRequis)) return;
    const motifs = buildAlternanceMotifs(agentsRequis);
    const next = current.map((s, i) => ({
      ...s,
      jours_travailles: motifs[i] ? [...motifs[i]] : [...(s.jours_travailles ?? [])],
      jour_repos: "" as const,
    }));
    setValue("slots", next, { shouldDirty: true, shouldValidate: true });
  }, [
    posteReady,
    posteModeEffectif,
    agentsRequis,
    getValues,
    setValue,
    slots.length,
  ]);

  // Garde la répartition alignée sur le poste (effectif / horaires).
  useEffect(() => {
    if (!posteReady) return;
    if (repartition === derivedRepartition) return;
    setValue("repartition", derivedRepartition, { shouldValidate: true });
    if (derivedRepartition === "moities" && agentsRequis >= 2) {
      const current = getValues("slots") ?? [];
      setValue("slots", ensureDistinctQuarts(current), {
        shouldValidate: true,
      });
    }
  }, [
    posteReady,
    derivedRepartition,
    repartition,
    agentsRequis,
    getValues,
    setValue,
  ]);

  const datesPeriode = useMemo(
    () =>
      dateDebut && dateFin && dateFin >= dateDebut
        ? datesDePeriode(dateDebut, dateFin)
        : [],
    [dateDebut, dateFin],
  );

  /** Case de travail par défaut d’un agent (quart choisi, ou jour + nuit s’il est seul). */
  const celluleDeTravail = (slot: AgentPlanningSlot, index: number) =>
    hoursForAgentSlot(index, agentsRequis, {
      ...posteHours,
      quart: slot.quart ?? (index === 0 ? "jour" : "nuit"),
    }).quart;

  const rythmeDeSemaine = (
    slot: AgentPlanningSlot,
    index: number,
    tous: AgentPlanningSlot[],
  ): CellulesPlanning =>
    cellulesDepuisSemaine({
      date_debut: datesPeriode[0],
      date_fin: datesPeriode[datesPeriode.length - 1],
      jours_travailles: slot.jours_travailles as JourSemaine[],
      travail: celluleDeTravail(slot, index),
      mode_effectif: posteModeEffectif,
      alternanceMotifs: alternanceMotifsFromSlots(tous, posteModeEffectif),
      slotIndex: index,
    });

  // Chaque jour de la période a sa case : l’enregistré, sinon le rythme de l’agent.
  const agentsKey = slots.map((s) => s.agent_id).join("|");
  useEffect(() => {
    if (datesPeriode.length === 0 || !heureDebut || !heureFin) return;
    if (posteVacationsLoading || prefilledPosteRef.current !== posteId) return;
    const current = getValues("slots") ?? [];
    let changed = false;
    const next = current.map((slot, index) => {
      if (!slot.agent_id) return slot;
      const manquante = datesPeriode.some((d) => !slot.cellules?.[d]);
      if (!manquante) return slot;
      changed = true;
      return {
        ...slot,
        cellules: completerCellules(
          slot.cellules,
          datesPeriode,
          cellulesEnregistrees(
            slot.agent_id,
            vacationsDuPoste,
            quartDeVacation,
          ),
          rythmeDeSemaine(slot, index, current),
        ),
      };
    });
    if (changed) setValue("slots", next, { shouldValidate: true });
    // eslint-disable-next-line react-hooks/exhaustive-deps -- recalcul à l’arrivée de nouvelles cases, pas à chaque clic
  }, [
    agentsKey,
    datesPeriode,
    posteId,
    heureDebut,
    heureFin,
    vacationsDuPoste,
    posteVacationsLoading,
  ]);

  // Préremplir le rythme URL agent une fois la fiche / vacations chargées.
  useEffect(() => {
    if (!urlAgentId || assignableAgents.length === 0) return;
    const current = getValues("slots") ?? [];
    const idx = current.findIndex((s) => s.agent_id === urlAgentId);
    if (idx < 0) return;
    // Ne pas écraser un rythme déjà personnalisé (moins de 7 jours).
    if ((current[idx].jours_travailles?.length ?? 7) < 7) return;
    const agent = assignableAgents.find((a) => a.id === urlAgentId);
    const dates = (posteVacations?.data ?? [])
      .filter(
        (v) =>
          v.agent_id === urlAgentId &&
          v.statut !== "annulee" &&
          v.statut !== "a_recouvrir",
      )
      .map((v) => v.date_debut.slice(0, 10));
    const nextSlot = slotFromAgentRepos(
      urlAgentId,
      posteModeEffectif === "alternance"
        ? null
        : (agent?.jour_repos as JourSemaine | null | undefined),
      dates,
      current[idx].quart ?? "jour",
      posteModeEffectif === "alternance"
        ? pickAlternanceReferenceWeekStart(dates)
        : null,
    );
    if (
      nextSlot.jours_travailles.length === current[idx].jours_travailles.length
    ) {
      return;
    }
    const next = [...current];
    next[idx] = nextSlot;
    setValue("slots", next, { shouldValidate: true });
  }, [
    urlAgentId,
    assignableAgents,
    posteVacations,
    getValues,
    setValue,
  ]);

  // Le calendrier suit la date de début : ajustement pendant le rendu plutôt
  // qu’un setState dans un effet (rendu en cascade).
  const [moisSuiviPour, setMoisSuiviPour] = useState<string | undefined>(undefined);
  if (dateDebut !== moisSuiviPour) {
    setMoisSuiviPour(dateDebut);
    if (dateDebut) setCalendarMonth(monthOf(dateDebut));
  }

  const selectedAgentIds = useMemo(
    () => slots.map((s) => s.agent_id).filter(Boolean),
    [slots],
  );

  const agentCalendars = useMemo(() => {
    if (
      !dateDebut ||
      !dateFin ||
      !heureDebut ||
      !heureFin ||
      !selectedAgentIds.length
    ) {
      return [] as Array<{
        agentId: string;
        title: string;
        planned: PlannedShift[];
        reposIndexes: number[];
      }>;
    }

    const filled = slots.filter((s) => s.agent_id);
    return filled.flatMap((slot, index) => {
      if (!slot.cellules) return [];
      const hours = hoursForAgentSlot(index, filled.length, {
        ...posteHours,
        quart: slot.quart ?? (index === 0 ? "jour" : "nuit"),
      });
      if (!hours.heure_debut || !hours.heure_fin) return [];
      const agent = agentsData?.data?.find((a) => a.id === slot.agent_id);
      const quartLabel =
        posteHours.repartition === "moities"
          ? hours.quart === "nuit"
            ? "Nuit"
            : "Jour"
          : null;
      const title = agent
        ? `${agent.prenom} ${agent.nom}${quartLabel ? ` · ${quartLabel}` : ""}`
        : hours.label;
      return [
        {
          agentId: slot.agent_id,
          title,
          planned: creneauxDepuisCellules({
            ...posteHours,
            cellules: slot.cellules,
            date_debut: dateDebut,
            date_fin: dateFin,
            index,
            total: filled.length,
            verrous: joursVerrouilles(
              slot.agent_id,
              vacationsDuPoste,
              datesPeriode,
              todayIso(),
            ),
          }),
          // La grille décide jour par jour : plus de repos fixe par jour de semaine.
          reposIndexes: [] as number[],
        },
      ];
    });
  }, [
    dateDebut,
    dateFin,
    heureDebut,
    heureFin,
    selectedAgentIds,
    slots,
    agentsData,
    vacationsDuPoste,
    datesPeriode,
    posteHoursNormalized,
    derivedRepartition,
  ]);

  const totalPlannedShifts = useMemo(
    () => agentCalendars.reduce((sum, c) => sum + c.planned.length, 0),
    [agentCalendars],
  );

  const existingByAgent = useMemo(() => {
    const selected = new Set(selectedAgentIds);
    const byAgent = new Map<
      string,
      NonNullable<typeof posteVacations>["data"]
    >();
    for (const v of posteVacations?.data ?? []) {
      if (!v.agent_id) continue;
      // Ces vacations seront effacées par `replace` à l’enregistrement :
      // ne pas les afficher comme « déjà planifié / conflit » dans l’aperçu.
      const date = v.date_debut.slice(0, 10);
      const willReplace =
        !!posteId &&
        v.poste_id === posteId &&
        selected.has(v.agent_id) &&
        !!dateDebut &&
        !!dateFin &&
        date >= dateDebut &&
        date <= dateFin &&
        date >= todayIso() &&
        !v.est_remplacement &&
        (v.statut === "planifiee" || v.statut === "a_recouvrir");
      if (willReplace) continue;

      const cur = byAgent.get(v.agent_id) ?? [];
      cur.push(v);
      byAgent.set(v.agent_id, cur);
    }
    return byAgent;
  }, [posteVacations, posteId, selectedAgentIds, dateDebut, dateFin]);

  const siteOptions = useMemo(
    () =>
      (sitesData?.data ?? []).map((s) => ({
        value: s.id,
        label: s.nom,
      })),
    [sitesData],
  );

  const posteOptions = useMemo(() => {
    return (postesData?.data ?? []).map((p) => {
      const n = p.agents_requis ?? 1;
      const effectif = `${n} agent${n > 1 ? "s" : ""}`;
      const h24 = p.heure_debut_nuit && p.heure_fin_nuit ? " · 24h" : "";
      return {
        value: p.id,
        label: `${p.nom} · ${effectif}${h24}`,
      };
    });
  }, [postesData]);

  // Un agent sorti de la grille à la main est remplacé : seuls ceux qu’elle n’a
  // jamais montrés gardent leurs créneaux.
  useEffect(() => {
    if (prefilledPosteRef.current !== posteId) return;
    setAgentsVusParPoste((parPoste) => {
      const vus = parPoste[posteId] ?? [];
      const nouveaux = selectedAgentIds.filter((id) => !vus.includes(id));
      return nouveaux.length > 0
        ? { ...parPoste, [posteId]: [...vus, ...nouveaux] }
        : parPoste;
    });
  }, [selectedAgentIds, posteId]);

  const agentsARetirer = aRetirerParPoste[posteId] ?? [];

  const autresAgentsDuPoste = useMemo(
    () =>
      agentsEnPlus(
        vacationsDuPoste,
        new Set([...(agentsVusParPoste[posteId] ?? []), ...selectedAgentIds]),
        todayIso(),
      ),
    [vacationsDuPoste, agentsVusParPoste, posteId, selectedAgentIds],
  );

  const agentOptionsForSlot = (slotIndex: number) => {
    const taken = new Set(
      slots
        .map((s, i) => (i === slotIndex ? "" : s.agent_id))
        .filter(Boolean),
    );
    return (agentsData?.data ?? [])
      .filter(
        (a) =>
          a.id === slots[slotIndex]?.agent_id ||
          (!taken.has(a.id) && assignableAgents.includes(a)),
      )
      .map((a) => ({
        value: a.id,
        label: `${a.prenom} ${a.nom} (${a.matricule})${
          a.statut === "disponible"
            ? ""
            : a.statut === "en_activite"
              ? " · en activité"
              : ` · ${a.statut}`
        }${a.jour_repos ? ` — repos ${a.jour_repos}` : ""}`,
      }));
  };

  const updateSlot = (
    index: number,
    patch: Partial<AgentPlanningSlot>,
    options?: { shouldValidate?: boolean },
  ) => {
    const current = [...(getValues("slots") ?? [])];
    while (current.length < agentsRequis) current.push(emptyPlanningSlot());
    current[index] = { ...current[index], ...patch };
    setValue("slots", current.slice(0, agentsRequis), {
      shouldDirty: true,
      shouldValidate: options?.shouldValidate ?? true,
    });
  };

  const vacationDatesForAgent = (agentId: string) =>
    (posteVacations?.data ?? [])
      .filter(
        (v) =>
          v.agent_id === agentId &&
          v.poste_id === posteId &&
          v.statut !== "annulee" &&
          v.statut !== "a_recouvrir",
      )
      .map((v) => v.date_debut.slice(0, 10));

  const alternanceReferenceWeek = useMemo(() => {
    if (posteModeEffectif !== "alternance") return null;
    const dates = (posteVacations?.data ?? [])
      .filter(
        (v) =>
          v.poste_id === posteId &&
          v.statut !== "annulee" &&
          v.statut !== "a_recouvrir",
      )
      .map((v) => v.date_debut.slice(0, 10));
    return pickAlternanceReferenceWeekStart(dates);
  }, [posteVacations, posteId, posteModeEffectif]);

  const buildSlotForAgent = (
    agentId: string,
    quart: "jour" | "nuit" = "jour",
  ): AgentPlanningSlot => {
    const agent = assignableAgents.find((a) => a.id === agentId);
    return slotFromAgentRepos(
      agentId,
      posteModeEffectif === "alternance"
        ? null
        : (agent?.jour_repos as JourSemaine | null | undefined),
      vacationDatesForAgent(agentId),
      quart,
      alternanceReferenceWeek,
    );
  };

  const setAgentAt = (index: number, agentId: string) => {
    const currentQuart =
      getValues("slots")?.[index]?.quart ?? (index === 0 ? "jour" : "nuit");
    if (!agentId) {
      updateSlot(index, emptyPlanningSlot("", currentQuart));
      return;
    }
    const existant = getValues("slots")?.[index];
    // Le programme appartient à la ligne : changer le nom ne le modifie pas.
    updateSlot(
      index,
      existant?.agent_id
        ? { agent_id: agentId }
        : buildSlotForAgent(agentId, currentQuart),
    );
  };

  const setJoursAt = (index: number, jours: string[]) => {
    // Les conflits jours sont cross-slots : clear toutes les erreurs jours
    // avant revalidate, sinon RHF+zod peut laisser un ancien message affiché.
    const current = getValues("slots") ?? [];
    for (let i = 0; i < Math.max(current.length, agentsRequis); i++) {
      clearErrors(`slots.${i}.jours_travailles`);
    }
    const tous = current.map((s, i) =>
      i === index
        ? { ...s, jours_travailles: jours as JourSemaine[], jour_repos: "" as const }
        : s,
    );
    remplirSelonSemaine(
      tous,
      posteModeEffectif === "alternance" ? undefined : index,
    );
  };

  /** Réécrit le programme depuis le rythme de semaine, sans toucher aux cases verrouillées. */
  const remplirSelonSemaine = (
    tous: AgentPlanningSlot[],
    seulement?: number,
  ) => {
    if (datesPeriode.length === 0) {
      setValue("slots", tous, { shouldDirty: true, shouldValidate: true });
      return;
    }
    const next = tous.map((slot, i) =>
      seulement !== undefined && i !== seulement
        ? slot
        : {
            ...slot,
            cellules: appliquerHorsVerrous(
              slot,
              (slot.jours_travailles?.length ?? 0) > 0
                ? rythmeDeSemaine(slot, i, tous)
                : {},
            ),
          },
    );
    setValue("slots", next, { shouldDirty: true, shouldValidate: true });
  };

  const verrousDe = (agentId: string) =>
    agentId
      ? joursVerrouilles(agentId, vacationsDuPoste, datesPeriode, todayIso())
      : {};

  const appliquerHorsVerrous = (
    slot: AgentPlanningSlot,
    nouvelles: CellulesPlanning,
  ): CellulesPlanning => {
    const verrous = verrousDe(slot.agent_id);
    const cellules: CellulesPlanning = { ...slot.cellules };
    for (const date of datesPeriode) {
      if (!verrous[date]) cellules[date] = nouvelles[date] ?? "repos";
    }
    return cellules;
  };

  const setCellule = (index: number, date: string) => {
    const slot = getValues("slots")?.[index];
    if (!slot) return;
    const actuelle = slot.cellules?.[date] ?? "repos";
    clearErrors(`slots.${index}.jours_travailles`);
    updateSlot(index, {
      cellules: {
        ...slot.cellules,
        [date]: celluleSuivante(actuelle, {
          decoupage: posteHasDecoupage,
          agentSeul: agentsRequis <= 1,
        }),
      },
    });
  };

  const appliquerCycle = (
    index: number,
    cycle: { travail: number; repos: number; decalage: number },
  ) => {
    const slot = getValues("slots")?.[index];
    if (!slot || datesPeriode.length === 0) return;
    updateSlot(index, {
      cellules: appliquerHorsVerrous(
        slot,
        cellulesDepuisCycle({
          ...cycle,
          date_debut: datesPeriode[0],
          date_fin: datesPeriode[datesPeriode.length - 1],
          cellule: celluleDeTravail(slot, index),
        }),
      ),
    });
  };

  const setQuartAt = (index: number, quart: "jour" | "nuit") => {
    const current = [...(getValues("slots") ?? [])];
    while (current.length < agentsRequis) current.push(emptyPlanningSlot());
    // Le quart s’applique aux jours travaillés de la période.
    const changerQuart = (slot: AgentPlanningSlot, q: "jour" | "nuit") => {
      const verrous = verrousDe(slot.agent_id);
      const cellules: CellulesPlanning = { ...slot.cellules };
      for (const date of datesPeriode) {
        if (!verrous[date] && cellules[date] && cellules[date] !== "repos") {
          cellules[date] = q;
        }
      }
      return { ...slot, quart: q, cellules };
    };
    current[index] = changerQuart(current[index], quart);
    // Évite deux agents sur le même quart en mode moitiés.
    if (repartition === "moities" && agentsRequis >= 2) {
      for (let i = 0; i < current.length; i++) {
        if (i === index) continue;
        if (current[i].quart === quart) {
          current[i] = changerQuart(
            current[i],
            quart === "jour" ? "nuit" : "jour",
          );
        }
      }
    }
    setValue("slots", current.slice(0, agentsRequis), {
      shouldDirty: true,
      shouldValidate: true,
    });
  };

  const busy = isSubmitting || createBulk.isPending || progress;

  const onSubmit = handleSubmit(async (values) => {
    try {
      await runPlanning(values, false);
    } catch {
      // formError déjà renseigné
    }
  });

  const runPlanning = async (
    values: AgentPlanningFormValues,
    annulerConflits: boolean,
  ) => {
    if (!canCreate) {
      toast("Vous n’avez pas le droit de planifier.", "danger");
      return;
    }
    setFormError(null);

    const filledSlots = values.slots.filter((s) => s.agent_id);
    if (filledSlots.length === 0) {
      setFormError({
        message: "Sélectionnez les agents du poste.",
        blockingVacationIds: [],
      });
      return;
    }

    const agentPlans = filledSlots.map((slot, index) => ({
      agentId: slot.agent_id,
      shifts: creneauxDepuisCellules({
        cellules: slot.cellules ?? {},
        date_debut: values.date_debut,
        date_fin: values.date_fin,
        index,
        total: filledSlots.length,
        verrous: verrousDe(slot.agent_id),
        heure_debut: values.heure_debut || "",
        heure_fin: values.heure_fin || "",
        heure_debut_nuit: values.heure_debut_nuit || undefined,
        heure_fin_nuit: values.heure_fin_nuit || undefined,
        repartition: values.repartition ?? "jour_entier",
      }),
    }));

    const vacations = agentPlans.flatMap((plan) =>
      plan.shifts.map((shift) => ({
        agent_id: plan.agentId,
        site_id: values.site_id,
        poste_id: values.poste_id,
        date_debut: shift.date,
        date_fin: shift.date_fin,
        heure_debut: shift.heure_debut,
        heure_fin: shift.heure_fin,
      })),
    );

    if (vacations.length === 0) {
      setFormError({
        message:
          "Aucun créneau à créer avec ce rythme — vérifiez les jours et horaires.",
        blockingVacationIds: [],
      });
      return;
    }

    setProgress(true);
    try {
      const result = await createBulk.mutateAsync({
        vacations,
        annuler_conflits: annulerConflits || undefined,
        replace: {
          poste_id: values.poste_id,
          date_debut: values.date_debut,
          date_fin: values.date_fin,
          scope: "poste",
          agent_ids: filledSlots.map((s) => s.agent_id),
          conserver_agent_ids: autresAgentsDuPoste
            .map((a) => a.id)
            .filter((id) => !agentsARetirer.includes(id)),
        },
      });

      const created = result.data.created;
      const removed = result.data.removed;
      const agentLabel =
        filledSlots.length === 1
          ? "1 agent"
          : `${filledSlots.length} agents`;
      const creneauxLabel =
        created === 1 ? "1 créneau" : `${created} créneaux`;
      toast(
        [
          removed > 0 ? `${removed} ancienne(s) remplacée(s)` : null,
          `${agentLabel} · ${creneauxLabel}`,
        ]
          .filter(Boolean)
          .join(" · ") + ".",
      );
      router.push("/vacations");
    } catch (err) {
      const message = getApiErrorMessage(err, "Échec de la planification.");
      setFormError({
        message,
        blockingVacationIds: getBlockingVacationIds(err),
      });
      toast(message, "danger");
      throw err;
    } finally {
      setProgress(false);
    }
  };

  const submitLabel = progress
    ? `Enregistrement · ${totalPlannedShifts} créneau${totalPlannedShifts > 1 ? "x" : ""}…`
    : totalPlannedShifts > 0
      ? `Enregistrer · ${totalPlannedShifts} créneau${totalPlannedShifts > 1 ? "x" : ""}`
      : "Enregistrer le planning";

  const slotsError =
    errors.slots?.message ||
    (Array.isArray(errors.slots)
      ? errors.slots.find((e) => e?.message || e?.agent_id?.message)?.message ||
        errors.slots.find((e) => e?.agent_id?.message)?.agent_id?.message
      : undefined);

  return (
    <PlanningWorkspaceShell
      siteId={siteId || undefined}
      icon={<CalendarDays className="size-5" />}
      title="Planifier le poste"
      description="Choisissez un poste : le formulaire s’adapte à l’effectif. Chaque agent a son propre rythme."
    >
      {!canCreate ? (
        <Alert tone="danger">Droits insuffisants pour planifier.</Alert>
      ) : (
        <form
          onSubmit={onSubmit}
          className={planningTwoColClassName}
          noValidate
        >
          <div className="min-w-0 space-y-4">
            <Card>
              <CardBody className="p-4">
                <SectionTitle
                  step="1"
                  title="Site et poste"
                  hint="Le poste définit l’effectif, les horaires et la couverture 24h."
                />
                <div className="grid gap-3 sm:grid-cols-2">
                  <Select
                    label="Site"
                    requiredMark
                    placeholder="Site"
                    options={siteOptions}
                    error={errors.site_id?.message}
                    value={siteId ?? ""}
                    onChange={(e) => {
                      setValue("site_id", e.target.value, {
                        shouldDirty: true,
                        shouldValidate: true,
                      });
                      setValue("poste_id", "", { shouldDirty: true });
                      setValue("slots", [], { shouldDirty: true });
                      pendingPosteRef.current = "";
                      prefilledPosteRef.current = "";
                    }}
                  />
                  <Select
                    label="Poste"
                    requiredMark
                    hint={
                      siteId && !posteId
                        ? "Choisissez le poste à couvrir"
                        : undefined
                    }
                    placeholder={
                      siteId ? "Poste" : "Choisissez d’abord un site"
                    }
                    options={posteOptions}
                    disabled={!siteId}
                    error={errors.poste_id?.message}
                    value={posteId ?? ""}
                    onChange={(e) => {
                      prefilledPosteRef.current = "";
                      setValue("slots", [], { shouldDirty: true });
                      setValue("poste_id", e.target.value, {
                        shouldDirty: true,
                        shouldValidate: true,
                      });
                    }}
                  />
                </div>
                {selectedPoste ? (
                  <div className="mt-3 space-y-1.5">
                    <div className="flex flex-wrap gap-1.5">
                      <Badge tone="success">
                        {agentsRequis} agent{agentsRequis > 1 ? "s" : ""}
                        {posteHasDecoupage
                          ? " · jour & nuit"
                          : posteCycle24h
                            ? " · 24h"
                            : ""}
                        {!posteHasDecoupage && agentsRequis >= 2
                          ? posteModeEffectif === "alternance"
                            ? " · alternance"
                            : " · ensemble"
                          : ""}
                      </Badge>
                    </div>
                    <p className="text-xs text-ink-muted">
                      Horaires du poste :{" "}
                      <span className="font-medium text-ink">
                        {posteHorairesLabel}
                      </span>
                    </p>
                    {posteCouvertureHint ? (
                      <p className="text-xs text-ink-muted">
                        {posteCouvertureHint}
                      </p>
                    ) : null}
                  </div>
                ) : null}
              </CardBody>
            </Card>

            {posteReady ? (
              <>
                <Card>
                  <CardBody className="p-4">
                    <SectionTitle
                      step="2"
                      title="Agents du poste"
                      hint={
                        derivedRepartition === "moities"
                          ? "Choisissez jour ou nuit pour chaque agent, puis ses jours."
                          : posteModeEffectif === "alternance"
                            ? "Un agent par jour : répartissez les jours, ils tourneront chaque semaine."
                            : "Mêmes horaires pour tous — choisissez les jours (souvent les mêmes)."
                      }
                    />
                    <div className="space-y-3">
                      {Array.from({ length: agentsRequis }, (_, index) => {
                        const slot = slots[index] ?? emptyPlanningSlot();
                        const slotErrors = Array.isArray(errors.slots)
                          ? errors.slots[index]
                          : undefined;
                        const joursError = slotErrors?.jours_travailles?.message;
                        const slotQuart =
                          slot.quart ?? (index === 0 ? "jour" : "nuit");
                        const hours = hoursForAgentSlot(
                          index,
                          agentsRequis,
                          {
                            ...posteHours,
                            quart: slotQuart,
                          },
                        );
                        const showQuartSelect = derivedRepartition === "moities";
                        const joursCount = slot.jours_travailles?.length ?? 0;
                        const compactAgents =
                          !showQuartSelect && posteModeEffectif === "alternance";
                        return (
                          <div
                            key={`agent-slot-${index}`}
                            className={cn(
                              "rounded-lg border border-border/80 bg-paper-muted/40",
                              compactAgents ? "px-3 py-2.5" : "p-3",
                            )}
                          >
                            <div className="flex flex-col gap-2.5">
                              <div className="min-w-0">
                                <div className="mb-1 flex flex-col gap-1 text-sm">
                                  <FieldLabel required>
                                    {hours.label}
                                    {hours.hint ? (
                                      <span className="ml-1.5 font-normal text-ink-faint">
                                        · {hours.hint}
                                      </span>
                                    ) : null}
                                  </FieldLabel>
                                </div>
                                <Select
                                  searchable
                                  placeholder={
                                    agentsLoading
                                      ? "Chargement des agents…"
                                      : assignableAgents.length === 0
                                        ? "Aucun agent disponible"
                                        : "Sélectionnez un agent disponible"
                                  }
                                  options={agentOptionsForSlot(index)}
                                  disabled={
                                    agentsLoading ||
                                    assignableAgents.length === 0
                                  }
                                  error={
                                    slotErrors?.agent_id?.message ||
                                    (!agentsLoading &&
                                    assignableAgents.length === 0
                                      ? "Aucun agent au statut disponible"
                                      : undefined)
                                  }
                                  value={slot.agent_id}
                                  onChange={(e) =>
                                    setAgentAt(index, e.target.value)
                                  }
                                />
                              </div>

                              <div className="flex flex-wrap items-end gap-x-3 gap-y-2">
                                {showQuartSelect ? (
                                  <div className="w-[7rem] shrink-0">
                                    <div className="mb-1 flex flex-col gap-1 text-sm">
                                      <FieldLabel required>Quart</FieldLabel>
                                    </div>
                                    <Select
                                      searchable={false}
                                      options={[
                                        { value: "jour", label: "Jour" },
                                        { value: "nuit", label: "Nuit" },
                                      ]}
                                      value={slotQuart}
                                      onChange={(e) =>
                                        setQuartAt(
                                          index,
                                          e.target.value as "jour" | "nuit",
                                        )
                                      }
                                    />
                                  </div>
                                ) : null}

                                <div className="min-w-0 flex-1 basis-[16rem]">
                                  <div className="mb-1 flex flex-col gap-1 text-sm">
                                    <FieldLabel required>
                                      Chaque semaine
                                      {joursCount > 0 ? (
                                        <span className="ml-1.5 font-normal text-ink-faint">
                                          ({joursCount})
                                        </span>
                                      ) : null}
                                    </FieldLabel>
                                  </div>
                                  <div className="overflow-x-auto pb-0.5">
                                    <div
                                      className="flex h-10 w-max items-center gap-1"
                                      role="group"
                                      aria-label="Jours travaillés"
                                    >
                                      {JOURS_SEMAINE.map((j) => {
                                        const selected = (
                                          slot.jours_travailles ?? []
                                        ).includes(j.value);
                                        return (
                                          <button
                                            key={j.value}
                                            type="button"
                                            title={j.label}
                                            aria-pressed={selected}
                                            onClick={() => {
                                              const current =
                                                slot.jours_travailles ?? [];
                                              const next = selected
                                                ? current.filter(
                                                    (d) => d !== j.value,
                                                  )
                                                : [...current, j.value];
                                              setJoursAt(index, next);
                                            }}
                                            className={cn(
                                              "inline-flex size-10 shrink-0 items-center justify-center rounded-md border text-xs font-semibold transition",
                                              selected
                                                ? "border-teal bg-teal text-white"
                                                : "border-border bg-white text-ink-muted hover:border-teal/40 hover:text-ink",
                                            )}
                                          >
                                            {j.short}
                                          </button>
                                        );
                                      })}
                                    </div>
                                  </div>
                                  {joursError ? (
                                    <FieldHint error={joursError} />
                                  ) : null}
                                </div>
                              </div>
                            </div>
                          </div>
                        );
                      })}
                    </div>
                    <p className="mt-2.5 text-xs text-ink-muted">
                      Ces jours remplissent la période ; ajustez ensuite chaque
                      date dans le planning ci-dessous.
                    </p>
                    {slotsError ? (
                      <p className="mt-2 text-xs text-danger">{slotsError}</p>
                    ) : null}
                  </CardBody>
                </Card>

                <Card>
                  <CardBody className="p-4">
                    <SectionTitle
                      step="3"
                      title="Période"
                      hint="Dates communes à tous les agents du poste."
                    />

                    <div className="grid gap-2.5 sm:grid-cols-2">
                      <DatePicker
                        label="Début"
                        requiredMark
                        error={errors.date_debut?.message}
                        {...register("date_debut")}
                      />
                      <DatePicker
                        label="Fin"
                        requiredMark
                        hint="Après cette date, le planning se poursuit automatiquement."
                        error={errors.date_fin?.message}
                        {...register("date_fin")}
                      />
                    </div>
                  </CardBody>
                </Card>

                {datesPeriode.length > 0 && slots.some((s) => s.agent_id) ? (
                  <Card>
                    <CardBody className="p-4">
                      <SectionTitle
                        step="4"
                        title="Planning de la période"
                        hint="Le programme exact de chaque agent, date par date. Cliquez sur une case pour la changer."
                      />
                      <PlanningPeriodeGrille
                        dates={datesPeriode}
                        lignes={slots.flatMap((slot, index) => {
                          if (!slot.agent_id) return [];
                          const agent = agentsData?.data?.find(
                            (a) => a.id === slot.agent_id,
                          );
                          return [
                            {
                              index,
                              label: agent
                                ? `${agent.prenom} ${agent.nom}`
                                : "Agent",
                              cellules: slot.cellules ?? {},
                              verrous: verrousDe(slot.agent_id),
                            },
                          ];
                        })}
                        onCellule={setCellule}
                        onCycle={appliquerCycle}
                      />
                    </CardBody>
                  </Card>
                ) : null}

                {autresAgentsDuPoste.length > 0 ? (
                  <Alert tone="warning">
                    <div className="space-y-2">
                      <p>Ce poste a aussi d’autres agents, absents de la grille :</p>
                      {autresAgentsDuPoste.map((a) => {
                        const retire = agentsARetirer.includes(a.id);
                        const creneaux = `${a.creneaux} créneau${a.creneaux > 1 ? "x" : ""}`;
                        return (
                          <div
                            key={a.id}
                            className="flex flex-wrap items-center justify-between gap-2"
                          >
                            <span>
                              {retire
                                ? `${a.nom} perdra ses ${creneaux} à l’enregistrement.`
                                : `${a.nom} garde ses ${creneaux}.`}
                            </span>
                            <Button
                              type="button"
                              variant="secondary"
                              onClick={() =>
                                setARetirerParPoste((parPoste) => ({
                                  ...parPoste,
                                  [posteId]: retire
                                    ? agentsARetirer.filter((id) => id !== a.id)
                                    : [...agentsARetirer, a.id],
                                }))
                              }
                            >
                              {retire ? "Garder" : "Retirer"}
                            </Button>
                          </div>
                        );
                      })}
                    </div>
                  </Alert>
                ) : null}

                {formError ? (
                  <BlockingVacationAlert
                    message={formError.message}
                    blockingVacationIds={formError.blockingVacationIds}
                    onForceApply={async () => {
                      await runPlanning(getValues(), true);
                    }}
                  />
                ) : null}

                <div className="hidden gap-2 lg:flex">
                  <Button
                    type="button"
                    variant="secondary"
                    disabled={busy}
                    onClick={() => router.push("/vacations")}
                  >
                    Annuler
                  </Button>
                  <Button type="submit" loading={busy} disabled={!canCreate}>
                    <CalendarDays className="size-4" />
                    {submitLabel}
                  </Button>
                </div>
              </>
            ) : (
              <Card>
                <CardBody className="p-4">
                  <p className="text-sm text-ink-muted">
                    Choisissez un site puis un poste pour continuer : agents,
                    période et horaires s’adaptent à l’effectif.
                  </p>
                </CardBody>
              </Card>
            )}
          </div>

          <aside className="min-w-0 space-y-4 lg:sticky lg:top-6 lg:self-start">
            <Card>
              <CardHeader
                title="Calendriers"
                description={
                  agentCalendars.length > 1
                    ? "Un calendrier par agent sélectionné."
                    : "Aperçu des créneaux à créer."
                }
              />
              <CardBody className="space-y-4 p-3 pt-0">
                {agentCalendars.length === 0 ? (
                  <p className="text-sm text-ink-muted">
                    Sélectionnez les agents et leurs jours travaillés pour
                    afficher les calendriers.
                  </p>
                ) : (
                  <>
                    <PlanningCalendarLegend compact />
                    {agentCalendars.map((cal) => (
                      <div key={cal.agentId} className="space-y-1.5">
                        <p className="text-xs font-semibold text-ink">
                          {cal.title}
                          <span className="ml-1.5 font-normal text-ink-faint">
                            · {cal.planned.length} créneau
                            {cal.planned.length > 1 ? "x" : ""}
                          </span>
                        </p>
                        <AgentMonthCalendar
                          month={calendarMonth}
                          onMonthChange={setCalendarMonth}
                          planned={cal.planned}
                          existing={existingByAgent.get(cal.agentId) ?? []}
                          reposDayIndexes={cal.reposIndexes}
                          showLegend={false}
                          density="compact"
                        />
                      </div>
                    ))}
                  </>
                )}
              </CardBody>
            </Card>

            <p className="hidden text-xs leading-relaxed text-ink-faint lg:block">
              À l’enregistrement, le planning à venir de ce poste sur la période
              est remplacé par cette grille. Le passé, les vacations démarrées
              et les remplacements déjà saisis sont conservés.
            </p>
          </aside>

          {posteReady ? (
            <div className="fixed inset-x-0 bottom-0 z-20 border-t border-border bg-white/95 p-3 backdrop-blur lg:hidden">
              <div className="mx-auto flex max-w-screen-2xl gap-2">
                <Button
                  type="button"
                  variant="secondary"
                  className="flex-1"
                  disabled={busy}
                  onClick={() => router.push("/vacations")}
                >
                  Annuler
                </Button>
                <Button
                  type="submit"
                  className="flex-1"
                  loading={busy}
                  disabled={!canCreate}
                >
                  {submitLabel}
                </Button>
              </div>
            </div>
          ) : null}
        </form>
      )}
    </PlanningWorkspaceShell>
  );
}
