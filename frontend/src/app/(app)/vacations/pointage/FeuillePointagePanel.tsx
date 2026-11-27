"use client";

import { useState } from "react";
import { FileDown } from "lucide-react";
import {
  useExportPointageFeuillePdf,
  usePointageFeuille,
} from "@/application/hooks/useResources";
import { Alert } from "@/presentation/components/ui/Alert";
import { Button } from "@/presentation/components/ui/Button";
import { Input } from "@/presentation/components/ui/Input";
import { useToast } from "@/presentation/providers/ToastProvider";
import {
  getApiErrorMessage,
  getApiErrorMessageAsync,
} from "@/shared/lib/api-error";
import {
  FeuillePointageGrille,
  LegendePointage,
} from "./FeuillePointageGrille";

/**
 * Feuille de pointage complète : recherche, export PDF, légende et grille.
 * Utilisée sur la page Pointage et dans RH → Paie.
 */
export function FeuillePointagePanel({
  dateDebut,
  dateFin,
  agentId,
  siteId,
  enabled = true,
}: {
  dateDebut: string;
  dateFin: string;
  agentId?: string;
  siteId?: string;
  enabled?: boolean;
}) {
  const { toast } = useToast();
  const [recherche, setRecherche] = useState("");
  const [inclureSansVacation, setInclureSansVacation] = useState(false);
  const exportPdf = useExportPointageFeuillePdf();

  const valide = !!dateDebut && !!dateFin && dateDebut <= dateFin;
  const params = {
    date_debut: dateDebut,
    date_fin: dateFin,
    agent_id: agentId || undefined,
    site_id: siteId || undefined,
    inclure_sans_vacation: inclureSansVacation || undefined,
  };
  const feuille = usePointageFeuille(enabled && valide ? params : undefined);

  const handleExportPdf = async () => {
    try {
      const data = await exportPdf.mutateAsync(params);
      const blob =
        data instanceof Blob
          ? data
          : new Blob([data as BlobPart], { type: "application/pdf" });
      const objectUrl = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = objectUrl;
      link.download = `pointage-${dateDebut}-au-${dateFin}.pdf`;
      link.rel = "noopener";
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(objectUrl);
      toast("PDF téléchargé.", "success");
    } catch (err) {
      toast(
        await getApiErrorMessageAsync(err, "Échec de la génération du PDF."),
        "danger",
      );
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-end gap-x-3 gap-y-3">
        <div className="w-72">
          <Input
            label="Rechercher un agent"
            placeholder="Nom ou matricule…"
            value={recherche}
            onChange={(e) => setRecherche(e.target.value)}
          />
        </div>
        <Button
          type="button"
          onClick={() => void handleExportPdf()}
          loading={exportPdf.isPending}
          disabled={!valide}
        >
          <FileDown className="size-4" />
          {exportPdf.isPending ? "Génération du PDF…" : "Exporter PDF"}
        </Button>
        <LegendePointage />
      </div>

      <label className="flex w-fit items-center gap-2 text-sm text-ink">
        <input
          type="checkbox"
          checked={inclureSansVacation}
          onChange={(e) => setInclureSansVacation(e.target.checked)}
        />
        Inclure les agents sans vacation sur la période
        <span className="text-xs text-ink-faint">
          (lignes vides — export plus long)
        </span>
      </label>

      {!valide ? (
        <Alert tone="warning">
          La date de début doit précéder ou égaler la date de fin.
        </Alert>
      ) : feuille.error ? (
        <Alert tone="danger">
          {getApiErrorMessage(
            feuille.error,
            "Impossible de charger la feuille de pointage.",
          )}
        </Alert>
      ) : feuille.isLoading || !feuille.data ? (
        <p className="py-8 text-center text-sm text-ink-muted">
          Chargement de la feuille…
        </p>
      ) : (
        <FeuillePointageGrille
          feuille={feuille.data.data}
          recherche={recherche}
        />
      )}
    </div>
  );
}
