"use client";

import {
  forwardRef,
  useEffect,
  useId,
  useMemo,
  useRef,
  useState,
  type ChangeEvent,
  type Ref,
  type SelectHTMLAttributes,
} from "react";
import { createPortal } from "react-dom";
import { Check, ChevronDown, Search } from "lucide-react";
import {
  FieldHint,
  FieldLabel,
} from "@/presentation/components/ui/FieldLabel";
import { useFloatingMenu } from "@/presentation/components/ui/useFloatingMenu";
import { cn } from "@/shared/lib/cn";

export type SelectOption = { value: string; label: string };

type SelectProps = Omit<
  SelectHTMLAttributes<HTMLSelectElement>,
  "children"
> & {
  label?: string;
  error?: string;
  hint?: string;
  requiredMark?: boolean;
  optionalMark?: boolean;
  options: SelectOption[];
  placeholder?: string;
  searchable?: boolean;
};

function fireChange(
  name: string | undefined,
  value: string,
  onChange?: SelectHTMLAttributes<HTMLSelectElement>["onChange"],
) {
  if (!onChange) return;
  onChange({
    target: { name: name ?? "", value },
    currentTarget: { name: name ?? "", value },
  } as ChangeEvent<HTMLSelectElement>);
}

function mergeRefs<T>(
  ...refs: Array<Ref<T> | undefined>
): (node: T | null) => void {
  return (node) => {
    for (const r of refs) {
      if (!r) continue;
      if (typeof r === "function") r(node);
      else (r as { current: T | null }).current = node;
    }
  };
}

export const Select = forwardRef<HTMLSelectElement, SelectProps>(
  function Select(
    {
      className,
      label,
      error,
      hint,
      options,
      id,
      requiredMark,
      optionalMark,
      required,
      placeholder = "Sélectionner…",
      searchable = true,
      value,
      defaultValue,
      disabled,
      name,
      onChange,
      onBlur,
      ...props
    },
    ref,
  ) {
    const autoId = useId();
    const selectId = id ?? (name ? String(name) : autoId);
    const hintId = `${selectId}-hint`;
    const listId = `${selectId}-listbox`;
    const showRequired = requiredMark ?? required;
    const rootRef = useRef<HTMLDivElement>(null);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const menuRef = useRef<HTMLDivElement>(null);
    const selectRef = useRef<HTMLSelectElement>(null);
    const controlled = value !== undefined;
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState("");
    const [internal, setInternal] = useState(
      String(value ?? defaultValue ?? ""),
    );
    const pos = useFloatingMenu(open, triggerRef);

    useEffect(() => {
      if (controlled) setInternal(String(value ?? ""));
    }, [controlled, value]);

    // Uncontrolled + RHF setValue : resync après changement d’options
    // (le <select> natif est mis à jour hors du state React du trigger).
    useEffect(() => {
      if (controlled) return;
      const el = selectRef.current;
      if (!el) return;
      const sync = () => {
        const next = el.value;
        setInternal((prev) => (prev === next ? prev : next));
      };
      sync();
      const t = window.setTimeout(sync, 0);
      return () => window.clearTimeout(t);
    }, [controlled, options, name, disabled]);

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

    const selected = useMemo(
      () => options.find((o) => o.value === internal),
      [options, internal],
    );

    const displayLabel = selected?.label
      ?? (internal ? "Chargement…" : null);

    const filtered = useMemo(() => {
      const q = query.trim().toLowerCase();
      if (!q) return options;
      return options.filter(
        (o) =>
          o.label.toLowerCase().includes(q) ||
          o.value.toLowerCase().includes(q),
      );
    }, [options, query]);

    const pick = (next: string) => {
      setInternal(next);
      if (selectRef.current) selectRef.current.value = next;
      fireChange(name, next, onChange);
      setOpen(false);
      setQuery("");
    };

    const menu =
      open && pos
        ? createPortal(
            <div
              ref={menuRef}
              id={listId}
              role="listbox"
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
                style={{ maxHeight: searchable ? pos.maxHeight - 52 : pos.maxHeight }}
              >
                {filtered.length === 0 ? (
                  <li className="px-3 py-2 text-xs text-ink-muted">
                    Aucun résultat
                  </li>
                ) : (
                  filtered.map((opt, index) => {
                    const active = opt.value === internal;
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
                          onClick={() => pick(opt.value)}
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
            htmlFor={selectId}
            required={showRequired}
            optional={optionalMark}
          >
            {label}
          </FieldLabel>
        ) : null}

        <select
          ref={mergeRefs(selectRef, ref)}
          id={selectId}
          name={name}
          value={controlled ? String(value) : undefined}
          defaultValue={controlled ? undefined : String(defaultValue ?? "")}
          required={required}
          disabled={disabled}
          aria-hidden
          tabIndex={-1}
          className="sr-only"
          onChange={(e) => {
            setInternal(e.target.value);
            onChange?.(e);
          }}
          onBlur={onBlur}
          {...props}
        >
          {placeholder ? <option value="">{placeholder}</option> : null}
          {options.map((opt, index) => (
            <option key={`${opt.value}-${index}`} value={opt.value}>
              {opt.label}
            </option>
          ))}
        </select>

        <button
          ref={triggerRef}
          type="button"
          id={`${selectId}-trigger`}
          disabled={disabled}
          aria-expanded={open}
          aria-controls={listId}
          aria-invalid={error ? true : undefined}
          aria-required={showRequired || undefined}
          aria-describedby={hintId}
          className={cn(
            "flex h-10 w-full items-center justify-between gap-2 rounded-md border border-border bg-white px-3 text-left text-ink outline-none transition focus:border-teal focus:ring-2 focus:ring-teal/20 disabled:cursor-not-allowed disabled:bg-paper-muted",
            open && "border-teal ring-2 ring-teal/20",
            error && "border-danger focus:border-danger focus:ring-danger/20",
            className,
          )}
          onClick={() => {
            if (disabled) return;
            setOpen((v) => !v);
          }}
        >
          <span className={cn("truncate", !displayLabel && "text-ink-faint")}>
            {displayLabel ?? placeholder}
          </span>
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
  },
);
