"use client";

import { Suspense, useMemo, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { format, parseISO } from "date-fns";
import { fr } from "date-fns/locale";
import { ArrowLeft, CalendarDays, ChevronLeft, ChevronRight } from "lucide-react";
import {
  usePlanifierSemaineRondier,
  usePlanningRondiers,
  usePostes,
  useSites,
} from "@/application/hooks/useResources";
import type { Poste, TrouRondier } from "@/domain/types/entities";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { Alert } from "@/presentation/components/ui/Alert";
import { Button } from "@/presentation/components/ui/Button";
import { Card, CardBody, CardHeader } from "@/presentation/components/ui/Card";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { Select } from "@/presentation/components/ui/Select";
import { Spinner } from "@/presentation/components/ui/Spinner";
import { useToast } from "@/presentation/providers/ToastProvider";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { cn } from "@/shared/lib/cn";

type Ligne = { site_id: string; poste_id: string; quart: "jour" | "nuit" };

const LIBRE: Ligne = { site_id: "", poste_id: "", quart: "jour" };

function isoLocal(d: Date) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}

function lundiDe(iso: string) {
  const d = new Date(`${iso}T12:00:00`);
  const jour = d.getDay();
  d.setDate(d.getDate() + (jour === 0 ? -6 : 1 - jour));
  return isoLocal(d);
}

function plusJours(iso: string, n: number) {
  const d = new Date(`${iso}T12:00:00`);
  d.setDate(d.getDate() + n);
  return isoLocal(d);
}

function jourLong(iso: string) {
  const s = format(parseISO(iso), "EEEE d MMMM", { locale: fr });
  return s.charAt(0).toUpperCase() + s.slice(1);
}

const hm = (v: string | null | undefined) => (v ?? "").slice(0, 5);

function aDesQuarts(p: Poste | undefined) {
  return !!(p?.heure_debut_nuit && p?.heure_fin_nuit);
}

function horairesPoste(p: Poste | undefined, quart: "jour" | "nuit") {
  if (!p) return "";
  if (aDesQuarts(p) && quart === "nuit") {
    return `${hm(p.heure_debut_nuit)}–${hm(p.heure_fin_nuit)}`;
  }
  return p.heure_debut && p.heure_fin ? `${hm(p.heure_debut)}–${hm(p.heure_fin)}` : "";
}

const MOTIF: Record<TrouRondier["motif"], string> = {
  repos: "Repos",
  absence: "Absence",
  vide: "Place vide",
};

function PlanifierRondierForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { toast } = useToast();
  const aujourdhui = isoLocal(new Date());

  const [lundi, setLundi] = useState(() =>
    lundiDe(searchParams.get("lundi") || aujourdhui),
  );
  const [agentId, setAgentId] = useState(searchParams.get("agent_id") ?? "");
  /** Saisie par rondier + semaine ; absente = valeurs du planning existant. */
  const [saisies, setSaisies] = useState<Record<string, Ligne[]>>({});
  const [erreur, setErreur] = useState<string | null>(null);

  const dates = useMemo(
    () => Array.from({ length: 7 }, (_, i) => plusJours(lundi, i)),
    [lundi],
  );

  const planning = usePlanningRondiers({ lundi });
  const { data: sitesData } = useSites({ all: true });
  const { data: postesData } = usePostes({ all: true });
  const planifier = usePlanifierSemaineRondier();

  const rondiers = useMemo(() => planning.data?.data.rondiers ?? [], [planning.data]);
  const rondier = rondiers.find((r) => r.id === agentId);
  const postes = useMemo(() => postesData?.data ?? [], [postesData]);
  const posteParId = useMemo(() => new Map(postes.map((p) => [p.id, p])), [postes]);

  const cle = `${agentId}:${lundi}`;
  const lignesExistantes = useMemo<Ligne[]>(
    () =>
      dates.map((d) => {
        const v = rondier?.vacations.find(
          (x) => x.date_debut === d && x.statut !== "annulee" && x.poste,
        );
        if (!v?.poste) return LIBRE;
        const p = posteParId.get(v.poste.id);
        const nuit = aDesQuarts(p) && hm(v.heure_debut) === hm(p?.heure_debut_nuit);
        return {
          site_id: v.site?.id ?? p?.site_id ?? "",
          poste_id: v.poste.id,
          quart: nuit ? "nuit" : "jour",
        };
      }),
    [dates, rondier, posteParId],
  );
  const lignes = saisies[cle] ?? lignesExistantes;

  const modifier = (index: number, patch: Partial<Ligne>) => {
    setSaisies((prev) => {
      const base = prev[cle] ?? lignesExistantes;
      return {
        ...prev,
        [cle]: base.map((l, i) => (i === index ? { ...l, ...patch } : l)),
      };
    });
  };

  const trousParJour = useMemo(() => {
    const map = new Map<string, TrouRondier[]>();
    for (const t of planning.data?.data.trous ?? []) {
      const list = map.get(t.date) ?? [];
      list.push(t);
      map.set(t.date, list);
    }
    return map;
  }, [planning.data]);

  const siteOptions = useMemo(
    () => [
      { value: "", label: "— Libre —" },
      ...(sitesData?.data ?? []).map((s) => ({ value: s.id, label: s.nom })),
    ],
    [sitesData],
  );

  const rondierOptions = [
    { value: "", label: "Choisissez un rondier" },
    ...rondiers.map((r) => ({
      value: r.id,
      label: `${r.prenom} ${r.nom}${r.matricule ? ` (${r.matricule})` : ""}`,
    })),
  ];

  const nbAffectes = lignes.filter((l, i) => l.poste_id && dates[i] >= aujourdhui).length;

  const enregistrer = async () => {
    if (!agentId) return;
    setErreur(null);
    const identique = (a: Ligne, b: Ligne) =>
      a.poste_id === b.poste_id && (!a.poste_id || a.quart === b.quart);
    // Seuls les jours modifiés sont envoyés : une autre vacation du rondier ce
    // jour-là (non affichée ici, ex. un recouvrement) n’est jamais touchée.
    const jours = dates
      .map((date, i) => ({ date, l: lignes[i], avant: lignesExistantes[i] }))
      .filter(({ date }) => date >= aujourdhui)
      .filter(({ l, avant }) => !identique(l, avant))
      .map(({ date, l }) => {
        const p = posteParId.get(l.poste_id);
        return {
          date,
          poste_id: l.poste_id || null,
          quart: l.poste_id && aDesQuarts(p) ? l.quart : null,
        };
      });
    if (jours.length === 0) {
      setErreur("Aucun changement à enregistrer pour les jours à venir.");
      return;
    }
    try {
      const res = await planifier.mutateAsync({ agent_id: agentId, jours });
      const { creees, retirees, conservees } = res.data;
      setSaisies((prev) => {
        const next = { ...prev };
        delete next[cle];
        return next;
      });
      toast(
        `Semaine enregistrée · ${creees} ajoutée(s), ${retirees} retirée(s), ${conservees} inchangée(s).`,
      );
      router.push("/planning-rondiers");
    } catch (err) {
      const message = getApiErrorMessage(err, "Échec de l’enregistrement de la semaine.");
      setErreur(message);
      toast(message, "danger");
    }
  };

  return (
    <>
      <PageHeader
        title="Planifier un rondier"
        description="Toute la semaine d’un rondier en une fois : un poste par jour, ou libre."
        actions={
          <Link
            href="/planning-rondiers"
            className="inline-flex items-center gap-2 text-sm font-medium text-teal hover:underline"
          >
            <ArrowLeft className="size-4" />
            Planning des rondiers
          </Link>
        }
      />

      <Card className="mb-4">
        <CardBody className="flex flex-wrap items-end gap-4 p-4">
          <div className="w-80">
            <Select
              label="Rondier"
              requiredMark
              options={rondierOptions}
              value={agentId}
              onChange={(e) => {
                setAgentId(e.target.value);
                setErreur(null);
              }}
            />
          </div>
          <div className="flex items-center gap-2">
            <Button
              type="button"
              variant="secondary"
              size="sm"
              onClick={() => setLundi((l) => plusJours(l, -7))}
              aria-label="Semaine précédente"
            >
              <ChevronLeft className="size-4" />
            </Button>
            <div className="min-w-[12rem] text-center">
              <p className="text-sm font-semibold text-ink">
                {format(parseISO(dates[0]), "d MMM", { locale: fr })} –{" "}
                {format(parseISO(dates[6]), "d MMM yyyy", { locale: fr })}
              </p>
              <p className="text-xs text-ink-muted">Semaine du {jourLong(lundi).toLowerCase()}</p>
            </div>
            <Button
              type="button"
              variant="secondary"
              size="sm"
              onClick={() => setLundi((l) => plusJours(l, 7))}
              aria-label="Semaine suivante"
            >
              <ChevronRight className="size-4" />
            </Button>
          </div>
          {planning.isFetching ? <Spinner /> : null}
        </CardBody>
      </Card>

      {rondiers.length === 0 && !planning.isLoading ? (
        <Alert tone="warning">
          Aucun rondier. Donnez le grade « Rondier » aux agents de relève (Personnel → Modifier → Grade).
        </Alert>
      ) : !agentId ? (
        <Alert tone="info">Choisissez un rondier pour remplir sa semaine.</Alert>
      ) : (
        <Card>
          <CardHeader
            title={`Semaine de ${rondier ? `${rondier.prenom} ${rondier.nom}` : "…"}`}
            description="Pour chaque jour : choisissez un trou à combler, ou un site et un poste. Laissez « Libre » pour un jour sans affectation. Les jours passés ne sont pas modifiés."
          />
          <CardBody className="space-y-2 pt-0">
            {dates.map((date, i) => {
              const l = lignes[i] ?? LIBRE;
              const passe = date < aujourdhui;
              const p = posteParId.get(l.poste_id);
              const postesDuSite = postes.filter((x) => x.site_id === l.site_id);
              const trous = (trousParJour.get(date) ?? []).filter(
                (t) => !l.site_id || t.site_id === l.site_id,
              );
              return (
                <div
                  key={date}
                  className={cn(
                    "grid gap-3 rounded-lg border p-3 md:grid-cols-[10rem_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_8rem] md:items-end",
                    l.poste_id ? "border-teal/40 bg-teal/5" : "border-border bg-white",
                    passe && "opacity-60",
                  )}
                >
                  <div>
                    <p className="text-sm font-semibold text-ink">{jourLong(date)}</p>
                    <p className="text-xs text-ink-faint">
                      {passe ? "Passé" : l.poste_id ? "Affecté" : "Libre"}
                      {date === aujourdhui ? " · aujourd’hui" : ""}
                    </p>
                  </div>
                  <Select
                    label="Trou à combler"
                    disabled={passe || trous.length === 0}
                    options={[
                      {
                        value: "",
                        label: trous.length
                          ? `${trous.length} trou(s) — choisir…`
                          : "Aucun trou ce jour",
                      },
                      ...trous.map((t, k) => ({
                        value: String(k),
                        label: `${t.site ?? "Site"} · ${t.poste} · ${t.heure_debut}–${t.heure_fin} · ${t.titulaire ? `${MOTIF[t.motif]} de ${t.titulaire}` : MOTIF[t.motif]}`,
                      })),
                    ]}
                    value=""
                    onChange={(e) => {
                      const t = trous[Number(e.target.value)];
                      if (!t) return;
                      modifier(i, {
                        site_id: t.site_id,
                        poste_id: t.poste_id,
                        quart: t.quart === "nuit" ? "nuit" : "jour",
                      });
                    }}
                  />
                  <Select
                    label="Site"
                    disabled={passe}
                    options={siteOptions}
                    value={l.site_id}
                    onChange={(e) =>
                      modifier(i, { site_id: e.target.value, poste_id: "", quart: "jour" })
                    }
                  />
                  <Select
                    label="Poste"
                    disabled={passe || !l.site_id}
                    options={[
                      { value: "", label: l.site_id ? "Choisir un poste" : "—" },
                      ...postesDuSite.map((x) => ({ value: x.id, label: x.nom })),
                    ]}
                    value={l.poste_id}
                    onChange={(e) => modifier(i, { poste_id: e.target.value, quart: "jour" })}
                  />
                  {aDesQuarts(p) ? (
                    <Select
                      label="Quart"
                      disabled={passe}
                      options={[
                        { value: "jour", label: `Jour ${horairesPoste(p, "jour")}` },
                        { value: "nuit", label: `Nuit ${horairesPoste(p, "nuit")}` },
                      ]}
                      value={l.quart}
                      onChange={(e) => modifier(i, { quart: e.target.value as "jour" | "nuit" })}
                    />
                  ) : (
                    <div className="pb-2 text-xs text-ink-muted">
                      {p ? horairesPoste(p, "jour") || "Horaires du poste" : ""}
                    </div>
                  )}
                </div>
              );
            })}

            {erreur ? <Alert tone="danger">{erreur}</Alert> : null}

            <div className="flex flex-wrap items-center justify-between gap-3 pt-2">
              <p className="text-sm text-ink-muted">
                {nbAffectes} jour{nbAffectes > 1 ? "s" : ""} affecté{nbAffectes > 1 ? "s" : ""} à partir d’aujourd’hui.
                Tout est enregistré ensemble : si un jour est refusé, rien n’est modifié.
              </p>
              <div className="flex gap-2">
                <Button
                  type="button"
                  variant="secondary"
                  disabled={planifier.isPending}
                  onClick={() =>
                    setSaisies((prev) => {
                      const next = { ...prev };
                      delete next[cle];
                      return next;
                    })
                  }
                >
                  Réinitialiser
                </Button>
                <Button
                  type="button"
                  loading={planifier.isPending}
                  onClick={() => void enregistrer()}
                >
                  <CalendarDays className="size-4" />
                  Enregistrer la semaine
                </Button>
              </div>
            </div>
          </CardBody>
        </Card>
      )}
    </>
  );
}

export default function PlanifierRondierPage() {
  return (
    <PermissionGate permission="vacations.create" title="Planifier un rondier">
      <Suspense
        fallback={
          <div className="flex justify-center py-16">
            <Spinner className="size-6" />
          </div>
        }
      >
        <PlanifierRondierForm />
      </Suspense>
    </PermissionGate>
  );
}
