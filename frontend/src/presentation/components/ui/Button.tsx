import { cn } from "@/shared/lib/cn";
import { forwardRef, type ButtonHTMLAttributes } from "react";
import { Spinner } from "@/presentation/components/ui/Spinner";

type Variant = "primary" | "secondary" | "ghost" | "danger";
type Size = "sm" | "md";

const variants: Record<Variant, string> = {
  primary:
    "bg-teal text-white hover:bg-teal-dark shadow-sm disabled:opacity-50",
  secondary:
    "bg-white text-ink border border-border hover:bg-paper disabled:opacity-50",
  ghost: "bg-transparent text-ink hover:bg-paper-muted disabled:opacity-50",
  danger: "bg-danger text-white hover:bg-danger/90 disabled:opacity-50",
};

const sizes: Record<Size, string> = {
  sm: "h-8 px-3 text-xs",
  md: "h-10 px-4 text-sm",
};

export const Button = forwardRef<
  HTMLButtonElement,
  ButtonHTMLAttributes<HTMLButtonElement> & {
    variant?: Variant;
    size?: Size;
    loading?: boolean;
  }
>(function Button(
  {
    className,
    variant = "primary",
    size = "md",
    type = "button",
    loading,
    disabled,
    children,
    ...props
  },
  ref,
) {
  return (
    <button
      ref={ref}
      type={type}
      disabled={disabled || loading}
      aria-busy={loading || undefined}
      className={cn(
        "inline-flex items-center justify-center gap-2 rounded-md font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal/40",
        variants[variant],
        sizes[size],
        className,
      )}
      {...props}
    >
      {loading ? (
        <Spinner
          className={cn(
            "size-4",
            variant === "primary" || variant === "danger"
              ? "border-white/30 border-t-white"
              : undefined,
          )}
        />
      ) : null}
      {children}
    </button>
  );
});
