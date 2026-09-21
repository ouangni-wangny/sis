"use client";

import {
  forwardRef,
  useEffect,
  useId,
  useRef,
  useState,
  type ChangeEvent,
  type InputHTMLAttributes,
  type Ref,
} from "react";
import { createPortal } from "react-dom";
import { fr } from "date-fns/locale";
import { format, isValid, parse, parseISO } from "date-fns";
import { Calendar as CalendarIcon, ChevronLeft, ChevronRight } from "lucide-react";
import { DayPicker } from "react-day-picker";
import {
  FieldHint,
  FieldLabel,
} from "@/presentation/components/ui/FieldLabel";
import { useFloatingMenu } from "@/presentation/components/ui/useFloatingMenu";
import { cn } from "@/shared/lib/cn";
import "react-day-picker/style.css";

type DatePickerProps = Omit<
  InputHTMLAttributes<HTMLInputElement>,
  "type" | "value" | "defaultValue" | "onChange"
> & {
  label?: string;
  error?: string;
  hint?: string;
  requiredMark?: boolean;
  optionalMark?: boolean;
  value?: string;
  defaultValue?: string;
  onChange?: (e: ChangeEvent<HTMLInputElement>) => void;
};

function toDate(value?: string): Date | undefined {
  if (!value) return undefined;
  const iso = parseISO(value);
  if (isValid(iso)) return iso;
  const dmy = parse(value, "yyyy-MM-dd", new Date());
  return isValid(dmy) ? dmy : undefined;
}

function fireChange(
  name: string | undefined,
  value: string,
  onChange?: DatePickerProps["onChange"],
) {
  if (!onChange) return;
  onChange({
    target: { name: name ?? "", value },
    currentTarget: { name: name ?? "", value },
  } as ChangeEvent<HTMLInputElement>);
}

function mergeRefs<T>(...refs: Array<Ref<T> | undefined>): (node: T | null) => void {
  return (node) => {
    for (const r of refs) {
      if (!r) continue;
      if (typeof r === "function") r(node);
      else (r as { current: T | null }).current = node;
    }
  };
}

export const DatePicker = forwardRef<HTMLInputElement, DatePickerProps>(
  function DatePicker(
    {
      className,
      label,
      error,
      hint,
      id,
      requiredMark,
      optionalMark,
      required,
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
    const inputId = id ?? (name ? String(name) : autoId);
    const hintId = `${inputId}-hint`;
    const showRequired = requiredMark ?? required;
    const rootRef = useRef<HTMLDivElement>(null);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const menuRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const controlled = value !== undefined;
    const [open, setOpen] = useState(false);
    const [internal, setInternal] = useState(
      String(value ?? defaultValue ?? ""),
    );
    const pos = useFloatingMenu(open, triggerRef, {
      minWidth: 264,
      maxMenuHeight: 360,
    });

    useEffect(() => {
      if (controlled) setInternal(String(value ?? ""));
    }, [controlled, value]);

    useEffect(() => {
      if (controlled) return;
      const el = inputRef.current;
      if (!el) return;
      const sync = () => {
        if (el.value !== internal) setInternal(el.value);
      };
      sync();
      const t = window.setTimeout(sync, 0);
      return () => window.clearTimeout(t);
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [controlled]);

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
      };
      document.addEventListener("mousedown", onDoc);
      return () => document.removeEventListener("mousedown", onDoc);
    }, [open]);

    const selected = toDate(internal);
    const display = selected
      ? format(selected, "dd MMM yyyy", { locale: fr })
      : "";

    const pick = (day?: Date) => {
      if (!day) return;
      const next = format(day, "yyyy-MM-dd");
      setInternal(next);
      if (inputRef.current) inputRef.current.value = next;
      fireChange(name, next, onChange);
      setOpen(false);
    };

    const clear = () => {
      setInternal("");
      if (inputRef.current) inputRef.current.value = "";
      fireChange(name, "", onChange);
      setOpen(false);
    };

    const menu =
      open && pos
        ? createPortal(
            <div
              ref={menuRef}
              className="fixed z-[200] rounded-md border border-border bg-white p-2 shadow-lg ring-1 ring-ink/5 animate-fade-in"
              style={{
                top: pos.top,
                left: pos.left,
                width: Math.max(pos.width, 264),
                maxHeight: pos.maxHeight,
                transform:
                  pos.placement === "top" ? "translateY(-100%)" : undefined,
              }}
            >
              <DayPicker
                mode="single"
                locale={fr}
                selected={selected}
                onSelect={pick}
                defaultMonth={selected ?? new Date()}
                captionLayout="dropdown"
                startMonth={new Date(new Date().getFullYear() - 90, 0)}
                endMonth={new Date(new Date().getFullYear() + 15, 11)}
                className="sis-day-picker"
                components={{
                  Chevron: ({ orientation }) =>
                    orientation === "left" ? (
                      <ChevronLeft className="size-4" />
                    ) : (
                      <ChevronRight className="size-4" />
                    ),
                }}
              />
              {internal ? (
                <button
                  type="button"
                  className="mt-1 w-full rounded-md px-2 py-1.5 text-xs text-ink-muted transition hover:bg-paper-muted"
                  onClick={clear}
                >
                  Effacer
                </button>
              ) : null}
            </div>,
            document.body,
          )
        : null;

    return (
      <div className="flex flex-col gap-1.5 text-sm" ref={rootRef}>
        {label ? (
          <FieldLabel
            htmlFor={inputId}
            required={showRequired}
            optional={optionalMark}
          >
            {label}
          </FieldLabel>
        ) : null}

        <input
          ref={mergeRefs(inputRef, ref)}
          id={inputId}
          type="text"
          name={name}
          value={controlled ? String(value ?? "") : undefined}
          defaultValue={controlled ? undefined : String(defaultValue ?? "")}
          required={required}
          disabled={disabled}
          readOnly
          tabIndex={-1}
          aria-hidden
          className="sr-only"
          onChange={(e) => {
            setInternal(e.target.value);
            onChange?.(e);
          }}
          onBlur={onBlur}
          {...props}
        />

        <button
          ref={triggerRef}
          type="button"
          disabled={disabled}
          aria-expanded={open}
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
            if (!disabled) setOpen((v) => !v);
          }}
        >
          <span className={cn("truncate", !display && "text-ink-faint")}>
            {display || "Choisir une date…"}
          </span>
          <CalendarIcon
            className={cn(
              "size-4 shrink-0 text-ink-faint",
              open && "text-teal",
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
