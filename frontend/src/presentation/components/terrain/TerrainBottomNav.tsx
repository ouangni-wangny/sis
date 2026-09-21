"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  Home,
  ShieldCheck,
  TriangleAlert,
  UserRound,
} from "lucide-react";
import { cn } from "@/shared/lib/cn";

const TABS: {
  href: string;
  label: string;
  icon: typeof Home;
  exact?: boolean;
}[] = [
  { href: "/terrain", label: "Accueil", icon: Home, exact: true },
  { href: "/terrain/controle", label: "Contrôles", icon: ShieldCheck },
  { href: "/terrain/anomalie", label: "Signalisation", icon: TriangleAlert },
  { href: "/terrain/profil", label: "Profil", icon: UserRound },
];

export function TerrainBottomNav() {
  const pathname = usePathname();

  return (
    <nav className="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-white">
      <div className="mx-auto flex max-w-lg items-stretch justify-around px-1 pb-[max(0.4rem,env(safe-area-inset-bottom))] pt-1">
        {TABS.map((tab) => {
          const active = tab.exact
            ? pathname === tab.href
            : pathname === tab.href || pathname.startsWith(`${tab.href}/`);
          const Icon = tab.icon;
          return (
            <Link
              key={tab.href}
              href={tab.href}
              className={cn(
                "flex min-w-0 flex-1 flex-col items-center gap-1 border-t-2 px-1 py-2 text-[11px] font-semibold transition",
                active
                  ? "border-[#0F6B5C] text-[#0F6B5C]"
                  : "border-transparent text-ink-faint hover:text-ink-muted",
              )}
            >
              <Icon className="size-5 shrink-0" strokeWidth={active ? 2.4 : 2} />
              <span className="truncate">{tab.label}</span>
            </Link>
          );
        })}
      </div>
    </nav>
  );
}
