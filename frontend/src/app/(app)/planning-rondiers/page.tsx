"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import { format, parseISO } from "date-fns";
import { fr } from "date-fns/locale";
import {
  ChevronLeft,
  ChevronRight,
  Moon,
  Pencil,
  Plus,
  Sun,
  UserPlus,
  X,
} from "lucide-react";
import {
  useAffecterRondier,
  usePlanningRondiers,
  useRecouvrirVacation,
  useRetirerRondier,
  useSites,
} from "@/application/hooks/useResources";
import { useZones } from "@/application/hooks/useZones";
import type {
  RondierSemaine,
  RondierVacation,
  TrouRondier,
} from "@/domain/types/entities";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { Alert } from "@/presentation/components/ui/Alert";
import { Badge } from "@/presentation/components/ui/Badge";
import { Button } from "@/presentation/components/ui/Button";
import { Card, CardBody, CardHeader } from "@/presentation/components/ui/Card";
import { ConfirmDialog } from "@/presentation/components/ui/ConfirmDialog";
import { Input } from "@/presentation/components/ui/Input";
import { Modal } from "@/presentation/components/ui/Modal";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { Select } from "@/presentation/components/ui/Select";
import { Spinner } from "@/presentation/components/ui/Spinner";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { useToast } from "@/presentation/providers/ToastProvider";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { can } from "@/shared/lib/can";
import { cn } from "@/shared/lib/cn";

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

function jourCourt(iso: string) {
  return format(parseISO(iso), "EEE d", { locale: fr });
}

function jourLong(iso: string) {
  const s = format(parseISO(iso), "EEEE d MMMM", { locale: fr });
  return s.charAt(0).toUpperCase() + s.slice(1);
}

function estNuit(debut: string, fin: string) {
  return fin <= debut;
}

const MOTIF: Record<TrouRondier["motif"], { label: string; tone: "warning" | "danger" | "neutral" }> = {
  repos: { label: "Repos", tone: "warning" },
  absence: { label: "Absence", tone: "danger" },
  vide: { label: "Place vide", tone: "neutral" },
};

function libelleTrou(t: TrouRondier) {
  if (t.motif === "repos" && t.titulaire) return `Repos de ${t.titulaire}`;
  if (t.motif === "absence" && t.titulaire) return `Absence de ${t.titulaire}`;
  return MOTIF[t.motif].label;
}

export default function PlanningRondiersPage() {
  const { user } = useAuth();
  const { toast } = useToast();
  const canCreate = can(user, "vacations.create");
  const canDelete = can(user, "vacations.delete");

  const aujourdhui = isoLocal(new Date());
  const [lundi, setLundi] = useState(() => lundiDe(aujourdhui));
  const [zoneId, setZoneId] = useState("");
  const [siteId, setSiteId] = useState("");
  const [recherche, setRecherche] = useState("");
  const [rechercheTrou, setRechercheTrou] = useState("");
  const [trouChoisi, setTrouChoisi] = useState<TrouRondier | null>(null);
  const [aRetirer, setARetirer] = useState<{
    vacation: RondierVacation;
    rondier: RondierSemaine;
  } | null>(null);

  const dates = useMemo(
    () => Array.from({ length: 7 }, (_, i) => plusJours(lundi, i)),
    [lundi],
  );

  const planning = usePlanningRondiers({
    lundi,
    zone_id: zoneId || undefined,
    site_id: siteId || undefined,
  });
  const { data: zonesData } = useZones({ all: true });
  const { data: sitesData } = useSites({ all: true });
  const affecter = useAffecterRondier();
  const recouvrir = useRecouvrirVacation();
  const retirer = useRetirerRondier();

  const zoneOptions = useMemo(
    () => [
      { value: "", label: "Toutes les zones" },
      ...(zonesData?.data ?? []).map((z) => ({ value: z.id, label: z.nom })),
    ],
    [zonesData],
  );
  const siteOptions = useMemo(
    () => [
      { value: "", label: "Tous les sites" },
      ...(sitesData?.data ?? [])
        .filter((s) => !zoneId || s.zone_id === zoneId)
        .map((s) => ({ value: s.id, label: s.nom })),
    ],
    [sitesData, zoneId],
  );

  const donnees = planning.data?.data;
  const rondiers = useMemo(() => {
    const mots = recherche.toLowerCase().split(/\s+/).filter(Boolean);
    return (donnees?.rondiers ?? []).filter((r) => {
      const texte = `${r.prenom} ${r.nom} ${r.matricule ?? ""}`.toLowerCase();
      return mots.every((m) => texte.includes(m));
    });
  }, [donnees, recherche]);

  // Trous d’aujourd’hui et des jours à venir uniquement, groupés par jour.
  const trousParJour = useMemo(() => {
    const mots = rechercheTrou.toLowerCase().split(/\s+/).filter(Boolean);
    const map = new Map<string, TrouRondier[]>();
    for (const t of donnees?.trous ?? []) {
      if (t.date < aujourdhui) continue;
      const texte = `${t.site ?? ""} ${t.poste} ${t.titulaire ?? ""}`.toLowerCase();
      if (!mots.every((m) => texte.includes(m))) continue;
      const list = map.get(t.date) ?? [];
      list.push(t);
      map.set(t.date, list);
    }
    return map;
  }, [donnees, aujourdhui, rechercheTrou]);
  const nbTrous = [...trousParJour.values()].reduce((n, l) => n + l.length, 0);

  const vacationsDuJour = (r: RondierSemaine, date: string) =>
    r.vacations.filter((v) => v.date_debut === date);

  const placer = async (trou: TrouRondier, rondier: RondierSemaine) => {
    try {
      if (trou.motif === "absence" && trou.vacation_id) {
        await recouvrir.mutateAsync({ id: trou.vacation_id, agent_id: rondier.id });
      } else {
        await affecter.mutateAsync({
          agent_id: rondier.id,
          poste_id: trou.poste_id,
          date: trou.date,
          quart: trou.quart,
        });
      }
      toast(
        `${rondier.prenom} ${rondier.nom} placé sur ${trou.poste} (${trou.site ?? "site"}) le ${jourLong(trou.date).toLowerCase()}.`,
      );
      setTrouChoisi(null);
    } catch (err) {
      toast(getApiErrorMessage(err, "Impossible de placer ce rondier."), "danger");
    }
  };

  const enCours = affecter.isPending || recouvrir.isPending;

  return (
    <PermissionGate permission="vacations.view" title="Planning des rondiers">
      <PageHeader
        title="Planning des rondiers"
        description="Agents de relève : placez-les sur les repos et les absences des titulaires, sur tous les sites."
        actions={
          canCreate ? (
            <Link
              href={`/planning-rondiers/planifier?lundi=${lundi}`}
              className="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-teal px-4 text-sm font-medium text-white shadow-sm transition-colors hover:bg-teal-dark"
            >
              <Plus className="size-4" />
              Planifier un rondier
            </Link>
          ) : undefined
        }
      />

      <div className="mb-4 flex flex-wrap items-end gap-3">
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
          <Button
            type="button"
            variant="secondary"
            size="sm"
            onClick={() => setLundi(lundiDe(aujourdhui))}
          >
            Aujourd’hui
          </Button>
        </div>
        <div className="w-48">
          <Select
            aria-label="Zone"
            options={zoneOptions}
            value={zoneId}
            onChange={(e) => {
              setZoneId(e.target.value);
              setSiteId("");
            }}
          />
        </div>
        <div className="w-56">
          <Select
            aria-label="Site"
            options={siteOptions}
            value={siteId}
            onChange={(e) => setSiteId(e.target.value)}
          />
        </div>
        <div className="w-60">
          <Input
            aria-label="Rechercher un rondier"
            placeholder="Rechercher un rondier…"
            value={recherche}
            onChange={(e) => setRecherche(e.target.value)}
          />
        </div>
        {planning.isFetching ? <Spinner /> : null}
      </div>

      {planning.error ? (
        <Alert tone="danger">
          {getApiErrorMessage(planning.error, "Impossible de charger le planning des rondiers.")}
        </Alert>
      ) : null}

      <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <Card className="min-w-0">
          <CardHeader
            title="Semaine des rondiers"
            description="Une ligne par rondier : où il tient un poste chaque jour."
          />
          <CardBody className="pt-0">
            {planning.isLoading ? (
              <p className="py-10 text-center text-sm text-ink-muted">Chargement…</p>
            ) : (donnees?.rondiers.length ?? 0) === 0 ? (
              <div className="rounded-lg border border-dashed border-border p-6 text-center text-sm text-ink-muted">
                <p className="font-medium text-ink">Aucun rondier pour le moment.</p>
                <p className="mt-1">
                  Donnez le grade <b>Rondier</b> aux agents de relève (Personnel →
                  Modifier → Grade). Le grade se règle dans{" "}
                  <Link href="/parametres" className="font-medium text-teal underline">
                    Paramètres → Grades
                  </Link>
                  .
                </p>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full min-w-[56rem] border-separate border-spacing-1 text-xs">
                  <thead>
                    <tr>
                      <th className="sticky left-0 z-10 w-44 bg-white px-2 py-2 text-left text-[10px] font-bold uppercase tracking-wider text-ink-muted">
                        Rondier
                      </th>
                      {dates.map((d) => (
                        <th
                          key={d}
                          className={cn(
                            "rounded-md px-1 py-2 text-center text-[11px] font-semibold capitalize text-ink",
                            d === aujourdhui ? "bg-teal/10" : "bg-paper-muted",
                          )}
                        >
                          {jourCourt(d)}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {rondiers.map((r) => (
                      <tr key={r.id}>
                        <td className="sticky left-0 z-10 bg-white px-2 py-2 align-top">
                          <Link
                            href={`/agents/${r.id}`}
                            className="font-semibold text-ink hover:underline"
                          >
                            {r.prenom} {r.nom}
                          </Link>
                          <p className="text-[10px] text-ink-faint">
                            {r.matricule ?? "—"} ·{" "}
                            {r.vacations.filter((v) => dates.includes(v.date_debut)).length} vacation(s)
                          </p>
                          {canCreate ? (
                            <Link
                              href={`/planning-rondiers/planifier?agent_id=${r.id}&lundi=${lundi}`}
                              className="mt-1 inline-flex items-center gap-1 rounded border border-teal px-1.5 py-0.5 text-[10px] font-semibold text-teal hover:bg-teal hover:text-white"
                            >
                              <Pencil className="size-3" />
                              Planifier la semaine
                            </Link>
                          ) : null}
                        </td>
                        {dates.map((d) => {
                          const vacs = vacationsDuJour(r, d);
                          return (
                            <td
                              key={d}
                              className={cn(
                                "min-w-[7.5rem] rounded-md border p-1 align-top",
                                vacs.length > 0
                                  ? "border-teal/30 bg-teal/5"
                                  : "border-dashed border-border bg-white",
                                d < aujourdhui && "opacity-60",
                              )}
                            >
                              {vacs.length === 0 ? (
                                <span className="block py-2 text-center text-[10px] text-ink-faint">
                                  Libre
                                </span>
                              ) : (
                                <div className="flex flex-col gap-1">
                                  {vacs.map((v) => {
                                    const nuit = estNuit(v.heure_debut, v.heure_fin);
                                    const retirable =
                                      canDelete &&
                                      d >= aujourdhui &&
                                      v.statut === "planifiee";
                                    return (
                                      <div
                                        key={v.id}
                                        className="rounded border border-border bg-white px-1.5 py-1"
                                        title={`${v.site?.nom ?? ""} · ${v.poste?.nom ?? ""} · ${v.heure_debut}–${v.heure_fin}`}
                                      >
                                        <div className="flex items-start justify-between gap-1">
                                          <span className="font-semibold leading-tight text-ink">
                                            {v.site?.nom ?? "Site"}
                                          </span>
                                          {retirable ? (
                                            <button
                                              type="button"
                                              onClick={() => setARetirer({ vacation: v, rondier: r })}
                                              className="rounded p-0.5 text-ink-faint hover:bg-danger/10 hover:text-danger"
                                              aria-label="Retirer ce rondier du poste"
                                              title="Retirer (erreur de planification)"
                                            >
                                              <X className="size-3" />
                                            </button>
                                          ) : null}
                                        </div>
                                        <p className="leading-tight text-ink-muted">{v.poste?.nom ?? "—"}</p>
                                        <p className="mt-0.5 flex items-center gap-1 text-[10px] text-ink-faint">
                                          {nuit ? <Moon className="size-3" /> : <Sun className="size-3" />}
                                          {v.heure_debut}–{v.heure_fin}
                                        </p>
                                        {v.statut === "a_recouvrir" ? (
                                          <span className="text-[10px] font-semibold text-danger">Absent</span>
                                        ) : null}
                                      </div>
                                    );
                                  })}
                                </div>
                              )}
                            </td>
                          );
                        })}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </CardBody>
        </Card>

        <Card className="min-w-0 xl:sticky xl:top-6 xl:self-start">
          <CardHeader
            title={`Trous à combler · ${nbTrous}`}
            description="Repos des titulaires, absences et places vides, à partir d’aujourd’hui."
          />
          <div className="px-5 py-3">
            <Input
              aria-label="Rechercher un site"
              placeholder="Rechercher un site, un poste ou un titulaire…"
              value={rechercheTrou}
              onChange={(e) => setRechercheTrou(e.target.value)}
            />
          </div>
          <CardBody className="max-h-[70vh] space-y-4 overflow-y-auto pt-0">
            {planning.isLoading ? (
              <p className="py-6 text-center text-sm text-ink-muted">Chargement…</p>
            ) : nbTrous === 0 ? (
              <p className="py-6 text-center text-sm text-ink-muted">
                {rechercheTrou
                  ? `Aucun trou pour « ${rechercheTrou} ».`
                  : "Aucun trou sur la période affichée. Tous les postes sont tenus."}
              </p>
            ) : (
              [...trousParJour.entries()].map(([date, trous]) => (
                <div key={date}>
                  <p className="mb-1.5 text-xs font-semibold uppercase tracking-wide text-ink-muted">
                    {jourLong(date)}
                    {date === aujourdhui ? " · aujourd’hui" : ""}
                  </p>
                  <ul className="space-y-1.5">
                    {trous.map((t, i) => (
                      <li
                        key={`${t.poste_id}-${t.quart ?? "s"}-${t.vacation_id ?? i}`}
                        className="rounded-md border border-border bg-white p-2"
                      >
                        <div className="flex items-start justify-between gap-2">
                          <div className="min-w-0">
                            <p className="truncate text-sm font-semibold text-ink">{t.poste}</p>
                            <p className="truncate text-xs text-ink-muted">{t.site ?? "—"}</p>
                            <p className="mt-0.5 flex items-center gap-1 text-[11px] text-ink-faint">
                              {t.quart === "nuit" || estNuit(t.heure_debut, t.heure_fin) ? (
                                <Moon className="size-3" />
                              ) : (
                                <Sun className="size-3" />
                              )}
                              {t.heure_debut}–{t.heure_fin}
                            </p>
                          </div>
                          <Badge tone={MOTIF[t.motif].tone}>{libelleTrou(t)}</Badge>
                        </div>
                        {canCreate ? (
                          <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            className="mt-2 w-full"
                            onClick={() => setTrouChoisi(t)}
                          >
                            <UserPlus className="size-4" />
                            Placer un rondier
                          </Button>
                        ) : null}
                      </li>
                    ))}
                  </ul>
                </div>
              ))
            )}
          </CardBody>
        </Card>
      </div>

      <Modal
        open={!!trouChoisi}
        onClose={() => {
          if (enCours) return;
          setTrouChoisi(null);
        }}
        preventClose={enCours}
        title="Placer un rondier"
        description={
          trouChoisi
            ? `${trouChoisi.poste} · ${trouChoisi.site ?? ""} · ${jourLong(trouChoisi.date)} · ${trouChoisi.heure_debut}–${trouChoisi.heure_fin} — ${libelleTrou(trouChoisi)}`
            : undefined
        }
        size="lg"
      >
        {trouChoisi ? (
          (donnees?.rondiers.length ?? 0) === 0 ? (
            <p className="text-sm text-ink-muted">Aucun rondier disponible.</p>
          ) : (
            <ul className="divide-y divide-border">
              {[...(donnees?.rondiers ?? [])]
                .sort((a, b) => vacationsDuJour(a, trouChoisi.date).length - vacationsDuJour(b, trouChoisi.date).length)
                .map((r) => {
                  const deja = vacationsDuJour(r, trouChoisi.date);
                  return (
                    <li key={r.id} className="flex items-center justify-between gap-3 py-2">
                      <div className="min-w-0">
                        <p className="text-sm font-medium text-ink">
                          {r.prenom} {r.nom}
                          <span className="ml-1 text-xs font-normal text-ink-faint">{r.matricule}</span>
                        </p>
                        {deja.length === 0 ? (
                          <p className="text-xs font-medium text-teal">Libre ce jour</p>
                        ) : (
                          <p className="text-xs text-warning">
                            Déjà :{" "}
                            {deja
                              .map((v) => `${v.site?.nom ?? "site"} ${v.heure_debut}–${v.heure_fin}`)
                              .join(", ")}
                          </p>
                        )}
                      </div>
                      <Button
                        type="button"
                        size="sm"
                        variant={deja.length === 0 ? "primary" : "secondary"}
                        loading={enCours}
                        onClick={() => void placer(trouChoisi, r)}
                      >
                        Placer
                      </Button>
                    </li>
                  );
                })}
            </ul>
          )
        ) : null}
      </Modal>

      <ConfirmDialog
        open={!!aRetirer}
        onClose={() => {
          if (retirer.isPending) return;
          setARetirer(null);
        }}
        loading={retirer.isPending}
        title="Retirer le rondier"
        description={
          aRetirer
            ? `${aRetirer.rondier.prenom} ${aRetirer.rondier.nom} ne tiendra plus ${aRetirer.vacation.poste?.nom ?? "ce poste"} (${aRetirer.vacation.site?.nom ?? ""}) le ${jourLong(aRetirer.vacation.date_debut).toLowerCase()}. La place redeviendra un trou à combler.`
            : ""
        }
        confirmLabel="Retirer"
        onConfirm={async () => {
          if (!aRetirer) return;
          try {
            await retirer.mutateAsync(aRetirer.vacation.id);
            toast("Rondier retiré du poste.");
            setARetirer(null);
          } catch (err) {
            toast(getApiErrorMessage(err, "Impossible de retirer ce rondier."), "danger");
          }
        }}
      />
    </PermissionGate>
  );
}
