import { cn } from "@/shared/lib/cn";
import type { ReactNode } from "react";

export function FieldLabel({
  htmlFor,
  children,
  required,
  optional,
  className,
}: {
  htmlFor?: string;
  children: ReactNode;
  required?: boolean;
  optional?: boolean;
  className?: string;
}) {
  return (
    <label
      htmlFor={htmlFor}
      className={cn(
        "flex cursor-pointer items-baseline gap-1.5 font-medium text-ink",
        className,
      )}
    >
      <span>{children}</span>
      {required ? (
        <abbr
          title="Champ obligatoire"
          className="cursor-help no-underline text-danger"
          aria-label="obligatoire"
        >
          *
        </abbr>
      ) : null}
      {optional && !required ? (
        <span className="text-xs font-normal text-ink-faint">(optionnel)</span>
      ) : null}
    </label>
  );
}

export function FieldHint({
  id,
  children,
  error,
}: {
  id?: string;
  children?: ReactNode;
  error?: string;
}) {
  if (error) {
    return (
      <p id={id} role="alert" className="text-xs text-danger">
        {error}
      </p>
    );
  }
  if (!children) return null;
  return (
    <p id={id} className="text-xs text-ink-faint">
      {children}
    </p>
  );
}

export function RequiredFieldsLegend({ className }: { className?: string }) {
  return (
    <p className={cn("text-xs text-ink-muted", className)}>
      Les champs marqués d’un{" "}
      <span className="font-semibold text-danger" aria-hidden>
        *
      </span>{" "}
      sont obligatoires.
    </p>
  );
}
