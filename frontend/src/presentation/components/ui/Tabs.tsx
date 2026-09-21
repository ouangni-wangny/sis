"use client";

import { cn } from "@/shared/lib/cn";
import type { ReactNode } from "react";

export type TabItem = {
  id: string;
  label: string;
  count?: number;
};

export function Tabs({
  items,
  value,
  onChange,
  className,
}: {
  items: TabItem[];
  value: string;
  onChange: (id: string) => void;
  className?: string;
}) {
  return (
    <div
      role="tablist"
      className={cn(
        "flex flex-wrap gap-1 border-b border-border",
        className,
      )}
    >
      {items.map((item) => {
        const active = item.id === value;
        return (
          <button
            key={item.id}
            type="button"
            role="tab"
            aria-selected={active}
            onClick={() => onChange(item.id)}
            className={cn(
              "relative -mb-px px-4 py-2.5 text-sm font-medium transition-colors",
              active
                ? "border-b-2 border-teal text-teal"
                : "border-b-2 border-transparent text-ink-muted hover:text-ink",
            )}
          >
            {item.label}
            {item.count != null ? (
              <span
                className={cn(
                  "ml-2 rounded-full px-1.5 py-0.5 text-[10px] font-semibold tabular-nums",
                  active
                    ? "bg-teal/10 text-teal"
                    : "bg-paper-muted text-ink-faint",
                )}
              >
                {item.count}
              </span>
            ) : null}
          </button>
        );
      })}
    </div>
  );
}

export function TabPanel({
  when,
  active,
  children,
  className,
}: {
  when: string;
  active: string;
  children: ReactNode;
  className?: string;
}) {
  if (when !== active) return null;
  return (
    <div role="tabpanel" className={cn("pt-4", className)}>
      {children}
    </div>
  );
}
