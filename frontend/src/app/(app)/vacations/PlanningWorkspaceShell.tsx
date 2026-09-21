"use client";

import type { ReactNode } from "react";
import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { RequiredFieldsLegend } from "@/presentation/components/ui/FieldLabel";

/** Coquille Planifier le poste : en-tête + contenu 2 colonnes. */
export function PlanningWorkspaceShell({
  title,
  description,
  icon,
  siteId,
  children,
}: {
  title: string;
  description: string;
  icon: ReactNode;
  siteId?: string;
  children: ReactNode;
}) {
  return (
    <div className="-m-6 min-h-full bg-[linear-gradient(180deg,#f7f6f2_0%,#ffffff_42%)] px-6 py-6 pb-8 max-lg:pb-28">
      <div className="mx-auto max-w-screen-2xl">
        <Link
          href={
            siteId
              ? `/vacations?site_id=${encodeURIComponent(siteId)}`
              : "/vacations"
          }
          className="mb-6 inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-teal"
        >
          <ArrowLeft className="size-4" />
          Planning postes
        </Link>

        <header className="mb-6 flex flex-col gap-4 border-b border-border/80 pb-6 sm:flex-row sm:items-end sm:justify-between">
          <div className="flex items-start gap-3">
            <span className="flex size-11 shrink-0 items-center justify-center rounded-lg bg-teal text-white shadow-sm">
              {icon}
            </span>
            <div>
              <p className="text-xs font-medium uppercase tracking-[0.14em] text-teal">
                Opérations
              </p>
              <h1 className="mt-1 text-2xl font-semibold tracking-tight text-ink">
                {title}
              </h1>
              <p className="mt-1 max-w-2xl text-sm text-ink-muted">
                {description}
              </p>
            </div>
          </div>
          <RequiredFieldsLegend className="sm:text-right" />
        </header>

        {children}
      </div>
    </div>
  );
}

/** Formulaire large + aperçu calendrier plus étroit. */
export const planningTwoColClassName =
  "grid gap-6 lg:grid-cols-[minmax(0,1.7fr)_minmax(17.5rem,22rem)] lg:items-start";
