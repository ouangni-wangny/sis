"use client";

import { LogOut, MapPin, Shield } from "lucide-react";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { Button } from "@/presentation/components/ui/Button";

function initials(prenom?: string, nom?: string) {
  return `${(prenom ?? "").charAt(0)}${(nom ?? "").charAt(0)}`.toUpperCase() || "?";
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-start justify-between gap-3 border-b border-border px-4 py-3 last:border-b-0">
      <span className="shrink-0 text-xs font-medium uppercase tracking-wide text-ink-faint">
        {label}
      </span>
      <span className="text-right text-sm font-semibold text-ink">{value}</span>
    </div>
  );
}

export default function TerrainProfilPage() {
  const { user, logout } = useAuth();
  const agent = user?.agent;
  const zones = [
    ...new Map(
      (agent?.perimetres ?? [])
        .filter((p) => p.zone?.nom || p.zone_id)
        .map((p) => [p.zone_id ?? p.zone?.id ?? p.id, p.zone?.nom ?? "Zone"]),
    ).entries(),
  ];

  return (
    <div className="space-y-4 px-4 pt-5">
      <header className="border border-[#0A4F44] bg-[#0F6B5C] p-4 text-white">
        <div className="flex items-center gap-3">
          <div className="flex size-14 shrink-0 items-center justify-center border border-white/40 bg-white/15 text-base font-bold">
            {initials(user?.prenom, user?.nom)}
          </div>
          <div className="min-w-0">
            <h1 className="truncate text-lg font-bold">
              {user?.prenom} {user?.nom}
            </h1>
            <p className="font-mono text-sm text-white/80">
              {user?.matricule ?? agent?.matricule ?? "—"}
            </p>
            {agent?.type ? (
              <span className="mt-2 inline-block border border-white/35 bg-white/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide">
                {agent.type}
              </span>
            ) : null}
          </div>
        </div>
      </header>

      <section className="border border-border bg-white">
        <div className="flex items-center gap-2 border-b border-border px-4 py-2.5">
          <Shield className="size-3.5 text-[#0F6B5C]" />
          <h2 className="text-xs font-semibold uppercase tracking-wide text-ink-faint">
            Identité
          </h2>
        </div>
        <Row label="Prénom" value={user?.prenom || agent?.prenom || "—"} />
        <Row label="Nom" value={user?.nom || agent?.nom || "—"} />
        <Row label="Matricule" value={user?.matricule ?? agent?.matricule ?? "—"} />
      </section>

      <section className="border border-border bg-white">
        <div className="flex items-center gap-2 border-b border-border px-4 py-2.5">
          <MapPin className="size-3.5 text-[#0F6B5C]" />
          <h2 className="text-xs font-semibold uppercase tracking-wide text-ink-faint">
            Périmètre
          </h2>
        </div>
        {zones.length === 0 ? (
          <p className="px-4 py-4 text-sm text-ink-muted">
            Aucune zone assignée. Contactez votre administrateur.
          </p>
        ) : (
          <ul className="divide-y divide-border">
            {zones.map(([id, nom]) => (
              <li
                key={id}
                className="flex items-center gap-2 px-4 py-3 text-sm font-medium text-ink"
              >
                <span className="size-1.5 shrink-0 bg-[#0F6B5C]" />
                {nom}
              </li>
            ))}
          </ul>
        )}
      </section>

      <Button
        type="button"
        variant="danger"
        className="w-full !rounded-none"
        onClick={() =>
          void logout().then(() => {
            window.location.href = "/login";
          })
        }
      >
        <LogOut className="size-4" />
        Se déconnecter
      </Button>
    </div>
  );
}
