"use client";

import { useEffect, useId, useRef, type ReactNode } from "react";
import { X } from "lucide-react";
import { Button } from "@/presentation/components/ui/Button";
import { cn } from "@/shared/lib/cn";

const sizeClass = {
  md: "max-w-lg",
  lg: "max-w-2xl",
  xl: "max-w-3xl",
  "2xl": "max-w-5xl",
} as const;

export function Modal({
  open,
  onClose,
  title,
  description,
  children,
  footer,
  preventClose,
  size = "md",
  scrollable = true,
  bodyClassName,
}: {
  open: boolean;
  onClose: () => void;
  title: string;
  description?: string;
  children: ReactNode;
  footer?: ReactNode;
  preventClose?: boolean;
  size?: keyof typeof sizeClass;
  /** When false, body grows with content (no inner scroll). */
  scrollable?: boolean;
  bodyClassName?: string;
}) {
  const titleId = useId();
  const panelRef = useRef<HTMLDivElement>(null);
  const onCloseRef = useRef(onClose);
  onCloseRef.current = onClose;

  useEffect(() => {
    if (!open) return;

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";

    const t = window.setTimeout(() => {
      const el = panelRef.current?.querySelector<HTMLElement>(
        'input:not([type=hidden]):not([tabindex="-1"]), textarea, button:not([aria-label=Fermer]):not([tabindex="-1"])',
      );
      el?.focus();
    }, 30);

    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape" && !preventClose) onCloseRef.current();
    };
    window.addEventListener("keydown", onKey);

    return () => {
      document.body.style.overflow = previousOverflow;
      window.clearTimeout(t);
      window.removeEventListener("keydown", onKey);
    };
  }, [open, preventClose]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <button
        type="button"
        aria-label="Fermer la fenêtre"
        className="absolute inset-0 bg-ink/40 backdrop-blur-[1px]"
        onClick={() => {
          if (!preventClose) onClose();
        }}
      />
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        className={cn(
          "relative z-10 w-full animate-fade-in rounded-lg border border-border bg-white shadow-xl",
          sizeClass[size],
        )}
      >
        <div className="flex items-start justify-between gap-4 border-b border-border px-5 py-4">
          <div>
            <h2 id={titleId} className="text-base font-semibold text-ink">
              {title}
            </h2>
            {description ? (
              <p className="mt-0.5 text-xs text-ink-muted">{description}</p>
            ) : null}
          </div>
          <Button
            variant="ghost"
            size="sm"
            onClick={onClose}
            disabled={preventClose}
            aria-label="Fermer"
          >
            <X className="size-4" />
          </Button>
        </div>
        <div
          className={cn(
            "px-5 py-4",
            scrollable && "max-h-[min(70vh,560px)] overflow-y-auto",
            bodyClassName,
          )}
        >
          {children}
        </div>
        {footer ? (
          <div className="flex flex-wrap items-center justify-end gap-2 border-t border-border px-5 py-3">
            {footer}
          </div>
        ) : null}
      </div>
    </div>
  );
}
