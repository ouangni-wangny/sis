"use client";

import { useEffect, useId, useMemo, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { Check, ChevronDown, Search, X } from "lucide-react";
import {
  FieldHint,
  FieldLabel,
} from "@/presentation/components/ui/FieldLabel";
import {
  type SelectOption,
} from "@/presentation/components/ui/Select";
import { useFloatingMenu } from "@/presentation/components/ui/useFloatingMenu";
import { cn } from "@/shared/lib/cn";

export type MultiSelectProps = {
  label?: string;
  error?: string;
  hint?: string;
  requiredMark?: boolean;
  optionalMark?: boolean;
  options: SelectOption[];
  value: string[];
  onChange: (next: string[]) => void;
  placeholder?: string;
  searchable?: boolean;
  disabled?: boolean;
  id?: string;
  className?: string;
};

export function MultiSelect({
  label,
  error,
  hint,
  requiredMark,
  optionalMark,
  options,
  value,
  onChange,
  placeholder = "Sélectionner…",
  searchable = false,
  disabled,
  id,
  className,
}: MultiSelectProps) {
  const autoId = useId();
  const selectId = id ?? autoId;
  const hintId = `${selectId}-hint`;
  const listId = `${selectId}-listbox`;
  const rootRef = useRef<HTMLDivElement>(null);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const menuRef = useRef<HTMLDivElement>(null);
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const pos = useFloatingMenu(open, triggerRef);

  const selectedSet = useMemo(() => new Set(value), [value]);

  const selectedOptions = useMemo(
    () => options.filter((o) => selectedSet.has(o.value)),
    [options, selectedSet],
  );

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return options;
    return options.filter(
      (o) =>
        o.label.toLowerCase().includes(q) ||
        o.value.toLowerCase().includes(q),
    );
  }, [options, query]);

  useEffect(() => {
    if (!open) return;
    const onDoc = (e: MouseEvent) => {
      const target = e.target as Node;
      if (
        rootRef.current?.contains(target) ||
        menuRef.current?.contains(target)
      ) {
        return;
      }
      setOpen(false);
      setQuery("");
    };
    document.addEventListener("mousedown", onDoc);
    return () => document.removeEventListener("mousedown", onDoc);
  }, [open]);

  const toggle = (optValue: string) => {
    if (selectedSet.has(optValue)) {
      onChange(value.filter((v) => v !== optValue));
    } else {
      onChange([...value, optValue]);
    }
  };

  const remove = (optValue: string) => {
    onChange(value.filter((v) => v !== optValue));
  };

  const display =
    selectedOptions.length === 0
      ? null
      : selectedOptions.length <= 2
        ? selectedOptions.map((o) => o.label).join(", ")
        : `${selectedOptions.length} sélectionnés`;

  const menu =
    open && pos
      ? createPortal(
          <div
            ref={menuRef}
            id={listId}
            role="listbox"
            aria-multiselectable
            className="fixed z-[200] overflow-hidden rounded-md border border-border bg-white shadow-lg ring-1 ring-ink/5 animate-fade-in"
            style={{
              top: pos.top,
              left: pos.left,
              width: pos.width,
              maxHeight: pos.maxHeight,
              transform:
                pos.placement === "top" ? "translateY(-100%)" : undefined,
            }}
          >
            {searchable ? (
              <div className="relative border-b border-border bg-paper-muted/40 p-2">
                <Search className="pointer-events-none absolute left-4 top-1/2 size-3.5 -translate-y-1/2 text-ink-faint" />
                <input
                  autoFocus
                  value={query}
                  onChange={(e) => setQuery(e.target.value)}
                  placeholder="Filtrer…"
                  className="h-9 w-full rounded-md border border-border bg-white pl-8 pr-3 text-sm outline-none focus:border-teal focus:ring-2 focus:ring-teal/20"
                />
              </div>
            ) : null}
            <ul
              className="overflow-y-auto py-1"
              style={{
                maxHeight: searchable ? pos.maxHeight - 52 : pos.maxHeight,
              }}
            >
              {filtered.length === 0 ? (
                <li className="px-3 py-2 text-xs text-ink-muted">
                  Aucun résultat
                </li>
              ) : (
                filtered.map((opt, index) => {
                  const active = selectedSet.has(opt.value);
                  return (
                    <li key={`${opt.value}-${index}`}>
                      <button
                        type="button"
                        role="option"
                        aria-selected={active}
                        className={cn(
                          "flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm transition hover:bg-paper-muted",
                          active && "bg-teal/10 font-medium text-teal-dark",
                        )}
                        onClick={() => toggle(opt.value)}
                      >
                        <span className="truncate">{opt.label}</span>
                        {active ? (
                          <Check className="size-3.5 shrink-0 text-teal" />
                        ) : null}
                      </button>
                    </li>
                  );
                })
              )}
            </ul>
          </div>,
          document.body,
        )
      : null;

  return (
    <div className="flex flex-col gap-1.5 text-sm" ref={rootRef}>
      {label ? (
        <FieldLabel
          htmlFor={`${selectId}-trigger`}
          required={requiredMark}
          optional={optionalMark}
        >
          {label}
        </FieldLabel>
      ) : null}

      <button
        ref={triggerRef}
        type="button"
        id={`${selectId}-trigger`}
        disabled={disabled}
        aria-expanded={open}
        aria-controls={listId}
        aria-invalid={error ? true : undefined}
        aria-required={requiredMark || undefined}
        aria-describedby={hintId}
        className={cn(
          "flex min-h-10 w-full items-center justify-between gap-2 rounded-md border border-border bg-white px-3 py-1.5 text-left text-ink outline-none transition focus:border-teal focus:ring-2 focus:ring-teal/20 disabled:cursor-not-allowed disabled:bg-paper-muted",
          open && "border-teal ring-2 ring-teal/20",
          error && "border-danger focus:border-danger focus:ring-danger/20",
          className,
        )}
        onClick={() => {
          if (disabled) return;
          setOpen((v) => !v);
        }}
      >
        <div className="flex min-w-0 flex-1 flex-wrap items-center gap-1">
          {selectedOptions.length > 0 && selectedOptions.length <= 3 ? (
            selectedOptions.map((o) => (
              <span
                key={o.value}
                className="inline-flex max-w-full items-center gap-0.5 rounded bg-teal/10 px-1.5 py-0.5 text-[11px] font-medium text-teal"
              >
                <span className="truncate">{o.label}</span>
                <span
                  role="button"
                  tabIndex={-1}
                  aria-label={`Retirer ${o.label}`}
                  className="rounded p-0.5 hover:bg-teal/20"
                  onClick={(e) => {
                    e.stopPropagation();
                    remove(o.value);
                  }}
                  onKeyDown={(e) => {
                    if (e.key === "Enter" || e.key === " ") {
                      e.preventDefault();
                      e.stopPropagation();
                      remove(o.value);
                    }
                  }}
                >
                  <X className="size-2.5" />
                </span>
              </span>
            ))
          ) : (
            <span className={cn("truncate", !display && "text-ink-faint")}>
              {display ?? placeholder}
            </span>
          )}
        </div>
        <ChevronDown
          className={cn(
            "size-4 shrink-0 text-ink-faint transition",
            open && "rotate-180 text-teal",
          )}
        />
      </button>

      {menu}

      <FieldHint id={hintId} error={error}>
        {hint}
      </FieldHint>
    </div>
  );
}
