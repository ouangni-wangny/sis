"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useCallback, useMemo, useRef, useState } from "react";
import { type ColumnDef } from "@tanstack/react-table";
import {
  ArrowLeft,
  Briefcase,
  CalendarClock,
  CalendarRange,
  IdCard,
  MapPin,
  Phone,
  Route,
  ShieldCheck,
  Smartphone,
  Upload,
  User,
  Wallet,
} from "lucide-react";
import {
  useAbsences,
  useAccorderConges,
  useAgent,
  useAgentBulletins,
  useCongesMouvements,
  useControles,
  useContrats,
  useSites,
  useUploadAgentDocument,
  useVacations,
} from "@/application/hooks/useResources";
import { weekdayIndex } from "@/domain/schemas/agent-planning";
import {
  formatAbsenceDuration,
  labelSourceAbsence,
  labelTypeAbsence,
  sourceAbsenceTone,
} from "@/domain/schemas/absence";
import type {
  Absence,
  BulletinPaie,
  Contrat,
  SoldeCongesMouvement,
  Vacation,
} from "@/domain/types/entities";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { DataTable } from "@/presentation/components/tables/DataTable";
import { Alert } from "@/presentation/components/ui/Alert";
import { Badge, statusTone } from "@/presentation/components/ui/Badge";
import { Button } from "@/presentation/components/ui/Button";
import { Spinner } from "@/presentation/components/ui/Spinner";
import { TabPanel, Tabs } from "@/presentation/components/ui/Tabs";
import { AgentMonthCalendar } from "../../vacations/planifier/AgentMonthCalendar";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { useToast } from "@/presentation/providers/ToastProvider";
import { apiClient } from "@/infrastructure/http/apiClient";
import { agentsApi, bulletinsPaieApi } from "@/infrastructure/http/resources";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import {
  can,
  canManageAbsences,
  canSeeSalaire,
  canViewPaie,
} from "@/shared/lib/can";
import {
  formatDate,
  formatDateTime,
  formatSalaire,
  labelAgentStatut,
  labelize,
  MOIS_LABELS,
} from "@/shared/lib/format";

type AgentTab =
  | "overview"
  | "vacations"
  | "contrats"
  | "absences"
  | "paie"
  | "documents"
  | "conges";

type VacationsSubTab = "calendrier" | "liste";

const VACATION_STATUT_LABEL: Record<string, string> = {
  planifiee: "Planifiée",
  en_cours: "En cours",
  terminee: "Terminée",
  annulee: "Annulée",
  a_recouvrir: "À recouvrir",
};

function currentYearMonth() {
  const now = new Date();
  const y = now.getFullYear();
  const m = String(now.getMonth() + 1).padStart(2, "0");
  return `${y}-${m}`;
}

function MetaCard({
  icon: Icon,
  label,
  value,
  hint,
}: {
  icon: typeof CalendarRange;
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

async function downloadAgentDocument(
  agentId: string,
  collection: "piece_identite" | "permis",
  filename: string,
) {
  const response = await apiClient.get(
    agentsApi.downloadDocument(agentId, collection),
    { responseType: "blob" },
  );
  const blob = new Blob([response.data]);
  const objectUrl = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = objectUrl;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(objectUrl);
}

async function downloadBulletinPdf(bulletinId: string, filename: string) {
  const response = await apiClient.get(bulletinsPaieApi.downloadPdf(bulletinId), {
    responseType: "blob",
  });
  const blob = new Blob([response.data], { type: "application/pdf" });
  const objectUrl = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = objectUrl;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(objectUrl);
}

export default function AgentDetailPage() {
  const params = useParams();
  const id = params.id as string;
  const { user } = useAuth();
  const { toast } = useToast();
  const [tab, setTab] = useState<AgentTab>("overview");
  const [vacationsSubTab, setVacationsSubTab] =
    useState<VacationsSubTab>("calendrier");
  const cniInputRef = useRef<HTMLInputElement>(null);
  const permisInputRef = useRef<HTMLInputElement>(null);

  const [vacationsPage, setVacationsPage] = useState(1);
  const [vacationsPerPage, setVacationsPerPage] = useState(15);
  const [calendarMonth, setCalendarMonth] = useState(currentYearMonth);
  const [contratsPage, setContratsPage] = useState(1);
  const [contratsPerPage, setContratsPerPage] = useState(15);
  const [absencesPage, setAbsencesPage] = useState(1);
  const [absencesPerPage, setAbsencesPerPage] = useState(15);
  const [bulletinsPage, setBulletinsPage] = useState(1);
  const [bulletinsPerPage, setBulletinsPerPage] = useState(15);
  const [mouvementsPage, setMouvementsPage] = useState(1);
  const [mouvementsPerPage, setMouvementsPerPage] = useState(15);

  const { data, isLoading, isError } = useAgent(id);
  const agent = data?.data;
  const isControleur = agent?.type === "controleur";
  const zoneId = agent?.perimetres?.[0]?.zone_id ?? undefined;
  const zone = agent?.perimetres?.[0]?.zone?.nom;
  const contrat = agent?.contrat_actif;

  const canDocs = can(user, "documents.manage");
  const canConges = canManageAbsences(user);
  const canVacations = can(user, "vacations.view");
  const canPlanifier = can(user, "vacations.create");
  const canUpdateAgent = can(user, "agents.update");
  const canPaie = canViewPaie(user);
  const showSalaire = canSeeSalaire(user);
  const canViewRh =
    can(user, ["contrats.manage", "contrats.view", "contrats.alerts"]) ||
    can(user, ["absences.manage", "absences.view"]);

  const { data: zoneSitesData, isLoading: sitesLoading } = useSites(
    zoneId ? { zone_id: zoneId, all: true } : undefined,
    { enabled: isControleur && !!zoneId },
  );
  const zoneSites = zoneSitesData?.data ?? [];

  const { data: vacationsData, isLoading: vacationsLoading } = useVacations(
    {
      agent_id: id,
      page: vacationsPage,
      per_page: vacationsPerPage,
    },
    {
      enabled:
        tab === "vacations" &&
        vacationsSubTab === "liste" &&
        !!id &&
        canVacations,
    },
  );
  const { data: calendarVacationsData, isLoading: calendarLoading } =
    useVacations(
      { agent_id: id, per_page: 200 },
      {
        enabled:
          tab === "vacations" &&
          vacationsSubTab === "calendrier" &&
          !!id &&
          canVacations,
      },
    );
  const { data: calendarAbsencesData } = useAbsences(
    { agent_id: id, per_page: 100 },
    {
      enabled:
        tab === "vacations" &&
        vacationsSubTab === "calendrier" &&
        !!id &&
        canViewRh,
    },
  );
  const { data: calendarControlesData } = useControles(
    { controle_agent_id: id, per_page: 100 },
    {
      enabled:
        tab === "vacations" &&
        vacationsSubTab === "calendrier" &&
        !!id &&
        canVacations,
    },
  );
  const reposDayIndex = useMemo(
    () => (agent?.jour_repos ? weekdayIndex(agent.jour_repos) : null),
    [agent?.jour_repos],
  );
  const { data: contratsData, isLoading: contratsLoading } = useContrats(
    { agent_id: id, page: contratsPage, per_page: contratsPerPage },
    { enabled: tab === "contrats" && !!id },
  );
  const { data: absencesData, isLoading: absencesLoading } = useAbsences(
    { agent_id: id, page: absencesPage, per_page: absencesPerPage },
    { enabled: tab === "absences" && !!id },
  );
  const { data: bulletinsData, isLoading: bulletinsLoading } = useAgentBulletins(
    id,
    { page: bulletinsPage, per_page: bulletinsPerPage },
    { enabled: tab === "paie" && !!id && canPaie },
  );
  const { data: mouvementsData, isLoading: mouvementsLoading } =
    useCongesMouvements(
      id,
      { page: mouvementsPage, per_page: mouvementsPerPage },
      { enabled: tab === "conges" && !!id },
    );
  const uploadDocument = useUploadAgentDocument();
  const accorderConges = useAccorderConges();

  const handleUpload = useCallback(
    async (collection: "piece_identite" | "permis", file: File) => {
      try {
        await uploadDocument.mutateAsync({ agentId: id, collection, file });
        toast("Document enregistré.");
      } catch (err) {
        toast(getApiErrorMessage(err, "Échec de l’envoi."), "danger");
      }
    },
    [id, toast, uploadDocument],
  );

  const vacationColumns = useMemo<ColumnDef<Vacation>[]>(
    () => [
      {
        id: "site",
        header: "Site",
        cell: ({ row }) => row.original.site?.nom ?? "—",
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

  const contratColumns = useMemo<ColumnDef<Contrat>[]>(
    () => [
      {
        accessorKey: "reference",
        header: "Référence",
        cell: ({ getValue }) => (getValue() as string | null) || "—",
      },
      {
        accessorKey: "type",
        header: "Type",
        cell: ({ getValue }) => labelize(String(getValue())),
      },
      {
        accessorKey: "date_debut",
        header: "Début",
        cell: ({ getValue }) => formatDate(String(getValue())),
      },
      {
        accessorKey: "date_fin",
        header: "Fin",
        cell: ({ getValue }) => formatDate(getValue() as string | null),
      },
      {
        accessorKey: "periode_essai_mois",
        header: "Essai",
        cell: ({ getValue }) => {
          const v = getValue() as number | null;
          return v != null ? `${v} mois` : "—";
        },
      },
      ...(showSalaire
        ? ([
            {
              accessorKey: "salaire_brut",
              header: "Brut",
              cell: ({ getValue }) =>
                formatSalaire(getValue() as string | number | null, user),
            },
            {
              accessorKey: "salaire_net",
              header: "Net",
              cell: ({ getValue }) =>
                formatSalaire(getValue() as string | number | null, user),
            },
          ] as ColumnDef<Contrat>[])
        : []),
      {
        accessorKey: "statut",
        header: "Statut",
        cell: ({ getValue }) => (
          <Badge tone={statusTone(String(getValue()))}>
            {labelize(String(getValue()))}
          </Badge>
        ),
      },
    ],
    [showSalaire, user],
  );

  const absenceColumns = useMemo<ColumnDef<Absence>[]>(
    () => [
      {
        accessorKey: "type",
        header: "Type",
        cell: ({ getValue }) => labelTypeAbsence(String(getValue())),
      },
      {
        id: "source",
        header: "Origine",
        cell: ({ row }) => (
          <Badge tone={sourceAbsenceTone(row.original.source)}>
            {labelSourceAbsence(row.original.source)}
          </Badge>
        ),
      },
      {
        accessorKey: "date_debut",
        header: "Du",
        cell: ({ getValue }) => formatDate(String(getValue())),
      },
      {
        accessorKey: "date_fin",
        header: "Au",
        cell: ({ getValue }) => formatDate(getValue() as string | null),
      },
      {
        id: "duree",
        header: "Durée",
        cell: ({ row }) =>
          formatAbsenceDuration(row.original.date_debut, row.original.date_fin),
      },
      {
        accessorKey: "motif",
        header: "Motif",
        cell: ({ getValue }) => (getValue() as string | null) || "—",
      },
      {
        accessorKey: "statut",
        header: "Statut",
        cell: ({ getValue }) => (
          <Badge tone={statusTone(String(getValue()))}>
            {labelize(String(getValue()))}
          </Badge>
        ),
      },
    ],
    [],
  );

  const bulletinColumns = useMemo<ColumnDef<BulletinPaie>[]>(
    () => [
      {
        id: "periode",
        header: "Période",
        cell: ({ row }) => {
          const p = row.original.periode_paie;
          if (!p) return "—";
          const mois = MOIS_LABELS[p.mois - 1] ?? String(p.mois);
          return `${mois} ${p.annee}`;
        },
      },
      ...(showSalaire
        ? ([
            {
              accessorKey: "salaire_brut",
              header: "Brut",
              cell: ({ getValue }) =>
                formatSalaire(getValue() as string | number | null, user),
            },
            {
              accessorKey: "salaire_net",
              header: "Net",
              cell: ({ getValue }) =>
                formatSalaire(getValue() as string | number | null, user),
            },
          ] as ColumnDef<BulletinPaie>[])
        : []),
      {
        accessorKey: "statut",
        header: "Statut",
        cell: ({ getValue }) => (
          <Badge tone={statusTone(String(getValue()))}>
            {labelize(String(getValue()))}
          </Badge>
        ),
      },
      {
        accessorKey: "paye_le",
        header: "Payé le",
        cell: ({ getValue }) =>
          formatDate(getValue() as string | null | undefined),
      },
      {
        id: "actions",
        header: "PDF",
        enableSorting: false,
        cell: ({ row }) => (
          <Button
            size="sm"
            variant="secondary"
            onClick={async () => {
              try {
                await downloadBulletinPdf(
                  row.original.id,
                  `bulletin-${row.original.id}.pdf`,
                );
              } catch (err) {
                toast(
                  getApiErrorMessage(err, "PDF indisponible pour ce bulletin."),
                  "danger",
                );
              }
            }}
          >
            Télécharger
          </Button>
        ),
      },
    ],
    [showSalaire, toast, user],
  );

  const mouvementColumns = useMemo<ColumnDef<SoldeCongesMouvement>[]>(
    () => [
      {
        accessorKey: "created_at",
        header: "Date",
        cell: ({ getValue }) => formatDateTime(getValue() as string | undefined),
      },
      {
        accessorKey: "type",
        header: "Type",
        cell: ({ getValue }) => labelize(String(getValue())),
      },
      {
        accessorKey: "jours",
        header: "Jours",
        cell: ({ getValue }) => String(getValue()),
      },
      {
        accessorKey: "solde_apres",
        header: "Solde après",
        cell: ({ getValue }) => String(getValue()),
      },
      {
        accessorKey: "motif",
        header: "Motif",
        cell: ({ getValue }) => (getValue() as string | null) || "—",
      },
    ],
    [],
  );

  const tabItems = useMemo(
    () =>
      [
        { id: "overview", label: "Vue d’ensemble" },
        canVacations ? { id: "vacations", label: "Vacations" } : null,
        canViewRh ? { id: "contrats", label: "Contrats" } : null,
        canViewRh ? { id: "absences", label: "Absences" } : null,
        canPaie ? { id: "paie", label: "Paie" } : null,
        canDocs ? { id: "documents", label: "Documents" } : null,
        canViewRh ? { id: "conges", label: "Congés" } : null,
      ].filter(Boolean) as Array<{ id: AgentTab; label: string }>,
    [canDocs, canPaie, canVacations, canViewRh],
  );

  return (
    <PermissionGate permission="agents.view" title="Agent">
      <div className="mx-auto max-w-5xl">
        <Link
          href="/agents"
          className="mb-6 inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-teal"
        >
          <ArrowLeft className="size-4" />
          Personnel
        </Link>

        {isLoading ? (
          <div className="flex justify-center py-24">
            <Spinner className="size-8" />
          </div>
        ) : isError || !agent ? (
          <Alert tone="danger" title="Agent introuvable">
            Cet agent n&apos;existe pas ou n&apos;est plus accessible.
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
                      {labelize(agent.type)} · {agent.matricule}
                    </p>
                    <h1 className="mt-2 max-w-3xl text-2xl font-semibold tracking-tight sm:text-3xl">
                      {agent.prenom} {agent.nom}
                    </h1>
                    {agent.grade?.libelle ? (
                      <p className="mt-3 inline-flex items-center gap-2 text-sm text-white/80">
                        <Briefcase className="size-4" />
                        {agent.grade.libelle}
                      </p>
                    ) : null}
                  </div>
                  <div className="flex flex-col items-end gap-3">
                    <div className="flex flex-wrap justify-end gap-1.5">
                      <Badge tone={statusTone(agent.statut)}>
                        {labelAgentStatut(agent.statut)}
                      </Badge>
                      {agent.pool_siege ? (
                        <Badge tone="info">Pool siège</Badge>
                      ) : null}
                    </div>
                    <div className="flex flex-wrap justify-end gap-2">
                      {canUpdateAgent ? (
                        <Link href="/agents">
                          <Button size="sm" variant="secondary">
                            Modifier la fiche
                          </Button>
                        </Link>
                      ) : null}
                      {canPlanifier && !isControleur ? (
                        <Link href={`/vacations/planifier?agent_id=${id}`}>
                          <Button size="sm">Planifier</Button>
                        </Link>
                      ) : null}
                    </div>
                  </div>
                </div>
              </div>
            </header>

            <Tabs
              items={tabItems}
              value={tab}
              onChange={(next) => setTab(next as AgentTab)}
            />

            <TabPanel when="overview" active={tab}>
              <div className="space-y-8">
                <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                  <MetaCard
                    icon={Phone}
                    label="Téléphone"
                    value={agent.telephone ?? "—"}
                  />
                  <MetaCard
                    icon={MapPin}
                    label="Ville"
                    value={agent.ville ?? "—"}
                    hint={agent.domicile ?? undefined}
                  />
                  <MetaCard
                    icon={CalendarClock}
                    label="Jour de repos"
                    value={
                      agent.jour_repos ? labelize(agent.jour_repos) : "Non défini"
                    }
                    hint="Permanent — Personnel → Modifier (pas dans l’édition vacation)"
                  />
                  {isControleur ? (
                    <MetaCard
                      icon={Route}
                      label="Zone"
                      value={zone ?? "Aucune"}
                      hint="Périmètre contrôleur"
                    />
                  ) : (
                    <MetaCard
                      icon={CalendarRange}
                      label="Fin de contrat"
                      value={
                        contrat?.date_fin ? formatDate(contrat.date_fin) : "—"
                      }
                    />
                  )}
                  <MetaCard
                    icon={CalendarRange}
                    label="Embauche"
                    value={
                      agent.date_embauche
                        ? formatDate(agent.date_embauche)
                        : "—"
                    }
                    hint="Indépendant du contrat en cours"
                  />
                </section>

                <div className="grid gap-6 lg:grid-cols-2">
                  <section className="rounded-2xl border border-border bg-white p-6 shadow-[0_1px_0_rgba(20,26,16,0.04)]">
                    <div className="mb-4 flex items-center gap-2">
                      <span className="flex size-8 items-center justify-center rounded-lg bg-teal/10 text-teal">
                        <User className="size-4" />
                      </span>
                      <h2 className="text-base font-semibold text-ink">
                        Identité
                      </h2>
                    </div>
                    <div className="divide-y divide-border/70">
                      <InfoRow
                        label="Civilité"
                        value={
                          agent.civilite ? labelize(agent.civilite) : "—"
                        }
                      />
                      <InfoRow
                        label="Date de naissance"
                        value={
                          agent.date_naissance
                            ? formatDate(agent.date_naissance)
                            : "—"
                        }
                      />
                      <InfoRow
                        label="Lieu de naissance"
                        value={agent.lieu_naissance ?? "—"}
                      />
                      <InfoRow
                        label="Nationalité"
                        value={agent.nationalite ?? "—"}
                      />
                      <InfoRow label="N° CNI" value={agent.numero_cni ?? "—"} />
                      <InfoRow
                        label="Situation matrimoniale"
                        value={
                          agent.situation_matrimoniale
                            ? labelize(agent.situation_matrimoniale)
                            : "—"
                        }
                      />
                      <InfoRow
                        label="Nombre d’enfants"
                        value={String(agent.nombre_enfants ?? 0)}
                      />
                    </div>
                  </section>

                  <section className="rounded-2xl border border-border bg-white p-6 shadow-[0_1px_0_rgba(20,26,16,0.04)]">
                    <div className="mb-4 flex items-center justify-between gap-2">
                      <div className="flex items-center gap-2">
                        <span className="flex size-8 items-center justify-center rounded-lg bg-brand-accent/10 text-brand-accent">
                          <IdCard className="size-4" />
                        </span>
                        <h2 className="text-base font-semibold text-ink">
                          Contrat actif
                        </h2>
                      </div>
                      {canViewRh ? (
                        <Link
                          href="/rh"
                          className="text-xs font-medium text-teal hover:underline"
                        >
                          Voir RH
                        </Link>
                      ) : null}
                    </div>
                    {contrat ? (
                      <div className="divide-y divide-border/70">
                        <InfoRow label="Type" value={labelize(contrat.type)} />
                        <InfoRow
                          label="Référence"
                          value={contrat.reference ?? "—"}
                        />
                        <InfoRow
                          label="Début"
                          value={formatDate(contrat.date_debut)}
                        />
                        <InfoRow
                          label="Fin"
                          value={
                            contrat.date_fin
                              ? formatDate(contrat.date_fin)
                              : "Indéterminée"
                          }
                        />
                        <InfoRow
                          label="Période d’essai"
                          value={
                            contrat.periode_essai_mois != null
                              ? `${contrat.periode_essai_mois} mois`
                              : "—"
                          }
                        />
                        {showSalaire ? (
                          <>
                            <InfoRow
                              label="Salaire brut"
                              value={formatSalaire(contrat.salaire_brut, user)}
                            />
                            <InfoRow
                              label="Salaire net"
                              value={formatSalaire(contrat.salaire_net, user)}
                            />
                          </>
                        ) : null}
                        <InfoRow
                          label="Statut"
                          value={labelize(contrat.statut)}
                        />
                      </div>
                    ) : (
                      <p className="text-sm text-ink-faint">
                        Aucun contrat actif.
                      </p>
                    )}
                  </section>

                  <section className="rounded-2xl border border-border bg-white p-6 shadow-[0_1px_0_rgba(20,26,16,0.04)]">
                    <div className="mb-4 flex items-center gap-2">
                      <span className="flex size-8 items-center justify-center rounded-lg bg-teal/10 text-teal">
                        <ShieldCheck className="size-4" />
                      </span>
                      <h2 className="text-base font-semibold text-ink">
                        Affectation et carrière
                      </h2>
                    </div>
                    <div className="divide-y divide-border/70">
                      <InfoRow
                        label="Grade"
                        value={agent.grade?.libelle ?? "—"}
                      />
                      {isControleur ? (
                        <InfoRow label="Zone" value={zone ?? "Aucune"} />
                      ) : null}
                      <InfoRow label="CNPS" value={agent.cnps ?? "—"} />
                      <InfoRow
                        label="Jour de repos"
                        value={
                          agent.jour_repos
                            ? labelize(agent.jour_repos)
                            : "—"
                        }
                      />
                      <InfoRow
                        label="Solde congés"
                        value={`${agent.solde_conges_jours ?? 0} j`}
                      />
                      <InfoRow
                        label="Fin de permis"
                        value={
                          agent.date_expiration_permis
                            ? formatDate(agent.date_expiration_permis)
                            : "—"
                        }
                      />
                    </div>
                  </section>

                  <section className="rounded-2xl border border-border bg-white p-6 shadow-[0_1px_0_rgba(20,26,16,0.04)]">
                    <div className="mb-4 flex items-center gap-2">
                      <span className="flex size-8 items-center justify-center rounded-lg bg-brand-accent/10 text-brand-accent">
                        <Smartphone className="size-4" />
                      </span>
                      <h2 className="text-base font-semibold text-ink">
                        Accès mobile
                      </h2>
                    </div>
                    <div className="divide-y divide-border/70">
                      <InfoRow label="Email" value={agent.email ?? "—"} />
                      <InfoRow
                        label="Code PIN"
                        value={agent.has_pin ? "Configuré" : "Non configuré"}
                      />
                    </div>
                    {(canVacations || canPaie || canViewRh) && (
                      <div className="mt-4 flex flex-wrap gap-2 border-t border-border/70 pt-4">
                        {canVacations ? (
                          <Link
                            href={`/vacations?agent_id=${id}`}
                            className="text-xs font-medium text-teal hover:underline"
                          >
                            Planning vacations
                          </Link>
                        ) : null}
                        {canPaie ? (
                          <Link
                            href="/rh/paie"
                            className="text-xs font-medium text-teal hover:underline"
                          >
                            Module paie
                          </Link>
                        ) : null}
                        {canViewRh ? (
                          <Link
                            href="/rh"
                            className="text-xs font-medium text-teal hover:underline"
                          >
                            Contrats & absences
                          </Link>
                        ) : null}
                      </div>
                    )}
                  </section>
                </div>

                {isControleur ? (
                  <section className="rounded-2xl border border-border bg-white p-6 shadow-[0_1px_0_rgba(20,26,16,0.04)]">
                    <div className="mb-4 flex items-center gap-2">
                      <span className="flex size-8 items-center justify-center rounded-lg bg-teal/10 text-teal">
                        <MapPin className="size-4" />
                      </span>
                      <div>
                        <h2 className="text-base font-semibold text-ink">
                          Sites de la zone
                        </h2>
                        <p className="text-sm text-ink-muted">
                          {zone
                            ? `Tous les sites couverts par la zone ${zone}.`
                            : "Aucune zone assignée."}
                        </p>
                      </div>
                    </div>
                    {!zoneId ? (
                      <p className="text-sm text-ink-faint">
                        Ce contrôleur n&apos;a pas encore de zone assignée.
                      </p>
                    ) : sitesLoading ? (
                      <div className="flex justify-center py-8">
                        <Spinner className="size-6" />
                      </div>
                    ) : zoneSites.length === 0 ? (
                      <p className="text-sm text-ink-faint">
                        Aucun site dans cette zone.
                      </p>
                    ) : (
                      <div className="grid gap-2 sm:grid-cols-2">
                        {zoneSites.map((site) => (
                          <div
                            key={site.id}
                            className="rounded-xl border border-border/80 bg-paper/60 px-4 py-3"
                          >
                            <p className="text-sm font-medium text-ink">
                              {site.nom}
                            </p>
                            <p className="text-xs text-ink-muted">
                              {site.client?.raison_sociale ?? "—"}
                              {site.adresse ? ` · ${site.adresse}` : ""}
                            </p>
                          </div>
                        ))}
                      </div>
                    )}
                  </section>
                ) : null}
              </div>
            </TabPanel>

            <TabPanel when="vacations" active={tab}>
              <div className="space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <p className="text-sm text-ink-muted">
                    Planning de l’agent — édition depuis Vacations.
                  </p>
                  <div className="flex flex-wrap gap-2">
                    <Link href={`/vacations?agent_id=${id}`}>
                      <Button size="sm" variant="secondary">
                        Ouvrir la liste
                      </Button>
                    </Link>
                    {canPlanifier ? (
                      <Link href={`/vacations/planifier?agent_id=${id}`}>
                        <Button size="sm">Planifier</Button>
                      </Link>
                    ) : null}
                  </div>
                </div>

                <Tabs
                  items={[
                    { id: "calendrier", label: "Calendrier" },
                    { id: "liste", label: "Liste" },
                  ]}
                  value={vacationsSubTab}
                  onChange={(next) =>
                    setVacationsSubTab(next as VacationsSubTab)
                  }
                  className="border-border/80"
                />

                <TabPanel when="calendrier" active={vacationsSubTab}>
                  <div className="space-y-3">
                    <p className="text-sm text-ink-muted">
                      Planning, absences et contrôles de présence
                      {agent.jour_repos
                        ? ` · repos le ${labelize(agent.jour_repos)}`
                        : ""}
                      .
                    </p>
                    {calendarLoading ? (
                      <div className="flex justify-center py-12">
                        <Spinner className="size-6" />
                      </div>
                    ) : (
                      <AgentMonthCalendar
                        month={calendarMonth}
                        onMonthChange={setCalendarMonth}
                        existing={calendarVacationsData?.data ?? []}
                        absences={calendarAbsencesData?.data ?? []}
                        controles={calendarControlesData?.data ?? []}
                        reposDayIndex={reposDayIndex}
                        mode="readonly"
                      />
                    )}
                  </div>
                </TabPanel>

                <TabPanel when="liste" active={vacationsSubTab}>
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
                    emptyTitle="Aucune vacation pour cet agent"
                    emptyAction={
                      canPlanifier ? (
                        <Link href={`/vacations/planifier?agent_id=${id}`}>
                          <Button size="sm">Planifier cet agent</Button>
                        </Link>
                      ) : undefined
                    }
                  />
                </TabPanel>
              </div>
            </TabPanel>

            <TabPanel when="contrats" active={tab}>
              <div className="mb-3 flex justify-end">
                <Link
                  href="/rh"
                  className="text-xs font-medium text-teal hover:underline"
                >
                  Gérer dans RH
                </Link>
              </div>
              <DataTable
                data={contratsData?.data ?? []}
                columns={contratColumns}
                isLoading={contratsLoading}
                pagination={tablePagination(
                  contratsPage,
                  contratsData?.meta.per_page ?? contratsPerPage,
                  contratsData?.meta.total ?? 0,
                  setContratsPage,
                  setContratsPerPage,
                )}
                emptyTitle="Aucun contrat pour cet agent"
              />
            </TabPanel>

            <TabPanel when="absences" active={tab}>
              <div className="mb-3 flex justify-end">
                <Link
                  href="/rh"
                  className="text-xs font-medium text-teal hover:underline"
                >
                  Gérer dans RH
                </Link>
              </div>
              <DataTable
                data={absencesData?.data ?? []}
                columns={absenceColumns}
                isLoading={absencesLoading}
                pagination={tablePagination(
                  absencesPage,
                  absencesData?.meta.per_page ?? absencesPerPage,
                  absencesData?.meta.total ?? 0,
                  setAbsencesPage,
                  setAbsencesPerPage,
                )}
                emptyTitle="Aucune absence pour cet agent"
              />
            </TabPanel>

            <TabPanel when="paie" active={tab}>
              <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <p className="inline-flex items-center gap-1.5 text-sm text-ink-muted">
                  <Wallet className="size-4" />
                  Bulletins de paie de cet agent
                </p>
                <Link
                  href="/rh/paie"
                  className="text-xs font-medium text-teal hover:underline"
                >
                  Module paie
                </Link>
              </div>
              <DataTable
                data={bulletinsData?.data ?? []}
                columns={bulletinColumns}
                isLoading={bulletinsLoading}
                pagination={tablePagination(
                  bulletinsPage,
                  bulletinsData?.meta.per_page ?? bulletinsPerPage,
                  bulletinsData?.meta.total ?? 0,
                  setBulletinsPage,
                  setBulletinsPerPage,
                )}
                emptyTitle="Aucun bulletin pour cet agent"
              />
            </TabPanel>

            <TabPanel when="documents" active={tab}>
              <div className="grid gap-4 sm:grid-cols-2">
                {(
                  [
                    {
                      key: "piece_identite" as const,
                      label: "Pièce d’identité (CNI)",
                      ref: cniInputRef,
                    },
                    {
                      key: "permis" as const,
                      label: "Permis de conduire",
                      ref: permisInputRef,
                    },
                  ] as const
                ).map(({ key, label, ref }) => (
                  <div
                    key={key}
                    className="rounded-xl border border-border bg-white p-5 shadow-sm"
                  >
                    <h3 className="text-sm font-semibold text-ink">{label}</h3>
                    <p className="mt-1 text-xs text-ink-muted">
                      PDF, JPG ou PNG — max 10 Mo
                    </p>
                    <div className="mt-4 flex flex-wrap gap-2">
                      <input
                        ref={ref}
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png"
                        className="hidden"
                        onChange={(e) => {
                          const file = e.target.files?.[0];
                          if (file) void handleUpload(key, file);
                          e.target.value = "";
                        }}
                      />
                      <Button
                        size="sm"
                        loading={uploadDocument.isPending}
                        onClick={() => ref.current?.click()}
                      >
                        <Upload className="size-4" />
                        Téléverser
                      </Button>
                      <Button
                        size="sm"
                        variant="secondary"
                        onClick={async () => {
                          try {
                            await downloadAgentDocument(id, key, `${key}.pdf`);
                          } catch (err) {
                            toast(
                              getApiErrorMessage(err, "Document introuvable."),
                              "danger",
                            );
                          }
                        }}
                      >
                        Télécharger
                      </Button>
                    </div>
                  </div>
                ))}
              </div>
            </TabPanel>

            <TabPanel when="conges" active={tab}>
              <div className="space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-white p-4">
                  <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                      <p className="text-xs text-ink-muted">Solde congés</p>
                      <p className="text-xl font-semibold text-ink">
                        {agent.solde_conges_jours ?? 0} j
                      </p>
                    </div>
                    <div>
                      <p className="text-xs text-ink-muted">
                        Acquisition annuelle
                      </p>
                      <p className="text-xl font-semibold text-ink">
                        {agent.conges_acquis_annuel ?? 30} j
                      </p>
                    </div>
                  </div>
                  {canConges ? (
                    <Button
                      size="sm"
                      loading={accorderConges.isPending}
                      onClick={async () => {
                        try {
                          await accorderConges.mutateAsync({ agentId: id });
                          toast("Acquisition annuelle accordée.");
                        } catch (err) {
                          toast(
                            getApiErrorMessage(
                              err,
                              "Échec de l’acquisition.",
                            ),
                            "danger",
                          );
                        }
                      }}
                    >
                      Accorder acquisition annuelle
                    </Button>
                  ) : null}
                </div>
                <DataTable
                  data={mouvementsData?.data ?? []}
                  columns={mouvementColumns}
                  isLoading={mouvementsLoading}
                  pagination={tablePagination(
                    mouvementsPage,
                    mouvementsData?.meta.per_page ?? mouvementsPerPage,
                    mouvementsData?.meta.total ?? 0,
                    setMouvementsPage,
                    setMouvementsPerPage,
                  )}
                  emptyTitle="Aucun mouvement de congés"
                />
              </div>
            </TabPanel>
          </div>
        )}
      </div>
    </PermissionGate>
  );
}
