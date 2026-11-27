"use client";

import { useCallback, useMemo, useState } from "react";
import Link from "next/link";
import {
  AlertCircle,
  ArrowLeft,
  Check,
  CheckCircle2,
  ChevronRight,
  Clock,
  RefreshCw,
} from "lucide-react";
import { useChargement } from "@/application/hooks/useChargement";
import { vacationsApi } from "@/infrastructure/http/resources";
import type { Remplacant, Vacation } from "@/domain/types/entities";
import { Button } from "@/presentation/components/ui/Button";
import { Spinner } from "@/presentation/components/ui/Spinner";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { can } from "@/shared/lib/can";
import { cn } from "@/shared/lib/cn";

function aujourdhui() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}

function creneau(v: Vacation) {
  return `${v.heure_debut.slice(0, 5)}–${v.heure_fin.slice(0, 5)}`;
}

function raison(r: Remplacant) {
  if (r.descend) {
    return r.meme_poste
      ? "Termine sur ce poste · enchaîne en heures sup."
      : "Termine sur ce site · enchaîne en heures sup.";
  }
  if (r.pool_siege) return "Pool siège";
  return r.matricule ?? "Agent disponible";
}

/**
 * Absence constatée sur le terrain : le contrôleur désigne qui prend le poste.
 * L’agent qui descend (il finit sur ce site quand le créneau commence) est
 * proposé en premier et sa vacation est comptée en heures supplémentaires.
 */
export default function TerrainRecouvrirPage() {
  const { user } = useAuth();
  const autorise = can(user, "vacations.recouvrir") || can(user, "vacations.update");

  const [trous, setTrous] = useState<Vacation[]>([]);
  const [trou, setTrou] = useState<Vacation | null>(null);
  const [remplacants, setRemplacants] = useState<Remplacant[] | null>(null);
  const [choisi, setChoisi] = useState<string | null>(null);
  const [recherche, setRecherche] = useState("");
  const [erreur, setErreur] = useState<string | null>(null);
  const [ok, setOk] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const charger = useCallback(() => {
    const jour = aujourdhui();
    return vacationsApi.list({
      statut: "a_recouvrir",
      date_from: jour,
      date_to: jour,
      per_page: 100,
    });
  }, []);
  const appliquer = useCallback((res: Awaited<ReturnType<typeof charger>>) => {
    setTrous(res.data);
  }, []);
  const { loading, error, recharger } = useChargement(
    charger,
    appliquer,
    "Impossible de charger les postes à recouvrir.",
  );

  const ouvrir = async (v: Vacation) => {
    setErreur(null);
    setOk(null);
    setChoisi(null);
    setRecherche("");
    setRemplacants(null);
    setTrou(v);
    try {
      const res = await vacationsApi.remplacants(v.id);
      setRemplacants(res.data);
      // L’agent qui descend est le choix naturel : présélectionné.
      const descend = res.data.find((r) => r.descend);
      if (descend) setChoisi(descend.agent_id);
    } catch (err) {
      setRemplacants([]);
      setErreur(getApiErrorMessage(err, "Impossible de charger les remplaçants."));
    }
  };

  const filtres = useMemo(() => {
    const terme = recherche.trim().toLowerCase();
    const liste = remplacants ?? [];
    if (!terme) return liste;
    return liste.filter((r) =>
      `${r.agent} ${r.matricule ?? ""}`.toLowerCase().includes(terme),
    );
  }, [remplacants, recherche]);

  const choix = remplacants?.find((r) => r.agent_id === choisi) ?? null;

  const confirmer = async () => {
    if (!trou || !choisi) return;
    setBusy(true);
    setErreur(null);
    try {
      await vacationsApi.recouvrir(trou.id, { agent_id: choisi });
      setOk(
        `${choix?.agent ?? "L’agent"} prend le poste ${trou.poste?.nom ?? trou.site?.nom ?? ""} (${creneau(trou)})${
          choix?.descend ? " en heures supplémentaires" : ""
        }.`,
      );
      setTrou(null);
      await recharger();
    } catch (err) {
      setErreur(getApiErrorMessage(err, "Le remplacement n’a pas pu être enregistré."));
    } finally {
      setBusy(false);
    }
  };

  if (!autorise) {
    return (
      <div className="px-4 pt-5">
        <div className="border border-dashed border-border bg-white px-4 py-10 text-center text-sm text-ink-muted">
          Vous n’avez pas le droit de recouvrir une absence. Contactez le planning.
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-4 px-4 pt-5">
      <header className="flex items-center justify-between gap-3 border-b border-border pb-4">
        <div className="min-w-0">
          <h1 className="text-xl font-bold tracking-tight text-ink">À recouvrir</h1>
          <p className="mt-0.5 text-sm text-ink-muted">
            Agents absents aujourd’hui sur vos sites : désignez qui prend le poste.
          </p>
        </div>
        {!trou ? (
          <button
            type="button"
            className="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-ink-muted"
            onClick={() => void recharger()}
            aria-label="Actualiser"
          >
            <RefreshCw className="size-3.5" />
            Actualiser
          </button>
        ) : null}
      </header>

      {error || erreur ? (
        <div className="flex items-start gap-2 border border-red-300 bg-red-50 px-3 py-2.5 text-sm text-danger">
          <AlertCircle className="mt-0.5 size-4 shrink-0" />
          <span>{erreur ?? error}</span>
        </div>
      ) : null}
      {ok ? (
        <div className="flex items-start gap-2 border border-[#0F6B5C]/30 bg-[#E6F2EF] px-3 py-2.5 text-sm text-[#0F6B5C]">
          <CheckCircle2 className="mt-0.5 size-4 shrink-0" />
          <span>{ok}</span>
        </div>
      ) : null}

      {!trou ? (
        loading ? (
          <div className="flex justify-center py-10">
            <Spinner className="size-7" />
          </div>
        ) : trous.length === 0 ? (
          <div className="border border-dashed border-border bg-white px-4 py-10 text-center text-sm text-ink-muted">
            Aucun poste à recouvrir. Les absences d’aujourd’hui sur vos sites apparaîtront ici.
          </div>
        ) : (
          <ul className="divide-y divide-border border border-border bg-white">
            {trous.map((v) => (
              <li key={v.id}>
                <button
                  type="button"
                  onClick={() => void ouvrir(v)}
                  className="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-paper-muted/40"
                >
                  <div className="min-w-0 flex-1">
                    <p className="truncate font-semibold text-ink">
                      {v.site?.nom}
                      {v.poste ? ` · ${v.poste.nom}` : ""}
                    </p>
                    <p className="truncate text-sm text-ink-muted">
                      <Clock className="mr-1 inline size-3.5 align-[-2px]" />
                      {creneau(v)} · {v.agent?.prenom} {v.agent?.nom} absent
                    </p>
                  </div>
                  <ChevronRight className="size-4 shrink-0 text-ink-faint" />
                </button>
              </li>
            ))}
          </ul>
        )
      ) : (
        <section className="space-y-3">
          <button
            type="button"
            onClick={() => setTrou(null)}
            disabled={busy}
            className="inline-flex items-center gap-1 text-sm font-semibold text-ink-muted"
          >
            <ArrowLeft className="size-4" />
            Retour à la liste
          </button>
          <div className="border border-border bg-white px-4 py-3">
            <p className="font-semibold text-ink">
              {trou.site?.nom}
              {trou.poste ? ` · ${trou.poste.nom}` : ""}
            </p>
            <p className="text-sm text-ink-muted">
              {creneau(trou)} · remplace {trou.agent?.prenom} {trou.agent?.nom}
            </p>
          </div>

          <input
            type="search"
            value={recherche}
            onChange={(e) => setRecherche(e.target.value)}
            placeholder="Rechercher un agent (nom ou matricule)"
            aria-label="Rechercher un agent"
            className="h-11 w-full border border-border bg-white px-3 text-sm text-ink outline-none focus:border-[#0F6B5C]"
          />

          {remplacants === null ? (
            <div className="flex justify-center py-8">
              <Spinner className="size-6" />
            </div>
          ) : filtres.length === 0 ? (
            <div className="border border-dashed border-border bg-white px-4 py-8 text-center text-sm text-ink-muted">
              Aucun agent libre sur ce créneau. Prévenez le planning.
            </div>
          ) : (
            <ul className="divide-y divide-border border border-border bg-white" role="radiogroup">
              {filtres.map((r) => {
                const actif = r.agent_id === choisi;
                return (
                  <li key={r.agent_id}>
                    <button
                      type="button"
                      role="radio"
                      aria-checked={actif}
                      onClick={() => setChoisi(r.agent_id)}
                      className={cn(
                        "flex w-full items-center gap-3 px-4 py-3 text-left transition",
                        actif ? "bg-[#E6F2EF]" : "hover:bg-paper-muted/40",
                      )}
                    >
                      <div className="min-w-0 flex-1">
                        <p className="truncate font-semibold text-ink">
                          {r.agent}
                          {r.descend ? (
                            <span className="ml-2 inline-block border border-amber-400 bg-amber-50 px-1.5 py-0.5 align-middle text-[10px] font-bold uppercase tracking-wide text-amber-800">
                              Heures sup.
                            </span>
                          ) : null}
                        </p>
                        <p className="truncate text-xs text-ink-muted">{raison(r)}</p>
                      </div>
                      {actif ? <Check className="size-5 shrink-0 text-[#0F6B5C]" /> : null}
                    </button>
                  </li>
                );
              })}
            </ul>
          )}

          {choix ? (
            <p className="text-xs text-ink-muted">
              {choix.descend
                ? `${choix.agent} enchaîne après son poste : la vacation est comptée en heures supplémentaires.`
                : `${choix.agent} prend le poste comme remplaçant (heures normales ; les heures sup. sont décidées par le planning).`}
            </p>
          ) : null}

          <Button
            type="button"
            className="w-full !rounded-none"
            loading={busy}
            disabled={!choisi}
            onClick={() => void confirmer()}
          >
            Confirmer le remplaçant
          </Button>
        </section>
      )}

      {!trou ? (
        <p className="text-center text-xs text-ink-faint">
          Seules les absences d’aujourd’hui sur vos sites sont listées.{" "}
          <Link href="/terrain/controle" className="font-semibold text-[#0F6B5C]">
            Retour aux contrôles
          </Link>
        </p>
      ) : null}
    </div>
  );
}
