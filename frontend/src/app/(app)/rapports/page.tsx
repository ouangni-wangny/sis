"use client";

import { useCallback, useMemo, useState } from "react";
import { type ColumnDef } from "@tanstack/react-table";
import {
  AlertTriangle,
  CalendarDays,
  CalendarOff,
  ClipboardCheck,
  Download,
  FileSpreadsheet,
  FileText,
  Shield,
  Users,
  Wallet,
} from "lucide-react";
import {
  useGenerateRapport,
  useRapports,
} from "@/application/hooks/useResources";
import type { RapportExport } from "@/domain/types/entities";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { DataTable } from "@/presentation/components/tables/DataTable";
import { Badge, statusTone } from "@/presentation/components/ui/Badge";
import { Button } from "@/presentation/components/ui/Button";
import { DatePicker } from "@/presentation/components/ui/DatePicker";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { useToast } from "@/presentation/providers/ToastProvider";
import { apiClient } from "@/infrastructure/http/apiClient";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { cn } from "@/shared/lib/cn";
import { formatDate, formatDateTime, labelize } from "@/shared/lib/format";

type ReportType = {
  value: string;
  label: string;
  description: string;
  icon: typeof Users;
};

const REPORT_TYPES: ReportType[] = [
  {
    value: "agents",
    label: "Agents",
    description: "Effectifs, statuts et grades",
    icon: Users,
  },
  {
    value: "plannings",
    label: "Planning",
    description: "Vacations et couverture postes",
    icon: CalendarDays,
  },
  {
    value: "pointages",
    label: "Pointages",
    description: "Présences et horaires",
    icon: ClipboardCheck,
  },
  {
    value: "controles",
    label: "Contrôles",
    description: "Contrôles terrain des contrôleurs",
    icon: Shield,
  },
  {
    value: "anomalies",
    label: "Anomalies",
    description: "Incidents signalés",
    icon: AlertTriangle,
  },
  {
    value: "contrats",
    label: "Contrats",
    description: "Contrats et échéances",
    icon: FileText,
  },
  {
    value: "absences",
    label: "Absences",
    description: "Congés, maladies, permissions",
    icon: CalendarOff,
  },
  {
    value: "paie",
    label: "Paie",
    description: "Périodes et bulletins",
    icon: Wallet,
  },
];

const TYPE_LABELS: Record<string, string> = Object.fromEntries(
  REPORT_TYPES.map((o) => [o.value, o.label]),
);

const STATUT_LABEL: Record<string, string> = {
  pending: "En file",
  processing: "En cours",
  done: "Prêt",
  failed: "Échec",
};

function toLocalIsoDate(d: Date) {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${y}-${m}-${day}`;
}

function addDays(base: Date, days: number) {
  const d = new Date(base);
  d.setDate(d.getDate() + days);
  return d;
}

function startOfMonth(d: Date) {
  return new Date(d.getFullYear(), d.getMonth(), 1);
}

function endOfMonth(d: Date) {
  return new Date(d.getFullYear(), d.getMonth() + 1, 0);
}

function periodLabel(filtres: Record<string, unknown> | null | undefined) {
  if (!filtres) return null;
  const from = filtres.date_debut;
  const to = filtres.date_fin;
  if (typeof from === "string" && typeof to === "string") {
    return `${formatDate(from)} → ${formatDate(to)}`;
  }
  if (typeof from === "string") return `Depuis ${formatDate(from)}`;
  if (typeof to === "string") return `Jusqu’au ${formatDate(to)}`;
  return null;
}

async function downloadRapportFile(rapport: RapportExport) {
  const response = await apiClient.get(
    `/rapports/jobs/${rapport.id}/download`,
    { responseType: "blob" },
  );
  const ext = rapport.format === "xlsx" ? "xlsx" : "pdf";
  const mime =
    ext === "xlsx"
      ? "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
      : "application/pdf";
  const blob = new Blob([response.data], { type: mime });
  const objectUrl = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = objectUrl;
  link.download = `rapport-${rapport.type}-${rapport.created_at?.slice(0, 10) ?? "export"}.${ext}`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(objectUrl);
}

export default function RapportsPage() {
  const today = useMemo(() => new Date(), []);
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [dateDebut, setDateDebut] = useState(() =>
    toLocalIsoDate(startOfMonth(today)),
  );
  const [dateFin, setDateFin] = useState(() => toLocalIsoDate(today));
  const [type, setType] = useState("agents");
  const [format, setFormat] = useState<"pdf" | "xlsx">("pdf");
  const { data, isLoading } = useRapports({ page, per_page: perPage });
  const generate = useGenerateRapport();
  const { toast } = useToast();

  const selectedType = REPORT_TYPES.find((t) => t.value === type) ?? REPORT_TYPES[0];

  const applyPreset = useCallback(
    (preset: "7d" | "month" | "last_month") => {
      const now = new Date();
      if (preset === "7d") {
        setDateDebut(toLocalIsoDate(addDays(now, -6)));
        setDateFin(toLocalIsoDate(now));
        return;
      }
      if (preset === "month") {
        setDateDebut(toLocalIsoDate(startOfMonth(now)));
        setDateFin(toLocalIsoDate(now));
        return;
      }
      const prev = new Date(now.getFullYear(), now.getMonth() - 1, 1);
      setDateDebut(toLocalIsoDate(startOfMonth(prev)));
      setDateFin(toLocalIsoDate(endOfMonth(prev)));
    },
    [],
  );

  const runExport = useCallback(async () => {
    if (!dateDebut || !dateFin) {
      toast("Indiquez la période du rapport.", "danger");
      return;
    }
    if (dateFin < dateDebut) {
      toast("La date de fin doit être ≥ la date de début.", "danger");
      return;
    }
    try {
      const rapport = await generate.mutateAsync({
        type,
        format,
        filtres: { date_debut: dateDebut, date_fin: dateFin },
      });
      setPage(1);
      toast(
        `Rapport « ${TYPE_LABELS[type] ?? type} » prêt (${format.toUpperCase()}).`,
      );
      if (rapport?.data?.statut === "done" || rapport?.data?.export?.url) {
        try {
          await downloadRapportFile(rapport.data);
        } catch {
          /* historique reste disponible */
        }
      }
    } catch (err) {
      toast(
        getApiErrorMessage(err, "Impossible de lancer le rapport."),
        "danger",
      );
    }
  }, [dateDebut, dateFin, format, generate, toast, type]);

  const columns = useMemo<ColumnDef<RapportExport>[]>(
    () => [
      {
        accessorKey: "created_at",
        header: "Demandé le",
        cell: ({ getValue }) => (
          <span className="whitespace-nowrap text-sm">
            {formatDateTime(String(getValue()))}
          </span>
        ),
      },
      {
        accessorKey: "type",
        header: "Rapport",
        cell: ({ getValue }) => {
          const v = String(getValue());
          return (
            <span className="font-medium text-ink">
              {TYPE_LABELS[v] ?? labelize(v)}
            </span>
          );
        },
      },
      {
        id: "periode",
        header: "Période",
        cell: ({ row }) => {
          const label = periodLabel(row.original.filtres);
          return label ? (
            <span className="text-xs text-ink-muted">{label}</span>
          ) : (
            <span className="text-ink-faint">—</span>
          );
        },
      },
      {
        accessorKey: "format",
        header: "Format",
        cell: ({ getValue }) => (
          <span className="inline-flex items-center gap-1 font-mono text-xs uppercase text-ink-muted">
            {String(getValue()) === "xlsx" ? (
              <FileSpreadsheet className="size-3.5" />
            ) : (
              <FileText className="size-3.5" />
            )}
            {String(getValue())}
          </span>
        ),
      },
      {
        accessorKey: "statut",
        header: "Statut",
        cell: ({ getValue, row }) => {
          const v = String(getValue());
          return (
            <Badge tone={statusTone(v)} title={row.original.erreur ?? undefined}>
              {STATUT_LABEL[v] ?? labelize(v)}
            </Badge>
          );
        },
      },
      {
        id: "export",
        header: "",
        enableSorting: false,
        cell: ({ row }) => {
          const rapport = row.original;
          const ready = rapport.statut === "done" || !!rapport.export?.url;
          if (ready) {
            return (
              <Button
                size="sm"
                variant="secondary"
                onClick={async () => {
                  try {
                    await downloadRapportFile(rapport);
                  } catch (err) {
                    toast(
                      getApiErrorMessage(err, "Téléchargement impossible."),
                      "danger",
                    );
                  }
                }}
              >
                <Download className="size-3.5" />
                Télécharger
              </Button>
            );
          }
          if (rapport.statut === "failed") {
            return (
              <span
                className="text-xs text-danger"
                title={rapport.erreur ?? ""}
              >
                Échec
              </span>
            );
          }
          return <span className="text-xs text-ink-faint">…</span>;
        },
      },
    ],
    [toast],
  );

  return (
    <PermissionGate permission="rapports.generate" title="Rapports">
      <div className="mx-auto max-w-6xl space-y-8">
        <PageHeader
          title="Rapports"
          description="Choisissez un rapport, une période, puis exportez en PDF ou Excel."
        />

        <section className="overflow-hidden rounded-2xl border border-border bg-white shadow-[0_1px_0_rgba(20,26,16,0.04)]">
          <div className="border-b border-border px-5 py-4 sm:px-6">
            <h2 className="text-base font-semibold text-ink">Nouveau rapport</h2>
            <p className="mt-0.5 text-sm text-ink-muted">
              1. Type → 2. Période → 3. Format → Exporter
            </p>
          </div>

          <div className="space-y-6 px-5 py-5 sm:px-6 sm:py-6">
            <div>
              <p className="mb-3 text-[11px] font-medium uppercase tracking-[0.12em] text-ink-faint">
                Type de rapport
              </p>
              <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                {REPORT_TYPES.map((item) => {
                  const Icon = item.icon;
                  const active = type === item.value;
                  return (
                    <button
                      key={item.value}
                      type="button"
                      onClick={() => setType(item.value)}
                      className={cn(
                        "flex items-start gap-3 rounded-xl border px-3.5 py-3 text-left transition",
                        active
                          ? "border-teal bg-teal/[0.06] ring-1 ring-teal/30"
                          : "border-border bg-paper/40 hover:border-teal/40 hover:bg-white",
                      )}
                    >
                      <span
                        className={cn(
                          "mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg",
                          active
                            ? "bg-teal text-white"
                            : "bg-teal/10 text-teal",
                        )}
                      >
                        <Icon className="size-4" />
                      </span>
                      <span className="min-w-0">
                        <span className="block text-sm font-semibold text-ink">
                          {item.label}
                        </span>
                        <span className="mt-0.5 block text-xs leading-snug text-ink-muted">
                          {item.description}
                        </span>
                      </span>
                    </button>
                  );
                })}
              </div>
            </div>

            <div className="grid gap-5 lg:grid-cols-[1.2fr_0.8fr]">
              <div>
                <p className="mb-3 text-[11px] font-medium uppercase tracking-[0.12em] text-ink-faint">
                  Période
                </p>
                <div className="mb-3 flex flex-wrap gap-1.5">
                  {(
                    [
                      { id: "7d" as const, label: "7 derniers jours" },
                      { id: "month" as const, label: "Ce mois" },
                      { id: "last_month" as const, label: "Mois dernier" },
                    ] as const
                  ).map((preset) => (
                    <button
                      key={preset.id}
                      type="button"
                      onClick={() => applyPreset(preset.id)}
                      className="rounded-md border border-border bg-white px-2.5 py-1.5 text-xs font-medium text-ink-muted transition hover:border-teal/40 hover:text-teal"
                    >
                      {preset.label}
                    </button>
                  ))}
                </div>
                <div className="grid gap-3 sm:grid-cols-2">
                  <DatePicker
                    label="Du"
                    value={dateDebut}
                    onChange={(e) => setDateDebut(e.target.value)}
                  />
                  <DatePicker
                    label="Au"
                    value={dateFin}
                    onChange={(e) => setDateFin(e.target.value)}
                  />
                </div>
              </div>

              <div>
                <p className="mb-3 text-[11px] font-medium uppercase tracking-[0.12em] text-ink-faint">
                  Format
                </p>
                <div className="inline-flex rounded-lg border border-border bg-paper/50 p-1">
                  <button
                    type="button"
                    onClick={() => setFormat("pdf")}
                    className={cn(
                      "inline-flex items-center gap-1.5 rounded-md px-3.5 py-2 text-sm font-medium transition",
                      format === "pdf"
                        ? "bg-white text-ink shadow-sm"
                        : "text-ink-muted hover:text-ink",
                    )}
                  >
                    <FileText className="size-4" />
                    PDF
                  </button>
                  <button
                    type="button"
                    onClick={() => setFormat("xlsx")}
                    className={cn(
                      "inline-flex items-center gap-1.5 rounded-md px-3.5 py-2 text-sm font-medium transition",
                      format === "xlsx"
                        ? "bg-white text-ink shadow-sm"
                        : "text-ink-muted hover:text-ink",
                    )}
                  >
                    <FileSpreadsheet className="size-4" />
                    Excel
                  </button>
                </div>

                <div className="mt-5 rounded-xl border border-dashed border-border bg-paper/40 px-4 py-3">
                  <p className="text-xs text-ink-faint">Récapitulatif</p>
                  <p className="mt-1 text-sm font-medium text-ink">
                    {selectedType.label} · {format.toUpperCase()}
                  </p>
                  <p className="mt-0.5 text-xs text-ink-muted">
                    {formatDate(dateDebut)} → {formatDate(dateFin)}
                  </p>
                  <Button
                    className="mt-4 w-full sm:w-auto"
                    loading={generate.isPending}
                    onClick={() => void runExport()}
                  >
                    <Download className="size-4" />
                    Générer &amp; télécharger
                  </Button>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section className="space-y-3">
          <div>
            <h2 className="text-base font-semibold text-ink">Historique</h2>
            <p className="text-sm text-ink-muted">
              Retrouvez et retéléchargez vos exports précédents.
            </p>
          </div>
          <DataTable
            data={data?.data ?? []}
            columns={columns}
            isLoading={isLoading}
            selectable={false}
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
            emptyTitle="Aucun rapport pour l’instant"
            emptyDescription="Générez votre premier export ci-dessus."
          />
        </section>
      </div>
    </PermissionGate>
  );
}
