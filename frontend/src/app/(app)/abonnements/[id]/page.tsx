"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import {
  ArrowLeft,
  Building2,
  CalendarRange,
  FileText,
  MapPin,
  Package,
  RefreshCw,
} from "lucide-react";
import { useAbonnement } from "@/application/hooks/useResources";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { Alert } from "@/presentation/components/ui/Alert";
import { Badge, statusTone } from "@/presentation/components/ui/Badge";
import { Spinner } from "@/presentation/components/ui/Spinner";
import { formatDate, formatFcfa, labelize } from "@/shared/lib/format";

function splitPrestations(raw: string | null | undefined): string[] {
  if (!raw?.trim()) return [];
  return raw
    .split(/\s*\+\s*/)
    .map((s) => s.trim())
    .filter(Boolean);
}

function MetaCard({
  icon: Icon,
  label,
  value,
  hint,
}: {
  icon: typeof CalendarRange;
  label: string;
  value: string;
  hint?: string;
}) {
  return (
    <div className="rounded-xl border border-border bg-white p-4 shadow-[0_1px_0_rgba(20,26,16,0.04)]">
      <div className="flex items-center gap-2 text-ink-faint">
        <Icon className="size-4 shrink-0" />
        <span className="text-[11px] font-medium uppercase tracking-[0.12em]">
          {label}
        </span>
      </div>
      <p className="mt-2 text-base font-semibold tracking-tight text-ink">
        {value}
      </p>
      {hint ? (
        <p className="mt-0.5 text-xs text-ink-muted">{hint}</p>
      ) : null}
    </div>
  );
}

export default function AbonnementDetailPage() {
  const params = useParams();
  const id = params.id as string;
  const { data, isLoading, isError } = useAbonnement(id);
  const abo = data?.data;

  const lignes = abo?.lignes ?? [];
  const montantHt =
    abo?.montant_ht != null
      ? Number(abo.montant_ht)
      : lignes.reduce((sum, l) => sum + (Number(l.montant) || 0), 0);
  const montantTva = Math.round(montantHt * 0.18);
  const montantTtc = montantHt + montantTva;
  const factures = abo?.factures ?? [];
  const periLabel = labelize(abo?.periodicite ?? "mensuel");

  return (
    <PermissionGate permission="abonnements.view" title="Abonnement">
      <div className="mx-auto max-w-5xl">
        <Link
          href="/abonnements"
          className="mb-6 inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-teal"
        >
          <ArrowLeft className="size-4" />
          Abonnements
        </Link>

        {isLoading ? (
          <div className="flex justify-center py-24">
            <Spinner className="size-8" />
          </div>
        ) : isError || !abo ? (
          <Alert tone="danger" title="Abonnement introuvable">
            Cet abonnement n&apos;existe pas ou n&apos;est plus accessible.
          </Alert>
        ) : (
          <div className="animate-fade-in space-y-8">
            <header className="relative overflow-hidden rounded-2xl border border-border bg-ink text-white">
              <div
                className="pointer-events-none absolute inset-0 opacity-[0.12]"
                style={{
                  backgroundImage:
                    "radial-gradient(circle at 1px 1px, rgba(255,255,255,0.55) 1px, transparent 0)",
                  backgroundSize: "18px 18px",
                }}
              />
              <div className="pointer-events-none absolute -right-16 -top-20 size-64 rounded-full bg-teal-light/30 blur-3xl" />
              <div className="relative px-6 py-7 sm:px-8 sm:py-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                  <div className="min-w-0">
                    <p className="text-[11px] font-medium uppercase tracking-[0.16em] text-white/55">
                      Contrat commercial
                    </p>
                    <h1 className="mt-2 max-w-3xl text-2xl font-semibold tracking-tight sm:text-3xl">
                      {abo.designation ??
                        abo.offre?.libelle ??
                        "Abonnement combiné"}
                    </h1>
                    {abo.client ? (
                      <Link
                        href={`/clients/${abo.client.id}`}
                        className="mt-3 inline-flex items-center gap-2 text-sm text-white/80 transition hover:text-white"
                      >
                        <Building2 className="size-4" />
                        {abo.client.raison_sociale}
                      </Link>
                    ) : null}
                  </div>
                  <Badge tone={statusTone(abo.statut)}>
                    {labelize(abo.statut)}
                  </Badge>
                </div>
              </div>
            </header>

            <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
              <MetaCard
                icon={RefreshCw}
                label="Périodicité"
                value={labelize(abo.periodicite)}
                hint="Rythme de facturation"
              />
              <MetaCard
                icon={CalendarRange}
                label="Début"
                value={formatDate(abo.date_debut)}
              />
              <MetaCard
                icon={FileText}
                label="Prochaine facture"
                value={
                  abo.prochaine_facture_le
                    ? formatDate(abo.prochaine_facture_le)
                    : "—"
                }
                hint="Génération automatique"
              />
              <MetaCard
                icon={CalendarRange}
                label="Fin"
                value={
                  abo.date_fin ? formatDate(abo.date_fin) : "Reconduction tacite"
                }
                hint={abo.date_fin ? undefined : "Sans date de fin"}
              />
              <MetaCard
                icon={MapPin}
                label="Site"
                value={abo.site?.nom ?? "Aucun site"}
                hint={abo.site?.adresse ?? undefined}
              />
            </section>

            <div className="grid gap-6 lg:grid-cols-[1.55fr_1fr]">
              <section className="rounded-2xl border border-border bg-white p-6 shadow-[0_1px_0_rgba(20,26,16,0.04)]">
                <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
                  <div className="flex items-center gap-2">
                    <span className="flex size-8 items-center justify-center rounded-lg bg-teal/10 text-teal">
                      <Package className="size-4" />
                    </span>
                    <div>
                      <h2 className="text-base font-semibold text-ink">
                        Lignes de prestation
                      </h2>
                      <p className="text-sm text-ink-muted">
                        Détail du combiné · coût par période ({periLabel})
                      </p>
                    </div>
                  </div>
                  {lignes.length > 0 ? (
                    <div className="rounded-xl bg-ink px-4 py-2.5 text-right text-white">
                      <p className="text-[10px] uppercase tracking-[0.14em] text-white/55">
                        Total HT / {periLabel}
                      </p>
                      <p className="text-lg font-semibold tabular-nums">
                        {formatFcfa(montantHt)}
                      </p>
                    </div>
                  ) : null}
                </div>

                {lignes.length === 0 ? (
                  <div className="space-y-2">
                    {splitPrestations(
                      abo.designation ?? abo.offre?.libelle ?? "",
                    ).length === 0 ? (
                      <p className="text-sm text-ink-faint">
                        Aucune ligne de prestation.
                      </p>
                    ) : (
                      splitPrestations(
                        abo.designation ?? abo.offre?.libelle ?? "",
                      ).map((item, index) => (
                        <div
                          key={`${item}-${index}`}
                          className="rounded-xl border border-border/80 bg-paper/60 px-4 py-3 text-sm text-ink"
                        >
                          {item}
                        </div>
                      ))
                    )}
                  </div>
                ) : (
                  <>
                    <div className="overflow-x-auto">
                      <table className="w-full min-w-[520px] text-left text-sm">
                        <thead>
                          <tr className="border-b border-border text-[11px] uppercase tracking-wide text-ink-faint">
                            <th className="pb-2 pr-3 font-medium">#</th>
                            <th className="pb-2 pr-3 font-medium">
                              Désignation
                            </th>
                            <th className="pb-2 pr-3 text-right font-medium">
                              Qté
                            </th>
                            <th className="pb-2 pr-3 text-right font-medium">
                              PU HT
                            </th>
                            <th className="pb-2 text-right font-medium">
                              Montant HT
                            </th>
                          </tr>
                        </thead>
                        <tbody>
                          {lignes.map((ligne, index) => (
                            <tr
                              key={ligne.id}
                              className="border-b border-border/70 last:border-0"
                            >
                              <td className="py-3 pr-3 tabular-nums text-ink-faint">
                                {ligne.ordre ?? index + 1}
                              </td>
                              <td className="py-3 pr-3 text-ink">
                                {ligne.description}
                              </td>
                              <td className="py-3 pr-3 text-right tabular-nums text-ink">
                                {Number(ligne.quantite)}
                              </td>
                              <td className="py-3 pr-3 text-right tabular-nums text-ink">
                                {formatFcfa(ligne.prix_unitaire)}
                              </td>
                              <td className="py-3 text-right font-medium tabular-nums text-ink">
                                {formatFcfa(ligne.montant)}
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>

                    <div className="mt-5 space-y-1.5 border-t border-border pt-4 text-sm">
                      <div className="flex justify-between text-ink-muted">
                        <span>Total HT</span>
                        <span className="tabular-nums text-ink">
                          {formatFcfa(montantHt)}
                        </span>
                      </div>
                      <div className="flex justify-between text-ink-muted">
                        <span>TVA 18%</span>
                        <span className="tabular-nums text-ink">
                          {formatFcfa(montantTva)}
                        </span>
                      </div>
                      <div className="flex justify-between text-base font-semibold text-ink">
                        <span>Total TTC / {periLabel}</span>
                        <span className="tabular-nums">
                          {formatFcfa(montantTtc)}
                        </span>
                      </div>
                    </div>
                  </>
                )}
              </section>

              <section className="rounded-2xl border border-border bg-white p-6 shadow-[0_1px_0_rgba(20,26,16,0.04)]">
                <div className="mb-5 flex items-center gap-2">
                  <span className="flex size-8 items-center justify-center rounded-lg bg-brand-accent/10 text-brand-accent">
                    <Building2 className="size-4" />
                  </span>
                  <div>
                    <h2 className="text-base font-semibold text-ink">Client</h2>
                    <p className="text-sm text-ink-muted">Coordonnées</p>
                  </div>
                </div>
                {abo.client ? (
                  <dl className="space-y-3 text-sm">
                    <div>
                      <dt className="text-[11px] uppercase tracking-wide text-ink-faint">
                        Raison sociale
                      </dt>
                      <dd className="mt-0.5 font-medium text-ink">
                        <Link
                          href={`/clients/${abo.client.id}`}
                          className="text-teal hover:underline"
                        >
                          {abo.client.raison_sociale}
                        </Link>
                      </dd>
                    </div>
                    {abo.client.type ? (
                      <div>
                        <dt className="text-[11px] uppercase tracking-wide text-ink-faint">
                          Type
                        </dt>
                        <dd className="mt-0.5 text-ink">
                          {labelize(abo.client.type)}
                        </dd>
                      </div>
                    ) : null}
                    <div>
                      <dt className="text-[11px] uppercase tracking-wide text-ink-faint">
                        Contact
                      </dt>
                      <dd className="mt-0.5 text-ink">
                        {abo.client.personne_contact || "—"}
                      </dd>
                    </div>
                    <div>
                      <dt className="text-[11px] uppercase tracking-wide text-ink-faint">
                        Téléphone
                      </dt>
                      <dd className="mt-0.5 text-ink">
                        {abo.client.telephone || "—"}
                      </dd>
                    </div>
                    <div>
                      <dt className="text-[11px] uppercase tracking-wide text-ink-faint">
                        Email
                      </dt>
                      <dd className="mt-0.5 text-ink">
                        {abo.client.email || "—"}
                      </dd>
                    </div>
                    <div>
                      <dt className="text-[11px] uppercase tracking-wide text-ink-faint">
                        Adresse
                      </dt>
                      <dd className="mt-0.5 text-ink">
                        {abo.client.adresse || "—"}
                      </dd>
                    </div>
                  </dl>
                ) : (
                  <p className="text-sm text-ink-faint">Client non chargé.</p>
                )}
              </section>
            </div>

            <section className="rounded-2xl border border-border bg-white p-6 shadow-[0_1px_0_rgba(20,26,16,0.04)]">
              <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                  <span className="flex size-8 items-center justify-center rounded-lg bg-teal/10 text-teal">
                    <FileText className="size-4" />
                  </span>
                  <div>
                    <h2 className="text-base font-semibold text-ink">
                      Factures liées
                    </h2>
                    <p className="text-sm text-ink-muted">
                      Proformas et factures rattachées à ce contrat
                    </p>
                  </div>
                </div>
                <Link
                  href="/factures/nouvelle"
                  className="text-sm font-medium text-teal hover:underline"
                >
                  Nouvelle proforma
                </Link>
              </div>

              {factures.length === 0 ? (
                <div className="rounded-xl border border-dashed border-border bg-paper/50 px-4 py-8 text-center text-sm text-ink-muted">
                  Aucune facture liée pour le moment.
                </div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full min-w-[560px] text-left text-sm">
                    <thead>
                      <tr className="border-b border-border text-[11px] uppercase tracking-wide text-ink-faint">
                        <th className="pb-2 pr-3 font-medium">Numéro</th>
                        <th className="pb-2 pr-3 font-medium">Émission</th>
                        <th className="pb-2 pr-3 font-medium">HT</th>
                        <th className="pb-2 pr-3 font-medium">TTC</th>
                        <th className="pb-2 font-medium">Statut</th>
                      </tr>
                    </thead>
                    <tbody>
                      {factures.map((f) => (
                        <tr
                          key={f.id}
                          className="border-b border-border/70 last:border-0"
                        >
                          <td className="py-3 pr-3">
                            <Link
                              href={`/factures/${f.id}/edit`}
                              className="font-medium text-teal hover:underline"
                            >
                              {f.numero}
                            </Link>
                          </td>
                          <td className="py-3 pr-3 text-ink-muted">
                            {formatDate(f.date_emission)}
                          </td>
                          <td className="py-3 pr-3 tabular-nums text-ink">
                            {formatFcfa(f.montant_ht)}
                          </td>
                          <td className="py-3 pr-3 tabular-nums font-medium text-ink">
                            {formatFcfa(f.montant_ttc)}
                          </td>
                          <td className="py-3">
                            <Badge tone={statusTone(String(f.statut))}>
                              {labelize(String(f.statut))}
                            </Badge>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </section>
          </div>
        )}
      </div>
    </PermissionGate>
  );
}
