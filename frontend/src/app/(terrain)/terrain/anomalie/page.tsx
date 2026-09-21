"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Check,
  Construction,
  Ellipsis,
  Flame,
  Shield,
  TriangleAlert,
} from "lucide-react";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { anomaliesApi, sitesApi } from "@/infrastructure/http/resources";
import { Button } from "@/presentation/components/ui/Button";
import { Spinner } from "@/presentation/components/ui/Spinner";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { uuid } from "@/shared/lib/terrain";
import { cn } from "@/shared/lib/cn";
import type { Site } from "@/domain/types/entities";

const TYPES = [
  { value: "intrusion", label: "Intrusion", icon: Shield },
  { value: "incendie", label: "Incendie", icon: Flame },
  { value: "technique", label: "Technique", icon: Construction },
  { value: "autre", label: "Autre", icon: Ellipsis },
] as const;

const GRAVITES = [
  { value: "basse", label: "Basse", active: "bg-[#E6F2EF] text-[#0F6B5C] border-[#0F6B5C]" },
  { value: "moyenne", label: "Moyenne", active: "bg-amber-50 text-amber-800 border-amber-500" },
  { value: "haute", label: "Haute", active: "bg-orange-50 text-orange-800 border-orange-500" },
  { value: "critique", label: "Critique", active: "bg-red-50 text-danger border-danger" },
] as const;

export default function TerrainAnomaliePage() {
  const { user } = useAuth();
  const [sites, setSites] = useState<Site[]>([]);
  const [siteId, setSiteId] = useState<string | null>(null);
  const [type, setType] = useState("intrusion");
  const [gravite, setGravite] = useState("moyenne");
  const [commentaire, setCommentaire] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [ok, setOk] = useState(false);
  const [busy, setBusy] = useState(false);
  const [loadingSites, setLoadingSites] = useState(true);

  const zoneIds = useMemo(() => {
    const ids = (user?.agent?.perimetres ?? [])
      .map((p) => p.zone_id)
      .filter((id): id is string => Boolean(id));
    return [...new Set(ids)];
  }, [user?.agent?.perimetres]);

  const noPerimetre = zoneIds.length === 0;

  const loadSites = useCallback(async () => {
    setLoadingSites(true);
    setError(null);
    try {
      if (zoneIds.length === 0) {
        setSites([]);
        setSiteId(null);
        return;
      }
      const results = await Promise.all(
        zoneIds.map((zone_id) => sitesApi.list({ zone_id, per_page: 100 })),
      );
      const map = new Map<string, Site>();
      for (const res of results) {
        for (const s of res.data) map.set(s.id, s);
      }
      const list = [...map.values()].sort((a, b) =>
        a.nom.localeCompare(b.nom, "fr"),
      );
      setSites(list);
      setSiteId((prev) =>
        prev && list.some((s) => s.id === prev) ? prev : (list[0]?.id ?? null),
      );
    } catch (err) {
      setError(getApiErrorMessage(err, "Impossible de charger les sites."));
    } finally {
      setLoadingSites(false);
    }
  }, [zoneIds]);

  useEffect(() => {
    void loadSites();
  }, [loadSites]);

  const selectedSite = sites.find((s) => s.id === siteId);
  const canSubmit = Boolean(siteId && user?.agent?.id && !busy);

  async function submit() {
    if (!user?.agent?.id || !siteId) {
      setError("Choisissez un site.");
      return;
    }
    setBusy(true);
    setError(null);
    setOk(false);
    try {
      await anomaliesApi.create({
        signale_par_id: user.agent.id,
        site_id: siteId,
        type,
        gravite,
        commentaire: commentaire.trim() || undefined,
        client_uuid: uuid(),
      });
      setOk(true);
      setCommentaire("");
      setType("intrusion");
      setGravite("moyenne");
    } catch (err) {
      setError(getApiErrorMessage(err, "Échec de l’envoi."));
    } finally {
      setBusy(false);
    }
  }

  if (ok) {
    return (
      <div className="flex min-h-[70vh] flex-col items-center justify-center gap-4 px-6 text-center">
        <div className="flex size-16 items-center justify-center border border-[#0F6B5C]/30 bg-[#E6F2EF]">
          <Check className="size-8 text-[#0F6B5C]" />
        </div>
        <div>
          <h1 className="text-xl font-bold text-ink">Alerte envoyée</h1>
          <p className="mt-1 text-sm text-ink-muted">
            {selectedSite?.nom
              ? `Signalement enregistré pour ${selectedSite.nom}.`
              : "L’opération a été notifiée."}
          </p>
        </div>
        <Button
          type="button"
          className="mt-2 w-full max-w-xs !rounded-none bg-[#0F6B5C] hover:bg-[#0c574a]"
          onClick={() => setOk(false)}
        >
          Nouvelle signalisation
        </Button>
      </div>
    );
  }

  return (
    <div className="flex min-h-[calc(100dvh-5.5rem)] flex-col px-4 pt-4 pb-4">
      <header className="mb-4">
        <h1 className="text-xl font-bold tracking-tight text-ink">
          Signalisation
        </h1>
        <p className="mt-0.5 text-sm text-ink-muted">
          Site → type → gravité → envoi
        </p>
      </header>

      {error ? (
        <div className="mb-3 flex items-start gap-2 border border-red-300 bg-red-50 px-3 py-2.5 text-sm text-danger">
          <TriangleAlert className="mt-0.5 size-4 shrink-0" />
          <span>{error}</span>
        </div>
      ) : null}

      <div className="flex-1 space-y-3">
        <section className="border border-border bg-white p-3">
          <div className="mb-2 flex items-center justify-between">
            <h2 className="text-xs font-bold uppercase tracking-wide text-ink-faint">
              1 · Site
            </h2>
            {selectedSite ? (
              <span className="truncate text-xs font-medium text-[#0F6B5C]">
                {selectedSite.nom}
              </span>
            ) : null}
          </div>
          {loadingSites ? (
            <div className="flex justify-center py-6">
              <Spinner className="size-6" />
            </div>
          ) : noPerimetre ? (
            <p className="border border-dashed border-border bg-paper-muted px-3 py-3 text-sm text-ink-muted">
              Aucune zone assignée. Contactez votre administrateur.
            </p>
          ) : sites.length === 0 ? (
            <p className="border border-dashed border-border bg-paper-muted px-3 py-3 text-sm text-ink-muted">
              Aucun site dans votre périmètre.
            </p>
          ) : sites.length <= 4 ? (
            <div className="grid grid-cols-1 gap-1.5">
              {sites.map((s) => {
                const active = siteId === s.id;
                return (
                  <button
                    key={s.id}
                    type="button"
                    onClick={() => setSiteId(s.id)}
                    className={cn(
                      "border px-3 py-2.5 text-left text-sm font-semibold transition",
                      active
                        ? "border-[#0F6B5C] bg-[#E6F2EF] text-[#0F6B5C]"
                        : "border-border bg-white text-ink hover:border-[#0F6B5C]/40",
                    )}
                  >
                    {s.nom}
                  </button>
                );
              })}
            </div>
          ) : (
            <select
              value={siteId ?? ""}
              onChange={(e) => setSiteId(e.target.value || null)}
              className="h-11 w-full border border-border bg-white px-3 text-sm font-medium text-ink outline-none focus:border-[#0F6B5C]"
            >
              <option value="" disabled>
                Choisir un site…
              </option>
              {sites.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.nom}
                </option>
              ))}
            </select>
          )}
        </section>

        <section className="border border-border bg-white p-3">
          <h2 className="mb-2 text-xs font-bold uppercase tracking-wide text-ink-faint">
            2 · Que s’est-il passé ?
          </h2>
          <div className="grid grid-cols-4 gap-1.5">
            {TYPES.map((t) => {
              const Icon = t.icon;
              const active = type === t.value;
              return (
                <button
                  key={t.value}
                  type="button"
                  onClick={() => setType(t.value)}
                  className={cn(
                    "flex flex-col items-center gap-1 border px-1 py-2.5 transition",
                    active
                      ? "border-[#0F6B5C] bg-[#E6F2EF] text-[#0F6B5C]"
                      : "border-border bg-white text-ink-muted",
                  )}
                >
                  <Icon className="size-5" />
                  <span className="text-[10px] font-semibold leading-tight">
                    {t.label}
                  </span>
                </button>
              );
            })}
          </div>

          <h2 className="mb-2 mt-4 text-xs font-bold uppercase tracking-wide text-ink-faint">
            Gravité
          </h2>
          <div className="grid grid-cols-4 gap-1">
            {GRAVITES.map((g) => {
              const active = gravite === g.value;
              return (
                <button
                  key={g.value}
                  type="button"
                  onClick={() => setGravite(g.value)}
                  className={cn(
                    "border py-2 text-[11px] font-bold transition",
                    active
                      ? g.active
                      : "border-border bg-white text-ink-muted",
                  )}
                >
                  {g.label}
                </button>
              );
            })}
          </div>
        </section>

        <section className="border border-border bg-white p-3">
          <h2 className="mb-2 text-xs font-bold uppercase tracking-wide text-ink-faint">
            3 · Précisions{" "}
            <span className="font-normal normal-case tracking-normal">
              (optionnel)
            </span>
          </h2>
          <textarea
            value={commentaire}
            onChange={(e) => setCommentaire(e.target.value)}
            rows={3}
            className="w-full resize-none border border-border bg-paper-muted/40 px-3 py-2.5 text-sm outline-none focus:border-[#0F6B5C] focus:bg-white"
            placeholder="Décrivez brièvement la situation…"
          />
        </section>
      </div>

      <div className="sticky bottom-0 -mx-4 mt-4 border-t border-border bg-[#F2F5F4] px-4 py-3">
        <Button
          type="button"
          className="w-full !rounded-none bg-[#0F6B5C] text-white hover:bg-[#0c574a] disabled:opacity-50"
          loading={busy}
          disabled={!canSubmit}
          onClick={() => void submit()}
        >
          Envoyer l’alerte
        </Button>
      </div>
    </div>
  );
}
