"use client";

import type { ReactNode } from "react";
import { PowerOff } from "lucide-react";
import Link from "next/link";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { isFeatureEnabled, isDeveloppeurUser } from "@/shared/lib/can";

/** Bloque le contenu si le module (feature flag) est désactivé. */
export function FeatureGate({
  feature,
  children,
  title = "Module désactivé",
}: {
  feature: string;
  children: ReactNode;
  title?: string;
}) {
  const { user, featureFlags, isLoading } = useAuth();

  if (isLoading) return null;

  // Le développeur peut toujours accéder (pour tester / réactiver)
  if (isDeveloppeurUser(user)) {
    return <>{children}</>;
  }

  if (isFeatureEnabled(featureFlags, feature)) {
    return <>{children}</>;
  }

  return (
    <div>
      <PageHeader title={title} />
      <div className="flex flex-col items-center justify-center gap-3 rounded-lg border border-border bg-white/90 py-16 text-center">
        <PowerOff className="size-8 text-ink-faint" strokeWidth={1.5} />
        <p className="text-sm font-medium text-ink">
          Ce module est temporairement désactivé.
        </p>
        <p className="max-w-sm text-xs text-ink-muted">
          Contactez l’équipe technique si vous avez besoin d’y accéder.
        </p>
        <Link
          href="/"
          className="mt-2 text-sm font-medium text-teal hover:underline"
        >
          Retour au tableau de bord
        </Link>
      </div>
    </div>
  );
}
