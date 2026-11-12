"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { useForm, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { CalendarDays, ChevronLeft, ChevronRight } from "lucide-react";
import {
  useAgents,
  useDupliquerPlanningSemaine,
  usePlanningSemaine,
  usePostes,
  usePublierPlanningSemaine,
  useSites,
  useUpsertPlanningSemaine,
  useVacations,
} from "@/application/hooks/useResources";
import {
  agentPlanningSchema,
  buildAlternanceMotifs,
  cellulesDepuisCycle,
  cellulesDepuisSemaine,
  cellulesEnregistrees,
  celluleSuivante,
  completerCellules,
  creneauxDepuisCellules,
  datesDePeriode,
  emptyPlanningSlot,
  hoursForAgentSlot,
  isoJourFromDate,
  isoWeekFromDate,
  joursVerrouilles,
  needsAlternanceMotifPrefill,
  shiftIsoWeek,
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
import {
  FieldHint,
  FieldLabel,
} from "@/presentation/components/ui/FieldLabel";
import { Select } from "@/presentation/components/ui/Select";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { useToast } from "@/presentation/providers/ToastProvider";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { can } from "@/shared/lib/can";
import { cn } from "@/shared/lib/cn";
import { PlanningPeriodeGrille } from "./PlanningPeriodeGrille";
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

function formatWeekLabel(lundi: string, dimanche: string) {
  const fmt = (iso: string) => {
    const d = new Date(`${iso}T12:00:00`);
    return d.toLocaleDateString("fr-FR", {
      day: "numeric",
      month: "short",
    });
  };
  return `${fmt(lundi)} → ${fmt(dimanche)}`;
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

const currentWeek = isoWeekFromDate(todayIso());

const emptyDefaults: AgentPlanningFormValues = {
  slots: [],
  site_id: "",
  poste_id: "",
  date_debut: currentWeek.lundi,
  date_fin: currentWeek.dimanche,
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
  const upsertPlanning = useUpsertPlanningSemaine();
  const publierPlanning = usePublierPlanningSemaine();
  const dupliquerPlanning = useDupliquerPlanningSemaine();
  const [formError, setFormError] = useState<string | null>(null);
  const [progress, setProgress] = useState(false);
  const [agentsVusParPoste, setAgentsVusParPoste] = useState<Record<string, string[]>>({});

  const urlAgentId = searchParams.get("agent_id") ?? "";
  const urlSiteId = searchParams.get("site_id") ?? "";
  const urlPosteId = searchParams.get("poste_id") ?? "";
  const urlDate = searchParams.get("date") ?? "";
  const urlAnnee = searchParams.get("annee_iso");
  const urlSemaine = searchParams.get("semaine_iso");

  const initialWeek = useMemo(() => {
    if (urlAnnee && urlSemaine) {
      const shifted = shiftIsoWeek(Number(urlAnnee), Number(urlSemaine), 0);
      return shifted;
    }
    return isoWeekFromDate(urlDate || todayIso());
  }, [urlAnnee, urlSemaine, urlDate]);

  const [week, setWeek] = useState(initialWeek);

  const {
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
      date_debut: initialWeek.lundi,
      date_fin: initialWeek.dimanche,
    },
  });

  // Aligner la période du formulaire sur la semaine ISO sélectionnée.
  useEffect(() => {
    setValue("date_debut", week.lundi, { shouldDirty: false });
    setValue("date_fin", week.dimanche, { shouldDirty: false });
  }, [week.lundi, week.dimanche, setValue]);

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
      {
        poste_id: posteId,
        all: true,
        date_from: week.lundi,
        date_to: week.dimanche,
      },
      { enabled: !!posteId },
    );

  const { data: planningSemaineData, isFetching: planningSemaineLoading } =
    usePlanningSemaine(
      posteId
        ? {
            poste_id: posteId,
            annee_iso: week.annee_iso,
            semaine_iso: week.semaine_iso,
          }
        : undefined,
      { enabled: !!posteId },
    );

  const vacationsDuPoste = useMemo(
    () => posteVacations?.data ?? [],
    [posteVacations],
  );

  const planningExistant = planningSemaineData?.data;
  const planningADesCreneaux =
    (planningExistant?.creneaux?.length ?? 0) > 0;
  const planningStatut = !planningExistant?.id
    ? ("absent" as const)
    : !planningADesCreneaux
      ? ("absent" as const)
      : planningExistant.statut === "publiee"
        ? ("publiee" as const)
        : ("brouillon" as const);

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

  // Semaine sans template : slots vides — pas d’héritage S→S+1.
  // L’utilisateur doit « Dupliquer semaine précédente » ou saisir à neuf.
  useEffect(() => {
    if (!posteId || !selectedPoste) return;
    if (planningSemaineLoading || !planningSemaineData) return;
    if (hoursAppliedPosteRef.current !== posteId) return;
    const weekKey = `${posteId}:${week.annee_iso}-${week.semaine_iso}`;
    if (prefilledPosteRef.current === weekKey) return;
    if ((planningSemaineData.data.creneaux?.length ?? 0) > 0) return;

    const n = Math.max(1, selectedPoste.agents_requis ?? 1);
    const repartition = getValues("repartition") ?? "jour_entier";
    let next = Array.from({ length: n }, (_, i) =>
      emptyPlanningSlot("", i === 0 ? "jour" : "nuit"),
    );
    if (n === 2 && repartition === "moities") {
      next = ensureDistinctQuarts(next);
    }
    setValue("slots", next, { shouldValidate: true });
    prefilledPosteRef.current = weekKey;
  }, [
    posteId,
    selectedPoste,
    planningSemaineData,
    planningSemaineLoading,
    week.annee_iso,
    week.semaine_iso,
    getValues,
    setValue,
  ]);

  // Charge le template de la semaine ISO (créneaux enregistrés).
  useEffect(() => {
    if (!posteId || !selectedPoste || !planningSemaineData) return;
    if (planningSemaineLoading || hoursAppliedPosteRef.current !== posteId) return;
    const key = `${posteId}:${week.annee_iso}-${week.semaine_iso}`;
    if (prefilledPosteRef.current === key) return;

    const creneaux = planningSemaineData.data.creneaux ?? [];
    if (creneaux.length === 0) return;

    const n = Math.max(1, selectedPoste.agents_requis ?? 1);
    const byAgent = new Map<
      string,
      {
        jours: Set<JourSemaine>;
        quart: "jour" | "nuit";
        jourCount: number;
        nuitCount: number;
        cellules: CellulesPlanning;
      }
    >();

    for (const c of creneaux) {
      const date = addDaysIso(week.lundi, c.jour - 1);
      const jourName = JOURS_SEMAINE[c.jour - 1]?.value;
      if (!jourName) continue;
      const quart = (c.quart === "nuit" ? "nuit" : "jour") as "jour" | "nuit";
      const entry = byAgent.get(c.agent_id) ?? {
        jours: new Set<JourSemaine>(),
        quart,
        jourCount: 0,
        nuitCount: 0,
        cellules: {},
      };
      entry.jours.add(jourName);
      if (quart === "nuit") entry.nuitCount += 1;
      else entry.jourCount += 1;
      entry.quart = entry.nuitCount > entry.jourCount ? "nuit" : "jour";
      entry.cellules[date] =
        quart === "nuit"
          ? entry.cellules[date] === "jour"
            ? "les_deux"
            : "nuit"
          : entry.cellules[date] === "nuit"
            ? "les_deux"
            : "jour";
      byAgent.set(c.agent_id, entry);
    }

    // Titulaires = agents avec le plus de jours. Exclure les parasites 1 jour
    // quand d’autres agents ont un rythme plus dense.
    const allEntries = [...byAgent.entries()];
    const maxJours = Math.max(0, ...allEntries.map(([, info]) => info.jours.size));
    const titulairesOnly =
      maxJours > 1
        ? allEntries.filter(([, info]) => info.jours.size > 1)
        : allEntries;
    const ranked = (titulairesOnly.length > 0 ? titulairesOnly : allEntries).sort(
      (a, b) =>
        b[1].jours.size - a[1].jours.size ||
        b[1].jourCount + b[1].nuitCount - (a[1].jourCount + a[1].nuitCount) ||
        a[0].localeCompare(b[0]),
    );

    const toSlot = (
      agentId: string,
      info: {
        jours: Set<JourSemaine>;
        quart: "jour" | "nuit";
        cellules: CellulesPlanning;
      },
      fallbackQuart: "jour" | "nuit",
    ): AgentPlanningSlot => ({
      agent_id: agentId,
      quart: info.quart || fallbackQuart,
      jours_travailles: JOURS_SEMAINE.map((j) => j.value).filter((j) =>
        info.jours.has(j),
      ),
      jour_repos: "",
      cellules: info.cellules,
    });

    let next: AgentPlanningSlot[];
    const decoupage = posteHasDecoupage || getValues("repartition") === "moities";
    if (decoupage && n >= 2) {
      const pick = (quart: "jour" | "nuit") =>
        ranked.find(([, info]) => info.quart === quart) ??
        ranked.find(([, info]) =>
          quart === "nuit" ? info.nuitCount > 0 : info.jourCount > 0,
        );
      const jourRow = pick("jour");
      const nuitRow = ranked.find(
        ([id, info]) =>
          id !== jourRow?.[0] &&
          (info.quart === "nuit" || info.nuitCount > 0),
      ) ?? ranked.find(([id]) => id !== jourRow?.[0]);
      next = [
        jourRow
          ? toSlot(jourRow[0], jourRow[1], "jour")
          : emptyPlanningSlot("", "jour"),
        nuitRow
          ? toSlot(nuitRow[0], nuitRow[1], "nuit")
          : emptyPlanningSlot("", "nuit"),
      ];
      while (next.length < n) {
        next.push(emptyPlanningSlot("", "jour"));
      }
      next = ensureDistinctQuarts(next.slice(0, n));
    } else {
      next = Array.from({ length: n }, (_, i) => {
        const row = ranked[i];
        if (!row) return emptyPlanningSlot("", i === 0 ? "jour" : "nuit");
        return toSlot(row[0], row[1], i === 0 ? "jour" : "nuit");
      });
    }

    setValue("slots", next, { shouldValidate: true });
    prefilledPosteRef.current = key;
  }, [
    posteId,
    selectedPoste,
    planningSemaineData,
    planningSemaineLoading,
    posteHasDecoupage,
    week.annee_iso,
    week.semaine_iso,
    week.lundi,
    getValues,
    setValue,
  ]);

  // Changement de poste / semaine → réautoriser sync horaires / préremplissage.
  useEffect(() => {
    prefilledPosteRef.current = "";
    if (posteId) {
      // horaires poste : seulement au changement de poste
    }
  }, [posteId, week.annee_iso, week.semaine_iso]);

  useEffect(() => {
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

  /** Grille d’une semaine ISO = jours cochés tels quels (pas de rotation S→S+1). */
  const rythmeDeSemaine = (
    slot: AgentPlanningSlot,
    index: number,
    _tous: AgentPlanningSlot[],
  ): CellulesPlanning =>
    cellulesDepuisSemaine({
      date_debut: datesPeriode[0],
      date_fin: datesPeriode[datesPeriode.length - 1],
      jours_travailles: slot.jours_travailles as JourSemaine[],
      travail: celluleDeTravail(slot, index),
    });

  // Chaque jour de la période a sa case : l’enregistré, sinon le rythme de l’agent.
  const agentsKey = slots.map((s) => s.agent_id).join("|");
  useEffect(() => {
    if (datesPeriode.length === 0 || !heureDebut || !heureFin) return;
    const weekKey = `${posteId}:${week.annee_iso}-${week.semaine_iso}`;
    if (posteVacationsLoading || prefilledPosteRef.current !== weekKey) return;
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
          // Template éditeur : jamais les remplacements 1 jour (ops).
          cellulesEnregistrees(
            slot.agent_id,
            vacationsDuPoste.filter((v) => !v.est_remplacement),
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
      // À la republication, le prévu non-remplacement de la semaine est resync :
      // ne pas les afficher comme conflit dans l’aperçu template.
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

  /**
   * Applique le rythme sur toute la semaine, y compris les jours déjà passés.
   * Les verrous bloquent seulement le clic case-par-case : sinon lun/mar
   * resteraient figés (souvent « J » pour les 2 agents) et bloqueraient
   * l’alternance + la duplication vers la semaine suivante.
   */
  const appliquerHorsVerrous = (
    _slot: AgentPlanningSlot,
    nouvelles: CellulesPlanning,
  ): CellulesPlanning => {
    const cellules: CellulesPlanning = {};
    for (const date of datesPeriode) {
      cellules[date] = nouvelles[date] ?? "repos";
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

  const busy =
    isSubmitting ||
    upsertPlanning.isPending ||
    publierPlanning.isPending ||
    dupliquerPlanning.isPending ||
    progress;

  /**
   * Template semaine = tous les jours cochés, y compris le passé.
   * Les verrous (dates passées) bloquent l’édition UI / publication vacations,
   * mais ne doivent pas effacer lundi–mardi du modèle qu’on duplique ensuite.
   */
  const buildCreneauxPayload = (values: AgentPlanningFormValues) => {
    const filledSlots = values.slots.filter((s) => s.agent_id);
    return filledSlots.flatMap((slot, index) => {
      const shifts = creneauxDepuisCellules({
        cellules: slot.cellules ?? {},
        date_debut: week.lundi,
        date_fin: week.dimanche,
        index,
        total: filledSlots.length,
        heure_debut: values.heure_debut || "",
        heure_fin: values.heure_fin || "",
        heure_debut_nuit: values.heure_debut_nuit || undefined,
        heure_fin_nuit: values.heure_fin_nuit || undefined,
        repartition: values.repartition ?? "jour_entier",
      });
      return shifts.map((shift) => ({
        agent_id: slot.agent_id,
        jour: isoJourFromDate(shift.date),
        heure_debut: shift.heure_debut,
        heure_fin: shift.heure_fin,
        quart: (inferQuartFromHours(
          shift.heure_debut,
          shift.heure_fin,
          posteHalves,
        ) ?? slot.quart ?? "jour") as "jour" | "nuit",
      }));
    });
  };

  const saveBrouillon = async (values: AgentPlanningFormValues) => {
    if (!canCreate) {
      toast("Vous n’avez pas le droit de planifier.", "danger");
      return;
    }
    setFormError(null);
    const filledSlots = values.slots.filter((s) => s.agent_id);
    if (filledSlots.length === 0) {
      setFormError("Sélectionnez les agents du poste.");
      return;
    }
    const creneaux = buildCreneauxPayload(values);
    if (creneaux.length === 0) {
      setFormError(
        "Aucun créneau — cochez des jours travaillés ou ajustez la grille.",
      );
      return;
    }
    setProgress(true);
    try {
      await upsertPlanning.mutateAsync({
        poste_id: values.poste_id,
        annee_iso: week.annee_iso,
        semaine_iso: week.semaine_iso,
        creneaux,
      });
      toast(
        `Brouillon enregistré · semaine ${week.semaine_iso}/${week.annee_iso} · ${creneaux.length} créneau${creneaux.length > 1 ? "x" : ""}.`,
      );
    } catch (err) {
      const message = getApiErrorMessage(err, "Échec de l’enregistrement.");
      setFormError(message);
      toast(message, "danger");
      throw err;
    } finally {
      setProgress(false);
    }
  };

  const publierSemaine = async (values: AgentPlanningFormValues) => {
    if (!canCreate) {
      toast("Vous n’avez pas le droit de planifier.", "danger");
      return;
    }
    setFormError(null);
    const filledSlots = values.slots.filter((s) => s.agent_id);
    if (filledSlots.length === 0) {
      setFormError("Sélectionnez les agents du poste.");
      return;
    }
    const creneaux = buildCreneauxPayload(values);
    if (creneaux.length === 0) {
      setFormError(
        "Aucun créneau à publier — vérifiez les jours et horaires.",
      );
      return;
    }
    setProgress(true);
    try {
      await upsertPlanning.mutateAsync({
        poste_id: values.poste_id,
        annee_iso: week.annee_iso,
        semaine_iso: week.semaine_iso,
        creneaux,
      });
      await publierPlanning.mutateAsync({
        poste_id: values.poste_id,
        annee_iso: week.annee_iso,
        semaine_iso: week.semaine_iso,
      });
      toast(
        `Semaine ${week.semaine_iso}/${week.annee_iso} publiée · ${creneaux.length} créneau${creneaux.length > 1 ? "x" : ""}.`,
      );
      router.push("/vacations");
    } catch (err) {
      const message = getApiErrorMessage(err, "Échec de la publication.");
      setFormError(message);
      toast(message, "danger");
      throw err;
    } finally {
      setProgress(false);
    }
  };

  const onSubmit = handleSubmit(async (values) => {
    try {
      await saveBrouillon(values);
    } catch {
      // formError déjà renseigné
    }
  });

  const onPublier = handleSubmit(async (values) => {
    try {
      await publierSemaine(values);
    } catch {
      // formError déjà renseigné
    }
  });

  const onDupliquerPrecedente = async () => {
    if (!posteId) return;
    setFormError(null);
    setProgress(true);
    try {
      await dupliquerPlanning.mutateAsync({
        poste_id: posteId,
        annee_iso: week.annee_iso,
        semaine_iso: week.semaine_iso,
      });
      prefilledPosteRef.current = "";
      toast("Semaine précédente copiée — ajustez puis publiez.");
    } catch (err) {
      const message = getApiErrorMessage(
        err,
        "Impossible de dupliquer la semaine précédente.",
      );
      setFormError(message);
      toast(message, "danger");
    } finally {
      setProgress(false);
    }
  };

  const submitLabel = progress
    ? `Enregistrement · ${totalPlannedShifts} créneau${totalPlannedShifts > 1 ? "x" : ""}…`
    : totalPlannedShifts > 0
      ? `Enregistrer brouillon · ${totalPlannedShifts} créneau${totalPlannedShifts > 1 ? "x" : ""}`
      : "Enregistrer brouillon";

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
      title="Planifier la semaine"
      description="Emploi du temps d’une semaine ISO — chaque semaine se saisit et se publie indépendamment."
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
                                            aria-label={j.label}
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
                                              "inline-flex h-10 min-w-10 shrink-0 flex-col items-center justify-center rounded-md border px-1.5 text-[10px] font-semibold leading-tight transition sm:min-w-12 sm:text-xs",
                                              selected
                                                ? "border-teal bg-teal text-white"
                                                : "border-border bg-white text-ink-muted hover:border-teal/40 hover:text-ink",
                                            )}
                                          >
                                            <span className="hidden sm:inline">
                                              {j.label}
                                            </span>
                                            <span className="sm:hidden">
                                              {j.short}
                                            </span>
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
                      Ces jours préremplissent la semaine affichée ; ajustez
                      ensuite chaque case dans la grille.
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
                      title="Semaine ISO"
                      hint="Chaque semaine se saisit à part. Dupliquez la précédente pour partir d’une base."
                    />
                    <div className="flex flex-wrap items-center justify-between gap-3">
                      <div className="flex items-center gap-2">
                        <Button
                          type="button"
                          variant="secondary"
                          size="sm"
                          disabled={busy}
                          onClick={() =>
                            setWeek((w) =>
                              shiftIsoWeek(w.annee_iso, w.semaine_iso, -1),
                            )
                          }
                          aria-label="Semaine précédente"
                        >
                          <ChevronLeft className="size-4" />
                        </Button>
                        <div className="min-w-[11rem] text-center">
                          <p className="text-sm font-semibold text-ink">
                            S{week.semaine_iso} · {week.annee_iso}
                          </p>
                          <p className="text-xs text-ink-muted">
                            {formatWeekLabel(week.lundi, week.dimanche)}
                          </p>
                        </div>
                        <Button
                          type="button"
                          variant="secondary"
                          size="sm"
                          disabled={busy}
                          onClick={() =>
                            setWeek((w) =>
                              shiftIsoWeek(w.annee_iso, w.semaine_iso, 1),
                            )
                          }
                          aria-label="Semaine suivante"
                        >
                          <ChevronRight className="size-4" />
                        </Button>
                      </div>
                      <div className="flex flex-wrap items-center gap-2">
                        <Badge
                          tone={
                            planningStatut === "publiee"
                              ? "success"
                              : planningStatut === "brouillon"
                                ? "warning"
                                : "danger"
                          }
                        >
                          {planningStatut === "publiee"
                            ? "Publiée"
                            : planningStatut === "brouillon"
                              ? "Brouillon non publié"
                              : "Pas encore créée"}
                        </Badge>
                        <Button
                          type="button"
                          variant="secondary"
                          size="sm"
                          disabled={busy || !posteId}
                          onClick={() => void onDupliquerPrecedente()}
                        >
                          Dupliquer semaine précédente
                        </Button>
                      </div>
                    </div>
                    {planningStatut === "absent" ? (
                      <Alert tone="warning" className="mt-3">
                        Aucun planning pour la semaine S{week.semaine_iso}.
                        Saisissez les titulaires ou utilisez « Dupliquer semaine
                        précédente » — les ajouts 1 jour depuis la grille ne
                        remplissent pas ce modèle.
                      </Alert>
                    ) : planningStatut === "brouillon" ? (
                      <Alert tone="info" className="mt-3">
                        Brouillon = modèle de la semaine (pas un patch d’une
                        case). Publiez pour générer les vacations prévues ; les
                        remplacements déjà saisis sont conservés.
                      </Alert>
                    ) : (
                      <Alert tone="success" className="mt-3">
                        Semaine publiée. Republier remplace seulement le prévu
                        de cette semaine (remplacements / HS inchangés).
                      </Alert>
                    )}
                  </CardBody>
                </Card>

                {datesPeriode.length > 0 && slots.some((s) => s.agent_id) ? (
                  <Card>
                    <CardBody className="p-4">
                      <SectionTitle
                        step="4"
                        title="Grille de la semaine"
                        hint="Cliquez une case pour changer jour / nuit / repos. Publiez pour générer les vacations de cette semaine seule."
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

                {formError ? <Alert tone="danger">{formError}</Alert> : null}

                <div className="hidden flex-wrap gap-2 lg:flex">
                  <Button
                    type="button"
                    variant="secondary"
                    disabled={busy}
                    onClick={() => router.push("/vacations")}
                  >
                    Annuler
                  </Button>
                  <Button type="submit" loading={busy} disabled={!canCreate}>
                    {submitLabel}
                  </Button>
                  <Button
                    type="button"
                    loading={busy}
                    disabled={!canCreate}
                    onClick={() => void onPublier()}
                  >
                    <CalendarDays className="size-4" />
                    Publier la semaine
                  </Button>
                </div>
              </>
            ) : (
              <Card>
                <CardBody className="p-4">
                  <p className="text-sm text-ink-muted">
                    Choisissez un site puis un poste pour continuer : agents,
                    semaine et horaires s’adaptent à l’effectif.
                  </p>
                </CardBody>
              </Card>
            )}
          </div>

          <aside className="min-w-0 space-y-4 lg:sticky lg:top-6 lg:self-start">
            <Card>
              <CardHeader
                title="Cette semaine"
                description={`S${week.semaine_iso} · ${formatWeekLabel(week.lundi, week.dimanche)}`}
              />
              <CardBody className="space-y-2 p-4 pt-0 text-sm text-ink-muted">
                <p>
                  {totalPlannedShifts > 0
                    ? `${totalPlannedShifts} créneau${totalPlannedShifts > 1 ? "x" : ""} prêt${totalPlannedShifts > 1 ? "s" : ""} à enregistrer.`
                    : "Sélectionnez les agents et leurs jours pour remplir la grille."}
                </p>
                <p className="text-xs text-ink-faint">
                  Enregistrer / Publier = emploi du temps de la semaine entière,
                  pas une case isolée. Les ajouts ponctuels se font depuis la
                  grille site (remplacement 1 jour).
                </p>
              </CardBody>
            </Card>
          </aside>

          {posteReady ? (
            <div className="fixed inset-x-0 bottom-0 z-20 border-t border-border bg-white/95 p-3 backdrop-blur lg:hidden">
              <div className="mx-auto flex max-w-screen-2xl gap-2">
                <Button
                  type="submit"
                  className="flex-1"
                  loading={busy}
                  disabled={!canCreate}
                >
                  Brouillon
                </Button>
                <Button
                  type="button"
                  className="flex-1"
                  loading={busy}
                  disabled={!canCreate}
                  onClick={() => void onPublier()}
                >
                  Publier
                </Button>
              </div>
            </div>
          ) : null}
        </form>
      )}
    </PlanningWorkspaceShell>
  );
}
