"use client";

import { usePathname } from "next/navigation";
import { useState, type ReactNode } from "react";
import { RouteFeatureGate } from "@/presentation/components/auth/RouteFeatureGate";
import { Sidebar } from "@/presentation/components/layout/Sidebar";
import { Topbar } from "@/presentation/components/layout/Topbar";

export function AppShell({ children }: { children: ReactNode }) {
  const pathname = usePathname();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [lastPathname, setLastPathname] = useState(pathname);

  if (pathname !== lastPathname) {
    setLastPathname(pathname);
    setSidebarOpen(false);
  }

  return (
    <div className="fixed inset-0 flex overflow-hidden bg-canvas">
      <Sidebar open={sidebarOpen} onClose={() => setSidebarOpen(false)} />
      <div className="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
        <Topbar onMenuClick={() => setSidebarOpen(true)} />
        <main className="page-fade min-h-0 flex-1 overflow-y-auto overflow-x-hidden p-4 sm:p-6">
          <RouteFeatureGate>{children}</RouteFeatureGate>
        </main>
      </div>
    </div>
  );
}
