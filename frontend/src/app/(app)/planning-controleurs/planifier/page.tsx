"use client";

import { Suspense, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { ArrowLeft, ArrowRightLeft, ChevronLeft, ChevronRight, Repeat } from "lucide-react";
import {
  useSyncZoneReleve,
  useZones,
} from "@/application/hooks/useZones";
import {
  nextReleveBlocks,
  estControleurEnService,
  indiceEnService,
} from "@/domain/controleur-releve-48h";
import type { Zone } from "@/domain/types/entities";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { Alert } from "@/presentation/components/ui/Alert";
import { Button } from "@/presentation/components/ui/Button";
import { DatePicker } from "@/presentation/components/ui/DatePicker";
import { Select } from "@/presentation/components/ui/Select";
import { Spinner } from "@/presentation/components/ui/Spinner";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { useToast } from "@/presentation/providers/ToastProvider";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { can } from "@/shared/lib/can";
import { cn } from "@/shared/lib/cn";

type Ctrl = NonNullable<Zone["controleurs"]>[number];

function todayIso() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}

function monthOf(iso: string) {
  return iso.slice(0, 7);
}

function toIso(d: Date) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}

function shiftMonth(ym: string, delta: number) {
  const [y, m] = ym.split("-").map(Number);
  const d = new Date(y, m - 1 + delta, 1);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`;
}

function formatShort(iso: string) {
  const [y, m, d] = iso.split("-").map(Number);
  return new Date(y, m - 1, d).toLocaleDateString("fr-FR", {
    weekday: "short",
    day: "numeric",
    month: "short",
  });
}

function formatRange(start: string, end: string) {
  return `${formatShort(start)} → ${formatShort(end)}`;
}

function initials(c: Ctrl) {
  return `${c.prenom.charAt(0)}${c.nom.charAt(0)}`.toUpperCase();
}

function zoneControleurs(zone: Zone) {
  return [...(zone.controleurs ?? [])].sort(
    (a, b) => (a.indice_releve ?? 99) - (b.indice_releve ?? 99),
  );
}

function MonthGrid({
  month,
  onMonthChange,
  releveDepuis,
  releveJusque,
  c0,
  c1,
}: {
  month: string;
  onMonthChange: (ym: string) => void;
  releveDepuis: string;
  releveJusque: string;
  c0: Ctrl;
  c1: Ctrl;
}) {
  const cells = useMemo(() => {
    const [y, m] = month.split("-").map(Number);
    const first = new Date(y, m - 1, 1);
    const mondayOffset = (first.getDay() + 6) % 7;
    const start = new Date(first);
    start.setDate(first.getDate() - mondayOffset);
    return Array.from({ length: 42 }, (_, i) => {
      const d = new Date(start);
      d.setDate(start.getDate() + i);
      return d;
    });
  }, [month]);

  const label = useMemo(() => {
    const [y, m] = month.split("-").map(Number);
    return new Date(y, m - 1, 1).toLocaleDateString("fr-FR", {
      month: "long",
      year: "numeric",
    });
  }, [month]);

  return (
    <div>
      <div className="mb-3 flex items-center justify-between">
        <button
          type="button"
          className="inline-flex size-9 items-center justify-center rounded-full text-ink-muted transition hover:bg-white/70 hover:text-ink"
          onClick={() => onMonthChange(shiftMonth(month, -1))}
          aria-label="Mois précédent"
        >
          <ChevronLeft className="size-4" />
        </button>
        <p className="text-sm font-semibold capitalize tracking-tight text-ink">
          {label}
        </p>
        <button
          type="button"
          className="inline-flex size-9 items-center justify-center rounded-full text-ink-muted transition hover:bg-white/70 hover:text-ink"
          onClick={() => onMonthChange(shiftMonth(month, 1))}
          aria-label="Mois suivant"
        >
          <ChevronRight className="size-4" />
        </button>
      </div>

      <div className="grid grid-cols-7 gap-1 text-center text-[10px] font-medium uppercase tracking-wider text-ink-faint">
        {["Lun", "Mar", "Mer", "Jeu", "Ven", "Sam", "Dim"].map((d) => (
          <div key={d} className="py-1">
            {d}
          </div>
        ))}
      </div>

      <div className="mt-1 grid grid-cols-7 gap-1">
        {cells.map((d) => {
          const iso = toIso(d);
          const [y, m] = month.split("-").map(Number);
          const outMonth = d.getFullYear() !== y || d.getMonth() !== m - 1;
          const beforeCycle = iso < releveDepuis;
          const afterPeriod = iso > releveJusque;
          const outOfPeriod = beforeCycle || afterPeriod;
          const idx = outOfPeriod ? -1 : indiceEnService(releveDepuis, iso);
          const on = idx === 0 ? c0 : idx === 1 ? c1 : null;

          return (
            <div
              key={iso}
              title={
                on
                  ? `${on.prenom} ${on.nom}`
                  : beforeCycle
                    ? "Avant la période"
                    : afterPeriod
                      ? "Après la période"
                      : undefined
              }
              className={cn(
                "flex aspect-square flex-col items-center justify-center rounded-lg text-[11px] transition",
                outMonth && "opacity-25",
                outOfPeriod && !outMonth && "bg-white/40 text-ink-faint",
                !outOfPeriod &&
                  !outMonth &&
                  idx === 0 &&
                  "bg-[#2f3a24] text-white shadow-sm",
                !outOfPeriod &&
                  !outMonth &&
                  idx === 1 &&
                  "bg-[#5b7c99] text-white shadow-sm",
              )}
            >
              <span className="font-semibold tabular-nums">{d.getDate()}</span>
              {on && !outMonth ? (
                <span className="text-[9px] font-bold tracking-wide opacity-90">
                  {initials(on)}
                </span>
              ) : null}
            </div>
          );
        })}
      </div>
    </div>
  );
}

function PlanifierReleveForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const zoneIdParam = searchParams.get("zone_id") ?? "";

  const { data, isLoading } = useZones({ all: true });
  const syncReleve = useSyncZoneReleve();
  const { toast } = useToast();
  const { user } = useAuth();
  const canManage = can(user, ["zones.update", "zones.manage"]);

  const zones = data?.data ?? [];
  const readyZones = useMemo(
    () => zones.filter((z) => (z.controleurs?.length ?? 0) >= 2),
    [zones],
  );

  const [selectedId, setSelectedId] = useState(zoneIdParam);
  const selected =
    readyZones.find((z) => z.id === selectedId) ??
    readyZones.find((z) => z.id === zoneIdParam) ??
    readyZones[0] ??
    null;

  useEffect(() => {
    if (zoneIdParam && readyZones.some((z) => z.id === zoneIdParam)) {
      setSelectedId(zoneIdParam);
    }
  }, [zoneIdParam, readyZones]);

  const controleurs = selected ? zoneControleurs(selected) : [];
  const [ordreIds, setOrdreIds] = useState<string[]>([]);
  const [releveDepuis, setReleveDepuis] = useState(todayIso());
  const [releveJusque, setReleveJusque] = useState(() => {
    const t = todayIso();
    const [y, m, d] = t.split("-").map(Number);
    const dt = new Date(y, m - 1, d + 27);
    return toIso(dt);
  });
  const [calendarMonth, setCalendarMonth] = useState(monthOf(todayIso()));

  useEffect(() => {
    if (!selected) return;
    const ctrls = zoneControleurs(selected);
    setOrdreIds(ctrls.map((c) => c.id));
    const depuis = ctrls[0]?.releve_depuis ?? todayIso();
    const jusque =
      ctrls[0]?.releve_jusque ??
      (() => {
        const [y, m, d] = depuis.split("-").map(Number);
        return toIso(new Date(y, m - 1, d + 27));
      })();
    setReleveDepuis(depuis);
    setReleveJusque(jusque);
    setCalendarMonth(monthOf(depuis));
    if (!selectedId) setSelectedId(selected.id);
  }, [
    selected?.id,
    selectedId,
    selected?.controleurs
      ?.map(
        (c) =>
          `${c.id}:${c.indice_releve}:${c.releve_depuis}:${c.releve_jusque}`,
      )
      .join("|"),
  ]);

  const ordered = useMemo(() => {
    if (!ordreIds.length) return controleurs;
    return ordreIds
      .map((id) => controleurs.find((c) => c.id === id))
      .filter(Boolean) as Ctrl[];
  }, [ordreIds, controleurs]);

  const c0 = ordered[0];
  const c1 = ordered[1];

  const blocks = useMemo(() => {
    if (!releveDepuis || !releveJusque || !c0 || !c1) return [];
    return nextReleveBlocks(releveDepuis, releveDepuis, 4, releveJusque);
  }, [releveDepuis, releveJusque, c0, c1]);

  const zoneOptions = readyZones.map((z) => ({
    value: z.id,
    label: z.nom,
  }));

  const chooseStarter = (agentId: string) => {
    if (ordreIds.length !== 2) return;
    if (ordreIds[0] === agentId) return;
    setOrdreIds([ordreIds[1]!, ordreIds[0]!]);
  };

  const save = async () => {
    if (!selected || !canManage) return;
    if (!releveDepuis || !releveJusque) {
      toast("Indiquez le début et la fin de la période.", "danger");
      return;
    }
    if (releveJusque < releveDepuis) {
      toast("La date de fin doit être après le début.", "danger");
      return;
    }
    try {
      await syncReleve.mutateAsync({
        id: selected.id,
        releveDepuis,
        releveJusque,
        ordreAgentIds: ordreIds.length === 2 ? ordreIds : undefined,
      });
      toast("Planning enregistré.");
      router.push("/planning-controleurs");
    } catch (err) {
      toast(
        getApiErrorMessage(err, "Échec de l’enregistrement."),
        "danger",
      );
    }
  };

  return (
    <div className="-m-6 min-h-full bg-[linear-gradient(165deg,#eef1ea_0%,#f7f6f2_38%,#ffffff_100%)] px-6 py-8">
        <div className="mx-auto max-w-5xl">
          <header className="mb-8 max-w-2xl">
            <Link
              href="/planning-controleurs"
              className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-ink-muted transition hover:text-ink"
            >
              <ArrowLeft className="size-4" />
              Retour au planning
            </Link>
            <p className="text-xs font-semibold uppercase tracking-[0.16em] text-teal">
              Opérations
            </p>
            <h1 className="mt-2 text-3xl font-semibold tracking-tight text-ink sm:text-[2.35rem] sm:leading-tight">
              Planifier la relève
            </h1>
            <p className="mt-3 text-base leading-relaxed text-ink-muted">
              Un binôme par zone. Chacun en service{" "}
              <strong className="font-semibold text-ink">2 jours</strong>, puis{" "}
              <strong className="font-semibold text-ink">2 jours</strong> de
              repos — à tour de rôle.
            </p>
          </header>

          {isLoading ? (
            <p className="text-sm text-ink-muted">Chargement…</p>
          ) : readyZones.length === 0 ? (
            <Alert tone="warning">
              Aucun binôme prêt. Assignez 2 contrôleurs à une zone dans{" "}
              <Link
                href="/zones"
                className="font-medium text-teal hover:underline"
              >
                Zones
              </Link>
              .
            </Alert>
          ) : (
            <div className="grid gap-8 lg:grid-cols-[minmax(0,1.35fr)_minmax(17rem,22rem)] lg:items-start">
              <section className="space-y-6">
                <div className="rounded-2xl border border-border/60 bg-white/80 p-5 shadow-[0_1px_0_rgba(47,58,36,0.04)] backdrop-blur sm:p-6">
                  <Select
                    label="Zone"
                    requiredMark
                    searchable
                    options={zoneOptions}
                    value={selected?.id ?? ""}
                    onChange={(e) => setSelectedId(e.target.value)}
                  />
                </div>

                {c0 && c1 ? (
                  <>
                    <div className="rounded-2xl border border-border/60 bg-white/80 p-5 shadow-[0_1px_0_rgba(47,58,36,0.04)] backdrop-blur sm:p-6">
                      <div className="flex items-start justify-between gap-3">
                        <div>
                          <h2 className="text-sm font-semibold text-ink">
                            Qui commence le cycle ?
                          </h2>
                          <p className="mt-1 text-sm text-ink-muted">
                            Cliquez sur le contrôleur qui part en service le
                            premier jour.
                          </p>
                        </div>
                        <button
                          type="button"
                          onClick={() =>
                            setOrdreIds([ordreIds[1]!, ordreIds[0]!])
                          }
                          disabled={!canManage}
                          className="inline-flex size-9 shrink-0 items-center justify-center rounded-full border border-border text-ink-muted transition hover:border-teal/40 hover:text-teal disabled:opacity-40"
                          title="Inverser"
                          aria-label="Inverser l’ordre"
                        >
                          <ArrowRightLeft className="size-4" />
                        </button>
                      </div>

                      <div className="mt-4 grid gap-3 sm:grid-cols-2">
                        {[c0, c1].map((c, i) => {
                          const starts = i === 0;
                          const todayOn = estControleurEnService(
                            i,
                            releveDepuis,
                          );
                          return (
                            <button
                              key={c.id}
                              type="button"
                              disabled={!canManage}
                              onClick={() => chooseStarter(c.id)}
                              className={cn(
                                "rounded-xl border p-4 text-left transition",
                                starts
                                  ? "border-teal bg-teal text-white shadow-sm"
                                  : "border-border bg-[#f4f5f1] text-ink hover:border-teal/35",
                              )}
                            >
                              <div className="flex items-center gap-3">
                                <span
                                  className={cn(
                                    "flex size-11 items-center justify-center rounded-full text-sm font-bold",
                                    starts
                                      ? "bg-white/15 text-white"
                                      : "bg-white text-teal",
                                  )}
                                >
                                  {initials(c)}
                                </span>
                                <div className="min-w-0">
                                  <p className="truncate font-semibold tracking-tight">
                                    {c.prenom} {c.nom}
                                  </p>
                                  <p
                                    className={cn(
                                      "text-xs",
                                      starts
                                        ? "text-white/75"
                                        : "text-ink-muted",
                                    )}
                                  >
                                    {starts
                                      ? "Commence · 2 jours"
                                      : "Prend la suite · 2 jours"}
                                  </p>
                                </div>
                              </div>
                              <p
                                className={cn(
                                  "mt-3 text-xs font-medium",
                                  starts ? "text-white/80" : "text-ink-faint",
                                )}
                              >
                                {todayOn
                                  ? "En service aujourd’hui"
                                  : "Repos aujourd’hui"}
                              </p>
                            </button>
                          );
                        })}
                      </div>
                    </div>

                    <div className="rounded-2xl border border-border/60 bg-white/80 p-5 shadow-[0_1px_0_rgba(47,58,36,0.04)] backdrop-blur sm:p-6">
                      <h2 className="text-sm font-semibold text-ink">Période</h2>
                      <p className="mt-1 text-sm text-ink-muted">
                        Comme le planning postes — début et fin de la relève.
                      </p>
                      <div className="mt-4 grid gap-3 sm:grid-cols-2">
                        <DatePicker
                          label="Début"
                          requiredMark
                          value={releveDepuis}
                          onChange={(e) => {
                            const v = e.target.value;
                            setReleveDepuis(v);
                            if (v) {
                              setCalendarMonth(monthOf(v));
                              if (releveJusque && releveJusque < v) {
                                const [y, m, d] = v.split("-").map(Number);
                                setReleveJusque(
                                  toIso(new Date(y, m - 1, d + 27)),
                                );
                              }
                            }
                          }}
                        />
                        <DatePicker
                          label="Fin"
                          requiredMark
                          value={releveJusque}
                          onChange={(e) => setReleveJusque(e.target.value)}
                        />
                      </div>

                      {blocks.length > 0 ? (
                        <div className="mt-5">
                          <p className="text-xs font-semibold uppercase tracking-[0.12em] text-ink-faint">
                            Aperçu (4 premiers blocs)
                          </p>
                          <ul className="mt-3 space-y-2">
                            {blocks.map((b) => {
                              const person = b.indice === 0 ? c0 : c1;
                              return (
                                <li
                                  key={`${b.start}-${b.indice}`}
                                  className="flex items-center gap-3 rounded-xl bg-[#f4f5f1] px-3 py-2.5"
                                >
                                  <span
                                    className={cn(
                                      "flex size-8 shrink-0 items-center justify-center rounded-full text-[10px] font-bold text-white",
                                      b.indice === 0
                                        ? "bg-[#2f3a24]"
                                        : "bg-[#5b7c99]",
                                    )}
                                  >
                                    {initials(person)}
                                  </span>
                                  <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium text-ink">
                                      {person.prenom} {person.nom}
                                    </p>
                                    <p className="text-xs text-ink-muted">
                                      {formatRange(b.start, b.end)}
                                    </p>
                                  </div>
                                  <span className="shrink-0 text-xs font-medium text-ink-faint">
                                    2 j.
                                  </span>
                                </li>
                              );
                            })}
                          </ul>
                        </div>
                      ) : null}

                      {canManage ? (
                        <div className="mt-6 flex flex-wrap items-center gap-3">
                          <Button
                            type="button"
                            loading={syncReleve.isPending}
                            onClick={() => void save()}
                          >
                            <Repeat className="size-4" />
                            Enregistrer
                          </Button>
                          <p className="text-xs text-ink-muted">
                            Affectation zone :{" "}
                            <Link
                              href="/zones"
                              className="text-teal hover:underline"
                            >
                              Zones
                            </Link>
                          </p>
                        </div>
                      ) : null}
                    </div>
                  </>
                ) : null}
              </section>

              <aside className="rounded-2xl border border-border/60 bg-white/70 p-5 shadow-[0_1px_0_rgba(47,58,36,0.04)] backdrop-blur lg:sticky lg:top-6">
                <p className="text-xs font-semibold uppercase tracking-[0.12em] text-ink-faint">
                  Calendrier
                </p>
                <p className="mt-1 text-sm text-ink-muted">
                  Hors période = vide. Dedans = alternance 2 j. / 2 j.
                </p>
                {c0 && c1 && releveDepuis && releveJusque ? (
                  <div className="mt-4">
                    <div className="mb-3 flex flex-wrap gap-3 text-xs">
                      <span className="inline-flex items-center gap-1.5">
                        <span className="size-2.5 rounded-full bg-[#2f3a24]" />
                        {c0.prenom}
                      </span>
                      <span className="inline-flex items-center gap-1.5">
                        <span className="size-2.5 rounded-full bg-[#5b7c99]" />
                        {c1.prenom}
                      </span>
                    </div>
                    <MonthGrid
                      month={calendarMonth}
                      onMonthChange={setCalendarMonth}
                      releveDepuis={releveDepuis}
                      releveJusque={releveJusque}
                      c0={c0}
                      c1={c1}
                    />
                  </div>
                ) : null}
              </aside>
            </div>
          )}
        </div>
      </div>
  );
}

export default function PlanifierRelevePage() {
  return (
    <PermissionGate
      permission={["zones.view", "perimetres.view", "zones.manage"]}
      title="Planifier la relève"
    >
      <Suspense
        fallback={
          <div className="flex justify-center py-16">
            <Spinner className="size-6" />
          </div>
        }
      >
        <PlanifierReleveForm />
      </Suspense>
    </PermissionGate>
  );
}
