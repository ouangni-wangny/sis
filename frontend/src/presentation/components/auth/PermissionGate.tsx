"use client";

import type { ReactNode } from "react";
import { Lock } from "lucide-react";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { can, canAny } from "@/shared/lib/can";

/** Garde d’accès page : affiche un message si permission manquante. */
export function PermissionGate({
  permission,
  children,
  title = "Accès restreint",
}: {
  permission: string | string[];
  children: ReactNode;
  title?: string;
}) {
  const { user, isLoading } = useAuth();
  const allowed = Array.isArray(permission)
    ? canAny(user, permission)
    : can(user, permission);

  if (isLoading) return null;

  if (!allowed) {
    return (
      <div>
        <PageHeader title={title} />
        <div className="flex flex-col items-center justify-center gap-2 rounded-lg border border-border bg-white/90 py-16 text-center">
          <Lock className="size-8 text-ink-faint" strokeWidth={1.5} />
          <p className="text-sm font-medium text-ink">
            Vous n’avez pas l’autorisation d’accéder à ce module.
          </p>
          <p className="max-w-sm text-xs text-ink-muted">
            Contactez un administrateur si vous pensez que c’est une erreur.
          </p>
        </div>
      </div>
    );
  }

  return <>{children}</>;
}
