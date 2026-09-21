"use client";

import type { ReactNode } from "react";
import { Inbox } from "lucide-react";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";

/** Page module en attente de branchement complet, avec garde permission. */
export function ModulePlaceholder({
  title,
  description,
  permission,
  children,
}: {
  title: string;
  description: string;
  permission: string | string[];
  children?: ReactNode;
}) {
  return (
    <PermissionGate permission={permission} title={title}>
      <PageHeader title={title} description={description} />
      {children ?? (
        <div className="flex flex-col items-center justify-center gap-2 rounded-lg border border-border bg-white/90 py-16 text-center">
          <Inbox className="size-8 text-ink-faint" strokeWidth={1.5} />
          <p className="text-sm font-medium text-ink">Module en cours de branchement</p>
          <p className="max-w-md text-xs text-ink-muted">
            L’API et les permissions sont prêtes. L’écran détaillé sera
            complété dans la prochaine itération.
          </p>
        </div>
      )}
    </PermissionGate>
  );
}
