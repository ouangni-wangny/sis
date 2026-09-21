import { cn } from "@/shared/lib/cn";
import { forwardRef, type InputHTMLAttributes } from "react";
import {
  FieldHint,
  FieldLabel,
} from "@/presentation/components/ui/FieldLabel";

export type InputProps = InputHTMLAttributes<HTMLInputElement> & {
  label?: string;
  error?: string;
  hint?: string;
  requiredMark?: boolean;
  optionalMark?: boolean;
};

export const Input = forwardRef<HTMLInputElement, InputProps>(function Input(
  {
    className,
    label,
    error,
    hint,
    id,
    requiredMark,
    optionalMark,
    required,
    ...props
  },
  ref,
) {
  const inputId = id ?? (props.name ? String(props.name) : undefined);
  const hintId = inputId ? `${inputId}-hint` : undefined;
  const showRequired = requiredMark ?? required;

  return (
    <div className="flex flex-col gap-1.5 text-sm">
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
        ref={ref}
        id={inputId}
        required={required}
        aria-invalid={error ? true : undefined}
        aria-required={showRequired || undefined}
        aria-describedby={hintId}
        className={cn(
          "h-10 rounded-md border border-border bg-white px-3 text-ink placeholder:text-ink-faint outline-none transition focus:border-teal focus:ring-2 focus:ring-teal/20 disabled:cursor-not-allowed disabled:bg-paper-muted disabled:text-ink-faint",
          error && "border-danger focus:border-danger focus:ring-danger/20",
          className,
        )}
        {...props}
      />
      <FieldHint id={hintId} error={error}>
        {hint}
      </FieldHint>
    </div>
  );
});
