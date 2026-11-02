"use client";

import Link from "next/link";
import { Suspense, useCallback, useMemo, useState } from "react";
import { useSearchParams } from "next/navigation";
import { type ColumnDef } from "@tanstack/react-table";
import { ArrowLeft, HandCoins, Plus, Undo2 } from "lucide-react";
import { useDebouncedValue } from "@/application/hooks/useDebouncedValue";
import {
  useAgents,
  useAnnulerAvanceSalaire,
  useAvancesSalaire,
  useComptesTresorerieOptions,
  useCreateAvanceSalaire,
  useModesPaiementOptions,
  useSituationSalaireMois,
} from "@/application/hooks/useResources";
import type { AvanceSalaire } from "@/domain/types/entities";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { DataTable } from "@/presentation/components/tables/DataTable";
import { TableActions } from "@/presentation/components/tables/TableActions";
import { Alert } from "@/presentation/components/ui/Alert";
import { Badge, statusTone } from "@/presentation/components/ui/Badge";
import { Button } from "@/presentation/components/ui/Button";
import { DatePicker } from "@/presentation/components/ui/DatePicker";
import { Input } from "@/presentation/components/ui/Input";
import { Modal } from "@/presentation/components/ui/Modal";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { Select } from "@/presentation/components/ui/Select";
import { StatCard } from "@/presentation/components/ui/StatCard";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { useToast } from "@/presentation/providers/ToastProvider";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { canPayerPaie } from "@/shared/lib/can";
import { formatDate, formatSalaire, MOIS_LABELS } from "@/shared/lib/format";

const STATUT_LABELS: Record<string, string> = {
  en_cours: "À retenir",
  deduite: "Retenue",
  annulee: "Annulée",
};

const STATUT_OPTIONS = [
  { value: "", label: "Tous les statuts" },
  { value: "en_cours", label: "À retenir" },
  { value: "deduite", label: "Retenues" },
  { value: "annulee", label: "Annulées" },
];

const moisOptions = MOIS_LABELS.map((label, i) => ({
  value: String(i + 1),
  label,
}));

function anneeOptions(reference: number) {
  return [reference - 1, reference, reference + 1].map((a) => ({
    value: String(a),
    label: String(a),
  }));
}

function AvancesSalaireContent() {
  const { user } = useAuth();
  const { toast } = useToast();
  const canPayer = canPayerPaie(user);
  const searchParams = useSearchParams();

  const now = useMemo(() => new Date(), []);
  const moisCourant = String(now.getMonth() + 1);
  const anneeCourante = now.getFullYear();
  const aujourdhui = useMemo(() => {
    const m = String(now.getMonth() + 1).padStart(2, "0");
    const d = String(now.getDate()).padStart(2, "0");
    return `${now.getFullYear()}-${m}-${d}`;
  }, [now]);

  // Lien « Avance » depuis un bulletin : agent et mois déjà choisis.
  const agentParam = searchParams.get("agent_id") ?? "";
  const moisParam = searchParams.get("mois") ?? "";
  const anneeParam = searchParams.get("annee") ?? "";

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [q, setQ] = useState("");
  const search = useDebouncedValue(q);
  const [filterMois, setFilterMois] = useState(moisParam || moisCourant);
  const [filterAnnee, setFilterAnnee] = useState(
    anneeParam || String(anneeCourante),
  );
  const [filterStatut, setFilterStatut] = useState("");
  const [filterAgentId, setFilterAgentId] = useState(agentParam);

  const [open, setOpen] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [form, setForm] = useState({
    agent_id: "",
    montant: "",
    date_avance: aujourdhui,
    mois: moisCourant,
    annee: String(anneeCourante),
    mode: "",
    compte_tresorerie_id: "",
    reference: "",
    notes: "",
  });
  const [aAnnuler, setAAnnuler] = useState<AvanceSalaire | null>(null);
  const [motif, setMotif] = useState("");
  const [annulerError, setAnnulerError] = useState<string | null>(null);

  const { data, isLoading } = useAvancesSalaire({
    page,
    per_page: perPage,
    q: search || undefined,
    mois: filterMois ? Number(filterMois) : undefined,
    annee: filterAnnee ? Number(filterAnnee) : undefined,
    statut: filterStatut || undefined,
    agent_id: filterAgentId || undefined,
  });
  const { data: agentsData } = useAgents({ all: true });
  const { data: comptesRes } = useComptesTresorerieOptions({ enabled: canPayer });
  const { data: modesRes } = useModesPaiementOptions();
  const createAvance = useCreateAvanceSalaire();
  const annulerAvance = useAnnulerAvanceSalaire();

  const situation = useSituationSalaireMois(
    {
      agent_id: form.agent_id,
      mois: Number(form.mois),
      annee: Number(form.annee),
    },
    { enabled: open && !!form.agent_id },
  ).data?.data;

  const agentOptions = useMemo(
    () =>
      (agentsData?.data ?? []).map((a) => ({
        value: a.id,
        label: `${a.prenom} ${a.nom} (${a.matricule})`,
      })),
    [agentsData],
  );
  const comptes = useMemo(() => comptesRes?.data ?? [], [comptesRes]);
  const compteOptions = comptes.map((c) => ({
    value: c.id,
    label: c.libelle,
  }));
  const modeOptions = (modesRes?.data ?? []).map((m) => ({
    value: m.code,
    label: m.libelle,
  }));

  const openCreate = useCallback(() => {
    setForm({
      agent_id: agentParam,
      montant: "",
      date_avance: aujourdhui,
      mois: moisParam || moisCourant,
      annee: anneeParam || String(anneeCourante),
      mode: modesRes?.data?.[0]?.code ?? "",
      compte_tresorerie_id: comptes[0]?.id ?? "",
      reference: "",
      notes: "",
    });
    setFormError(null);
    setOpen(true);
  }, [
    agentParam,
    anneeCourante,
    anneeParam,
    aujourdhui,
    comptes,
    modesRes,
    moisCourant,
    moisParam,
  ]);

  const submit = async () => {
    if (!form.agent_id) {
      setFormError("Choisissez un agent.");
      return;
    }
    if (!(Number(form.montant) > 0)) {
      setFormError("Saisissez un montant supérieur à 0.");
      return;
    }
    if (!form.mode || !form.compte_tresorerie_id) {
      setFormError("Choisissez le mode de paiement et le compte débité.");
      return;
    }
    setFormError(null);
    try {
      await createAvance.mutateAsync({
        agent_id: form.agent_id,
        montant: Number(form.montant),
        date_avance: form.date_avance,
        mois: Number(form.mois),
        annee: Number(form.annee),
        mode: form.mode,
        compte_tresorerie_id: form.compte_tresorerie_id,
        reference: form.reference || null,
        notes: form.notes || null,
      });
      toast("Avance enregistrée.");
      setOpen(false);
    } catch (err) {
      setFormError(getApiErrorMessage(err, "Échec de l’enregistrement."));
    }
  };

  const confirmerAnnulation = async () => {
    if (!aAnnuler) return;
    setAnnulerError(null);
    try {
      await annulerAvance.mutateAsync({
        id: aAnnuler.id,
        motif: motif || null,
      });
      toast("Avance annulée : le montant est revenu sur le compte.");
      setAAnnuler(null);
    } catch (err) {
      setAnnulerError(getApiErrorMessage(err, "Échec de l’annulation."));
    }
  };

  const columns = useMemo<ColumnDef<AvanceSalaire>[]>(
    () => [
      {
        accessorKey: "date_avance",
        header: "Date",
        cell: ({ getValue }) => formatDate(String(getValue())),
      },
      {
        id: "agent",
        header: "Agent",
        cell: ({ row }) =>
          row.original.agent ? (
            <Link
              href={`/agents/${row.original.agent.id}`}
              className="hover:underline"
            >
              {row.original.agent.prenom} {row.original.agent.nom}
              <span className="ml-1 text-xs text-ink-faint">
                {row.original.agent.matricule}
              </span>
            </Link>
          ) : (
            "—"
          ),
      },
      {
        id: "retenue",
        header: "Retenue sur",
        cell: ({ row }) =>
          `${MOIS_LABELS[row.original.mois - 1] ?? row.original.mois} ${row.original.annee}`,
      },
      {
        accessorKey: "montant",
        header: "Montant",
        cell: ({ getValue }) =>
          formatSalaire(getValue() as string | number, user),
      },
      {
        id: "net",
        header: "Salaire net du mois",
        cell: ({ row }) =>
          row.original.situation?.salaire_net != null
            ? formatSalaire(row.original.situation.salaire_net, user)
            : "Non saisi",
      },
      {
        id: "avances_mois",
        header: "Total donné",
        cell: ({ row }) =>
          formatSalaire(row.original.situation?.avances_total ?? 0, user),
      },
      {
        id: "reste",
        header: "Reste à payer",
        cell: ({ row }) => (
          <span className="font-medium">
            {row.original.situation?.reste_a_payer != null
              ? formatSalaire(row.original.situation.reste_a_payer, user)
              : "—"}
          </span>
        ),
      },
      {
        id: "paiement",
        header: "Compte / mode",
        cell: ({ row }) => (
          <span className="inline-flex flex-col gap-0.5 text-xs">
            <span>{row.original.compte?.libelle ?? "—"}</span>
            <span className="uppercase text-ink-faint">{row.original.mode}</span>
          </span>
        ),
      },
      {
        accessorKey: "reference",
        header: "Référence",
        cell: ({ getValue }) => (
          <span className="font-mono text-xs">
            {(getValue() as string | null) || "—"}
          </span>
        ),
      },
      {
        accessorKey: "statut",
        header: "Statut",
        cell: ({ getValue }) => {
          const v = String(getValue());
          return <Badge tone={statusTone(v)}>{STATUT_LABELS[v] ?? v}</Badge>;
        },
      },
      {
        id: "actions",
        header: "Actions",
        enableSorting: false,
        cell: ({ row }) => (
          <TableActions
            canEdit={false}
            canDelete={false}
            items={[
              {
                key: "annuler",
                label: "Annuler l’avance",
                icon: Undo2,
                tone: "danger",
                hidden: !canPayer || row.original.statut !== "en_cours",
                onClick: () => {
                  setMotif("");
                  setAnnulerError(null);
                  setAAnnuler(row.original);
                },
              },
            ]}
          />
        ),
      },
    ],
    [canPayer, user],
  );

  const filtreAgent = agentOptions.find((o) => o.value === filterAgentId);
  const anneesFiltre = anneeOptions(anneeCourante);

  return (
    <div>
      <PageHeader
        title="Avances sur salaire"
        description="Ce qui a été donné à chaque agent et le salaire restant à payer. Une avance sort de la trésorerie à sa remise ; le règlement du bulletin ne verse que le reste."
        actions={
          <div className="flex flex-wrap gap-2">
            <Link href="/rh/paie">
              <Button variant="secondary" size="sm">
                <ArrowLeft className="size-4" />
                Paie
              </Button>
            </Link>
            {canPayer ? (
              <Button size="sm" onClick={openCreate}>
                <Plus className="size-4" />
                Nouvelle avance
              </Button>
            ) : null}
          </div>
        }
      />

      <div className="mb-4 grid gap-3 sm:grid-cols-3">
        <StatCard
          label="Total donné"
          value={formatSalaire(data?.total_donne ?? 0, user)}
          hint="Selon les filtres, hors avances annulées"
          icon={<HandCoins className="size-4" />}
        />
      </div>

      {filtreAgent ? (
        <div className="mb-3 flex flex-wrap items-center gap-2 text-sm text-ink-muted">
          Agent : <span className="font-medium text-ink">{filtreAgent.label}</span>
          <Button
            variant="secondary"
            size="sm"
            onClick={() => {
              setFilterAgentId("");
              setPage(1);
            }}
          >
            Voir tous les agents
          </Button>
        </div>
      ) : null}

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
          placeholder: "Nom, matricule ou référence…",
        }}
        toolbar={
          <>
            <div className="w-40">
              <Select
                aria-label="Filtrer par mois de retenue"
                options={[{ value: "", label: "Tous les mois" }, ...moisOptions]}
                value={filterMois}
                onChange={(e) => {
                  setFilterMois(e.target.value);
                  setPage(1);
                }}
              />
            </div>
            <div className="w-32">
              <Select
                aria-label="Filtrer par année"
                options={[
                  { value: "", label: "Toutes" },
                  ...anneesFiltre,
                ]}
                value={filterAnnee}
                onChange={(e) => {
                  setFilterAnnee(e.target.value);
                  setPage(1);
                }}
              />
            </div>
            <div className="w-40">
              <Select
                aria-label="Filtrer par statut"
                options={STATUT_OPTIONS}
                value={filterStatut}
                onChange={(e) => {
                  setFilterStatut(e.target.value);
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
        emptyTitle="Aucune avance"
        emptyDescription="Aucune avance sur salaire pour ces critères."
      />

      <Modal
        open={open}
        onClose={() => !createAvance.isPending && setOpen(false)}
        preventClose={createAvance.isPending}
        size="2xl"
        title="Nouvelle avance sur salaire"
        description="Le montant sort du compte choisi et sera retenu sur le salaire du mois indiqué."
        footer={
          <>
            <Button
              variant="secondary"
              disabled={createAvance.isPending}
              onClick={() => setOpen(false)}
            >
              Annuler
            </Button>
            <Button loading={createAvance.isPending} onClick={() => void submit()}>
              Enregistrer l’avance
            </Button>
          </>
        }
      >
        <div className="space-y-3">
          {formError ? <Alert tone="danger">{formError}</Alert> : null}
          <Select
            label="Agent"
            requiredMark
            placeholder="Choisir un agent"
            options={agentOptions}
            value={form.agent_id}
            onChange={(e) =>
              setForm((f) => ({ ...f, agent_id: e.target.value }))
            }
          />
          <div className="grid gap-3 sm:grid-cols-3">
            <Input
              label="Montant (FCFA)"
              requiredMark
              type="number"
              min={1}
              inputMode="numeric"
              value={form.montant}
              onChange={(e) =>
                setForm((f) => ({ ...f, montant: e.target.value }))
              }
            />
            <Select
              label="Retenue sur le mois"
              requiredMark
              options={moisOptions}
              value={form.mois}
              onChange={(e) => setForm((f) => ({ ...f, mois: e.target.value }))}
            />
            <Select
              label="Année"
              requiredMark
              options={anneesFiltre}
              value={form.annee}
              onChange={(e) =>
                setForm((f) => ({ ...f, annee: e.target.value }))
              }
            />
          </div>

          {form.agent_id && situation ? (
            <div className="grid gap-2 rounded-lg border border-border bg-paper-muted/40 p-3 text-sm sm:grid-cols-3">
              <div>
                <p className="text-[11px] text-ink-faint">
                  Salaire net
                  {situation.source === "contrat" ? " (contrat)" : ""}
                </p>
                <p className="font-medium">
                  {situation.salaire_net != null
                    ? formatSalaire(situation.salaire_net, user)
                    : "Non saisi"}
                </p>
              </div>
              <div>
                <p className="text-[11px] text-ink-faint">Déjà donné ce mois</p>
                <p className="font-medium">
                  {formatSalaire(situation.avances_total, user)}
                </p>
              </div>
              <div>
                <p className="text-[11px] text-ink-faint">
                  Reste à payer après cette avance
                </p>
                <p className="font-semibold text-ink">
                  {situation.reste_a_payer != null
                    ? formatSalaire(
                        Math.max(
                          0,
                          situation.reste_a_payer - Number(form.montant || 0),
                        ),
                        user,
                      )
                    : "—"}
                </p>
              </div>
            </div>
          ) : null}
          {form.agent_id && situation && situation.salaire_net == null ? (
            <Alert tone="warning">
              Le salaire de cet agent n’est pas encore saisi : le montant de
              l’avance ne peut pas être contrôlé.
            </Alert>
          ) : null}

          <div className="grid gap-3 sm:grid-cols-3">
            <DatePicker
              label="Date de remise"
              requiredMark
              value={form.date_avance}
              onChange={(e) =>
                setForm((f) => ({ ...f, date_avance: e.target.value }))
              }
            />
            <Select
              label="Mode de paiement"
              requiredMark
              options={modeOptions}
              value={form.mode}
              onChange={(e) => setForm((f) => ({ ...f, mode: e.target.value }))}
            />
            <Select
              label="Compte débité"
              requiredMark
              options={compteOptions}
              value={form.compte_tresorerie_id}
              onChange={(e) =>
                setForm((f) => ({
                  ...f,
                  compte_tresorerie_id: e.target.value,
                }))
              }
            />
          </div>
          <div className="grid gap-3 sm:grid-cols-2">
            <Input
              label="Référence"
              optionalMark
              hint="Générée automatiquement si vide."
              value={form.reference}
              onChange={(e) =>
                setForm((f) => ({ ...f, reference: e.target.value }))
              }
            />
            <Input
              label="Notes"
              optionalMark
              value={form.notes}
              onChange={(e) => setForm((f) => ({ ...f, notes: e.target.value }))}
            />
          </div>
        </div>
      </Modal>

      <Modal
        open={!!aAnnuler}
        onClose={() => !annulerAvance.isPending && setAAnnuler(null)}
        preventClose={annulerAvance.isPending}
        title="Annuler l’avance"
        description={
          aAnnuler
            ? `${formatSalaire(aAnnuler.montant, user)} reviendront sur le compte ${aAnnuler.compte?.libelle ?? ""}.`
            : undefined
        }
        footer={
          <>
            <Button
              variant="secondary"
              disabled={annulerAvance.isPending}
              onClick={() => setAAnnuler(null)}
            >
              Fermer
            </Button>
            <Button
              loading={annulerAvance.isPending}
              onClick={() => void confirmerAnnulation()}
            >
              Confirmer l’annulation
            </Button>
          </>
        }
      >
        <div className="space-y-3">
          {annulerError ? <Alert tone="danger">{annulerError}</Alert> : null}
          <Input
            label="Motif"
            optionalMark
            value={motif}
            onChange={(e) => setMotif(e.target.value)}
          />
        </div>
      </Modal>
    </div>
  );
}

export default function AvancesSalairePage() {
  return (
    <PermissionGate
      permission={["paie.manage", "paie.payer"]}
      title="Avances sur salaire"
    >
      <Suspense fallback={null}>
        <AvancesSalaireContent />
      </Suspense>
    </PermissionGate>
  );
}
