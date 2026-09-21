"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { AlertCircle, ChevronRight, RefreshCw } from "lucide-react";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { controlesApi, vacationsApi } from "@/infrastructure/http/resources";
import { Button } from "@/presentation/components/ui/Button";
import { Spinner } from "@/presentation/components/ui/Spinner";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import {
  hintPassagesRequis,
  indexControlesParPassage,
  labelBadgeControle,
  statusControleVacation,
  type PassageStatus,
} from "@/domain/controle-passages";
import type { Vacation } from "@/domain/types/entities";
import { cn } from "@/shared/lib/cn";

function initials(prenom?: string, nom?: string) {
  return `${(prenom ?? "").charAt(0)}${(nom ?? "").charAt(0)}`.toUpperCase() || "?";
}

export default function TerrainHomePage() {
  const { user } = useAuth();
  const router = useRouter();
  const [vacations, setVacations] = useState<Vacation[]>([]);
  const [statusByVacation, setStatusByVacation] = useState(
    () => new Map<string, PassageStatus>(),
  );
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [vacRes, ctrlRes] = await Promise.all([
        vacationsApi.list({ en_poste: 1, per_page: 50 }),
        controlesApi.list({ aujourd_hui: 1, mes_controles: 1, per_page: 100 }),
      ]);
      setVacations(vacRes.data);
      const byAgent = indexControlesParPassage(
        ctrlRes.data
          .filter((c) => c.controle_agent_id)
          .map((c) => ({
            controle_agent_id: c.controle_agent_id!,
            resultat: c.resultat,
            effectue_at: c.effectue_at,
          })),
      );
      const map = new Map<string, PassageStatus>();
      for (const v of vacRes.data) {
        map.set(v.id, statusControleVacation(v, byAgent));
      }
      setStatusByVacation(map);
    } catch (err) {
      setError(getApiErrorMessage(err, "Impossible de charger la mission."));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const hour = new Date().getHours();
  const greeting =
    hour < 12 ? "Bonjour" : hour < 18 ? "Bon après-midi" : "Bonsoir";
  const todayLabel = new Date().toLocaleDateString("fr-FR", {
    weekday: "long",
    day: "numeric",
    month: "long",
  });

  const total = vacations.length;
  const remaining = vacations.filter((v) => !statusByVacation.get(v.id)?.complet)
    .length;
  const doneCount = total - remaining;
  const progress = total > 0 ? Math.round((doneCount / total) * 100) : 0;
  const preview = vacations.slice(0, 5);
  const noPerimetre = (user?.agent?.perimetres?.length ?? 0) === 0;

  return (
    <div className="space-y-4 px-4 pt-5">
      <header className="flex items-start justify-between gap-3 border-b border-border pb-4">
        <div className="min-w-0">
          <p className="text-xs font-medium uppercase tracking-wide text-ink-faint">
            {greeting}
          </p>
          <h1 className="truncate text-xl font-bold tracking-tight text-ink">
            {user?.prenom} {user?.nom}
          </h1>
          <p className="mt-0.5 capitalize text-sm text-ink-muted">{todayLabel}</p>
        </div>
        <Link
          href="/terrain/profil"
          className="flex size-11 shrink-0 items-center justify-center border border-[#0F6B5C] bg-[#0F6B5C] text-sm font-bold text-white"
          aria-label="Profil"
        >
          {initials(user?.prenom, user?.nom)}
        </Link>
      </header>

      {error ? (
        <div className="flex items-start gap-2 border border-red-300 bg-red-50 px-3 py-2.5 text-sm text-danger">
          <AlertCircle className="mt-0.5 size-4 shrink-0" />
          <span>{error}</span>
        </div>
      ) : null}

      <section className="border border-[#0A4F44] bg-[#0F6B5C] p-4 text-white">
        <div className="flex items-baseline justify-between gap-3">
          <p className="text-[11px] font-semibold uppercase tracking-wider text-white/70">
            Mission du jour
          </p>
          <span className="text-xs font-medium text-white/70">
            {doneCount}/{total || "—"}
          </span>
        </div>
        <p className="mt-3 text-4xl font-extrabold leading-none tabular-nums">
          {remaining}
        </p>
        <p className="mt-1 text-sm font-medium text-white/90">
          {remaining === 0 && total > 0
            ? "Tous les agents sont contrôlés"
            : `restant${remaining > 1 ? "s" : ""} à contrôler`}
        </p>

        <div className="mt-4 h-1.5 w-full bg-white/20">
          <div
            className="h-full bg-white transition-all"
            style={{ width: `${progress}%` }}
          />
        </div>

        <Button
          type="button"
          className="mt-4 w-full !rounded-none !bg-white !text-[#0F6B5C] hover:!bg-white/95"
          onClick={() => router.push("/terrain/controle")}
        >
          {remaining > 0 ? "Lancer les contrôles" : "Voir les contrôles"}
        </Button>
      </section>

      <section className="space-y-2">
        <div className="flex items-center justify-between">
          <h2 className="text-xs font-semibold uppercase tracking-wide text-ink-faint">
            À vérifier
          </h2>
          <div className="flex items-center gap-3">
            <Link
              href="/terrain/controle"
              className="text-xs font-semibold text-[#0F6B5C]"
            >
              Tout voir
            </Link>
            <button
              type="button"
              className="inline-flex items-center gap-1 text-xs font-semibold text-ink-muted"
              onClick={() => void load()}
              aria-label="Actualiser"
            >
              <RefreshCw className="size-3.5" />
              Actualiser
            </button>
          </div>
        </div>

        {loading ? (
          <div className="flex justify-center py-10">
            <Spinner className="size-7" />
          </div>
        ) : preview.length === 0 ? (
          <div className="border border-dashed border-border bg-white px-4 py-10 text-center text-sm text-ink-muted">
            {noPerimetre
              ? "Aucune zone ne vous est assignée. Contactez votre administrateur."
              : "Aucun agent en poste à contrôler pour le moment."}
          </div>
        ) : (
          <ul className="divide-y divide-border border border-border bg-white">
            {preview.map((v) => {
              const status = statusByVacation.get(v.id);
              const badge = status
                ? labelBadgeControle(status)
                : { text: "À faire", tone: "muted" as const };
              const hint = status ? hintPassagesRequis(status) : null;
              const complet = Boolean(status?.complet);
              return (
                <li key={v.id}>
                  <button
                    type="button"
                    onClick={() =>
                      router.push(`/terrain/controle?vacationId=${v.id}`)
                    }
                    className="flex w-full items-center gap-3 px-3 py-3 text-left transition hover:bg-paper-muted/60"
                  >
                    <div className="flex size-10 shrink-0 items-center justify-center border border-[#0F6B5C]/25 bg-[#E6F2EF] text-xs font-bold text-[#0F6B5C]">
                      {initials(v.agent?.prenom, v.agent?.nom)}
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm font-semibold text-ink">
                        {v.agent?.prenom} {v.agent?.nom}
                      </p>
                      <p className="truncate text-xs text-ink-muted">
                        {v.site?.nom ?? "Site"} · {v.poste?.nom ?? "Poste"}
                      </p>
                      {hint ? (
                        <p className="mt-0.5 truncate text-[10px] text-ink-faint">
                          {hint}
                        </p>
                      ) : null}
                    </div>
                    {complet ? (
                      <span
                        className={cn(
                          "shrink-0 border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide",
                          badge.tone === "danger"
                            ? "border-red-300 bg-red-50 text-danger"
                            : "border-[#0F6B5C]/30 bg-[#E6F2EF] text-[#0F6B5C]",
                        )}
                      >
                        {badge.text}
                      </span>
                    ) : badge.tone === "partial" ? (
                      <span className="shrink-0 border border-amber-300 bg-amber-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-800">
                        {badge.text}
                      </span>
                    ) : (
                      <ChevronRight className="size-4 shrink-0 text-ink-faint" />
                    )}
                  </button>
                </li>
              );
            })}
          </ul>
        )}
      </section>

      <section className="grid grid-cols-2 gap-2">
        <Link
          href="/terrain/anomalie"
          className="border border-border bg-white px-3 py-3 text-sm font-semibold text-ink transition hover:border-[#0F6B5C]/40"
        >
          Signaler
          <span className="mt-0.5 block text-xs font-normal text-ink-muted">
            Anomalie site
          </span>
        </Link>
        <Link
          href="/terrain/profil"
          className="border border-border bg-white px-3 py-3 text-sm font-semibold text-ink transition hover:border-[#0F6B5C]/40"
        >
          Mon profil
          <span className="mt-0.5 block text-xs font-normal text-ink-muted">
            Compte & zones
          </span>
        </Link>
      </section>
    </div>
  );
}
