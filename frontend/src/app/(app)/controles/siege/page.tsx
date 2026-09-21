"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import { type ColumnDef } from "@tanstack/react-table";
import { ArrowLeft, CheckCircle2, XCircle } from "lucide-react";
import {
  useCreateControlePresence,
  useVacations,
} from "@/application/hooks/useResources";
import type { Vacation } from "@/domain/types/entities";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { DataTable } from "@/presentation/components/tables/DataTable";
import { Alert } from "@/presentation/components/ui/Alert";
import { Button } from "@/presentation/components/ui/Button";
import { Modal } from "@/presentation/components/ui/Modal";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { Textarea } from "@/presentation/components/ui/Textarea";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { useToast } from "@/presentation/providers/ToastProvider";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { can, hasRole } from "@/shared/lib/can";

export default function ControlesSiegePage() {
  const { user, featureFlags } = useAuth();
  const { toast } = useToast();
  const canCreate = can(user, "controles.create");
  const canOps =
    featureFlags["module.controles_siege"] !== false &&
    (hasRole(user, "operation") ||
      hasRole(user, "super-admin") ||
      hasRole(user, "developpeur"));

  const { data, isLoading, refetch } = useVacations(
    {
      en_poste: 1,
      site_interne: 1,
      per_page: 100,
    },
    { enabled: canOps },
  );

  const createControle = useCreateControlePresence();
  const [selected, setSelected] = useState<Vacation | null>(null);
  const [commentaire, setCommentaire] = useState("");
  const [busyResult, setBusyResult] = useState<"present" | "absent" | null>(
    null,
  );

  const rows = data?.data ?? [];

  const columns = useMemo<ColumnDef<Vacation>[]>(
    () => [
      {
        id: "agent",
        header: "Agent",
        cell: ({ row }) => (
          <div>
            <p className="font-medium text-ink">
              {row.original.agent
                ? `${row.original.agent.prenom} ${row.original.agent.nom}`
                : "—"}
            </p>
            <p className="text-xs text-ink-muted">
              {row.original.agent?.matricule ?? "—"}
            </p>
          </div>
        ),
      },
      {
        id: "site",
        header: "Site / poste",
        cell: ({ row }) => (
          <div>
            <p className="text-ink">{row.original.site?.nom ?? "—"}</p>
            <p className="text-xs text-ink-muted">
              {row.original.poste?.nom ?? "Poste non précisé"}
            </p>
          </div>
        ),
      },
      {
        id: "creneau",
        header: "Créneau",
        cell: ({ row }) => (
          <span className="tabular-nums text-ink-muted">
            {(row.original.heure_debut ?? "").slice(0, 5)} –{" "}
            {(row.original.heure_fin ?? "").slice(0, 5)}
          </span>
        ),
      },
      {
        id: "actions",
        header: "Actions",
        enableSorting: false,
        cell: ({ row }) =>
          canCreate ? (
            <Button
              size="sm"
              onClick={() => {
                setSelected(row.original);
                setCommentaire("");
              }}
            >
              Contrôler
            </Button>
          ) : null,
      },
    ],
    [canCreate],
  );

  const closeModal = () => {
    if (busyResult) return;
    setSelected(null);
    setCommentaire("");
  };

  const submit = async (resultat: "present" | "absent") => {
    if (!selected?.agent_id || !selected.site_id) return;
    setBusyResult(resultat);
    try {
      await createControle.mutateAsync({
        controle_agent_id: selected.agent_id,
        site_id: selected.site_id,
        poste_id: selected.poste_id,
        resultat,
        commentaire: commentaire.trim() || undefined,
      });
      toast(
        resultat === "present"
          ? "Présence enregistrée (contrôle siège)."
          : "Absence enregistrée (contrôle siège).",
      );
      setSelected(null);
      setCommentaire("");
      await refetch();
    } catch (err) {
      toast(
        getApiErrorMessage(err, "Échec de l’enregistrement du contrôle."),
        "danger",
      );
    } finally {
      setBusyResult(null);
    }
  };

  if (!canOps) {
    return (
      <PermissionGate permission="controles.view" title="Contrôle siège">
        <Alert tone="warning">
          Seul le rôle Opération peut enregistrer des contrôles sur les sites
          internes (siège).
        </Alert>
      </PermissionGate>
    );
  }

  return (
    <PermissionGate permission="controles.create" title="Contrôle siège">
      <div className="space-y-6">
        <div>
          <Link
            href="/controles"
            className="mb-3 inline-flex items-center gap-1.5 text-sm text-ink-muted hover:text-ink"
          >
            <ArrowLeft className="size-4" />
            Retour aux contrôles
          </Link>
          <PageHeader
            title="Contrôle siège"
            description="Agents en vacation sur un site interne — saisie Opération sans GPS ni photo."
          />
        </div>

        <Alert tone="info">
          Les sites doivent être cochés « Site interne (siège) » et les agents
          planifiés en vacation sur ce site pour apparaître ici.
        </Alert>

        <DataTable
          data={rows}
          columns={columns}
          isLoading={isLoading}
          emptyTitle="Aucun agent en poste sur un site interne"
        />

        <Modal
          open={!!selected}
          onClose={closeModal}
          preventClose={!!busyResult}
          title="Contrôle siège"
          description={
            selected?.agent
              ? `${selected.agent.prenom} ${selected.agent.nom} — ${selected.site?.nom ?? "site"}`
              : undefined
          }
          footer={
            <>
              <Button
                variant="secondary"
                onClick={closeModal}
                disabled={!!busyResult}
              >
                Annuler
              </Button>
              <Button
                variant="secondary"
                loading={busyResult === "absent"}
                disabled={!!busyResult}
                onClick={() => void submit("absent")}
              >
                <XCircle className="size-4" />
                Absent
              </Button>
              <Button
                loading={busyResult === "present"}
                disabled={!!busyResult}
                onClick={() => void submit("present")}
              >
                <CheckCircle2 className="size-4" />
                Présent
              </Button>
            </>
          }
        >
          <Textarea
            label="Commentaire"
            optionalMark
            rows={3}
            value={commentaire}
            onChange={(e) => setCommentaire(e.target.value)}
            placeholder="Optionnel — utile surtout en cas d’absence."
          />
        </Modal>
      </div>
    </PermissionGate>
  );
}
