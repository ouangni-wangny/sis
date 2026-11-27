"use client";

import { useState } from "react";
import { Award, Pencil } from "lucide-react";
import {
  useAgentQualifications,
  usePrimesQualification,
  useSyncAgentQualifications,
  useQualifications,
} from "@/application/hooks/useResources";
import { Button } from "@/presentation/components/ui/Button";
import { Card, CardBody, CardHeader } from "@/presentation/components/ui/Card";
import { MultiSelect } from "@/presentation/components/ui/MultiSelect";
import { useToast } from "@/presentation/providers/ToastProvider";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { formatFcfa } from "@/shared/lib/format";

/**
 * Qualifications de l’agent (Chef de poste, Garde armée…). Leurs primes sont
 * ajoutées automatiquement à ses bulletins : par mois (montant fixe) ou par jour
 * (montant × jours pointés du 26 au 25).
 */
export function AgentQualificationsCard({
  agentId,
  canEdit,
  canVoirMontants,
}: {
  agentId: string;
  canEdit: boolean;
  canVoirMontants: boolean;
}) {
  const { toast } = useToast();
  const { data, isLoading } = useAgentQualifications(agentId);
  const [edition, setEdition] = useState(false);
  const [choix, setChoix] = useState<string[]>([]);
  const { data: typesData } = useQualifications({ all: true });
  const { data: baremesData } = usePrimesQualification({ all: true }, canVoirMontants);
  const sync = useSyncAgentQualifications(agentId);

  const qualifications = data?.data ?? [];
  const valeurs = (typeId: string) =>
    (baremesData?.data ?? [])
      .filter((b) => b.qualification_id === typeId && b.actif)
      .map(
        (b) =>
          `${formatFcfa(b.montant)} ${b.unite === "jour" ? "/ jour" : "/ mois"}`,
      )
      .join(" + ");

  const ouvrir = () => {
    setChoix(qualifications.map((p) => p.id));
    setEdition(true);
  };

  const enregistrer = async () => {
    try {
      await sync.mutateAsync(choix);
      toast("Qualifications de l’agent enregistrées.", "success");
      setEdition(false);
    } catch (err) {
      toast(getApiErrorMessage(err, "Échec de l’enregistrement."), "danger");
    }
  };

  return (
    <Card>
      <CardHeader
        title="Qualifications"
        description="Chef de poste, Garde armée, Maître-chien… Leurs primes sont ajoutées aux bulletins."
        action={
          canEdit && !edition ? (
            <Button size="sm" variant="secondary" onClick={ouvrir}>
              <Pencil className="size-4" />
              Modifier
            </Button>
          ) : null
        }
      />
      <CardBody className="space-y-3">
        {edition ? (
          <>
            <MultiSelect
              label="Qualifications de l’agent"
              placeholder="Choisir les qualifications"
              searchable
              options={(typesData?.data ?? [])
                .filter((t) => t.actif || choix.includes(t.id))
                .map((t) => ({
                  value: t.id,
                  label: `${t.abreviation} — ${t.libelle}`,
                }))}
              value={choix}
              onChange={setChoix}
            />
            <div className="flex justify-end gap-2">
              <Button
                size="sm"
                variant="secondary"
                disabled={sync.isPending}
                onClick={() => setEdition(false)}
              >
                Annuler
              </Button>
              <Button
                size="sm"
                loading={sync.isPending}
                onClick={() => void enregistrer()}
              >
                Enregistrer
              </Button>
            </div>
          </>
        ) : isLoading ? (
          <p className="text-sm text-ink-muted">Chargement…</p>
        ) : qualifications.length === 0 ? (
          <p className="text-sm text-ink-faint">
            Aucune qualification. {canEdit ? "Cliquez sur « Modifier » pour en ajouter." : ""}
          </p>
        ) : (
          <ul className="flex flex-wrap gap-2">
            {qualifications.map((p) => (
              <li
                key={p.id}
                className="flex items-center gap-2 rounded-md border border-border bg-white px-3 py-2 text-sm shadow-sm"
              >
                <Award className="size-4 text-teal" />
                <span className="rounded bg-teal/10 px-1.5 font-mono font-semibold text-teal-dark">
                  {p.abreviation}
                </span>
                <span className="font-medium text-ink">{p.libelle}</span>
                {canVoirMontants && valeurs(p.id) ? (
                  <span className="text-xs text-ink-muted">· {valeurs(p.id)}</span>
                ) : null}
                {!p.actif ? (
                  <span className="text-xs text-ink-faint">(inactive)</span>
                ) : null}
              </li>
            ))}
          </ul>
        )}
      </CardBody>
    </Card>
  );
}
