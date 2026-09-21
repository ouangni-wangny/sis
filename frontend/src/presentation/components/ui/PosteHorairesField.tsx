"use client";

import { useEffect } from "react";
import { FieldLabel } from "@/presentation/components/ui/FieldLabel";
import { Input } from "@/presentation/components/ui/Input";
import { TimeSelect } from "@/presentation/components/ui/TimeSelect";
import {
  apply24hDecoupage,
  build24hCycle,
  hasDecoupageQuarts,
  isCouverture24h,
  isOvernightRange,
  normalizePosteHoraires,
  releveFromPoste,
  type PosteHorairesValues,
} from "@/domain/schemas/poste-horaires";
import { cn } from "@/shared/lib/cn";

export type PosteHoraireMode = "jour" | "nuit" | "24h";

export type { PosteHorairesValues };
export {
  apply24hDecoupage,
  hasDecoupageQuarts,
  isCouverture24h,
  normalizePosteHoraires,
};

export type PosteDraftValues = PosteHorairesValues & {
  nom: string;
  agents_requis: string;
  /** Sans quarts + effectif ≥ 2 : ensemble | alternance */
  mode_effectif?: "ensemble" | "alternance";
};

const MODES: { id: PosteHoraireMode; label: string }[] = [
  { id: "jour", label: "Jour" },
  { id: "nuit", label: "Nuit" },
  { id: "24h", label: "24h" },
];

export function detectPosteHoraireMode(
  values: PosteHorairesValues,
): PosteHoraireMode {
  if (isCouverture24h(values)) return "24h";
  if (
    values.heure_debut &&
    values.heure_fin &&
    isOvernightRange(values.heure_debut, values.heure_fin)
  ) {
    return "nuit";
  }
  return "jour";
}

export function applyPosteHoraireMode(
  mode: PosteHoraireMode,
  current: PosteHorairesValues,
): PosteHorairesValues {
  if (mode === "jour") {
    const wasDay =
      current.heure_debut &&
      current.heure_fin &&
      !isOvernightRange(current.heure_debut, current.heure_fin) &&
      !hasDecoupageQuarts(current);
    return {
      heure_debut: wasDay ? current.heure_debut : "07:00",
      heure_fin: wasDay ? current.heure_fin : "19:00",
      heure_debut_nuit: "",
      heure_fin_nuit: "",
    };
  }
  if (mode === "nuit") {
    return {
      heure_debut: "19:00",
      heure_fin: "07:00",
      heure_debut_nuit: "",
      heure_fin_nuit: "",
    };
  }
  // 24h par défaut : cycle continu (découpage optionnel ensuite)
  const releve = releveFromPoste(current);
  return build24hCycle(releve);
}

export function PosteHorairesField({
  values,
  onChange,
  disabled,
}: {
  values: PosteHorairesValues;
  onChange: (next: PosteHorairesValues) => void;
  disabled?: boolean;
}) {
  const mode = detectPosteHoraireMode(values);

  return (
    <div className="space-y-2.5">
      <ModeToggle
        mode={mode}
        onChange={(next) => {
          if (next === mode) return;
          onChange(applyPosteHoraireMode(next, values));
        }}
        disabled={disabled}
      />
      <CreneauTimes
        mode={mode}
        values={values}
        onChange={onChange}
        disabled={disabled}
      />
    </div>
  );
}

export function PosteDraftFields({
  values,
  onChange,
  nomRequired,
  nomId,
  effectifId,
  disabled,
}: {
  values: PosteDraftValues;
  onChange: (next: PosteDraftValues) => void;
  nomRequired?: boolean;
  nomId?: string;
  effectifId?: string;
  disabled?: boolean;
}) {
  const mode = detectPosteHoraireMode(values);
  const effectif = Math.max(1, Number(values.agents_requis) || 1);
  // Quarts Jour/Nuit : uniquement si ≥2 agents (1 agent 24h = cycle relève).
  const allowDecoupage = effectif >= 2;
  const showModeEffectif = effectif >= 2 && !hasDecoupageQuarts(values);
  const modeEffectif = values.mode_effectif === "alternance" ? "alternance" : "ensemble";

  const patch = (partial: Partial<PosteDraftValues>) =>
    onChange({ ...values, ...partial });

  // Effectif 1 + 24h : pas de quarts — bascule auto en cycle.
  useEffect(() => {
    if (allowDecoupage) return;
    if (mode !== "24h") return;
    if (!hasDecoupageQuarts(values)) return;
    onChange({
      ...values,
      ...apply24hDecoupage(releveFromPoste(values), false),
      mode_effectif: "ensemble",
    });
  }, [
    allowDecoupage,
    mode,
    values.heure_debut_nuit,
    values.heure_fin_nuit,
  ]);

  // Effectif 1 ou quarts : forcer ensemble.
  useEffect(() => {
    if (showModeEffectif) return;
    if ((values.mode_effectif ?? "ensemble") === "ensemble") return;
    onChange({ ...values, mode_effectif: "ensemble" });
  }, [showModeEffectif, values.mode_effectif]);

  return (
    <div className="space-y-3">
      <div className="grid gap-2.5 sm:grid-cols-[minmax(0,1fr)_4.75rem_auto] sm:items-end">
        <Input
          id={nomId}
          label="Nom du poste"
          requiredMark={nomRequired}
          placeholder="ex. Entrée principale"
          disabled={disabled}
          value={values.nom}
          onChange={(e) => patch({ nom: e.target.value })}
        />
        <Input
          id={effectifId}
          label="Effectif"
          type="number"
          min={1}
          disabled={disabled}
          value={values.agents_requis}
          onChange={(e) => {
            const nextEffectif = Math.max(1, Number(e.target.value) || 1);
            const next: PosteDraftValues = {
              ...values,
              agents_requis: e.target.value,
            };
            if (nextEffectif < 2) {
              next.mode_effectif = "ensemble";
              if (
                detectPosteHoraireMode(values) === "24h" &&
                hasDecoupageQuarts(values)
              ) {
                Object.assign(
                  next,
                  apply24hDecoupage(releveFromPoste(values), false),
                );
              }
            }
            onChange(next);
          }}
        />
        <ModeToggle
          mode={mode}
          onChange={(next) => {
            if (next === mode) return;
            onChange({
              ...values,
              ...applyPosteHoraireMode(next, values),
              mode_effectif:
                next === "24h" ? values.mode_effectif ?? "ensemble" : "ensemble",
            });
          }}
          disabled={disabled}
        />
      </div>

      <CreneauTimes
        mode={mode}
        values={values}
        allowDecoupage={allowDecoupage}
        showModeEffectif={showModeEffectif}
        modeEffectif={modeEffectif}
        onModeEffectifChange={(next) => patch({ mode_effectif: next })}
        onChange={(hours) => {
          const next: PosteDraftValues = { ...values, ...hours };
          if (hasDecoupageQuarts(hours)) {
            next.mode_effectif = "ensemble";
          }
          onChange(next);
        }}
        disabled={disabled}
      />
    </div>
  );
}

function ModeEffectifToggle({
  value,
  onChange,
  disabled,
}: {
  value: "ensemble" | "alternance";
  onChange: (next: "ensemble" | "alternance") => void;
  disabled?: boolean;
}) {
  return (
    <div className="flex shrink-0 flex-col gap-1.5 text-sm">
      <FieldLabel>Les agents</FieldLabel>
      <div
        className="inline-flex h-10 w-fit max-w-full items-center gap-0.5 rounded-md border border-border bg-white p-0.5"
        role="group"
        aria-label="Mode effectif"
      >
        {(
          [
            { value: "ensemble" as const, label: "Ensemble" },
            { value: "alternance" as const, label: "Alternance" },
          ] as const
        ).map((item) => {
          const active = value === item.value;
          return (
            <button
              key={item.value}
              type="button"
              disabled={disabled}
              title={
                item.value === "ensemble"
                  ? "Présents en même temps sur le poste"
                  : "À tour de rôle — un seul agent par jour"
              }
              onClick={() => onChange(item.value)}
              className={cn(
                "h-full rounded px-2.5 text-sm font-medium transition",
                "disabled:cursor-not-allowed disabled:opacity-50",
                active
                  ? "bg-ink text-white"
                  : "text-ink-muted hover:text-ink",
              )}
            >
              {item.label}
            </button>
          );
        })}
      </div>
    </div>
  );
}

function ModeToggle({
  mode,
  onChange,
  disabled,
}: {
  mode: PosteHoraireMode;
  onChange: (next: PosteHoraireMode) => void;
  disabled?: boolean;
}) {
  return (
    <div className="flex shrink-0 flex-col gap-1.5 text-sm">
      <FieldLabel>Couverture</FieldLabel>
      <div
        className="inline-flex h-10 items-center gap-0.5 rounded-md border border-border bg-white p-0.5"
        role="group"
        aria-label="Type de couverture"
      >
        {MODES.map((item) => {
          const active = mode === item.id;
          return (
            <button
              key={item.id}
              type="button"
              disabled={disabled}
              onClick={() => onChange(item.id)}
              className={cn(
                "h-full rounded px-2.5 text-sm font-medium transition",
                "disabled:cursor-not-allowed disabled:opacity-50",
                active
                  ? item.id === "nuit"
                    ? "bg-[#311f38] text-white"
                    : item.id === "24h"
                      ? "bg-teal text-white"
                      : "bg-amber-500 text-white"
                  : "text-ink-muted hover:text-ink",
              )}
            >
              {item.label}
            </button>
          );
        })}
      </div>
    </div>
  );
}

function DecoupageToggle({
  enabled,
  onChange,
  disabled,
}: {
  enabled: boolean;
  onChange: (next: boolean) => void;
  disabled?: boolean;
}) {
  return (
    <div className="flex shrink-0 flex-col gap-1.5 text-sm">
      <FieldLabel>Découpage</FieldLabel>
      <div
        className="inline-flex h-10 items-center gap-0.5 rounded-md border border-border bg-white p-0.5"
        role="group"
        aria-label="Découpage en quarts"
      >
        {(
          [
            { value: false, label: "Non" },
            { value: true, label: "Quarts" },
          ] as const
        ).map((item) => {
          const active = enabled === item.value;
          return (
            <button
              key={String(item.value)}
              type="button"
              disabled={disabled}
              onClick={() => onChange(item.value)}
              className={cn(
                "h-full rounded px-2.5 text-sm font-medium transition",
                "disabled:cursor-not-allowed disabled:opacity-50",
                active
                  ? "bg-ink text-white"
                  : "text-ink-muted hover:text-ink",
              )}
            >
              {item.label}
            </button>
          );
        })}
      </div>
    </div>
  );
}

function CreneauTimes({
  mode,
  values,
  onChange,
  disabled,
  allowDecoupage = true,
  showModeEffectif = false,
  modeEffectif = "ensemble",
  onModeEffectifChange,
}: {
  mode: PosteHoraireMode;
  values: PosteHorairesValues;
  onChange: (next: PosteHorairesValues) => void;
  disabled?: boolean;
  /** false = 1 agent 24h : cycle uniquement, pas de toggle Quarts. */
  allowDecoupage?: boolean;
  showModeEffectif?: boolean;
  modeEffectif?: "ensemble" | "alternance";
  onModeEffectifChange?: (next: "ensemble" | "alternance") => void;
}) {
  if (mode === "24h") {
    const releve = releveFromPoste(values);
    const decoupage = allowDecoupage && hasDecoupageQuarts(values);
    const quarts = decoupage ? values : null;

    return (
      <div className="flex flex-wrap items-end justify-center gap-2.5">
        {allowDecoupage ? (
          <DecoupageToggle
            enabled={decoupage}
            disabled={disabled}
            onChange={(next) => onChange(apply24hDecoupage(releve, next))}
          />
        ) : null}
        {showModeEffectif && onModeEffectifChange ? (
          <ModeEffectifToggle
            value={modeEffectif}
            disabled={disabled}
            onChange={onModeEffectifChange}
          />
        ) : null}
        <TimeSelect
          className="w-[7.5rem]"
          label="Relève"
          tone="day"
          disabled={disabled}
          value={releve}
          onChange={(next) =>
            onChange(apply24hDecoupage(next, allowDecoupage && decoupage))
          }
        />
        {quarts ? (
          <>
            <div className="flex flex-col gap-1.5 text-sm">
              <FieldLabel>Jour</FieldLabel>
              <div className="inline-flex h-10 items-center rounded-md border border-amber-200/90 bg-white px-2.5 text-sm font-semibold tabular-nums text-ink">
                {quarts.heure_debut}–{quarts.heure_fin}
              </div>
            </div>
            <div className="flex flex-col gap-1.5 text-sm">
              <FieldLabel>Nuit</FieldLabel>
              <div className="inline-flex h-10 items-center rounded-md border border-[#311f38]/25 bg-white px-2.5 text-sm font-semibold tabular-nums text-ink">
                {quarts.heure_debut_nuit}–{quarts.heure_fin_nuit}
              </div>
            </div>
          </>
        ) : (
          <div className="flex flex-col gap-1.5 text-sm">
            <FieldLabel>Cycle</FieldLabel>
            <div className="inline-flex h-10 items-center rounded-md border border-teal/30 bg-white px-2.5 text-sm font-semibold tabular-nums text-ink">
              {releve} → {releve}
            </div>
          </div>
        )}
      </div>
    );
  }

  const tone = mode === "nuit" ? "night" : "day";

  return (
    <div className="flex flex-wrap items-end justify-center gap-2.5">
      {showModeEffectif && onModeEffectifChange ? (
        <ModeEffectifToggle
          value={modeEffectif}
          disabled={disabled}
          onChange={onModeEffectifChange}
        />
      ) : null}
      <TimeSelect
        className="w-[7.5rem]"
        label="Début"
        tone={tone}
        disabled={disabled}
        value={values.heure_debut}
        onChange={(heure_debut) =>
          onChange({
            ...values,
            heure_debut,
            heure_debut_nuit: "",
            heure_fin_nuit: "",
          })
        }
      />
      <TimeSelect
        className="w-[7.5rem]"
        label="Fin"
        tone={tone}
        disabled={disabled}
        value={values.heure_fin}
        onChange={(heure_fin) =>
          onChange({
            ...values,
            heure_fin,
            heure_debut_nuit: "",
            heure_fin_nuit: "",
          })
        }
      />
    </div>
  );
}
