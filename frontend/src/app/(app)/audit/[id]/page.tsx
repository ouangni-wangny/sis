"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import {
  ArrowLeft,
  ArrowRight,
  Calendar,
  FileText,
  MapPin,
  UserRound,
} from "lucide-react";
import type { ReactNode } from "react";
import { useAuditEntry } from "@/application/hooks/useResources";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { Alert } from "@/presentation/components/ui/Alert";
import { Badge } from "@/presentation/components/ui/Badge";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { Spinner } from "@/presentation/components/ui/Spinner";
import { formatDateTime } from "@/shared/lib/format";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { cn } from "@/shared/lib/cn";

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

function isTechnicalField(field: string): boolean {
  return [
    "created_at",
    "updated_at",
    "deleted_at",
    "id",
    "remember_token",
    "password",
    "pin_hash",
  ].includes(field);
}

function displayValue(label: string | undefined, raw: unknown): string {
  if (label && label !== "référence introuvable") return label;
  if (raw == null || raw === "") return "vide";
  return String(raw);
}

export default function AuditDetailPage() {
  const params = useParams<{ id: string }>();
  const id = params?.id;
  const { data, isLoading, error } = useAuditEntry(id);
  const entry = data?.data;

  const changes = entry?.changes ?? [];
  const metierChanges = changes.filter((c) => !isTechnicalField(c.field));
  const techniqueChanges = changes.filter((c) => isTechnicalField(c.field));
  const visibleChanges =
    metierChanges.length > 0 ? metierChanges : changes.filter((c) => c.field !== "id");

  const acteur =
    entry?.acteur ??
    entry?.user?.label ??
    (entry?.user
      ? `${entry.user.prenom ?? ""} ${entry.user.nom ?? ""}`.trim()
      : "Système automatique");

  const fiche = entry?.auditable_label
    ? `${entry.auditable_type_label ?? "Élément"} — ${entry.auditable_label}`
    : (entry?.auditable_type_label ?? "Élément");

  const shortTitle = entry
    ? `${acteur} · ${entry.action_label ?? entry.action} · ${entry.auditable_type_label ?? "fiche"}`
    : "Détail de l’événement";

  return (
    <PermissionGate permission="audit.view" title="Détail d’audit">
      <div className="mx-auto max-w-4xl space-y-5">
        <Link
          href="/audit"
          className="inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-ink"
        >
          <ArrowLeft className="size-4" />
          Retour au journal
        </Link>

        <PageHeader
          title="Détail de l’événement"
          description="Qui a fait quoi, sur quelle fiche, et ce qui a changé."
        />

        {isLoading ? (
          <div className="flex justify-center py-16">
            <Spinner className="size-6" />
          </div>
        ) : null}

        {error ? (
          <Alert tone="danger">
            {getApiErrorMessage(error, "Impossible de charger cet événement.")}
          </Alert>
        ) : null}

        {!isLoading && !error && !entry ? (
          <Alert tone="warning">Événement introuvable.</Alert>
        ) : null}

        {entry ? (
          <div className="space-y-4">
            {/* Hero compact */}
            <section className="overflow-hidden rounded-2xl border border-border bg-white">
              <div className="flex flex-wrap items-center gap-2 border-b border-border bg-paper-muted/60 px-5 py-3">
                <Badge tone={actionTone(entry.action)}>
                  {entry.action_label ?? entry.action}
                </Badge>
                <span className="inline-flex items-center gap-1.5 text-sm text-ink-muted">
                  <Calendar className="size-3.5" />
                  {formatDateTime(String(entry.created_at))}
                </span>
              </div>
              <div className="px-5 py-4">
                <h2 className="text-xl font-semibold tracking-tight text-ink">
                  {shortTitle}
                </h2>
                <p className="mt-1 text-sm text-ink-muted">
                  {entry.action_explication}
                </p>
              </div>

              <div className="grid divide-y divide-border border-t border-border sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <MetaCell
                  icon={<UserRound className="size-4" />}
                  label="Qui ?"
                  value={acteur}
                  hint={
                    entry.user?.email ||
                    (!entry.user_id
                      ? "Action automatique (job / script)"
                      : undefined)
                  }
                />
                <MetaCell
                  icon={<FileText className="size-4" />}
                  label="Quelle fiche ?"
                  value={fiche}
                />
                <MetaCell
                  icon={<MapPin className="size-4" />}
                  label="D’où ?"
                  value={
                    entry.contexte_labels?.Origine?.replace(
                      "Action depuis l’application (navigateur / API)",
                      "Application",
                    ).replace(
                      "Action automatique (job, commande ou script)",
                      "Système",
                    ) ?? "—"
                  }
                  hint={entry.ip ? `IP ${entry.ip}` : undefined}
                />
              </div>
            </section>

            {/* Changes */}
            <section className="rounded-2xl border border-border bg-white">
              <div className="border-b border-border px-5 py-4">
                <h3 className="text-base font-semibold text-ink">
                  Modifications
                </h3>
                <p className="mt-0.5 text-sm text-ink-muted">
                  {entry.action === "updated"
                    ? "Ancienne valeur → nouvelle valeur"
                    : entry.action === "created"
                      ? "Valeurs enregistrées à la création"
                      : entry.action === "deleted"
                        ? "Valeurs au moment de la suppression"
                        : "Informations concernées"}
                </p>
              </div>

              {visibleChanges.length === 0 ? (
                <p className="px-5 py-8 text-center text-sm text-ink-muted">
                  Aucun détail métier disponible pour cet événement.
                </p>
              ) : entry.action === "updated" ? (
                <ul className="divide-y divide-border">
                  {visibleChanges.map((change) => {
                    const before = displayValue(
                      change.before_label,
                      change.before,
                    );
                    const after = displayValue(
                      change.after_label,
                      change.after,
                    );
                    return (
                      <li
                        key={change.field}
                        className="flex flex-col gap-2 px-5 py-3.5 sm:flex-row sm:items-center sm:gap-4"
                      >
                        <div className="w-full shrink-0 text-sm font-medium text-ink sm:w-40">
                          {change.field_label ?? change.field}
                        </div>
                        <div className="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                          <ValuePill tone="muted">{before}</ValuePill>
                          <ArrowRight
                            className="size-4 shrink-0 text-ink-muted"
                            aria-hidden
                          />
                          <ValuePill tone="accent">{after}</ValuePill>
                        </div>
                      </li>
                    );
                  })}
                </ul>
              ) : (
                <ul className="divide-y divide-border">
                  {visibleChanges.map((change) => {
                    const value =
                      entry.action === "deleted"
                        ? displayValue(change.before_label, change.before)
                        : displayValue(change.after_label, change.after);
                    return (
                      <li
                        key={change.field}
                        className="flex items-center justify-between gap-4 px-5 py-3"
                      >
                        <span className="text-sm font-medium text-ink">
                          {change.field_label ?? change.field}
                        </span>
                        <ValuePill tone="accent">{value}</ValuePill>
                      </li>
                    );
                  })}
                </ul>
              )}

              {techniqueChanges.length > 0 ? (
                <details className="border-t border-border">
                  <summary className="cursor-pointer px-5 py-3 text-sm text-ink-muted hover:bg-paper-muted/50 hover:text-ink">
                    Voir {techniqueChanges.length} info
                    {techniqueChanges.length > 1 ? "s" : ""} technique
                    {techniqueChanges.length > 1 ? "s" : ""}
                  </summary>
                  <ul className="divide-y divide-border border-t border-border bg-paper-muted/30">
                    {techniqueChanges.map((change) => (
                      <li
                        key={change.field}
                        className="flex flex-wrap items-center gap-2 px-5 py-2.5 text-xs text-ink-muted"
                      >
                        <span className="font-medium text-ink">
                          {change.field_label ?? change.field}
                        </span>
                        <span>
                          {displayValue(change.before_label, change.before)}
                        </span>
                        <ArrowRight className="size-3" />
                        <span>
                          {displayValue(change.after_label, change.after)}
                        </span>
                      </li>
                    ))}
                  </ul>
                </details>
              ) : null}
            </section>

            {entry.contexte_labels &&
            Object.keys(entry.contexte_labels).length > 0 ? (
              <details className="rounded-2xl border border-border bg-white">
                <summary className="cursor-pointer px-5 py-3.5 text-sm font-medium text-ink hover:bg-paper-muted/40">
                  Contexte technique
                </summary>
                <dl className="grid gap-px border-t border-border bg-border sm:grid-cols-2">
                  {Object.entries(entry.contexte_labels).map(
                    ([label, value]) => (
                      <div key={label} className="bg-white px-5 py-3">
                        <dt className="text-[11px] font-medium uppercase tracking-wide text-ink-muted">
                          {label}
                        </dt>
                        <dd className="mt-1 break-all text-sm text-ink">
                          {value}
                        </dd>
                      </div>
                    ),
                  )}
                </dl>
              </details>
            ) : null}
          </div>
        ) : null}
      </div>
    </PermissionGate>
  );
}

function MetaCell({
  icon,
  label,
  value,
  hint,
}: {
  icon: ReactNode;
  label: string;
  value: string;
  hint?: string;
}) {
  return (
    <div className="px-5 py-4">
      <div className="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-ink-muted">
        {icon}
        {label}
      </div>
      <p className="mt-1.5 truncate text-sm font-semibold text-ink" title={value}>
        {value}
      </p>
      {hint ? (
        <p className="mt-0.5 truncate text-xs text-ink-muted" title={hint}>
          {hint}
        </p>
      ) : (
        <p className="mt-0.5 text-xs text-transparent">.</p>
      )}
    </div>
  );
}

function ValuePill({
  children,
  tone,
}: {
  children: string;
  tone: "muted" | "accent";
}) {
  return (
    <span
      className={cn(
        "inline-flex max-w-full items-center rounded-md px-2.5 py-1 text-sm",
        tone === "muted" &&
          "bg-paper-muted text-ink-muted line-through decoration-ink-muted/40",
        tone === "accent" &&
          "bg-teal/10 font-semibold text-teal-dark ring-1 ring-teal/20",
      )}
      title={children}
    >
      <span className="truncate">{children}</span>
    </span>
  );
}
