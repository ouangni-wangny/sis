import { cn } from "@/shared/lib/cn";
import { AlertCircle, CheckCircle2, Info, type LucideIcon } from "lucide-react";
import type { ReactNode } from "react";

type Tone = "danger" | "success" | "info" | "warning";

const styles: Record<Tone, string> = {
  danger: "border-danger/25 bg-danger/5 text-danger",
  success: "border-teal/30 bg-teal/5 text-teal-dark",
  info: "border-border bg-paper-muted text-ink-muted",
  warning: "border-warning/30 bg-warning/5 text-warning",
};

const icons: Record<Tone, LucideIcon> = {
  danger: AlertCircle,
  success: CheckCircle2,
  info: Info,
  warning: AlertCircle,
};

export function Alert({
  tone = "info",
  children,
  className,
  title,
}: {
  tone?: Tone;
  children: ReactNode;
  className?: string;
  title?: string;
}) {
  const Icon = icons[tone];
  return (
    <div
      role={tone === "danger" ? "alert" : "status"}
      className={cn(
        "flex gap-2.5 rounded-md border px-3 py-2.5 text-sm",
        styles[tone],
        className,
      )}
    >
      <Icon className="mt-0.5 size-4 shrink-0" aria-hidden />
      <div className="min-w-0">
        {title ? <p className="font-medium">{title}</p> : null}
        <div className={cn(title && "mt-0.5 opacity-90")}>{children}</div>
      </div>
    </div>
  );
}
