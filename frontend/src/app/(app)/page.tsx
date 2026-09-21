"use client";

import Link from "next/link";
import {
  AlertTriangle,
  Banknote,
  ClipboardCheck,
  FileText,
  RefreshCw,
  UserRound,
  Users,
  Wallet,
} from "lucide-react";
import { useDashboardStats } from "@/application/hooks/useResources";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { Badge, statusTone } from "@/presentation/components/ui/Badge";
import { Card, CardBody, CardHeader } from "@/presentation/components/ui/Card";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { Spinner } from "@/presentation/components/ui/Spinner";
import { StatCard } from "@/presentation/components/ui/StatCard";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { can, canSeeSalaire, canViewAbsences, canViewContrats, canViewPaie } from "@/shared/lib/can";
import { formatFcfa, formatSalaire, labelize } from "@/shared/lib/format";

const ALERTE_LABELS: Record<string, string> = {
  fin_essai: "Fin essai",
  fin_contrat: "Fin contrat",
};

const PAIEMENT_LABELS: Record<string, string> = {
  non_payee: "Non payées",
  partiel: "Partielles",
  soldee: "Soldées",
};

function MiniBars({
  items,
  formatValue,
}: {
  items: { date: string; value: number }[];
  formatValue: (n: number) => string;
}) {
  const max = Math.max(1, ...items.map((i) => i.value));
  return (
    <div className="flex h-36 items-end gap-1.5">
      {items.map((day) => (
        <div
          key={day.date}
          className="flex flex-1 flex-col items-center gap-1.5"
        >
          <span className="max-w-full truncate font-mono text-[10px] tabular-nums text-ink-muted">
            {formatValue(day.value)}
          </span>
          <div
            className="w-full rounded-t bg-teal/80"
            style={{ height: `${Math.max(6, (day.value / max) * 100)}%` }}
            title={`${day.date}: ${formatValue(day.value)}`}
          />
          <span className="font-mono text-[10px] text-ink-faint">
            {day.date.slice(5)}
          </span>
        </div>
      ))}
    </div>
  );
}

export default function DashboardPage() {
  const { user } = useAuth();
  const canSeeCommercial =
    can(user, "factures.view") || can(user, "paiements.view");
  const canSeeRh =
    canViewContrats(user) || canViewAbsences(user) || canViewPaie(user);
  const { data, isLoading, isError } = useDashboardStats();
  const stats = data?.data;
  const commercial = canSeeCommercial ? stats?.commercial : undefined;
  const rh = canSeeRh ? stats?.rh : undefined;

  return (
    <PermissionGate permission="dashboard.view" title="Tableau de bord">
      <div className="space-y-8">
        <PageHeader
          title="Tableau de bord"
          description="Vue d’ensemble — opérations et commercial."
        />

        {isLoading ? (
          <div className="flex justify-center py-20">
            <Spinner className="size-8" />
          </div>
        ) : null}

        {isError ? (
          <div className="rounded-lg border border-danger/20 bg-danger/5 p-4 text-sm text-danger">
            Impossible de charger les statistiques.
          </div>
        ) : null}

        {stats ? (
          <>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <StatCard
                label="Agents actifs"
                value={stats.agents_actifs}
                hint={`${stats.agents_disponibles} disponible(s)`}
                icon={<UserRound className="size-4" />}
              />
              <StatCard
                label="Affectations"
                value={stats.vacations_actives}
                hint={`${stats.sites_surveilles} site(s)`}
                icon={<Users className="size-4" />}
              />
              <StatCard
                label="Contrôles du jour"
                value={stats.controles_du_jour}
                hint={
                  stats.controle_coverage
                    ? `${stats.controle_coverage.taux_couverture_pct}% des sites actifs couverts`
                    : undefined
                }
                icon={<ClipboardCheck className="size-4" />}
              />
              <StatCard
                label="Anomalies ouvertes"
                value={stats.anomalies_ouvertes}
                hint={
                  stats.postes_sous_effectif
                    ? `${stats.postes_sous_effectif} poste(s) sous-effectif`
                    : undefined
                }
                icon={<AlertTriangle className="size-4" />}
              />
            </div>

            {commercial ? (
              <section>
                <div className="mb-3 flex items-end justify-between gap-2">
                  <h2 className="text-base font-semibold text-ink">
                    Commercial
                  </h2>
                  <Link
                    href="/factures"
                    className="text-xs font-medium text-teal hover:underline"
                  >
                    Voir factures
                  </Link>
                </div>
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                  <StatCard
                    label="Facturé TTC"
                    value={formatFcfa(commercial.montant_facture_ttc)}
                    hint={`${commercial.factures_validees} facture(s)`}
                    icon={<Banknote className="size-4" />}
                  />
                  <StatCard
                    label="Encaissé"
                    value={formatFcfa(commercial.montant_paye)}
                    icon={<Wallet className="size-4" />}
                  />
                  <StatCard
                    label="Impayé"
                    value={formatFcfa(commercial.montant_impaye)}
                    hint={`${commercial.factures_par_paiement.non_payee} non payée(s) · ${commercial.factures_par_paiement.partiel} partielle(s)`}
                    icon={<AlertTriangle className="size-4" />}
                  />
                  <StatCard
                    label="Abonnements"
                    value={commercial.abonnements_actifs}
                    hint={
                      commercial.abonnements_echeance_30j > 0
                        ? `${commercial.abonnements_echeance_30j} échéance(s) ≤ 30 j`
                        : undefined
                    }
                    icon={<RefreshCw className="size-4" />}
                  />
                </div>
              </section>
            ) : null}

            {rh ? (
              <section>
                <div className="mb-3 flex items-end justify-between gap-2">
                  <h2 className="text-base font-semibold text-ink">
                    Ressources humaines
                  </h2>
                  <Link
                    href="/rh"
                    className="text-xs font-medium text-teal hover:underline"
                  >
                    Voir RH
                  </Link>
                </div>
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                  {canViewContrats(user) ? (
                    <StatCard
                      label="Contrats actifs"
                      value={rh.contrats_actifs}
                      hint={
                        rh.contrats_alertes_count > 0
                          ? `${rh.contrats_alertes_count} alerte(s)`
                          : undefined
                      }
                      icon={<FileText className="size-4" />}
                    />
                  ) : null}
                  {canViewContrats(user) && canSeeSalaire(user) ? (
                    <StatCard
                      label="Masse salariale"
                      value={formatSalaire(rh.masse_salariale, user)}
                      icon={<Banknote className="size-4" />}
                    />
                  ) : null}
                  {canViewAbsences(user) ? (
                    <StatCard
                      label="Absences en attente"
                      value={rh.absences_en_attente}
                      icon={<Users className="size-4" />}
                    />
                  ) : null}
                  {canViewPaie(user) ? (
                    <Link href="/rh/paie" className="block">
                      <StatCard
                        label="Paie"
                        value="Accéder"
                        hint="Périodes et bulletins"
                        icon={<Wallet className="size-4" />}
                      />
                    </Link>
                  ) : null}
                </div>
                {rh.contrats_alertes.length > 0 && canViewContrats(user) ? (
                  <Card className="mt-4">
                    <CardHeader title="Alertes contrats" />
                    <CardBody className="space-y-2">
                      {rh.contrats_alertes.slice(0, 5).map((a) => (
                        <div
                          key={`${a.contrat_id}-${a.alerte}`}
                          className="flex items-center justify-between gap-2 text-sm"
                        >
                          <span className="truncate text-ink-muted">
                            {a.agent_nom} ({a.matricule}) ·{" "}
                            {ALERTE_LABELS[a.alerte] ?? labelize(a.alerte)}
                          </span>
                          <Badge tone={a.jours_restants <= 7 ? "danger" : "warning"}>
                            {a.jours_restants} j
                          </Badge>
                        </div>
                      ))}
                    </CardBody>
                  </Card>
                ) : null}
              </section>
            ) : null}

            <div className="grid gap-4 lg:grid-cols-3">
              <Card className="lg:col-span-2">
                <CardHeader
                  title={
                    commercial
                      ? "Activité — 7 derniers jours"
                      : "Contrôles — 7 derniers jours"
                  }
                  description={
                    commercial
                      ? "Contrôles terrain et encaissements"
                      : "Volume quotidien des contrôles"
                  }
                />
                <CardBody className="space-y-6">
                  <div>
                    <p className="mb-2 text-[11px] font-medium uppercase tracking-wide text-ink-muted">
                      Contrôles
                    </p>
                    <MiniBars
                      items={stats.controles_7j.map((d) => ({
                        date: d.date,
                        value: d.count,
                      }))}
                      formatValue={(n) => String(n)}
                    />
                  </div>
                  {commercial ? (
                    <div>
                      <p className="mb-2 text-[11px] font-medium uppercase tracking-wide text-ink-muted">
                        Encaissements (FCFA)
                      </p>
                      <MiniBars
                        items={commercial.encaissements_7j.map((d) => ({
                          date: d.date,
                          value: d.montant,
                        }))}
                        formatValue={(n) =>
                          n > 0 ? formatFcfa(n).replace(/\s*FCFA$/, "") : "0"
                        }
                      />
                    </div>
                  ) : null}
                </CardBody>
              </Card>

              {commercial ? (
                <div className="space-y-4">
                  <Card>
                    <CardHeader title="Paiement factures" />
                    <CardBody className="space-y-2">
                      {Object.entries(commercial.factures_par_paiement).map(
                        ([key, count]) => (
                          <div
                            key={key}
                            className="flex items-center justify-between text-sm"
                          >
                            <span className="text-ink-muted">
                              {PAIEMENT_LABELS[key] ?? labelize(key)}
                            </span>
                            <Badge tone={statusTone(key)}>{count}</Badge>
                          </div>
                        ),
                      )}
                    </CardBody>
                  </Card>
                  <Card>
                    <CardHeader title="Par périodicité" />
                    <CardBody className="space-y-2">
                      {Object.entries(commercial.factures_par_periodicite).map(
                        ([key, row]) =>
                          row.count > 0 ? (
                            <div
                              key={key}
                              className="flex items-center justify-between gap-2 text-sm"
                            >
                              <span className="capitalize text-ink-muted">
                                {labelize(key)} ({row.count})
                              </span>
                              <span className="font-mono text-xs tabular-nums">
                                {formatFcfa(row.montant_ttc)}
                              </span>
                            </div>
                          ) : null,
                      )}
                    </CardBody>
                  </Card>
                </div>
              ) : null}
            </div>

            {stats.aujourdhui ? (
              <section>
                <h2 className="mb-3 text-base font-semibold text-ink">
                  Aujourd’hui
                </h2>
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                  <Card>
                    <CardHeader
                      title="Anomalies à traiter"
                      action={
                        <Link
                          href="/anomalies"
                          className="text-xs font-medium text-teal hover:underline"
                        >
                          Voir
                        </Link>
                      }
                    />
                    <CardBody className="space-y-2">
                      {stats.aujourdhui.anomalies_a_traiter.length === 0 ? (
                        <p className="text-xs text-ink-muted">
                          Aucune anomalie en attente.
                        </p>
                      ) : (
                        stats.aujourdhui.anomalies_a_traiter
                          .slice(0, 4)
                          .map((a) => (
                            <div
                              key={a.id}
                              className="flex items-center justify-between gap-2 text-sm"
                            >
                              <span className="truncate text-ink-muted">
                                {labelize(a.type)}
                                {a.site ? ` · ${a.site.nom}` : ""}
                              </span>
                              <Badge tone={statusTone(a.gravite)}>
                                {labelize(a.gravite)}
                              </Badge>
                            </div>
                          ))
                      )}
                    </CardBody>
                  </Card>

                  <Card>
                    <CardHeader
                      title="Planning du jour"
                      action={
                        <Link
                          href="/vacations"
                          className="text-xs font-medium text-teal hover:underline"
                        >
                          Voir
                        </Link>
                      }
                    />
                    <CardBody className="space-y-2">
                      {stats.aujourdhui.vacations.length === 0 ? (
                        <p className="text-xs text-ink-muted">
                          Aucun agent planifié.
                        </p>
                      ) : (
                        stats.aujourdhui.vacations.slice(0, 4).map((v) => (
                          <div
                            key={v.id}
                            className="flex items-center justify-between gap-2 text-sm"
                          >
                            <span className="truncate text-ink-muted">
                              {v.agent
                                ? `${v.agent.prenom} ${v.agent.nom}`
                                : "—"}
                              {v.site ? ` · ${v.site.nom}` : ""}
                            </span>
                            <span className="shrink-0 font-mono text-[11px] text-ink-faint">
                              {v.heure_debut.slice(0, 5)}–
                              {v.heure_fin.slice(0, 5)}
                            </span>
                          </div>
                        ))
                      )}
                    </CardBody>
                  </Card>

                  {stats.controle_coverage ? (
                    <Card>
                      <CardHeader
                        title="Sites sans contrôle aujourd’hui"
                        action={
                          <Link
                            href="/controles"
                            className="text-xs font-medium text-teal hover:underline"
                          >
                            Voir
                          </Link>
                        }
                      />
                      <CardBody className="space-y-2">
                        {stats.controle_coverage.sites_sans_controle.length ===
                        0 ? (
                          <p className="text-xs text-ink-muted">
                            {stats.controle_coverage.sites_actifs_aujourdhui > 0
                              ? "Tous les sites actifs ont été contrôlés."
                              : "Aucun site actif aujourd’hui."}
                          </p>
                        ) : (
                          stats.controle_coverage.sites_sans_controle
                            .slice(0, 4)
                            .map((s) => (
                              <div
                                key={s.id}
                                className="flex items-center justify-between gap-2 text-sm"
                              >
                                <span className="truncate text-ink-muted">
                                  {s.nom}
                                </span>
                                <Badge tone="warning">Non contrôlé</Badge>
                              </div>
                            ))
                        )}
                      </CardBody>
                    </Card>
                  ) : null}
                </div>
              </section>
            ) : null}
          </>
        ) : null}
      </div>
    </PermissionGate>
  );
}
