import { cn } from "@/shared/lib/cn";
import { forwardRef, type TextareaHTMLAttributes } from "react";
import {
  FieldHint,
  FieldLabel,
} from "@/presentation/components/ui/FieldLabel";

export const Textarea = forwardRef<
  HTMLTextAreaElement,
  TextareaHTMLAttributes<HTMLTextAreaElement> & {
    label?: string;
    error?: string;
    hint?: string;
    requiredMark?: boolean;
    optionalMark?: boolean;
  }
>(function Textarea(
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
  const areaId = id ?? (props.name ? String(props.name) : undefined);
  const hintId = areaId ? `${areaId}-hint` : undefined;
  const showRequired = requiredMark ?? required;

  return (
    <div className="flex flex-col gap-1.5 text-sm">
      {label ? (
        <FieldLabel
          htmlFor={areaId}
          required={showRequired}
          optional={optionalMark}
        >
          {label}
        </FieldLabel>
      ) : null}
      <textarea
        ref={ref}
        id={areaId}
        required={required}
        aria-invalid={error ? true : undefined}
        aria-required={showRequired || undefined}
        aria-describedby={hintId}
        className={cn(
          "min-h-24 rounded-md border border-border bg-white px-3 py-2 text-ink placeholder:text-ink-faint outline-none transition focus:border-teal focus:ring-2 focus:ring-teal/20 disabled:bg-paper-muted",
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
