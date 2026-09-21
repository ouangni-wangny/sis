import { cn } from "@/shared/lib/cn";

export function Spinner({ className }: { className?: string }) {
  return (
    <span
      className={cn(
        "inline-block size-5 animate-spin rounded-full border-2 border-teal/20 border-t-teal",
        className,
      )}
      role="status"
      aria-label="Chargement"
    />
  );
}
