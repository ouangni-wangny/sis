"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import { type ColumnDef } from "@tanstack/react-table";
import { Building2, CalendarRange, CalendarCheck } from "lucide-react";
import { useDebouncedValue } from "@/application/hooks/useDebouncedValue";
import { useControles, useSites } from "@/application/hooks/useResources";
import { RESULTAT_CONTROLE_FILTER_OPTIONS } from "@/domain/schemas/controle";
import type { Controle, ControleResultat } from "@/domain/types/entities";
import { DataTable } from "@/presentation/components/tables/DataTable";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { Badge } from "@/presentation/components/ui/Badge";
import { Button } from "@/presentation/components/ui/Button";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { Select } from "@/presentation/components/ui/Select";
import { TabPanel, Tabs } from "@/presentation/components/ui/Tabs";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { can, hasRole } from "@/shared/lib/can";
import { formatDateTime } from "@/shared/lib/format";
import { ControlesParSiteView } from "./ControlesParSiteView";
import { PhotoPreuve } from "./PhotoPreuve";

function resultatLabel(resultat?: ControleResultat) {
  if (resultat === "present") return "Présent";
  if (resultat === "absent") return "Absent";
  return "Enregistré";
}

function resultatTone(resultat?: ControleResultat) {
  if (resultat === "present") return "success" as const;
  if (resultat === "absent") return "danger" as const;
  return "neutral" as const;
}

export default function ControlesPage() {
  const { user, featureFlags } = useAuth();
  const canSiege =
    can(user, "controles.create") &&
    featureFlags["module.controles_siege"] !== false &&
    (hasRole(user, "operation") ||
      hasRole(user, "super-admin") ||
      hasRole(user, "developpeur"));
  const [vue, setVue] = useState<"site" | "liste">("site");
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [q, setQ] = useState("");
  const [siteFilter, setSiteFilter] = useState("");
  const [resultatFilter, setResultatFilter] = useState("");
  const [aujourdhuiOnly, setAujourdhuiOnly] = useState(false);
  const [recent30jOnly, setRecent30jOnly] = useState(false);
  const search = useDebouncedValue(q);

  const resetPage = () => setPage(1);

  const toggleAujourdhui = () => {
    setAujourdhuiOnly((v) => !v);
    resetPage();
  };

  const toggleRecent30j = () => {
    setRecent30jOnly((v) => !v);
    resetPage();
  };

  const { data: sitesData } = useSites({ all: true });

  const siteFilterOptions = useMemo(
    () => [
      { value: "", label: "Tous les sites" },
      ...(sitesData?.data ?? []).map((s) => ({
        value: s.id,
        label: s.nom,
      })),
    ],
    [sitesData],
  );

  const { data, isLoading } = useControles(
    {
      page,
      per_page: perPage,
      q: search || undefined,
      site_id: siteFilter || undefined,
      resultat: resultatFilter || undefined,
      aujourd_hui: aujourdhuiOnly || undefined,
      recent_30j: recent30jOnly || undefined,
    },
    { enabled: vue === "liste" },
  );

  const columns = useMemo<ColumnDef<Controle>[]>(
    () => [
      {
        accessorKey: "effectue_at",
        header: "Date / heure",
        cell: ({ getValue }) => (
          <span className="whitespace-nowrap font-medium text-ink">
            {formatDateTime(String(getValue()))}
          </span>
        ),
      },
      {
        id: "controle_agent",
        header: "Agent contrôlé",
        cell: ({ row }) => (
          <div className="min-w-[160px]">
            <p className="font-semibold text-ink">
              {row.original.controle_agent
                ? `${row.original.controle_agent.prenom} ${row.original.controle_agent.nom}`
                : "—"}
            </p>
            <p className="text-xs text-ink-muted">
              {row.original.controle_agent?.matricule ?? "Agent non renseigné"}
            </p>
          </div>
        ),
      },
      {
        id: "resultat",
        header: "Statut",
        cell: ({ row }) => (
          <Badge tone={resultatTone(row.original.resultat)}>
            {resultatLabel(row.original.resultat)}
          </Badge>
        ),
      },
      {
        id: "lieu",
        header: "Site / poste",
        cell: ({ row }) => (
          <div className="min-w-[140px]">
            <p className="text-ink">{row.original.site?.nom ?? "—"}</p>
            <p className="text-xs text-ink-muted">
              {row.original.poste?.nom ?? "Poste non précisé"}
            </p>
          </div>
        ),
      },
      {
        id: "controleur",
        header: "Contrôlé par",
        cell: ({ row }) => {
          const agent = row.original.agent;
          const ops = row.original.enregistre_par;
          if (agent) {
            return (
              <div className="min-w-[140px]">
                <p className="text-ink">
                  {agent.prenom} {agent.nom}
                </p>
                <p className="text-xs text-ink-muted">{agent.matricule}</p>
              </div>
            );
          }
          if (ops) {
            return (
              <div className="min-w-[140px]">
                <p className="text-ink">
                  {ops.full_name?.trim() ||
                    `${ops.prenom ?? ""} ${ops.nom ?? ""}`.trim() ||
                    ops.email ||
                    "Opération"}
                </p>
                <p className="text-xs text-ink-muted">Contrôle siège</p>
              </div>
            );
          }
          return <span className="text-ink-muted">—</span>;
        },
      },
      {
        id: "preuve",
        header: "Preuve",
        cell: ({ row }) => <PhotoPreuve photo={row.original.photos?.[0]} />,
      },
      {
        accessorKey: "commentaire",
        header: "Commentaire",
        cell: ({ getValue }) => (
          <span className="line-clamp-2 max-w-xs text-ink-muted">
            {(getValue() as string | null) || "—"}
          </span>
        ),
      },
    ],
    [],
  );

  return (
    <PermissionGate permission="controles.view" title="Contrôles">
      <div>
        <PageHeader
          title="Contrôles de présence"
          description={
            vue === "site"
              ? "Par jour et par site : agents à contrôler, agents contrôlés, et le détail au clic sur un site."
              : "Chaque ligne = un agent vérifié au poste (contrôleur terrain ou Opération siège)."
          }
          actions={
            canSiege ? (
              <Link href="/controles/siege">
                <Button type="button">
                  <Building2 className="size-4" />
                  Contrôle siège
                </Button>
              </Link>
            ) : undefined
          }
        />
        <Tabs
          items={[
            { id: "site", label: "Par site" },
            { id: "liste", label: "Tous les contrôles" },
          ]}
          value={vue}
          onChange={(next) => setVue(next as "site" | "liste")}
        />
        <TabPanel when="site" active={vue}>
          <ControlesParSiteView
            onVoirListe={(siteId) => {
              setSiteFilter(siteId);
              setAujourdhuiOnly(false);
              setRecent30jOnly(false);
              setQ("");
              setPage(1);
              setVue("liste");
            }}
          />
        </TabPanel>
        <TabPanel when="liste" active={vue}>
          <DataTable
            data={data?.data ?? []}
            columns={columns}
            isLoading={isLoading}
            search={{
              value: q,
              onChange: (value) => {
                setQ(value);
                setPage(1);
              },
              placeholder: "Rechercher agent, contrôleur, site, matricule…",
            }}
            toolbar={
              <div className="flex flex-wrap items-center gap-2">
                <Select
                  className="min-w-[12rem]"
                  value={siteFilter}
                  options={siteFilterOptions}
                  onChange={(event) => {
                    setSiteFilter(event.target.value);
                    resetPage();
                  }}
                />
                <Select
                  className="min-w-[11rem]"
                  value={resultatFilter}
                  options={RESULTAT_CONTROLE_FILTER_OPTIONS.map((o) => ({
                    value: o.value,
                    label: o.label,
                  }))}
                  onChange={(event) => {
                    setResultatFilter(event.target.value);
                    resetPage();
                  }}
                />
                <Button
                  type="button"
                  variant={aujourdhuiOnly ? "primary" : "secondary"}
                  size="sm"
                  aria-pressed={aujourdhuiOnly}
                  onClick={toggleAujourdhui}
                >
                  <CalendarCheck className="size-4" />
                  Aujourd&apos;hui
                  {aujourdhuiOnly ? " (actif)" : ""}
                </Button>
                <Button
                  type="button"
                  variant={recent30jOnly ? "primary" : "secondary"}
                  size="sm"
                  aria-pressed={recent30jOnly}
                  onClick={toggleRecent30j}
                >
                  <CalendarRange className="size-4" />
                  30 derniers jours
                  {recent30jOnly ? " (actif)" : ""}
                </Button>
              </div>
            }
            pagination={{
              page,
              perPage: data?.meta.per_page ?? perPage,
              total: data?.meta.total ?? 0,
              onPageChange: setPage,
              onPerPageChange: (n) => {
                setPerPage(n);
                setPage(1);
              },
            }}
            emptyTitle="Aucun contrôle"
          />
        </TabPanel>
      </div>
    </PermissionGate>
  );
}
