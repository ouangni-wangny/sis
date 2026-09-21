"use client";

import { LogOut, Menu } from "lucide-react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { Button } from "@/presentation/components/ui/Button";

export function Topbar({ onMenuClick }: { onMenuClick: () => void }) {
  const { user, logout } = useAuth();
  const router = useRouter();

  return (
    <header className="flex h-14 shrink-0 items-center justify-between gap-3 border-b border-border bg-white/70 px-4 backdrop-blur-sm sm:px-6">
      <div className="flex min-w-0 items-center gap-2">
        <button
          type="button"
          aria-label="Ouvrir le menu"
          onClick={onMenuClick}
          className="-ml-1 flex size-9 shrink-0 items-center justify-center rounded-md text-ink-muted hover:bg-paper-muted hover:text-ink lg:hidden"
        >
          <Menu className="size-5" />
        </button>
        <div className="min-w-0">
          <p className="hidden text-xs text-ink-muted sm:block">
            Opérations sécurité
          </p>
          <p className="truncate text-sm font-medium text-ink">
            {user ? `${user.prenom} ${user.nom}` : "Session back-office"}
          </p>
        </div>
      </div>
      <div className="flex shrink-0 items-center gap-3">
        {user?.email ? (
          <span className="hidden font-mono text-xs text-ink-muted lg:inline">
            {user.email}
          </span>
        ) : null}
        <Button
          variant="secondary"
          size="sm"
          onClick={async () => {
            await logout();
            router.replace("/login");
          }}
        >
          <LogOut className="size-3.5" />
          <span className="hidden sm:inline">Déconnexion</span>
        </Button>
      </div>
    </header>
  );
}
