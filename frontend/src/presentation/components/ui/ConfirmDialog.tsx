"use client";

import { Button } from "@/presentation/components/ui/Button";
import { Modal } from "@/presentation/components/ui/Modal";

export function ConfirmDialog({
  open,
  onClose,
  onConfirm,
  title,
  description,
  confirmLabel = "Supprimer",
  confirmVariant = "danger",
  loading,
}: {
  open: boolean;
  onClose: () => void;
  onConfirm: () => void | Promise<void>;
  title: string;
  description: string;
  confirmLabel?: string;
  confirmVariant?: "primary" | "danger";
  loading?: boolean;
}) {
  return (
    <Modal
      open={open}
      onClose={onClose}
      preventClose={loading}
      title={title}
      description={description}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={loading}>
            Annuler
          </Button>
          <Button
            variant={confirmVariant}
            loading={loading}
            onClick={() => void onConfirm()}
          >
            {loading ? `${confirmLabel}…` : confirmLabel}
          </Button>
        </>
      }
    >
      <p className="text-sm text-ink-muted">
        Cette action est définitive. Les données associées pourront être
        impactées.
      </p>
    </Modal>
  );
}
