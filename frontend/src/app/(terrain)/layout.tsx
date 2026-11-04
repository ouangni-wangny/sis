"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { TerrainBottomNav } from "@/presentation/components/terrain/TerrainBottomNav";
import { Alert } from "@/presentation/components/ui/Alert";
import { Button } from "@/presentation/components/ui/Button";
import { Spinner } from "@/presentation/components/ui/Spinner";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { isControleurUser, isTerrainUser } from "@/shared/lib/can";

export default function TerrainLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const { token, user, isLoading, logout } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (isLoading) return;
    if (!token) {
      router.replace("/login");
      return;
    }
    if (user && !isTerrainUser(user)) {
      router.replace("/");
    }
  }, [isLoading, token, user, router]);

  if (isLoading || !token || !user || !isTerrainUser(user)) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[#F2F5F4]">
        <Spinner className="size-8" />
      </div>
    );
  }

  if (!isControleurUser(user)) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[#F2F5F4] p-6">
        <div className="w-full max-w-lg space-y-4">
          <Alert tone="warning">
            Cet espace est réservé aux contrôleurs. Pour toute question sur votre
            planning, contactez votre responsable.
          </Alert>
          <Button
            type="button"
            variant="secondary"
            onClick={() =>
              void logout().then(() => {
                window.location.href = "/login";
              })
            }
          >
            Se déconnecter
          </Button>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-[#F2F5F4] text-ink">
      <div className="mx-auto min-h-screen max-w-lg pb-24">{children}</div>
      <TerrainBottomNav />
    </div>
  );
}
