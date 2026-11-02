"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import { type ColumnDef } from "@tanstack/react-table";
import {
  ArrowLeft,
  CalendarPlus,
  CircleHelp,
  Clock,
  UserCheck,
  UserX,
} from "lucide-react";
import {
  useAgents,
  usePointageSynthese,
  usePointageVacations,
  useSites,
} from "@/application/hooks/useResources";
import type {
  PointageControle,
  PointageLigne,
  PointageSyntheseAgent,
} from "@/domain/types/entities";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { DataTable } from "@/presentation/components/tables/DataTable";
import { Alert } from "@/presentation/components/ui/Alert";
import { Badge } from "@/presentation/components/ui/Badge";
import { Card, CardBody } from "@/presentation/components/ui/Card";
import { DatePicker } from "@/presentation/components/ui/DatePicker";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { Select } from "@/presentation/components/ui/Select";
import { StatCard } from "@/presentation/components/ui/StatCard";
import { TabPanel, Tabs } from "@/presentation/components/ui/Tabs";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { formatDate } from "@/shared/lib/format";

const CONTROLE: Record<
  PointageControle,
  { label: string; tone: "success" | "danger" | "neutral" }
> = {
  present: { label: "Présent", tone: "success" },
  absent: { label: "Absent", tone: "danger" },
  non_controle: { label: "Non contrôlé", tone: "neutral" },
};

function isoLocal(d: Date): string {
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const j = String(d.getDate()).padStart(2, "0");
  return `${d.getFullYear()}-${m}-${j}`;
}

function heures(value: number): string {
  return `${value.toLocaleString("fr-FR", { maximumFractionDigits: 2 })} h`;
}

export default function PointagePage() {
  const today = new Date();
  const [dateDebut, setDateDebut] = useState(
    isoLocal(new Date(today.getFullYear(), today.getMonth(), 1)),
  );
  const [dateFin, setDateFin] = useState(isoLocal(today));
  const [agentId, setAgentId] = useState("");
  const [siteId, setSiteId] = useState("");
  const [controle, setControle] = useState<"" | PointageControle>("");
  const [vue, setVue] = useState<"synthese" | "detail">("synthese");
  const [synthesePage, setSynthesePage] = useState(1);
  const [synthesePerPage, setSynthesePerPage] = useState(15);
  const [syntheseQ, setSyntheseQ] = useState("");

  const periodeValide = !!dateDebut && !!dateFin && dateDebut <= dateFin;
  const { data, isLoading, error } = usePointageVacations(
    periodeValide
      ? {
          date_debut: dateDebut,
          date_fin: dateFin,
          agent_id: agentId || undefined,
          site_id: siteId || undefined,
        }
      : undefined,
  );
  const synthese = usePointageSynthese(
    periodeValide && vue === "synthese"
      ? {
          date_debut: dateDebut,
          date_fin: dateFin,
          agent_id: agentId || undefined,
          site_id: siteId || undefined,
        }
      : undefined,
  );
  const syntheseLignes = useMemo(() => {
    const mots = syntheseQ.toLowerCase().split(/\s+/).filter(Boolean);
    return (synthese.data?.data ?? []).filter((l) => {
      const texte = `${l.agent} ${l.matricule ?? ""}`.toLowerCase();
      return mots.every((m) => texte.includes(m));
    });
  }, [synthese.data, syntheseQ]);
  const { data: agentsData } = useAgents({ type: "agent", all: true });
  const { data: sitesData } = useSites({ all: true });

  const lignes = useMemo(
    () =>
      (data?.data ?? []).filter((l) => !controle || l.controle === controle),
    [data, controle],
  );
  const totaux = data?.meta;

  const syntheseColumns = useMemo<ColumnDef<PointageSyntheseAgent>[]>(
    () => [
      {
        accessorKey: "agent",
        header: "Agent",
        cell: ({ row }) => (
          <span>
            {row.original.agent}
            {row.original.matricule ? (
              <span className="ml-1 text-xs text-ink-faint">
                {row.original.matricule}
              </span>
            ) : null}
          </span>
        ),
      },
      { accessorKey: "presences", header: "Présences" },
      { accessorKey: "absences", header: "Absences" },
      { accessorKey: "jours_supplementaires", header: "Jours supp." },
      {
        accessorKey: "heures_supplementaires",
        header: "Heures supp.",
        cell: ({ getValue }) => heures(getValue() as number),
      },
      { accessorKey: "non_controles", header: "Non contrôlés" },
      {
        accessorKey: "heures_comptees",
        header: "Heures comptées",
        cell: ({ getValue }) => heures(getValue() as number),
      },
    ],
    [],
  );

  const columns = useMemo<ColumnDef<PointageLigne>[]>(
    () => [
      {
        accessorKey: "date",
        header: "Date",
        cell: ({ getValue }) => formatDate(getValue() as string),
      },
      {
        accessorKey: "agent",
        header: "Agent",
        cell: ({ getValue }) => (getValue() as string | null) ?? "—",
      },
      {
        id: "lieu",
        header: "Site / poste",
        cell: ({ row }) =>
          [row.original.site, row.original.poste].filter(Boolean).join(" · ") ||
          "—",
      },
      {
        id: "horaires",
        header: "Horaires",
        cell: ({ row }) =>
          `${row.original.heure_debut} – ${row.original.heure_fin}`,
      },
      {
        accessorKey: "controle",
        header: "Contrôle",
        cell: ({ row }) => {
          const c = CONTROLE[row.original.controle];
          return (
            <span className="inline-flex flex-col gap-0.5">
              <Badge tone={c.tone}>{c.label}</Badge>
              {row.original.statut === "a_recouvrir" ? (
                <span className="text-[10px] text-ink-faint">
                  Absence enregistrée
                </span>
              ) : null}
            </span>
          );
        },
      },
      {
        accessorKey: "heures_prevues",
        header: "Prévues",
        cell: ({ getValue }) => heures(getValue() as number),
      },
      {
        id: "heures_supp",
        header: "Dont sup.",
        cell: ({ row }) =>
          row.original.heures_supplementaires > 0 ? (
            <Badge tone="warning">
              {heures(row.original.heures_supplementaires)}
            </Badge>
          ) : (
            "—"
          ),
      },
      {
        accessorKey: "heures_comptees",
        header: "Comptées",
        cell: ({ getValue }) => (
          <span className="font-medium">{heures(getValue() as number)}</span>
        ),
      },
    ],
    [],
  );

  return (
    <PermissionGate permission="vacations.view" title="Pointage">
      <div className="space-y-6">
        <PageHeader
          title="Pointage des vacations"
          description="Présence constatée par les contrôles. Un créneau non contrôlé n’est ni présent ni absent et ses heures ne sont pas comptées."
          actions={
            <Link
              href="/vacations"
              className="inline-flex h-10 items-center gap-2 rounded-md border border-border bg-white px-4 text-sm font-medium text-ink hover:bg-paper-muted"
            >
              <ArrowLeft className="size-4" />
              Planning
            </Link>
          }
        />

        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
          <StatCard
            label="Présents"
            value={totaux?.present ?? 0}
            hint="Créneaux contrôlés présents"
            icon={<UserCheck className="size-4" />}
          />
          <StatCard
            label="Absents"
            value={totaux?.absent ?? 0}
            hint="Contrôle absent ou absence enregistrée"
            icon={<UserX className="size-4" />}
          />
          <StatCard
            label="Jours supplémentaires"
            value={totaux?.jours_supplementaires ?? 0}
            hint={`${heures(totaux?.heures_supplementaires ?? 0)} de remplacement`}
            icon={<CalendarPlus className="size-4" />}
          />
          <StatCard
            label="Non contrôlés"
            value={totaux?.non_controle ?? 0}
            hint="Ni présent ni absent — 0 h comptée"
            icon={<CircleHelp className="size-4" />}
          />
          <StatCard
            label="Heures comptées"
            value={heures(totaux?.heures_comptees ?? 0)}
            hint="Créneaux présents uniquement"
            icon={<Clock className="size-4" />}
          />
        </div>

        <Card>
          <CardBody>
            <div className="flex flex-wrap items-end gap-3">
              <div className="w-44">
                <DatePicker
                  label="Du"
                  value={dateDebut}
                  onChange={(e) => setDateDebut(e.target.value)}
                />
              </div>
              <div className="w-44">
                <DatePicker
                  label="Au"
                  value={dateFin}
                  onChange={(e) => setDateFin(e.target.value)}
                />
              </div>
              <div className="w-56">
                <Select
                  label="Agent"
                  optionalMark
                  searchable
                  options={[
                    { value: "", label: "Tous les agents" },
                    ...(agentsData?.data ?? []).map((a) => ({
                      value: a.id,
                      label: `${a.prenom} ${a.nom} (${a.matricule})`,
                    })),
                  ]}
                  value={agentId}
                  onChange={(e) => setAgentId(e.target.value)}
                />
              </div>
              <div className="w-56">
                <Select
                  label="Site"
                  optionalMark
                  options={[
                    { value: "", label: "Tous les sites" },
                    ...(sitesData?.data ?? []).map((s) => ({
                      value: s.id,
                      label: s.nom,
                    })),
                  ]}
                  value={siteId}
                  onChange={(e) => setSiteId(e.target.value)}
                />
              </div>
              <div className="w-48">
                <Select
                  label="Contrôle"
                  optionalMark
                  options={[
                    { value: "", label: "Tous" },
                    { value: "present", label: "Présent" },
                    { value: "absent", label: "Absent" },
                    { value: "non_controle", label: "Non contrôlé" },
                  ]}
                  value={controle}
                  onChange={(e) =>
                    setControle(e.target.value as "" | PointageControle)
                  }
                />
              </div>
            </div>
          </CardBody>
        </Card>

        {!periodeValide ? (
          <Alert tone="warning">
            La date de début doit précéder ou égaler la date de fin.
          </Alert>
        ) : null}
        {error ? (
          <Alert tone="danger">
            {getApiErrorMessage(error, "Impossible de charger le pointage.")}
          </Alert>
        ) : null}

        <Tabs
          items={[
            { id: "synthese", label: "Synthèse par agent" },
            { id: "detail", label: "Détail des créneaux" },
          ]}
          value={vue}
          onChange={(id) => setVue(id as "synthese" | "detail")}
        />

        <TabPanel when="synthese" active={vue}>
          <DataTable
            data={syntheseLignes.slice(
              (synthesePage - 1) * synthesePerPage,
              synthesePage * synthesePerPage,
            )}
            columns={syntheseColumns}
            isLoading={synthese.isLoading}
            selectable={false}
            getRowId={(l) => l.agent_id}
            search={{
              value: syntheseQ,
              onChange: (value) => {
                setSyntheseQ(value);
                setSynthesePage(1);
              },
              placeholder: "Nom ou matricule…",
            }}
            pagination={{
              page: synthesePage,
              perPage: synthesePerPage,
              total: syntheseLignes.length,
              onPageChange: setSynthesePage,
              onPerPageChange: (n) => {
                setSynthesePerPage(n);
                setSynthesePage(1);
              },
            }}
            emptyTitle="Aucun agent sur la période"
            emptyDescription="Modifiez la période ou les filtres."
          />
        </TabPanel>

        <TabPanel when="detail" active={vue}>
          <DataTable
            data={lignes}
            columns={columns}
            isLoading={isLoading}
            selectable={false}
            getRowId={(l) => `${l.vacation_id}-${l.date}`}
            emptyTitle="Aucun créneau sur la période"
            emptyDescription="Modifiez la période ou les filtres."
          />
        </TabPanel>
      </div>
    </PermissionGate>
  );
}
