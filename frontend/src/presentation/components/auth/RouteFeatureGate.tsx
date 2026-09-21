"use client";

import type { ReactNode } from "react";
import { usePathname } from "next/navigation";
import { FeatureGate } from "@/presentation/components/auth/FeatureGate";
import { featureForPath } from "@/shared/config/routeFeatures";

/** Applique le FeatureGate selon la route courante. */
export function RouteFeatureGate({ children }: { children: ReactNode }) {
  const pathname = usePathname() ?? "/";
  const feature = featureForPath(pathname);

  if (!feature) {
    return <>{children}</>;
  }

  return <FeatureGate feature={feature}>{children}</FeatureGate>;
}
