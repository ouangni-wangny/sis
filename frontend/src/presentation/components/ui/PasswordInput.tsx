"use client";

import { Eye, EyeOff } from "lucide-react";
import { forwardRef, useState, type InputHTMLAttributes } from "react";
import { cn } from "@/shared/lib/cn";
import {
  FieldHint,
  FieldLabel,
} from "@/presentation/components/ui/FieldLabel";

type Props = Omit<InputHTMLAttributes<HTMLInputElement>, "type"> & {
  label?: string;
  error?: string;
  hint?: string;
  requiredMark?: boolean;
};

export const PasswordInput = forwardRef<HTMLInputElement, Props>(
  function PasswordInput(
    {
      className,
      label,
      error,
      hint,
      id,
      requiredMark,
      required,
      ...props
    },
    ref,
  ) {
    const [visible, setVisible] = useState(false);
    const inputId = id ?? (props.name ? String(props.name) : undefined);
    const hintId = inputId ? `${inputId}-hint` : undefined;
    const showRequired = requiredMark ?? required;

    return (
      <div className="flex flex-col gap-1.5 text-sm">
        {label ? (
          <FieldLabel htmlFor={inputId} required={showRequired}>
            {label}
          </FieldLabel>
        ) : null}
        <div className="relative">
          <input
            ref={ref}
            id={inputId}
            type={visible ? "text" : "password"}
            required={required}
            aria-invalid={error ? true : undefined}
            aria-required={showRequired || undefined}
            aria-describedby={hintId}
            className={cn(
              "h-10 w-full rounded-md border border-border bg-white px-3 pr-10 text-ink placeholder:text-ink-faint outline-none transition focus:border-teal focus:ring-2 focus:ring-teal/20",
              error && "border-danger focus:border-danger focus:ring-danger/20",
              className,
            )}
            {...props}
          />
          <button
            type="button"
            tabIndex={-1}
            className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-ink-faint hover:bg-paper-muted hover:text-ink"
            aria-label={visible ? "Masquer le mot de passe" : "Afficher le mot de passe"}
            onClick={() => setVisible((v) => !v)}
          >
            {visible ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
          </button>
        </div>
        <FieldHint id={hintId} error={error}>
          {hint}
        </FieldHint>
      </div>
    );
  },
);
