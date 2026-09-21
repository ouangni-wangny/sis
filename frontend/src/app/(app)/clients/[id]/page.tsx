"use client";

import type { ReactNode } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import {
  ArrowLeft,
  Building2,
  CalendarRange,
  ChevronRight,
  Mail,
  MapPin,
  Phone,
  RefreshCw,
} from "lucide-react";
import { useClient } from "@/application/hooks/useClients";
import { useAbonnements } from "@/application/hooks/useResources";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { Alert } from "@/presentation/components/ui/Alert";
import { Badge, statusTone } from "@/presentation/components/ui/Badge";
import { Spinner } from "@/presentation/components/ui/Spinner";
import { formatDate, labelize } from "@/shared/lib/format";

function DetailRow({
  label,
  value,
}: {
  label: string;
  value: ReactNode;
}) {
  return (
    <div className="border-b border-border/80 py-3 last:border-0 sm:grid sm:grid-cols-[140px_1fr] sm:gap-4">
      <dt className="text-[11px] font-medium uppercase tracking-[0.12em] text-ink-faint">
        {label}
      </dt>
      <dd className="mt-0.5 text-sm text-ink sm:mt-0">{value || "—"}</dd>
    </div>
  );
}

function splitPrestations(raw: string | null | undefined): string[] {
  if (!raw?.trim()) return [];
  return raw
    .split(/\s*\+\s*/)
    .map((s) => s.trim())
    .filter(Boolean);
}

export default function ClientDetailPage() {
  const params = useParams();
  const id = params.id as string;
  const { data, isLoading, isError } = useClient(id);
  const { data: abonnementsData, isLoading: aboLoading } = useAbonnements({
    client_id: id,
    per_page: 50,
  });
  const client = data?.data;
  const abonnements = abonnementsData?.data ?? [];

  return (
    <PermissionGate permission="clients.view" title="Client">
      <div className="mx-auto max-w-5xl">
        <Link
          href="/clients"
          className="mb-6 inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-teal"
        >
          <ArrowLeft className="size-4" />
          Clients
        </Link>

        {isLoading ? (
          <div className="flex justify-center py-24">
            <Spinner className="size-8" />
          </div>
        ) : isError || !client ? (
          <Alert tone="danger" title="Client introuvable">
            Ce client n&apos;existe pas ou n&apos;est plus accessible.
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
              <div className="pointer-events-none absolute -right-10 -top-16 size-56 rounded-full bg-brand-accent/40 blur-3xl" />
              <div className="relative flex flex-wrap items-start justify-between gap-4 px-6 py-7 sm:px-8">
                <div>
                  <p className="text-[11px] font-medium uppercase tracking-[0.16em] text-white/55">
                    Fiche client
                  </p>
                  <h1 className="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">
                    {client.raison_sociale}
                  </h1>
                  <p className="mt-2 text-sm text-white/70">
                    {labelize(client.type)}
                    {client.personne_contact
                      ? ` · ${client.personne_contact}`
                      : ""}
                  </p>
                </div>
                <Badge tone={statusTone(client.statut)}>
                  {labelize(client.statut)}
                </Badge>
              </div>
            </header>

            <div className="grid gap-6 lg:grid-cols-[1fr_1.2fr]">
              <section className="rounded-2xl border border-border bg-white p-6 shadow-[0_1px_0_rgba(20,26,16,0.04)]">
                <div className="mb-4 flex items-center gap-2">
                  <span className="flex size-8 items-center justify-center rounded-lg bg-teal/10 text-teal">
                    <Building2 className="size-4" />
                  </span>
                  <h2 className="text-base font-semibold text-ink">
                    Coordonnées
                  </h2>
                </div>
                <dl>
                  <DetailRow label="Type" value={labelize(client.type)} />
                  <DetailRow
                    label="Contact"
                    value={client.personne_contact ?? client.nom_responsable}
                  />
                  <DetailRow
                    label="Téléphone"
                    value={
                      client.telephone ? (
                        <span className="inline-flex items-center gap-1.5">
                          <Phone className="size-3.5 text-ink-faint" />
                          {client.telephone}
                        </span>
                      ) : null
                    }
                  />
                  <DetailRow
                    label="Email"
                    value={
                      client.email ? (
                        <span className="inline-flex items-center gap-1.5">
                          <Mail className="size-3.5 text-ink-faint" />
                          {client.email}
                        </span>
                      ) : null
                    }
                  />
                  <DetailRow
                    label="Adresse"
                    value={
                      client.adresse ? (
                        <span className="inline-flex items-start gap-1.5">
                          <MapPin className="mt-0.5 size-3.5 shrink-0 text-ink-faint" />
                          {client.adresse}
                        </span>
                      ) : null
                    }
                  />
                  <DetailRow
                    label="Sites"
                    value={
                      <span className="font-mono tabular-nums">
                        {Number(client.sites_count ?? 0)}
                      </span>
                    }
                  />
                </dl>
              </section>

              <section className="rounded-2xl border border-border bg-white p-6 shadow-[0_1px_0_rgba(20,26,16,0.04)]">
                <div className="mb-5 flex flex-wrap items-center justify-between gap-2">
                  <div className="flex items-center gap-2">
                    <span className="flex size-8 items-center justify-center rounded-lg bg-teal/10 text-teal">
                      <RefreshCw className="size-4" />
                    </span>
                    <div>
                      <h2 className="text-base font-semibold text-ink">
                        Abonnements
                      </h2>
                      <p className="text-sm text-ink-muted">
                        Contrats actifs et historiques
                      </p>
                    </div>
                  </div>
                  <Link
                    href="/abonnements"
                    className="text-sm font-medium text-teal hover:underline"
                  >
                    Tous les abonnements
                  </Link>
                </div>

                {aboLoading ? (
                  <div className="flex justify-center py-10">
                    <Spinner className="size-6" />
                  </div>
                ) : abonnements.length === 0 ? (
                  <div className="rounded-xl border border-dashed border-border bg-paper/50 px-4 py-10 text-center text-sm text-ink-muted">
                    Aucun abonnement pour ce client.
                  </div>
                ) : (
                  <ul className="space-y-3">
                    {abonnements.map((abo) => {
                      const items = splitPrestations(
                        abo.designation ?? abo.offre?.libelle ?? "",
                      );
                      const title =
                        items[0] ??
                        abo.designation ??
                        abo.offre?.libelle ??
                        "Abonnement";
                      const extra = Math.max(0, items.length - 1);

                      return (
                        <li key={abo.id}>
                          <Link
                            href={`/abonnements/${abo.id}`}
                            className="group flex items-start justify-between gap-3 rounded-xl border border-border bg-paper/40 px-4 py-3.5 transition hover:border-teal/30 hover:bg-white"
                          >
                            <div className="min-w-0">
                              <div className="flex flex-wrap items-center gap-2">
                                <p className="truncate font-medium text-ink group-hover:text-teal">
                                  {title}
                                </p>
                                <Badge tone={statusTone(abo.statut)}>
                                  {labelize(abo.statut)}
                                </Badge>
                              </div>
                              <p className="mt-1 text-xs text-ink-muted">
                                {labelize(abo.periodicite)}
                                {extra > 0
                                  ? ` · +${extra} autre${extra > 1 ? "s" : ""}`
                                  : ""}
                                {abo.site?.nom ? ` · ${abo.site.nom}` : ""}
                              </p>
                              <p className="mt-1 inline-flex items-center gap-1 text-xs text-ink-faint">
                                <CalendarRange className="size-3" />
                                {formatDate(abo.date_debut)}
                                {" → "}
                                {abo.date_fin
                                  ? formatDate(abo.date_fin)
                                  : "tacite"}
                              </p>
                            </div>
                            <ChevronRight className="mt-1 size-4 shrink-0 text-ink-faint transition group-hover:text-teal" />
                          </Link>
                        </li>
                      );
                    })}
                  </ul>
                )}
              </section>
            </div>
          </div>
        )}
      </div>
    </PermissionGate>
  );
}
