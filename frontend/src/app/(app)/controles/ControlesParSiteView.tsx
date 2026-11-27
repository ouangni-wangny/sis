"use client";

import { useMemo, useState } from "react";
import { format, parseISO } from "date-fns";
import { fr } from "date-fns/locale";
import {
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  List,
  Search,
} from "lucide-react";
import { useControlesParSite } from "@/application/hooks/useResources";
import type {
  ControleSite,
  ControleSiteEtat,
  ControleSiteLigne,
} from "@/domain/types/entities";
import { Badge } from "@/presentation/components/ui/Badge";
import { Button } from "@/presentation/components/ui/Button";
import { Input } from "@/presentation/components/ui/Input";
import { Select } from "@/presentation/components/ui/Select";
import { Spinner } from "@/presentation/components/ui/Spinner";
import { cn } from "@/shared/lib/cn";
import { PhotoPreuve } from "./PhotoPreuve";

function isoLocal(d: Date) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}

function plusJours(iso: string, n: number) {
  const d = new Date(`${iso}T12:00:00`);
  d.setDate(d.getDate() + n);
  return isoLocal(d);
}

function heure(iso: string | null | undefined) {
  if (!iso) return "—";
  return format(parseISO(iso), "HH:mm");
}

const ETAT: Record<
  ControleSiteEtat,
  { label: string; tone: "success" | "danger" | "warning" | "neutral" | "info" }
> = {
  present: { label: "Présent", tone: "success" },
  absent: { label: "Absent", tone: "danger" },
  non_controle: { label: "Pas encore contrôlé", tone: "warning" },
  absence_declaree: { label: "Absence déclarée", tone: "neutral" },
  hors_planning: { label: "Hors planning", tone: "info" },
};

type Filtre = "tous" | "incomplets" | "absents";

const FILTRES: Array<{ value: Filtre; label: string }> = [
  { value: "tous", label: "Tous les sites" },
  { value: "incomplets", label: "Sites pas entièrement contrôlés" },
  { value: "absents", label: "Sites avec un absent" },
];

function nomAgent(l: ControleSiteLigne) {
  return l.agent ? `${l.agent.prenom} ${l.agent.nom}`.trim() : "Agent inconnu";
}

function Progression({ site }: { site: ControleSite }) {
  const total = site.a_controler;
  const pct = total > 0 ? Math.round((site.controles / total) * 100) : 0;
  return (
    <div className="flex min-w-[9rem] items-center gap-2">
      <div
        className="h-2 flex-1 overflow-hidden rounded-full bg-paper-muted"
        role="progressbar"
        aria-valuenow={pct}
        aria-valuemin={0}
        aria-valuemax={100}
        aria-label={`${site.controles} contrôlés sur ${total}`}
      >
        <div
          className={cn(
            "h-full rounded-full",
            total === 0
              ? "bg-transparent"
              : pct === 100
                ? "bg-teal"
                : pct >= 50
                  ? "bg-amber-400"
                  : "bg-danger/70",
          )}
          style={{ width: `${pct}%` }}
        />
      </div>
      <span className="w-14 text-right font-mono text-xs tabular-nums text-ink">
        {site.controles}/{total}
      </span>
    </div>
  );
}

function Chiffre({
  label,
  value,
  tone,
}: {
  label: string;
  value: number;
  tone?: "warning" | "danger" | "success";
}) {
  return (
    <div className="text-center">
      <p
        className={cn(
          "font-mono text-sm font-semibold tabular-nums",
          value === 0
            ? "text-ink-faint"
            : tone === "warning"
              ? "text-warning"
              : tone === "danger"
                ? "text-danger"
                : tone === "success"
                  ? "text-teal-dark"
                  : "text-ink",
        )}
      >
        {value}
      </p>
      <p className="text-[10px] uppercase tracking-wide text-ink-faint">
        {label}
      </p>
    </div>
  );
}

function Kpi({
  label,
  value,
  hint,
  tone,
}: {
  label: string;
  value: string | number;
  hint?: string;
  tone?: "warning" | "danger" | "success";
}) {
  return (
    <div className="rounded-lg border border-border bg-white/90 p-3 shadow-sm">
      <p className="text-[11px] font-medium uppercase tracking-wide text-ink-muted">
        {label}
      </p>
      <p
        className={cn(
          "mt-1 font-mono text-xl font-semibold tabular-nums",
          tone === "warning"
            ? "text-warning"
            : tone === "danger"
              ? "text-danger"
              : tone === "success"
                ? "text-teal-dark"
                : "text-ink",
        )}
      >
        {value}
      </p>
      {hint ? <p className="text-xs text-ink-faint">{hint}</p> : null}
    </div>
  );
}

function DetailSite({
  site,
  onVoirListe,
}: {
  site: ControleSite;
  onVoirListe: (siteId: string) => void;
}) {
  return (
    <div className="border-t border-border bg-paper-muted/30 px-3 py-3 sm:px-5">
      {site.lignes.length === 0 ? (
        <p className="text-sm text-ink-muted">Aucun agent ce jour-là.</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full min-w-[720px] text-sm">
            <thead>
              <tr className="text-left text-[11px] uppercase tracking-wide text-ink-faint">
                <th className="py-1.5 pr-3 font-medium">Agent</th>
                <th className="py-1.5 pr-3 font-medium">Poste / horaires</th>
                <th className="py-1.5 pr-3 font-medium">Résultat</th>
                <th className="py-1.5 pr-3 font-medium">Contrôle</th>
                <th className="py-1.5 pr-3 font-medium">Preuve</th>
                <th className="py-1.5 font-medium">Commentaire</th>
              </tr>
            </thead>
            <tbody>
              {site.lignes.map((l, i) => {
                const etat = ETAT[l.etat];
                return (
                  <tr
                    key={`${l.vacation_id ?? l.controle?.id ?? i}`}
                    className={cn(
                      "border-t border-border/60 align-top",
                      l.etat === "non_controle" && "bg-amber-50/50",
                    )}
                  >
                    <td className="py-2 pr-3">
                      <p className="font-medium text-ink">{nomAgent(l)}</p>
                      <p className="text-xs text-ink-muted">
                        {l.agent?.matricule ?? "—"}
                        {l.est_remplacement ? " · remplaçant" : ""}
                      </p>
                    </td>
                    <td className="py-2 pr-3">
                      <p className="text-ink">
                        {l.poste ?? "Poste non précisé"}
                      </p>
                      <p className="font-mono text-xs text-ink-muted">
                        {l.heure_debut
                          ? `${l.heure_debut} – ${l.heure_fin}`
                          : "pas de vacation prévue"}
                      </p>
                    </td>
                    <td className="py-2 pr-3">
                      <Badge tone={etat.tone}>{etat.label}</Badge>
                      {l.etat === "hors_planning" && l.controle ? (
                        <p className="mt-1 text-xs text-ink-muted">
                          Constaté{" "}
                          {l.controle.resultat === "absent"
                            ? "absent"
                            : "présent"}
                        </p>
                      ) : null}
                    </td>
                    <td className="py-2 pr-3">
                      {l.controle ? (
                        <>
                          <p className="text-ink">
                            {heure(l.controle.effectue_at)}
                            {l.nb_controles > 1 ? (
                              <span className="text-xs text-ink-muted">
                                {" "}
                                · {l.nb_controles} contrôles
                              </span>
                            ) : null}
                          </p>
                          <p className="text-xs text-ink-muted">
                            {l.controle.controleur ?? "—"}
                            {l.controle.siege ? " (siège)" : ""}
                          </p>
                        </>
                      ) : (
                        <span className="text-ink-faint">—</span>
                      )}
                    </td>
                    <td className="py-2 pr-3">
                      <PhotoPreuve photo={l.controle?.photo} />
                    </td>
                    <td className="py-2">
                      <span className="line-clamp-2 max-w-xs text-ink-muted">
                        {l.controle?.commentaire || "—"}
                      </span>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
      <div className="mt-2 flex justify-end">
        <Button
          type="button"
          size="sm"
          variant="secondary"
          onClick={() => onVoirListe(site.site_id)}
        >
          <List className="size-4" />
          Tous les contrôles de ce site
        </Button>
      </div>
    </div>
  );
}

/**
 * Contrôles d’une journée, groupés par site : combien d’agents à contrôler,
 * combien l’ont été, et au clic le détail agent par agent.
 */
export function ControlesParSiteView({
  onVoirListe,
}: {
  onVoirListe: (siteId: string) => void;
}) {
  const aujourdhui = isoLocal(new Date());
  const [date, setDate] = useState(aujourdhui);
  const [recherche, setRecherche] = useState("");
  const [zone, setZone] = useState("");
  const [filtre, setFiltre] = useState<Filtre>("tous");
  const [ouverts, setOuverts] = useState<Set<string>>(new Set());

  const { data, isLoading, isFetching, isError } = useControlesParSite({
    date,
  });
  const jour = data?.data;

  const zones = useMemo(() => {
    const noms = new Set<string>();
    for (const s of jour?.sites ?? []) if (s.zone) noms.add(s.zone);
    return [...noms].sort((a, b) => a.localeCompare(b, "fr"));
  }, [jour]);

  const sites = useMemo(() => {
    const q = recherche.trim().toLowerCase();
    return (jour?.sites ?? []).filter((s) => {
      if (zone && s.zone !== zone) return false;
      if (filtre === "incomplets" && s.non_controles === 0) return false;
      if (filtre === "absents" && s.absents === 0) return false;
      if (!q) return true;
      if (s.site.toLowerCase().includes(q)) return true;
      if (s.zone?.toLowerCase().includes(q)) return true;
      return s.lignes.some((l) =>
        `${nomAgent(l)} ${l.agent?.matricule ?? ""} ${l.poste ?? ""} ${l.controle?.controleur ?? ""}`
          .toLowerCase()
          .includes(q),
      );
    });
  }, [jour, recherche, zone, filtre]);

  const basculer = (id: string) =>
    setOuverts((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });

  const t = jour?.totaux;
  const taux =
    t && t.a_controler > 0
      ? Math.round((t.controles / t.a_controler) * 100)
      : 0;
  const libelleJour = format(parseISO(date), "EEEE d MMMM yyyy", {
    locale: fr,
  });

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-2">
        <Button
          type="button"
          size="sm"
          variant="secondary"
          aria-label="Jour précédent"
          onClick={() => setDate(plusJours(date, -1))}
        >
          <ChevronLeft className="size-4" />
        </Button>
        <input
          type="date"
          value={date}
          max={aujourdhui}
          onChange={(e) => e.target.value && setDate(e.target.value)}
          className="h-9 rounded-md border border-border bg-white px-2 text-sm text-ink"
          aria-label="Jour des contrôles"
        />
        <Button
          type="button"
          size="sm"
          variant="secondary"
          aria-label="Jour suivant"
          disabled={date >= aujourdhui}
          onClick={() => setDate(plusJours(date, 1))}
        >
          <ChevronRight className="size-4" />
        </Button>
        <Button
          type="button"
          size="sm"
          variant={date === aujourdhui ? "primary" : "secondary"}
          onClick={() => setDate(aujourdhui)}
        >
          Aujourd&apos;hui
        </Button>
        <p className="text-sm font-medium capitalize text-ink">{libelleJour}</p>
        {isFetching ? <Spinner /> : null}
      </div>

      {t ? (
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
          <Kpi
            label="Agents à contrôler"
            value={t.a_controler}
            hint={`sur ${t.sites} site${t.sites > 1 ? "s" : ""}`}
          />
          <Kpi
            label="Contrôlés"
            value={t.controles}
            hint={`${taux} % des agents`}
            tone={
              t.a_controler > 0 && t.controles === t.a_controler
                ? "success"
                : undefined
            }
          />
          <Kpi
            label="Pas encore contrôlés"
            value={t.non_controles}
            tone={t.non_controles > 0 ? "warning" : "success"}
          />
          <Kpi
            label="Absents constatés"
            value={t.absents}
            tone={t.absents > 0 ? "danger" : undefined}
          />
          <Kpi
            label="Sites entièrement contrôlés"
            value={`${t.sites_complets}/${t.sites}`}
            hint={
              t.hors_planning > 0
                ? `${t.hors_planning} contrôle${t.hors_planning > 1 ? "s" : ""} hors planning`
                : undefined
            }
          />
        </div>
      ) : null}

      <div className="rounded-xl border border-border bg-white shadow-sm">
        <div className="flex flex-wrap items-center gap-2 border-b border-border px-3 py-3 sm:px-5">
          <div className="relative min-w-[14rem] flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-faint" />
            <Input
              aria-label="Rechercher un site, un agent ou un contrôleur"
              placeholder="Rechercher un site, un agent, un contrôleur…"
              value={recherche}
              onChange={(e) => setRecherche(e.target.value)}
              className="pl-9"
            />
          </div>
          {zones.length > 1 ? (
            <Select
              className="min-w-[10rem]"
              value={zone}
              options={[
                { value: "", label: "Toutes les zones" },
                ...zones.map((z) => ({ value: z, label: z })),
              ]}
              onChange={(e) => setZone(e.target.value)}
            />
          ) : null}
          <Select
            className="min-w-[14rem]"
            value={filtre}
            options={FILTRES}
            onChange={(e) => setFiltre(e.target.value as Filtre)}
          />
        </div>

        {isLoading ? (
          <div className="flex justify-center py-12">
            <Spinner className="size-6" />
          </div>
        ) : isError ? (
          <p className="px-5 py-10 text-center text-sm text-danger">
            Impossible de charger les contrôles de ce jour.
          </p>
        ) : sites.length === 0 ? (
          <p className="px-5 py-10 text-center text-sm text-ink-muted">
            {(jour?.sites.length ?? 0) === 0
              ? "Aucun agent planifié ni contrôle ce jour-là."
              : "Aucun site ne correspond à ces filtres."}
          </p>
        ) : (
          <ul>
            {sites.map((site) => {
              const ouvert = ouverts.has(site.site_id);
              return (
                <li
                  key={site.site_id}
                  className="border-b border-border last:border-b-0"
                >
                  <button
                    type="button"
                    onClick={() => basculer(site.site_id)}
                    aria-expanded={ouvert}
                    className="flex w-full flex-wrap items-center gap-x-4 gap-y-2 px-3 py-3 text-left transition hover:bg-paper-muted/40 sm:px-5"
                  >
                    <ChevronDown
                      className={cn(
                        "size-4 shrink-0 text-ink-faint transition-transform",
                        ouvert ? "rotate-0" : "-rotate-90",
                      )}
                    />
                    <div className="min-w-[10rem] flex-1">
                      <p className="font-semibold text-ink">{site.site}</p>
                      <p className="text-xs text-ink-muted">
                        {site.zone ?? "Sans zone"}
                        {site.dernier_controle_at
                          ? ` · dernier contrôle ${heure(site.dernier_controle_at)}`
                          : " · aucun contrôle"}
                      </p>
                    </div>
                    <Progression site={site} />
                    <div className="flex gap-4">
                      <Chiffre label="À contrôler" value={site.a_controler} />
                      <Chiffre
                        label="Présents"
                        value={site.presents}
                        tone="success"
                      />
                      <Chiffre
                        label="Absents"
                        value={site.absents}
                        tone="danger"
                      />
                      <Chiffre
                        label="Restants"
                        value={site.non_controles}
                        tone="warning"
                      />
                    </div>
                  </button>
                  {ouvert ? (
                    <DetailSite site={site} onVoirListe={onVoirListe} />
                  ) : null}
                </li>
              );
            })}
          </ul>
        )}
      </div>
      <p className="text-xs text-ink-faint">
        « À contrôler » = agents planifiés ce jour-là sur le site (hors
        contrôleurs). Un agent absent déjà déclaré (congé, absence à recouvrir)
        n’est pas compté. « Hors planning » = agent contrôlé sur le site sans
        vacation prévue. Mise à jour automatique toutes les 2 minutes.
      </p>
    </div>
  );
}
