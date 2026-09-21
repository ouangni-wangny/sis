"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { TerrainBottomNav } from "@/presentation/components/terrain/TerrainBottomNav";
import { Spinner } from "@/presentation/components/ui/Spinner";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { isTerrainUser } from "@/shared/lib/can";

export default function TerrainLayout({
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

  return (
    <div className="min-h-screen bg-[#F2F5F4] text-ink">
      <div className="mx-auto min-h-screen max-w-lg pb-24">{children}</div>
      <TerrainBottomNav />
    </div>
  );
}
