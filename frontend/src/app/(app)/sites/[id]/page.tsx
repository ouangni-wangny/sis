"use client";

import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useMemo, useState } from "react";
import { type ColumnDef } from "@tanstack/react-table";
import {
  ArrowLeft,
  Building2,
  MapPin,
  Navigation,
  Radar,
  UserRound,
  Wallet,
} from "lucide-react";
import {
  useCheckpoints,
  usePostes,
  useSite,
  useVacations,
} from "@/application/hooks/useResources";
import type {
  Checkpoint,
  Poste,
  Vacation,
} from "@/domain/types/entities";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { DataTable } from "@/presentation/components/tables/DataTable";
import { Alert } from "@/presentation/components/ui/Alert";
import { Badge, statusTone } from "@/presentation/components/ui/Badge";
import { Button } from "@/presentation/components/ui/Button";
import { Spinner } from "@/presentation/components/ui/Spinner";
import { TabPanel, Tabs } from "@/presentation/components/ui/Tabs";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { WeekGrid } from "../../vacations/WeekGrid";
import { can } from "@/shared/lib/can";
import {
  formatDate,
  formatFcfa,
  labelize,
} from "@/shared/lib/format";

type SiteTab = "overview" | "postes" | "checkpoints" | "planning";
type PlanningSubTab = "grille" | "liste";

const VACATION_STATUT_LABEL: Record<string, string> = {
  planifiee: "Planifiée",
  en_cours: "En cours",
  terminee: "Terminée",
  annulee: "Annulée",
  a_recouvrir: "À recouvrir",
};

function MetaCard({
  icon: Icon,
  label,
  value,
  hint,
}: {
  icon: typeof MapPin;
  label: string;
  value: string;
  hint?: string;
}) {
  return (
    <div className="rounded-xl border border-border bg-white p-4 shadow-[0_1px_0_rgba(20,26,16,0.04)]">
      <div className="flex items-center gap-2 text-ink-faint">
        <Icon className="size-4 shrink-0" />
        <span className="text-[11px] font-medium uppercase tracking-[0.12em]">
          {label}
        </span>
      </div>
      <p className="mt-2 text-base font-semibold tracking-tight text-ink">
        {value}
      </p>
      {hint ? <p className="mt-0.5 text-xs text-ink-muted">{hint}</p> : null}
    </div>
  );
}

function InfoRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between gap-4 py-2 text-sm">
      <span className="text-ink-muted">{label}</span>
      <span className="text-right font-medium text-ink">{value}</span>
    </div>
  );
}

function tablePagination(
  page: number,
  perPage: number,
  total: number,
  setPage: (p: number) => void,
  setPerPage: (n: number) => void,
) {
  return {
    page,
    perPage,
    total,
    onPageChange: setPage,
    onPerPageChange: (n: number) => {
      setPerPage(n);
      setPage(1);
    },
  };
}

function formatPosteHours(poste: Poste) {
  const jour =
    poste.heure_debut && poste.heure_fin
      ? `${poste.heure_debut.slice(0, 5)}–${poste.heure_fin.slice(0, 5)}`
      : null;
  const nuit =
    poste.heure_debut_nuit && poste.heure_fin_nuit
      ? `${poste.heure_debut_nuit.slice(0, 5)}–${poste.heure_fin_nuit.slice(0, 5)}`
      : null;
  if (jour && nuit) return `Jour ${jour} · Nuit ${nuit}`;
  if (jour) return jour;
  if (nuit) return `Nuit ${nuit}`;
  return "—";
}

export default function SiteDetailPage() {
  const params = useParams();
  const router = useRouter();
  const id = params.id as string;
  const { user } = useAuth();
  const [tab, setTab] = useState<SiteTab>("overview");
  const [planningSubTab, setPlanningSubTab] =
    useState<PlanningSubTab>("grille");
  const [vacationsPage, setVacationsPage] = useState(1);
  const [vacationsPerPage, setVacationsPerPage] = useState(15);

  const canUpdate = can(user, "sites.update");
  const canVacations = can(user, "vacations.view");
  const canPlanifier = can(user, "vacations.create");
  const canUpdateVacation = can(user, "vacations.update");

  const { data, isLoading, isError } = useSite(id);
  const site = data?.data;

  const { data: postesData, isLoading: postesLoading } = usePostes(
    { site_id: id, all: true },
    { enabled: (tab === "postes" || tab === "overview") && !!id },
  );
  const { data: checkpointsData, isLoading: checkpointsLoading } =
    useCheckpoints(tab === "checkpoints" ? id : undefined);
  const { data: vacationsData, isLoading: vacationsLoading } = useVacations(
    {
      site_id: id,
      page: vacationsPage,
      per_page: vacationsPerPage,
    },
    {
      enabled:
        tab === "planning" &&
        planningSubTab === "liste" &&
        !!id &&
        canVacations,
    },
  );

  const postes = postesData?.data ?? site?.postes ?? [];
  const checkpoints = checkpointsData?.data ?? site?.checkpoints ?? [];

  const siteOptions = useMemo(
    () => (site ? [{ value: site.id, label: site.nom }] : []),
    [site],
  );

  const posteColumns = useMemo<ColumnDef<Poste>[]>(
    () => [
      {
        accessorKey: "nom",
        header: "Poste",
        cell: ({ getValue }) => (
          <span className="font-medium text-ink">{String(getValue())}</span>
        ),
      },
      {
        accessorKey: "agents_requis",
        header: "Effectif",
        cell: ({ getValue }) => String(getValue() ?? "—"),
      },
      {
        id: "horaires",
        header: "Horaires",
        cell: ({ row }) => (
          <span className="font-mono text-xs">{formatPosteHours(row.original)}</span>
        ),
      },
      {
        id: "actions",
        header: "",
        enableSorting: false,
        cell: ({ row }) =>
          canPlanifier ? (
            <Link
              href={`/vacations/planifier?site_id=${id}&poste_id=${row.original.id}`}
            >
              <Button size="sm" variant="secondary">
                Planifier
              </Button>
            </Link>
          ) : null,
      },
    ],
    [canPlanifier, id],
  );

  const checkpointColumns = useMemo<ColumnDef<Checkpoint>[]>(
    () => [
      {
        accessorKey: "nom",
        header: "Checkpoint",
        cell: ({ getValue }) => (
          <span className="font-medium text-ink">{String(getValue())}</span>
        ),
      },
      {
        accessorKey: "ordre",
        header: "Ordre",
        cell: ({ getValue }) => {
          const v = getValue() as number | null;
          return v != null ? String(v) : "—";
        },
      },
      {
        accessorKey: "code_qr",
        header: "Code QR",
        cell: ({ getValue }) => (getValue() as string | null) || "—",
      },
      {
        id: "coords",
        header: "GPS",
        cell: ({ row }) => {
          const { latitude, longitude } = row.original;
          if (latitude == null || longitude == null) return "—";
          return (
            <span className="font-mono text-xs">
              {latitude.toFixed(5)}, {longitude.toFixed(5)}
            </span>
          );
        },
      },
    ],
    [],
  );

  const vacationColumns = useMemo<ColumnDef<Vacation>[]>(
    () => [
      {
        id: "agent",
        header: "Agent",
        cell: ({ row }) =>
          row.original.agent
            ? `${row.original.agent.prenom} ${row.original.agent.nom}`
            : "—",
      },
      {
        id: "poste",
        header: "Poste",
        cell: ({ row }) => row.original.poste?.nom ?? "—",
      },
      {
        accessorKey: "date_debut",
        header: "Du",
        cell: ({ getValue }) => formatDate(String(getValue())),
      },
      {
        accessorKey: "date_fin",
        header: "Au",
        cell: ({ getValue }) =>
          formatDate(getValue() as string | null | undefined),
      },
      {
        id: "horaires",
        header: "Horaires",
        cell: ({ row }) => (
          <span className="font-mono text-xs">
            {row.original.heure_debut?.slice(0, 5)} –{" "}
            {row.original.heure_fin?.slice(0, 5)}
          </span>
        ),
      },
      {
        accessorKey: "statut",
        header: "Statut",
        cell: ({ getValue }) => {
          const v = String(getValue());
          return (
            <Badge tone={statusTone(v)}>
              {VACATION_STATUT_LABEL[v] ?? labelize(v)}
            </Badge>
          );
        },
      },
    ],
    [],
  );

  const tabItems = useMemo(
    () =>
      [
        { id: "overview", label: "Vue d’ensemble" },
        { id: "postes", label: "Postes", count: postes.length || undefined },
        {
          id: "checkpoints",
          label: "Checkpoints",
          count: checkpoints.length || undefined,
        },
        canVacations ? { id: "planning", label: "Planning" } : null,
      ].filter(Boolean) as Array<{
        id: SiteTab;
        label: string;
        count?: number;
      }>,
    [canVacations, checkpoints.length, postes.length],
  );

  return (
    <PermissionGate permission="sites.view" title="Site">
      <div className="mx-auto max-w-5xl">
        <Link
          href="/sites"
          className="mb-6 inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-teal"
        >
          <ArrowLeft className="size-4" />
          Sites
        </Link>

        {isLoading ? (
          <div className="flex justify-center py-24">
            <Spinner className="size-8" />
          </div>
        ) : isError || !site ? (
          <Alert tone="danger" title="Site introuvable">
            Ce site n&apos;existe pas ou n&apos;est plus accessible.
          </Alert>
        ) : (
          <div className="animate-fade-in space-y-6">
            <header className="relative overflow-hidden rounded-2xl border border-border bg-ink text-white">
              <div
                className="pointer-events-none absolute inset-0 opacity-[0.12]"
                style={{
                  backgroundImage:
                    "radial-gradient(circle at 1px 1px, rgba(255,255,255,0.55) 1px, transparent 0)",
                  backgroundSize: "18px 18px",
                }}
              />
              <div className="pointer-events-none absolute -right-16 -top-20 size-64 rounded-full bg-teal-light/30 blur-3xl" />
              <div className="relative px-6 py-7 sm:px-8 sm:py-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                  <div className="min-w-0">
                    <p className="text-[11px] font-medium uppercase tracking-[0.16em] text-white/55">
                      Fiche site
                      {site.zone?.nom ? ` · ${site.zone.nom}` : ""}
                    </p>
                    <h1 className="mt-2 max-w-3xl text-2xl font-semibold tracking-tight sm:text-3xl">
                      {site.nom}
                    </h1>
                    {site.client?.raison_sociale ? (
                      <p className="mt-3 inline-flex items-center gap-2 text-sm text-white/80">
                        <Building2 className="size-4" />
                        <Link
                          href={`/clients/${site.client_id}`}
                          className="hover:underline"
                        >
                          {site.client.raison_sociale}
                        </Link>
                      </p>
                    ) : null}
                  </div>
                  <div className="flex flex-wrap justify-end gap-2">
                    {canUpdate ? (
                      <Link href="/sites">
                        <Button size="sm" variant="secondary">
                          Modifier
                        </Button>
                      </Link>
                    ) : null}
                    {canPlanifier ? (
                      <Link href={`/vacations/planifier?site_id=${id}`}>
                        <Button size="sm">Planifier</Button>
                      </Link>
                    ) : null}
                  </div>
                </div>
              </div>
            </header>

            <Tabs
              items={tabItems}
              value={tab}
              onChange={(next) => setTab(next as SiteTab)}
            />

            <TabPanel when="overview" active={tab}>
              <div className="space-y-8">
                <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                  <MetaCard
                    icon={Building2}
                    label="Client"
                    value={site.client?.raison_sociale ?? "—"}
                  />
                  <MetaCard
                    icon={Radar}
                    label="Zone"
                    value={site.zone?.nom ?? "—"}
                  />
                  <MetaCard
                    icon={UserRound}
                    label="Responsable"
                    value={site.responsable ?? "—"}
                  />
                  <MetaCard
                    icon={Wallet}
                    label="Tarif mensuel"
                    value={formatFcfa(site.tarif_mensuel)}
                  />
                </section>

                <div className="grid gap-6 lg:grid-cols-2">
                  <section className="rounded-2xl border border-border bg-white p-6 shadow-[0_1px_0_rgba(20,26,16,0.04)]">
                    <div className="mb-4 flex items-center gap-2">
                      <span className="flex size-8 items-center justify-center rounded-lg bg-teal/10 text-teal">
                        <MapPin className="size-4" />
                      </span>
                      <h2 className="text-base font-semibold text-ink">
                        Localisation
                      </h2>
                    </div>
                    <div className="divide-y divide-border/70">
                      <InfoRow label="Adresse" value={site.adresse ?? "—"} />
                      <InfoRow
                        label="Type"
                        value={
                          site.interne ? "Site interne (siège)" : "Site client"
                        }
                      />
                      <InfoRow
                        label="Latitude"
                        value={
                          site.latitude != null ? String(site.latitude) : "—"
                        }
                      />
                      <InfoRow
                        label="Longitude"
                        value={
                          site.longitude != null ? String(site.longitude) : "—"
                        }
                      />
                      <InfoRow
                        label="Rayon géofence"
                        value={
                          site.rayon_metres != null
                            ? `${site.rayon_metres} m`
                            : "—"
                        }
                      />
                    </div>
                    {site.latitude != null && site.longitude != null ? (
                      <a
                        href={`https://www.openstreetmap.org/?mlat=${site.latitude}&mlon=${site.longitude}#map=17/${site.latitude}/${site.longitude}`}
                        target="_blank"
                        rel="noreferrer"
                        className="mt-4 inline-flex items-center gap-1.5 text-xs font-medium text-teal hover:underline"
                      >
                        <Navigation className="size-3.5" />
                        Voir sur la carte
                      </a>
                    ) : null}
                  </section>

                  <section className="rounded-2xl border border-border bg-white p-6 shadow-[0_1px_0_rgba(20,26,16,0.04)]">
                    <div className="mb-4 flex items-center justify-between gap-2">
                      <div className="flex items-center gap-2">
                        <span className="flex size-8 items-center justify-center rounded-lg bg-brand-accent/10 text-brand-accent">
                          <Radar className="size-4" />
                        </span>
                        <h2 className="text-base font-semibold text-ink">
                          Couverture
                        </h2>
                      </div>
                      <button
                        type="button"
                        className="text-xs font-medium text-teal hover:underline"
                        onClick={() => setTab("postes")}
                      >
                        Voir les postes
                      </button>
                    </div>
                    {postesLoading ? (
                      <div className="flex justify-center py-8">
                        <Spinner className="size-6" />
                      </div>
                    ) : postes.length === 0 ? (
                      <p className="text-sm text-ink-faint">
                        Aucun poste configuré sur ce site.
                      </p>
                    ) : (
                      <ul className="space-y-2">
                        {postes.slice(0, 5).map((poste) => (
                          <li
                            key={poste.id}
                            className="rounded-xl border border-border/80 bg-paper/60 px-4 py-3"
                          >
                            <div className="flex items-start justify-between gap-3">
                              <div>
                                <p className="text-sm font-medium text-ink">
                                  {poste.nom}
                                </p>
                                <p className="text-xs text-ink-muted">
                                  {poste.agents_requis} agent
                                  {poste.agents_requis > 1 ? "s" : ""} ·{" "}
                                  {formatPosteHours(poste)}
                                </p>
                              </div>
                            </div>
                          </li>
                        ))}
                        {postes.length > 5 ? (
                          <p className="pt-1 text-xs text-ink-faint">
                            +{postes.length - 5} autre
                            {postes.length - 5 > 1 ? "s" : ""} poste
                            {postes.length - 5 > 1 ? "s" : ""}
                          </p>
                        ) : null}
                      </ul>
                    )}
                  </section>
                </div>
              </div>
            </TabPanel>

            <TabPanel when="postes" active={tab}>
              <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm text-ink-muted">
                  Postes de surveillance du site.
                </p>
                {canUpdate ? (
                  <Link href="/sites">
                    <Button size="sm" variant="secondary">
                      Gérer dans Sites
                    </Button>
                  </Link>
                ) : null}
              </div>
              <DataTable
                data={postes}
                columns={posteColumns}
                isLoading={postesLoading}
                emptyTitle="Aucun poste sur ce site"
                selectable={false}
              />
            </TabPanel>

            <TabPanel when="checkpoints" active={tab}>
              <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm text-ink-muted">
                  Points de contrôle pour les rondes.
                </p>
                {can(user, "checkpoints.manage") ? (
                  <Link href="/sites">
                    <Button size="sm" variant="secondary">
                      Gérer dans Sites
                    </Button>
                  </Link>
                ) : null}
              </div>
              <DataTable
                data={checkpoints}
                columns={checkpointColumns}
                isLoading={checkpointsLoading}
                emptyTitle="Aucun checkpoint sur ce site"
                selectable={false}
              />
            </TabPanel>

            <TabPanel when="planning" active={tab}>
              <div className="space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <p className="text-sm text-ink-muted">
                    Affectations planifiées sur ce site.
                  </p>
                  <div className="flex flex-wrap gap-2">
                    <Link href={`/vacations?site_id=${id}`}>
                      <Button size="sm" variant="secondary">
                        Ouvrir Vacations
                      </Button>
                    </Link>
                    {canPlanifier ? (
                      <Link href={`/vacations/planifier?site_id=${id}`}>
                        <Button size="sm">Planifier</Button>
                      </Link>
                    ) : null}
                  </div>
                </div>

                <Tabs
                  items={[
                    { id: "grille", label: "Grille" },
                    { id: "liste", label: "Liste" },
                  ]}
                  value={planningSubTab}
                  onChange={(next) =>
                    setPlanningSubTab(next as PlanningSubTab)
                  }
                />

                <TabPanel when="grille" active={planningSubTab} className="pt-2">
                  <WeekGrid
                    siteId={id}
                    siteOptions={siteOptions}
                    onSiteChange={() => undefined}
                    onCreateFor={(ctx) => {
                      if (!canPlanifier) return;
                      router.push(
                        `/vacations/planifier?site_id=${ctx.siteId}&poste_id=${ctx.posteId}&date_debut=${ctx.date}`,
                      );
                    }}
                    onEditVacation={(vacation) => {
                      if (!canUpdateVacation) return;
                      router.push(`/vacations?agent_id=${vacation.agent_id}`);
                    }}
                  />
                </TabPanel>

                <TabPanel when="liste" active={planningSubTab}>
                  <DataTable
                    data={vacationsData?.data ?? []}
                    columns={vacationColumns}
                    isLoading={vacationsLoading}
                    pagination={tablePagination(
                      vacationsPage,
                      vacationsData?.meta.per_page ?? vacationsPerPage,
                      vacationsData?.meta.total ?? 0,
                      setVacationsPage,
                      setVacationsPerPage,
                    )}
                    emptyTitle="Aucune vacation sur ce site"
                    emptyAction={
                      canPlanifier ? (
                        <Link href={`/vacations/planifier?site_id=${id}`}>
                          <Button size="sm">Planifier sur ce site</Button>
                        </Link>
                      ) : undefined
                    }
                  />
                </TabPanel>
              </div>
            </TabPanel>
          </div>
        )}
      </div>
    </PermissionGate>
  );
}
