"use client";

import { useState } from "react";
import { Trash2 } from "lucide-react";
import { vacationsApi } from "@/infrastructure/http/resources";
import { Alert } from "@/presentation/components/ui/Alert";
import { Button } from "@/presentation/components/ui/Button";
import { ConfirmDialog } from "@/presentation/components/ui/ConfirmDialog";
import { useToast } from "@/presentation/providers/ToastProvider";
import { getApiErrorMessage } from "@/shared/lib/api-error";

/**
 * Alerte de conflit planning + annulation groupée / réapplication forcée.
 */
export function BlockingVacationAlert({
  message,
  blockingVacationIds,
  /**
   * Relance l’enregistrement côté API avec annulation auto des conflits
   * (préféré : une seule transaction).
   */
  onForceApply,
  /** Fallback : après annulation manuelle des IDs listés. */
  onCleared,
}: {
  message: string;
  blockingVacationIds: string[];
  onForceApply?: () => void | Promise<void>;
  onCleared?: () => void | Promise<void>;
}) {
  const { toast } = useToast();
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [pending, setPending] = useState(false);

  const ids = [...new Set(blockingVacationIds.filter(Boolean))];
  const count = ids.length;
  const canAct = count > 0 && (!!onForceApply || !!onCleared);

  if (!message) return null;

  const label =
    count <= 1
      ? "Annuler la vacation et réappliquer"
      : `Annuler les ${count} vacations et réappliquer`;

  return (
    <>
      <Alert tone="danger">
        <div className="space-y-2.5">
          <p>{message}</p>
          {canAct ? (
            <div className="flex flex-wrap items-center gap-2">
              <Button
                type="button"
                size="sm"
                variant="danger"
                disabled={pending}
                onClick={() => setConfirmOpen(true)}
              >
                <Trash2 className="size-3.5" />
                {label}
              </Button>
              <p className="text-xs opacity-80">
                {count > 1
                  ? "Toutes les vacations en conflit d’un coup, puis nouvel essai."
                  : "Libère l’agent puis relance automatiquement."}
              </p>
            </div>
          ) : null}
        </div>
      </Alert>

      <ConfirmDialog
        open={confirmOpen}
        onClose={() => {
          if (pending) return;
          setConfirmOpen(false);
        }}
        loading={pending}
        title={
          count > 1
            ? `Annuler ${count} vacations bloquantes`
            : "Annuler la vacation bloquante"
        }
        description={
          count > 1
            ? `${count} vacations (autres postes / créneaux) seront annulées, puis le planning sera réappliqué.`
            : "La vacation bloquante sera annulée, puis le planning sera réappliqué."
        }
        confirmLabel={label}
        onConfirm={async () => {
          setPending(true);
          try {
            if (onForceApply) {
              await onForceApply();
            } else {
              for (const id of ids) {
                await vacationsApi.update(id, { statut: "annulee" });
              }
              toast(
                count > 1
                  ? `${count} vacations annulées.`
                  : "Vacation annulée.",
              );
              await onCleared?.();
            }
            setConfirmOpen(false);
          } catch (err) {
            toast(
              getApiErrorMessage(err, "Échec de l’annulation / réapplication."),
              "danger",
            );
          } finally {
            setPending(false);
          }
        }}
      />
    </>
  );
}
