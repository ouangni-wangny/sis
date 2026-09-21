"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { AppShell } from "@/presentation/components/layout/AppShell";
import { Spinner } from "@/presentation/components/ui/Spinner";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { isTerrainUser } from "@/shared/lib/can";

export default function AppLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const { token, user, isLoading } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (isLoading) return;
    if (!token) {
      router.replace("/login");
      return;
    }
    if (user && isTerrainUser(user)) {
      router.replace("/terrain");
    }
  }, [isLoading, token, user, router]);

  if (isLoading || !token || (user && isTerrainUser(user))) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-canvas">
        <Spinner className="size-8" />
      </div>
    );
  }

  return <AppShell>{children}</AppShell>;
}
