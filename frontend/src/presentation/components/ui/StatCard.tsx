import type { ReactNode } from "react";
import { cn } from "@/shared/lib/cn";

export function StatCard({
  label,
  value,
  hint,
  icon,
  className,
}: {
  label: string;
  value: string | number;
  hint?: string;
  icon?: ReactNode;
  className?: string;
}) {
  return (
    <div
      className={cn(
        "rounded-lg border border-border bg-white/90 p-4 shadow-sm",
        className,
      )}
    >
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-xs font-medium uppercase tracking-wide text-ink-muted">
            {label}
          </p>
          <p className="mt-2 font-mono text-2xl font-semibold tabular-nums text-ink">
            {value}
          </p>
          {hint ? (
            <p className="mt-1 text-xs text-ink-faint">{hint}</p>
          ) : null}
        </div>
        {icon ? (
          <div className="rounded-md bg-teal/10 p-2 text-teal">{icon}</div>
        ) : null}
      </div>
    </div>
  );
}
