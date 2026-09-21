"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import { type ColumnDef } from "@tanstack/react-table";
import { CalendarPlus } from "lucide-react";
import { useDebouncedValue } from "@/application/hooks/useDebouncedValue";
import { usePostes, usePosteCoverage } from "@/application/hooks/useResources";
import {
  hasDecoupageQuarts,
  isCycle24hInterval,
  planningCouvertureResume,
} from "@/domain/schemas/poste-horaires";
import type { Poste, PosteCoverage } from "@/domain/types/entities";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { DataTable } from "@/presentation/components/tables/DataTable";
import { Badge } from "@/presentation/components/ui/Badge";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { cn } from "@/shared/lib/cn";

const coverageLabel: Record<PosteCoverage["statut"], string> = {
  ok: "Couvert",
  sous_effectif: "Sous-effectif",
  sur_effectif: "Sur-effectif",
  non_couvert: "Non couvert",
};

const coverageTone: Record<
  PosteCoverage["statut"],
  "success" | "warning" | "danger" | "neutral"
> = {
  ok: "success",
  sous_effectif: "warning",
  sur_effectif: "danger",
  non_couvert: "danger",
};

type CoverageFilter = "all" | "problems";

function todayIso() {
  return new Date().toISOString().slice(0, 10);
}

function posteHoursLabel(poste: Poste) {
  const hours = {
    heure_debut: poste.heure_debut?.slice(0, 5) ?? "",
    heure_fin: poste.heure_fin?.slice(0, 5) ?? "",
    heure_debut_nuit: poste.heure_debut_nuit?.slice(0, 5) ?? "",
    heure_fin_nuit: poste.heure_fin_nuit?.slice(0, 5) ?? "",
  };
  if (!hours.heure_debut || !hours.heure_fin) return "—";
  return planningCouvertureResume(hours, poste.agents_requis ?? 1);
}

function posteModeLabel(poste: Poste) {
  const n = Math.max(1, poste.agents_requis ?? 1);
  const hours = {
    heure_debut: poste.heure_debut?.slice(0, 5) ?? "",
    heure_fin: poste.heure_fin?.slice(0, 5) ?? "",
    heure_debut_nuit: poste.heure_debut_nuit?.slice(0, 5) ?? "",
    heure_fin_nuit: poste.heure_fin_nuit?.slice(0, 5) ?? "",
  };
  if (hasDecoupageQuarts(hours)) return "Jour & nuit";
  if (n >= 2 && poste.mode_effectif === "alternance") return "Alternance";
  if (n >= 2) return "Ensemble";
  if (isCycle24hInterval(hours)) return "24h";
  return null;
}

function coverageDetail(c: PosteCoverage) {
  const cap = c.capacite_jour ?? c.agents_requis;
  if (c.couverture_24h && c.jour && c.nuit) {
    return `Jour ${c.jour.planifies} · Nuit ${c.nuit.planifies}`;
  }
  return `${c.planifies}/${cap}`;
}

export default function PostesPage() {
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [q, setQ] = useState("");
  const search = useDebouncedValue(q);
  const [filter, setFilter] = useState<CoverageFilter>("all");

  const { data, isLoading } = usePostes({
    page,
    per_page: perPage,
    q: search || undefined,
  });
  const { data: coverageData, isLoading: coverageLoading } = usePosteCoverage({
    date: todayIso(),
  });

  const coverageByPoste = useMemo(() => {
    const map = new Map<string, PosteCoverage>();
    for (const c of coverageData?.data ?? []) {
      map.set(c.poste_id, c);
    }
    return map;
  }, [coverageData]);

  const stats = useMemo(() => {
    const all = coverageData?.data ?? [];
    return {
      total: all.length,
      ok: all.filter((c) => c.statut === "ok").length,
      problems: all.filter((c) => c.statut !== "ok").length,
    };
  }, [coverageData]);

  const rows = useMemo(() => {
    const list = data?.data ?? [];
    if (filter !== "problems") return list;
    return list.filter((p) => {
      const c = coverageByPoste.get(p.id);
      return c && c.statut !== "ok";
    });
  }, [data, filter, coverageByPoste]);

  const columns = useMemo<ColumnDef<Poste>[]>(
    () => [
      {
        accessorKey: "nom",
        header: "Poste",
        cell: ({ row }) => {
          const poste = row.original;
          const mode = posteModeLabel(poste);
          return (
            <div className="min-w-0">
              <p className="font-medium text-ink">{poste.nom}</p>
              {mode ? (
                <p className="mt-0.5 text-[11px] text-ink-faint">{mode}</p>
              ) : null}
            </div>
          );
        },
      },
      {
        id: "site",
        header: "Site",
        cell: ({ row }) => (
          <span className="text-ink-muted">
            {row.original.site?.nom ?? "—"}
          </span>
        ),
      },
      {
        accessorKey: "agents_requis",
        header: "Effectif",
        cell: ({ getValue }) => (
          <span className="font-mono tabular-nums text-ink">
            {Number(getValue())}
          </span>
        ),
      },
      {
        id: "horaires",
        header: "Horaires",
        cell: ({ row }) => (
          <span className="text-xs leading-snug text-ink-muted">
            {posteHoursLabel(row.original)}
          </span>
        ),
      },
      {
        id: "couverture",
        header: "Aujourd’hui",
        cell: ({ row }) => {
          const c = coverageByPoste.get(row.original.id);
          if (coverageLoading && !c) {
            return <span className="text-xs text-ink-faint">…</span>;
          }
          if (!c) {
            return <span className="text-xs text-ink-faint">—</span>;
          }
          return (
            <div className="flex flex-col items-start gap-1">
              <Badge tone={coverageTone[c.statut]}>
                {coverageLabel[c.statut]}
              </Badge>
              <span className="font-mono text-[11px] tabular-nums text-ink-faint">
                {coverageDetail(c)}
              </span>
            </div>
          );
        },
      },
      {
        id: "actions",
        header: "",
        cell: ({ row }) => {
          const poste = row.original;
          const c = coverageByPoste.get(poste.id);
          const needsPlan = !c || c.statut !== "ok";
          if (!needsPlan || !poste.site_id) return null;
          return (
            <Link
              href={`/vacations/planifier?site_id=${poste.site_id}&poste_id=${poste.id}`}
              className="inline-flex h-8 items-center justify-center gap-1.5 whitespace-nowrap rounded-md border border-border bg-white px-3 text-xs font-medium text-ink transition hover:bg-paper"
            >
              <CalendarPlus className="size-3.5" />
              Planifier
            </Link>
          );
        },
      },
    ],
    [coverageByPoste, coverageLoading],
  );

  return (
    <PermissionGate permission={["postes.view", "sites.view"]} title="Postes">
      <div>
        <PageHeader
          title="Postes"
          description="Vue des postes et de la couverture du jour. Création depuis Sites."
          actions={
            <Link
              href="/sites"
              className="inline-flex h-8 items-center justify-center rounded-md border border-border bg-white px-3 text-xs font-medium text-ink transition hover:bg-paper"
            >
              Gérer les sites
            </Link>
          }
        />

        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
          <div className="flex flex-wrap items-center gap-2 text-sm">
            <span className="text-ink-muted">Aujourd’hui</span>
            <span
              className={cn(
                "rounded-md px-2 py-0.5 text-xs font-semibold tabular-nums",
                stats.problems > 0
                  ? "bg-danger/10 text-danger"
                  : "bg-teal/10 text-teal",
              )}
            >
              {coverageLoading
                ? "…"
                : stats.problems > 0
                  ? `${stats.problems} à couvrir`
                  : "Tout est couvert"}
            </span>
            {!coverageLoading && stats.total > 0 ? (
              <span className="text-xs text-ink-faint">
                {stats.ok}/{stats.total} OK
              </span>
            ) : null}
          </div>

          <div
            className="inline-flex h-9 items-center gap-0.5 rounded-md border border-border bg-white p-0.5"
            role="group"
            aria-label="Filtrer la couverture"
          >
            {(
              [
                { id: "all" as const, label: "Tous" },
                {
                  id: "problems" as const,
                  label: "À couvrir",
                },
              ] as const
            ).map((item) => {
              const active = filter === item.id;
              return (
                <button
                  key={item.id}
                  type="button"
                  onClick={() => setFilter(item.id)}
                  className={cn(
                    "h-full rounded px-2.5 text-xs font-medium transition",
                    active
                      ? "bg-ink text-white"
                      : "text-ink-muted hover:text-ink",
                  )}
                >
                  {item.label}
                  {item.id === "problems" && stats.problems > 0
                    ? ` (${stats.problems})`
                    : ""}
                </button>
              );
            })}
          </div>
        </div>

        <DataTable
          data={rows}
          columns={columns}
          isLoading={isLoading}
          search={{
            value: q,
            onChange: (value) => {
              setQ(value);
              setPage(1);
            },
            placeholder: "Rechercher poste, site…",
          }}
          pagination={{
            page,
            perPage: data?.meta.per_page ?? perPage,
            total:
              filter === "problems"
                ? rows.length
                : (data?.meta.total ?? 0),
            onPageChange: setPage,
            onPerPageChange: (n) => {
              setPerPage(n);
              setPage(1);
            },
          }}
          emptyTitle={
            filter === "problems"
              ? "Aucun poste à couvrir"
              : "Aucun poste"
          }
          emptyAction={
            filter === "problems" ? (
              <button
                type="button"
                className="text-sm font-medium text-teal hover:underline"
                onClick={() => setFilter("all")}
              >
                Voir tous les postes
              </button>
            ) : (
              <Link
                href="/sites"
                className="text-sm font-medium text-teal hover:underline"
              >
                Créer depuis Sites
              </Link>
            )
          }
        />
      </div>
    </PermissionGate>
  );
}
