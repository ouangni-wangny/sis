"use client";

import { useId, useMemo } from "react";
import { Moon, Sun } from "lucide-react";
import { FieldHint } from "@/presentation/components/ui/FieldLabel";
import { TimeSelect } from "@/presentation/components/ui/TimeSelect";
import {
  isOvernight as isOvernightRange,
  shiftDurationHours,
} from "@/domain/time/shift-interval";
import { cn } from "@/shared/lib/cn";

export type TimeRangePreset = {
  id: string;
  label: string;
  shortLabel?: string;
  debut: string;
  fin: string;
  tone?: "day" | "night";
};

export const SHIFT_PRESETS: TimeRangePreset[] = [
  {
    id: "jour",
    label: "Jour",
    debut: "07:00",
    fin: "19:00",
    tone: "day",
  },
  {
    id: "nuit",
    label: "Nuit",
    debut: "19:00",
    fin: "07:00",
    tone: "night",
  },
];

export const DAY_SHIFT_PRESETS = SHIFT_PRESETS.filter((p) => p.tone === "day");
export const NIGHT_SHIFT_PRESETS = SHIFT_PRESETS.filter(
  (p) => p.tone === "night",
);

export { isOvernightRange, shiftDurationHours };

export type TimeRangeFieldProps = {
  label?: string;
  debut: string;
  fin: string;
  onDebutChange: (value: string) => void;
  onFinChange: (value: string) => void;
  onRangeChange?: (debut: string, fin: string) => void;
  debutLabel?: string;
  finLabel?: string;
  error?: string;
  debutError?: string;
  finError?: string;
  hint?: string;
  requiredMark?: boolean;
  disabled?: boolean;
  className?: string;
  presets?: TimeRangePreset[];
  tone?: "default" | "day" | "night";
  showDuration?: boolean;
  embedded?: boolean;
};

export function TimeRangeField({
  label,
  debut,
  fin,
  onDebutChange,
  onFinChange,
  onRangeChange,
  debutLabel = "Début",
  finLabel = "Fin",
  error,
  debutError,
  finError,
  hint,
  disabled,
  className,
  presets = SHIFT_PRESETS,
  tone = "default",
  showDuration = true,
  embedded = false,
}: TimeRangeFieldProps) {
  const id = useId();
  const hintId = `${id}-hint`;

  const activePresetId = useMemo(() => {
    const match = presets.find((p) => p.debut === debut && p.fin === fin);
    return match?.id ?? null;
  }, [presets, debut, fin]);

  const duration = useMemo(
    () => shiftDurationHours(debut, fin),
    [debut, fin],
  );
  const overnight = isOvernightRange(debut, fin);

  const applyPreset = (preset: TimeRangePreset) => {
    onDebutChange(preset.debut);
    onFinChange(preset.fin);
    onRangeChange?.(preset.debut, preset.fin);
  };

  const ToneIcon = tone === "night" ? Moon : tone === "day" ? Sun : null;

  const accent =
    tone === "day"
      ? "border-l-amber-400"
      : tone === "night"
        ? "border-l-[#311f38]"
        : "border-l-teal";

  return (
    <div
      className={cn(
        embedded
          ? cn("border-l-2 pl-2.5", accent)
          : cn(
              "rounded-lg border border-border/80 bg-white p-2.5",
              tone === "day" && "bg-amber-50/40",
              tone === "night" && "bg-[#f4f0f6]/80",
            ),
        className,
      )}
    >
      <div className="mb-2 flex items-center justify-between gap-2">
        <div className="flex min-w-0 items-center gap-1.5">
          {ToneIcon ? (
            <ToneIcon
              className={cn(
                "size-3.5 shrink-0",
                tone === "day" && "text-amber-600",
                tone === "night" && "text-[#311f38]",
              )}
              aria-hidden
            />
          ) : null}
          {label ? (
            <p className="truncate text-xs font-semibold text-ink">{label}</p>
          ) : null}
        </div>
        {showDuration && duration != null ? (
          <span
            className={cn(
              "shrink-0 text-[11px] font-semibold tabular-nums",
              overnight ? "text-[#311f38]" : "text-ink-muted",
            )}
          >
            {duration} h
            {overnight ? (
              <span className="ml-1 font-medium text-ink-faint">+1 j</span>
            ) : null}
          </span>
        ) : null}
      </div>

      {presets.length > 0 ? (
        <div
          className="mb-2 flex flex-wrap gap-1"
          role="group"
          aria-label="Presets horaires"
        >
          {presets.map((preset) => {
            const active = activePresetId === preset.id;
            return (
              <button
                key={preset.id}
                type="button"
                disabled={disabled}
                onClick={() => applyPreset(preset)}
                className={cn(
                  "rounded-md border px-2 py-0.5 text-[11px] font-medium transition",
                  "disabled:cursor-not-allowed disabled:opacity-50",
                  active
                    ? tone === "night" || preset.tone === "night"
                      ? "border-[#311f38] bg-[#311f38] text-white"
                      : tone === "day" || preset.tone === "day"
                        ? "border-amber-500 bg-amber-500 text-white"
                        : "border-teal bg-teal text-white"
                    : "border-border/80 bg-white text-ink-muted hover:border-teal/40 hover:text-ink",
                )}
              >
                {preset.label}
                {preset.shortLabel ? (
                  <span
                    className={cn(
                      "ml-1 tabular-nums",
                      active ? "text-white/80" : "text-ink-faint",
                    )}
                  >
                    {preset.shortLabel}
                  </span>
                ) : null}
              </button>
            );
          })}
        </div>
      ) : null}

      <div className="flex items-end gap-1.5">
        <TimeSelect
          id={`${id}-debut`}
          label={debutLabel}
          value={debut}
          onChange={onDebutChange}
          error={debutError}
          disabled={disabled}
          tone={tone === "default" ? "day" : tone}
          className="min-w-0 flex-1"
        />
        <div className="mb-1.5 flex h-10 shrink-0 items-center" aria-hidden>
          <div
            className={cn(
              "h-px w-3",
              tone === "night" ? "bg-[#311f38]/30" : "bg-amber-300/80",
              tone === "default" && "bg-border",
            )}
          />
        </div>
        <TimeSelect
          id={`${id}-fin`}
          label={finLabel}
          value={fin}
          onChange={onFinChange}
          error={finError}
          disabled={disabled}
          tone={tone === "default" ? (overnight ? "night" : "day") : tone}
          className="min-w-0 flex-1"
        />
      </div>

      <FieldHint id={hintId} error={error}>
        {hint ?? (overnight ? "Se termine le lendemain." : undefined)}
      </FieldHint>
    </div>
  );
}
