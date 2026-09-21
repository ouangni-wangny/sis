"use client";

import { useId, useMemo } from "react";
import { FieldHint, FieldLabel } from "@/presentation/components/ui/FieldLabel";
import { cn } from "@/shared/lib/cn";

const HOURS = Array.from({ length: 24 }, (_, i) =>
  String(i).padStart(2, "0"),
);

const MINUTES = ["00", "15", "30", "45"] as const;

function parseTime(value: string): { h: string; m: string } {
  const match = /^(\d{1,2}):(\d{2})$/.exec(value.trim());
  if (!match) return { h: "", m: "" };
  const h = String(Number(match[1])).padStart(2, "0");
  const mNum = Number(match[2]);
  const snapped =
    MINUTES.find((m) => Number(m) === mNum) ??
    MINUTES.reduce((best, m) =>
      Math.abs(Number(m) - mNum) < Math.abs(Number(best) - mNum) ? m : best,
    );
  return { h: HOURS.includes(h) ? h : "", m: snapped };
}

function formatTime(h: string, m: string): string {
  if (!h || !m) return "";
  return `${h}:${m}`;
}

export type TimeSelectProps = {
  id?: string;
  label?: string;
  value?: string;
  onChange?: (value: string) => void;
  error?: string;
  hint?: string;
  requiredMark?: boolean;
  optionalMark?: boolean;
  disabled?: boolean;
  className?: string;
  minuteStep?: 5 | 15;
  tone?: "default" | "day" | "night";
};

export function TimeSelect({
  id,
  label,
  value = "",
  onChange,
  error,
  hint,
  requiredMark,
  optionalMark,
  disabled,
  className,
  minuteStep = 15,
  tone = "default",
}: TimeSelectProps) {
  const autoId = useId();
  const inputId = id ?? autoId;
  const hintId = `${inputId}-hint`;
  const { h, m } = parseTime(value);

  const minuteOptions = useMemo(() => {
    if (minuteStep === 5) {
      return Array.from({ length: 12 }, (_, i) =>
        String(i * 5).padStart(2, "0"),
      );
    }
    return [...MINUTES];
  }, [minuteStep]);

  const minutes = useMemo(() => {
    if (m && !minuteOptions.includes(m)) {
      return [...minuteOptions, m].sort((a, b) => Number(a) - Number(b));
    }
    return minuteOptions;
  }, [m, minuteOptions]);

  const setPart = (nextH: string, nextM: string) => {
    onChange?.(formatTime(nextH, nextM));
  };

  return (
    <div className={cn("flex flex-col gap-1.5 text-sm", className)}>
      {label ? (
        <FieldLabel
          htmlFor={`${inputId}-h`}
          required={requiredMark}
          optional={optionalMark}
        >
          {label}
        </FieldLabel>
      ) : null}
      <div
        className={cn(
          "inline-flex h-10 items-center gap-0 rounded-md border bg-white px-1 transition",
          "focus-within:ring-2",
          tone === "day" &&
            "border-amber-200/90 focus-within:border-amber-400 focus-within:ring-amber-400/20",
          tone === "night" &&
            "border-[#311f38]/25 focus-within:border-[#311f38]/50 focus-within:ring-[#311f38]/15",
          tone === "default" &&
            "border-border focus-within:border-teal focus-within:ring-teal/20",
          error &&
            "border-danger focus-within:border-danger focus-within:ring-danger/20",
          disabled && "cursor-not-allowed bg-paper-muted opacity-70",
        )}
      >
        <select
          id={`${inputId}-h`}
          aria-label={label ? `${label} — heure` : "Heure"}
          aria-invalid={error ? true : undefined}
          aria-describedby={hintId}
          disabled={disabled}
          value={h}
          onChange={(e) => setPart(e.target.value, m || "00")}
          className="h-full min-w-[2.4rem] cursor-pointer appearance-none rounded border-0 bg-transparent px-0.5 text-center text-sm font-semibold tabular-nums text-ink outline-none disabled:cursor-not-allowed"
        >
          <option value="">––</option>
          {HOURS.map((hour) => (
            <option key={hour} value={hour}>
              {hour}
            </option>
          ))}
        </select>
        <span className="select-none text-xs text-ink-faint" aria-hidden>
          :
        </span>
        <select
          id={`${inputId}-m`}
          aria-label={label ? `${label} — minutes` : "Minutes"}
          aria-invalid={error ? true : undefined}
          disabled={disabled}
          value={m}
          onChange={(e) => setPart(h || "00", e.target.value)}
          className="h-full min-w-[2.4rem] cursor-pointer appearance-none rounded border-0 bg-transparent px-0.5 text-center text-sm font-semibold tabular-nums text-ink outline-none disabled:cursor-not-allowed"
        >
          <option value="">––</option>
          {minutes.map((min) => (
            <option key={min} value={min}>
              {min}
            </option>
          ))}
        </select>
      </div>
      <FieldHint id={hintId} error={error}>
        {hint}
      </FieldHint>
    </div>
  );
}
