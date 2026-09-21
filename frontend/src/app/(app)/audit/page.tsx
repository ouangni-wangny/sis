"use client";

import { useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { type ColumnDef } from "@tanstack/react-table";
import { Eye } from "lucide-react";
import { useDebouncedValue } from "@/application/hooks/useDebouncedValue";
import { useAudit } from "@/application/hooks/useResources";
import type { JournalAudit } from "@/domain/types/entities";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { DataTable } from "@/presentation/components/tables/DataTable";
import { Badge } from "@/presentation/components/ui/Badge";
import { Button } from "@/presentation/components/ui/Button";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { Select } from "@/presentation/components/ui/Select";
import { formatDateTime } from "@/shared/lib/format";

const ACTION_OPTIONS = [
  { value: "", label: "Toutes les actions" },
  { value: "created", label: "Création" },
  { value: "updated", label: "Modification" },
  { value: "deleted", label: "Suppression" },
  { value: "restored", label: "Restauration" },
];

const TYPE_OPTIONS = [
  { value: "", label: "Toutes les fiches" },
  { value: "Agent", label: "Personnel" },
  { value: "Contrat", label: "Contrat" },
  { value: "Absence", label: "Absence" },
  { value: "User", label: "Compte utilisateur" },
  { value: "Vacation", label: "Vacation / planning" },
  { value: "Zone", label: "Zone" },
  { value: "Site", label: "Site" },
  { value: "Poste", label: "Poste" },
  { value: "RondierPerimetre", label: "Périmètre contrôleur" },
  { value: "Client", label: "Client" },
  { value: "Offre", label: "Offre" },
  { value: "Abonnement", label: "Abonnement" },
  { value: "Facture", label: "Facture" },
  { value: "Paiement", label: "Paiement" },
  { value: "Controle", label: "Contrôle" },
  { value: "Anomalie", label: "Anomalie" },
  { value: "Ronde", label: "Ronde" },
  { value: "Grade", label: "Grade" },
  { value: "Ville", label: "Ville" },
];

function actionTone(
  action: string,
): "success" | "warning" | "danger" | "info" | "neutral" {
  switch (action) {
    case "created":
    case "restored":
      return "success";
    case "updated":
      return "warning";
    case "deleted":
      return "danger";
    default:
      return "neutral";
  }
}

function acteurLabel(entry: JournalAudit): string {
  if (entry.acteur) return entry.acteur;
  if (entry.user?.label) return entry.user.label;
  if (entry.user) {
    const name = `${entry.user.prenom ?? ""} ${entry.user.nom ?? ""}`.trim();
    return name || entry.user.email || "Utilisateur";
  }
  return "Système automatique";
}

export default function AuditPage() {
  const router = useRouter();
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(30);
  const [q, setQ] = useState("");
  const search = useDebouncedValue(q);
  const [actionFilter, setActionFilter] = useState("");
  const [typeFilter, setTypeFilter] = useState("");

  const { data, isLoading } = useAudit({
    page,
    per_page: perPage,
    q: search || undefined,
    action: actionFilter || undefined,
    auditable_type: typeFilter || undefined,
  });

  const columns = useMemo<ColumnDef<JournalAudit>[]>(
    () => [
      {
        accessorKey: "created_at",
        header: "Quand ?",
        cell: ({ getValue }) => formatDateTime(String(getValue())),
      },
      {
        id: "acteur",
        header: "Qui ?",
        cell: ({ row }) => (
          <div className="min-w-0">
            <div className="font-medium text-ink">{acteurLabel(row.original)}</div>
            {row.original.user?.email ? (
              <div className="truncate text-xs text-ink-muted">
                {row.original.user.email}
              </div>
            ) : !row.original.user_id ? (
              <div className="text-xs text-ink-muted">
                Tâche automatique ou script
              </div>
            ) : null}
          </div>
        ),
      },
      {
        accessorKey: "action",
        header: "Action",
        cell: ({ row }) => (
          <Badge tone={actionTone(row.original.action)}>
            {row.original.action_label ?? row.original.action}
          </Badge>
        ),
      },
      {
        id: "cible",
        header: "Sur quoi ?",
        cell: ({ row }) => (
          <span className="font-medium text-ink">
            {row.original.auditable_type_label ?? "Élément"}
          </span>
        ),
      },
      {
        id: "explication",
        header: "En résumé",
        cell: ({ row }) => (
          <span className="line-clamp-2 max-w-xl text-sm text-ink-muted">
            {row.original.explication ||
              row.original.resume ||
              "Voir le détail de l’événement"}
          </span>
        ),
      },
      {
        id: "actions",
        header: "",
        enableSorting: false,
        cell: ({ row }) => (
          <Button
            type="button"
            variant="secondary"
            size="sm"
            onClick={() => router.push(`/audit/${row.original.id}`)}
          >
            <Eye className="size-4" />
            Voir le détail
          </Button>
        ),
      },
    ],
    [router],
  );

  return (
    <PermissionGate permission="audit.view" title="Journal d’audit">
      <div>
        <PageHeader
          title="Journal d’audit"
          description="Historique clair de ce qui a été créé, modifié ou supprimé dans SIS — pour comprendre qui a fait quoi, et quand."
        />

        <div className="mb-4 rounded-lg border border-border bg-paper-muted px-4 py-3 text-sm text-ink-muted">
          Astuce : cliquez sur{" "}
          <span className="font-medium text-ink">Voir le détail</span> pour
          afficher l’explication complète, champ par champ (avant → après).
        </div>

        <DataTable
          data={data?.data ?? []}
          columns={columns}
          isLoading={isLoading}
          selectable={false}
          search={{
            value: q,
            onChange: (value) => {
              setQ(value);
              setPage(1);
            },
            placeholder: "Rechercher un utilisateur, un résumé, une IP…",
          }}
          toolbar={
            <>
              <div className="w-44 sm:w-52">
                <Select
                  aria-label="Filtrer par action"
                  options={ACTION_OPTIONS}
                  value={actionFilter}
                  onChange={(e) => {
                    setActionFilter(e.target.value);
                    setPage(1);
                  }}
                />
              </div>
              <div className="w-52 sm:w-60">
                <Select
                  aria-label="Filtrer par type de fiche"
                  options={TYPE_OPTIONS}
                  value={typeFilter}
                  onChange={(e) => {
                    setTypeFilter(e.target.value);
                    setPage(1);
                  }}
                />
              </div>
            </>
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
          emptyTitle="Aucune entrée d’audit"
          emptyDescription="Dès qu’une fiche est créée ou modifiée, elle apparaîtra ici."
        />
      </div>
    </PermissionGate>
  );
}
