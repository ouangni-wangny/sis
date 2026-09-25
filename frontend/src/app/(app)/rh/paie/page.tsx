"use client";

import Link from "next/link";
import { useCallback, useEffect, useMemo, useState } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { type ColumnDef } from "@tanstack/react-table";
import {
  ArrowLeft,
  CalendarRange,
  CheckCircle2,
  Download,
  FileDown,
  FileText,
  Lock,
  Plus,
  Wallet,
} from "lucide-react";
import { useDebouncedValue } from "@/application/hooks/useDebouncedValue";
import {
  useBulletinsPaie,
  useCloturerPeriodePaie,
  useCreatePeriodePaie,
  useDeletePeriodePaie,
  useExportBulletinsPdf,
  useGenererBulletinPdf,
  useGenererBulletinsPaie,
  useGrades,
  useMarquerBulletinPaye,
  useRenseignerSalairePercu,
  useRenseignerSalairePercuBulk,
  useMarquerBulletinPayeBulk,
  useComptesTresorerieOptions,
  usePeriodesPaie,
  useModesPaiementOptions,
  useValiderPeriodePaie,
  useVilles,
} from "@/application/hooks/useResources";
import type { BulletinPaie, PeriodePaie } from "@/domain/types/entities";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { DataTable } from "@/presentation/components/tables/DataTable";
import { TableActions } from "@/presentation/components/tables/TableActions";
import { Alert } from "@/presentation/components/ui/Alert";
import { Badge, statusTone } from "@/presentation/components/ui/Badge";
import { Button } from "@/presentation/components/ui/Button";
import { Card, CardBody, CardHeader } from "@/presentation/components/ui/Card";
import { ConfirmDialog } from "@/presentation/components/ui/ConfirmDialog";
import { RequiredFieldsLegend } from "@/presentation/components/ui/FieldLabel";
import { Input } from "@/presentation/components/ui/Input";
import { Modal } from "@/presentation/components/ui/Modal";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { Select } from "@/presentation/components/ui/Select";
import { StatCard } from "@/presentation/components/ui/StatCard";
import { Textarea } from "@/presentation/components/ui/Textarea";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { useToast } from "@/presentation/providers/ToastProvider";
import { apiClient } from "@/infrastructure/http/apiClient";
import { bulletinsPaieApi, periodesPaieApi } from "@/infrastructure/http/resources";
import { getApiErrorMessage, getApiErrorMessageAsync } from "@/shared/lib/api-error";
import { canManagePaie, canPayerPaie, canSeeSalaire } from "@/shared/lib/can";
import {
  formatDate,
  formatFcfa,
  formatSalaire,
  labelize,
  labelMoisAnnee,
  MOIS_LABELS,
} from "@/shared/lib/format";
import { cn } from "@/shared/lib/cn";

const periodeSchema = z.object({
  mois: z.string().min(1, "Mois requis"),
  annee: z.string().min(4, "Année requise"),
  commentaire: z.string().optional(),
});

type PeriodeFormValues = z.infer<typeof periodeSchema>;

const now = new Date();

const emptyPeriodeDefaults: PeriodeFormValues = {
  mois: String(now.getMonth() + 1),
  annee: String(now.getFullYear()),
  commentaire: "",
};

const STATUT_PERIODE_OPTIONS = [
  { value: "", label: "Tous les statuts" },
  { value: "brouillon", label: "Brouillon" },
  { value: "validee", label: "Validée" },
  { value: "cloturee", label: "Clôturée" },
];

const STATUT_BULLETIN_OPTIONS = [
  { value: "", label: "Tous les statuts" },
  { value: "brouillon", label: "Brouillon" },
  { value: "valide", label: "Validé" },
  { value: "paye", label: "Payé" },
];

async function downloadBulletinPdf(bulletinId: string) {
  const response = await apiClient.get(bulletinsPaieApi.downloadPdf(bulletinId), {
    responseType: "blob",
  });
  const blob = new Blob([response.data], { type: "application/pdf" });
  const objectUrl = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = objectUrl;
  link.download = `bulletin-${bulletinId}.pdf`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(objectUrl);
}

export default function PaiePage() {
  const { toast } = useToast();
  const { user } = useAuth();
  const canManage = canManagePaie(user);
  const canPayer = canPayerPaie(user);
  const showSalaire = canSeeSalaire(user);

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [periodeQ, setPeriodeQ] = useState("");
  const [filterAnnee, setFilterAnnee] = useState("");
  const [filterMois, setFilterMois] = useState("");
  const [filterStatut, setFilterStatut] = useState("");
  const periodeSearch = useDebouncedValue(periodeQ);

  const [selectedPeriodeId, setSelectedPeriodeId] = useState<string | null>(null);
  const [bulletinsPage, setBulletinsPage] = useState(1);
  const [bulletinsPerPage, setBulletinsPerPage] = useState(50);
  const [bulletinsQ, setBulletinsQ] = useState("");
  const [filterBulletinGradeId, setFilterBulletinGradeId] = useState("");
  const [filterBulletinVilleId, setFilterBulletinVilleId] = useState("");
  const [filterBulletinStatut, setFilterBulletinStatut] = useState("");
  const [filterBulletinMode, setFilterBulletinMode] = useState("");
  const [pdfListeLoading, setPdfListeLoading] = useState(false);
  const bulletinsSearch = useDebouncedValue(bulletinsQ);
  const [periodeOpen, setPeriodeOpen] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [salaireBulletin, setSalaireBulletin] = useState<BulletinPaie | null>(null);
  const [salaireForm, setSalaireForm] = useState({ salaire_net: "" });
  const [salaireError, setSalaireError] = useState<string | null>(null);
  const [payeBulletin, setPayeBulletin] = useState<BulletinPaie | null>(null);
  const [payeForm, setPayeForm] = useState({
    mode: "virement",
    compte_tresorerie_id: "",
    reference: "",
  });
  const [payeError, setPayeError] = useState<string | null>(null);
  const [periodeToDelete, setPeriodeToDelete] = useState<PeriodePaie | null>(
    null,
  );
  const [periodeToCloturer, setPeriodeToCloturer] = useState<PeriodePaie | null>(
    null,
  );
  const [selectedBulletinIds, setSelectedBulletinIds] = useState<string[]>([]);
  const [bulkSalaireOpen, setBulkSalaireOpen] = useState(false);
  const [bulkSalaireBulletins, setBulkSalaireBulletins] = useState<BulletinPaie[]>([]);
  const [bulkSalaireMontants, setBulkSalaireMontants] = useState<
    Record<string, string>
  >({});
  const [bulkSalaireError, setBulkSalaireError] = useState<string | null>(null);
  const [bulkSalaireBusy, setBulkSalaireBusy] = useState(false);
  const [bulkSalaireApplyAll, setBulkSalaireApplyAll] = useState("");
  const [bulkPayOpen, setBulkPayOpen] = useState(false);
  const [bulkPayBulletins, setBulkPayBulletins] = useState<BulletinPaie[]>([]);
  const [bulkPayForm, setBulkPayForm] = useState({
    mode: "virement",
    compte_tresorerie_id: "",
    reference: "",
  });
  const [bulkPayError, setBulkPayError] = useState<string | null>(null);
  const [bulkPayBusy, setBulkPayBusy] = useState(false);
  const [bulkPayConfirmOpen, setBulkPayConfirmOpen] = useState(false);

  const anneeOptions = useMemo(() => {
    const current = now.getFullYear();
    return [
      { value: "", label: "Toutes les années" },
      ...Array.from({ length: 6 }, (_, i) => {
        const year = String(current - i);
        return { value: year, label: year };
      }),
    ];
  }, []);

  const moisFilterOptions = useMemo(
    () => [
      { value: "", label: "Tous les mois" },
      ...MOIS_LABELS.map((label, i) => ({
        value: String(i + 1),
        label,
      })),
    ],
    [],
  );

  const periodes = usePeriodesPaie({
    page,
    per_page: perPage,
    q: periodeSearch || undefined,
    annee: filterAnnee || undefined,
    mois: filterMois || undefined,
    statut: filterStatut || undefined,
  });
  const periodesBrouillon = usePeriodesPaie({ statut: "brouillon", per_page: 1 });
  const periodesValidees = usePeriodesPaie({ statut: "validee", per_page: 1 });
  const periodesCloturees = usePeriodesPaie({ statut: "cloturee", per_page: 1 });
  const bulletins = useBulletinsPaie(selectedPeriodeId ?? undefined, {
    page: bulletinsPage,
    per_page: bulletinsPerPage,
    q: bulletinsSearch || undefined,
    grade_id: filterBulletinGradeId || undefined,
    ville_id: filterBulletinVilleId || undefined,
    statut: filterBulletinStatut || undefined,
    mode: filterBulletinMode || undefined,
  });
  const createPeriode = useCreatePeriodePaie();
  const genererBulletins = useGenererBulletinsPaie();
  const validerPeriode = useValiderPeriodePaie();
  const cloturerPeriode = useCloturerPeriodePaie();
  const deletePeriode = useDeletePeriodePaie();
  const genererPdf = useGenererBulletinPdf();
  const exportBulletinsPdf = useExportBulletinsPdf();
  const renseignerSalaire = useRenseignerSalairePercu();
  const renseignerSalaireBulk = useRenseignerSalairePercuBulk();
  const marquerPaye = useMarquerBulletinPaye();
  const marquerPayeBulk = useMarquerBulletinPayeBulk();
  const { data: gradesData } = useGrades({ all: true });
  const { data: villesData } = useVilles({ all: true });
  const { data: comptesRes } = useComptesTresorerieOptions({
    enabled: canPayer,
  });
  const comptes = comptesRes?.data ?? [];
  const { data: modesRes } = useModesPaiementOptions();
  const modeOptions = useMemo(() => {
    const modes = modesRes?.data ?? [];
    return modes.map((m) => ({ value: m.code, label: m.libelle }));
  }, [modesRes]);
  const gradeFilterOptions = useMemo(
    () => [
      { value: "", label: "Tous les grades" },
      ...(gradesData?.data ?? []).map((g) => ({
        value: g.id,
        label: g.libelle,
      })),
    ],
    [gradesData],
  );
  const villeFilterOptions = useMemo(
    () => [
      { value: "", label: "Toutes les villes" },
      ...(villesData?.data ?? []).map((v) => ({
        value: v.id,
        label: v.libelle,
      })),
    ],
    [villesData],
  );
  const modeFilterOptions = useMemo(
    () => [{ value: "", label: "Tous les modes" }, ...modeOptions],
    [modeOptions],
  );

  const selectedPeriode = useMemo(
    () =>
      (periodes.data?.data ?? []).find((p) => p.id === selectedPeriodeId) ??
      null,
    [periodes.data?.data, selectedPeriodeId],
  );

  // Par défaut : mois calendaire en cours s’il existe, sinon première période de la liste.
  useEffect(() => {
    if (selectedPeriodeId) return;
    const list = periodes.data?.data ?? [];
    if (!list.length) return;
    const now = new Date();
    const moisCourant = now.getMonth() + 1;
    const anneeCourante = now.getFullYear();
    const current =
      list.find((p) => p.mois === moisCourant && p.annee === anneeCourante) ??
      list[0];
    setSelectedPeriodeId(current.id);
  }, [periodes.data?.data, selectedPeriodeId]);

  const setStatutFilter = (statut: string) => {
    setFilterStatut((current) => (current === statut ? "" : statut));
    setPage(1);
  };

  const selectPeriode = (id: string) => {
    setSelectedPeriodeId(id);
    setBulletinsPage(1);
    setBulletinsQ("");
    setFilterBulletinGradeId("");
    setFilterBulletinVilleId("");
    setFilterBulletinStatut("");
    setFilterBulletinMode("");
    setSelectedBulletinIds([]);
  };

  const handleExportBulletinsPdf = async () => {
    if (!selectedPeriodeId || !selectedPeriode) {
      toast("Sélectionnez une période.", "danger");
      return;
    }
    setPdfListeLoading(true);
    try {
      const data = await exportBulletinsPdf.mutateAsync({
        periodeId: selectedPeriodeId,
        q: bulletinsSearch || undefined,
        grade_id: filterBulletinGradeId || undefined,
        ville_id: filterBulletinVilleId || undefined,
        statut: filterBulletinStatut || undefined,
        mode: filterBulletinMode || undefined,
      });
      const blob =
        data instanceof Blob
          ? data
          : new Blob([data as BlobPart], { type: "application/pdf" });
      const objectUrl = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = objectUrl;
      const mois = String(selectedPeriode.mois).padStart(2, "0");
      link.download = `bulletins-${selectedPeriode.annee}-${mois}.pdf`;
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
    } finally {
      setPdfListeLoading(false);
    }
  };

  const bulletinsList = useMemo(
    () => bulletins.data?.data ?? [],
    [bulletins.data?.data],
  );

  const unpaidSelected = useMemo(
    () =>
      bulletinsList.filter(
        (b) => selectedBulletinIds.includes(b.id) && b.statut !== "paye",
      ),
    [bulletinsList, selectedBulletinIds],
  );

  const unpaidReadySelected = useMemo(
    () => unpaidSelected.filter((b) => b.salaire_renseigne),
    [unpaidSelected],
  );

  const unpaidNeedSalaireSelected = useMemo(
    () => unpaidSelected.filter((b) => !b.salaire_renseigne),
    [unpaidSelected],
  );

  const unpaidTotalQuery = useBulletinsPaie(
    selectedPeriodeId ?? undefined,
    { non_payes: 1, per_page: 1 },
  );
  const unpaidTotal = unpaidTotalQuery.data?.meta.total ?? 0;

  const openBulkSalaire = useCallback(
    (rows: BulletinPaie[]) => {
      if (!rows.length) {
        toast("Aucun bulletin à renseigner.", "danger");
        return;
      }
      setBulkSalaireBulletins(rows);
      setBulkSalaireMontants(
        Object.fromEntries(
          rows.map((b) => {
            const net = Number(b.salaire_net);
            return [
              b.id,
              Number.isFinite(net) && net > 0 ? String(net) : "",
            ];
          }),
        ),
      );
      setBulkSalaireApplyAll("");
      setBulkSalaireError(null);
      setBulkSalaireOpen(true);
    },
    [toast],
  );

  const openBulkPay = useCallback(
    (rows: BulletinPaie[]) => {
      const ready = rows.filter((b) => b.salaire_renseigne && b.statut !== "paye");
      if (!ready.length) {
        toast(
          "Aucun bulletin prêt : la RH doit d’abord saisir les salaires perçus.",
          "danger",
        );
        return;
      }
      setBulkPayBulletins(ready);
      setBulkPayForm({
        mode: modeOptions[0]?.value ?? "virement",
        compte_tresorerie_id: comptes[0]?.id ?? "",
        reference: "",
      });
      setBulkPayError(null);
      setBulkPayOpen(true);
    },
    [comptes, modeOptions, toast],
  );

  const applySalaireToAll = useCallback(
    (amount: string | number) => {
      const value = String(amount).trim();
      if (!value) return;
      setBulkSalaireApplyAll(value);
      setBulkSalaireMontants(
        Object.fromEntries(bulkSalaireBulletins.map((b) => [b.id, value])),
      );
    },
    [bulkSalaireBulletins],
  );

  const salaireProposes = useMemo(() => {
    const counts = new Map<number, number>();
    for (const b of bulkSalaireBulletins) {
      const net = Number(b.salaire_net);
      if (!Number.isFinite(net) || net <= 0) continue;
      const key = Math.round(net);
      counts.set(key, (counts.get(key) ?? 0) + 1);
    }
    return Array.from(counts.entries())
      .sort((a, b) => b[1] - a[1] || b[0] - a[0])
      .slice(0, 12)
      .map(([montant, count]) => ({ montant, count }));
  }, [bulkSalaireBulletins]);

  const openBulkSalaireAllUnpaid = useCallback(async () => {
    if (!selectedPeriodeId) return;
    setBulkSalaireBusy(true);
    setBulkSalaireError(null);
    try {
      const res = await periodesPaieApi.bulletins(selectedPeriodeId, {
        non_payes: 1,
        all: 1,
      });
      const rows = (Array.isArray(res.data) ? res.data : []).filter(
        (b) => !b.salaire_renseigne,
      );
      openBulkSalaire(rows);
    } catch (err) {
      toast(
        getApiErrorMessage(err, "Impossible de charger les bulletins."),
        "danger",
      );
    } finally {
      setBulkSalaireBusy(false);
    }
  }, [openBulkSalaire, selectedPeriodeId, toast]);

  const openBulkPayAllUnpaid = useCallback(async () => {
    if (!selectedPeriodeId) return;
    setBulkPayBusy(true);
    setBulkPayError(null);
    try {
      const res = await periodesPaieApi.bulletins(selectedPeriodeId, {
        non_payes: 1,
        all: 1,
      });
      const rows = Array.isArray(res.data) ? res.data : [];
      openBulkPay(rows);
    } catch (err) {
      toast(
        getApiErrorMessage(err, "Impossible de charger les bulletins."),
        "danger",
      );
    } finally {
      setBulkPayBusy(false);
    }
  }, [openBulkPay, selectedPeriodeId, toast]);

  const closeBulkSalaire = () => {
    if (bulkSalaireBusy) return;
    setBulkSalaireOpen(false);
    setBulkSalaireBulletins([]);
    setBulkSalaireApplyAll("");
    setBulkSalaireError(null);
  };

  const closeBulkPay = () => {
    if (bulkPayBusy) return;
    setBulkPayOpen(false);
    setBulkPayConfirmOpen(false);
    setBulkPayBulletins([]);
    setBulkPayError(null);
  };

  const validateBulkSalaire = (): boolean => {
    for (const b of bulkSalaireBulletins) {
      const net = Number(bulkSalaireMontants[b.id]);
      if (!Number.isFinite(net) || net <= 0) {
        setBulkSalaireError("Chaque ligne doit avoir un salaire perçu > 0.");
        return false;
      }
    }
    setBulkSalaireError(null);
    return true;
  };

  const validateBulkPay = (): boolean => {
    if (!bulkPayForm.mode) {
      setBulkPayError("Choisissez un mode de paiement.");
      return false;
    }
    if (!bulkPayForm.compte_tresorerie_id) {
      setBulkPayError("Choisissez un compte de trésorerie.");
      return false;
    }
    setBulkPayError(null);
    return true;
  };

  const executeBulkSalaire = async () => {
    if (!validateBulkSalaire()) return;
    setBulkSalaireBusy(true);
    setBulkSalaireError(null);
    try {
      const items = bulkSalaireBulletins.map((b) => ({
        id: b.id,
        salaire_net: Number(bulkSalaireMontants[b.id]),
      }));
      const res = await renseignerSalaireBulk.mutateAsync(items);
      const ok = res.data?.updated ?? items.length;
      toast(
        `${ok} salaire${ok > 1 ? "s" : ""} perçu${ok > 1 ? "s" : ""} enregistré${ok > 1 ? "s" : ""}.`,
      );
      setBulkSalaireOpen(false);
      setBulkSalaireBulletins([]);
      setBulkSalaireApplyAll("");
      setBulkSalaireError(null);
      setSelectedBulletinIds([]);
    } catch (err) {
      setBulkSalaireError(
        getApiErrorMessage(err, "Échec de l’enregistrement groupé."),
      );
      toast(getApiErrorMessage(err, "Échec de l’enregistrement."), "danger");
    } finally {
      setBulkSalaireBusy(false);
    }
  };

  const executeBulkPay = async () => {
    if (!validateBulkPay()) {
      setBulkPayConfirmOpen(false);
      return;
    }
    setBulkPayBusy(true);
    setBulkPayError(null);
    try {
      const res = await marquerPayeBulk.mutateAsync({
        bulletin_ids: bulkPayBulletins.map((b) => b.id),
        mode: bulkPayForm.mode,
        compte_tresorerie_id: bulkPayForm.compte_tresorerie_id,
        reference: bulkPayForm.reference || undefined,
      });
      const ok = res.data?.updated ?? bulkPayBulletins.length;
      toast(
        `${ok} bulletin${ok > 1 ? "s" : ""} marqué${ok > 1 ? "s" : ""} payé.`,
      );
      setBulkPayConfirmOpen(false);
      setBulkPayOpen(false);
      setBulkPayBulletins([]);
      setSelectedBulletinIds([]);
    } catch (err) {
      setBulkPayConfirmOpen(false);
      setBulkPayError(getApiErrorMessage(err, "Échec du règlement groupé."));
      toast(getApiErrorMessage(err, "Échec du règlement."), "danger");
    } finally {
      setBulkPayBusy(false);
    }
  };

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<PeriodeFormValues>({
    resolver: zodResolver(periodeSchema),
    defaultValues: emptyPeriodeDefaults,
  });

  const periodeBusy = isSubmitting || createPeriode.isPending;

  const closePeriodeModal = () => {
    if (periodeBusy) return;
    setPeriodeOpen(false);
    setFormError(null);
    reset(emptyPeriodeDefaults);
  };

  const runPeriodeAction = useCallback(
    async (action: () => Promise<unknown>, successMessage: string) => {
      try {
        await action();
        toast(successMessage);
      } catch (err) {
        toast(getApiErrorMessage(err, "Échec de l’opération."), "danger");
      }
    },
    [toast],
  );

  const periodeColumns = useMemo<ColumnDef<PeriodePaie>[]>(
    () => [
      {
        id: "periode",
        header: "Période",
        cell: ({ row }) => labelMoisAnnee(row.original.mois, row.original.annee),
      },
      {
        accessorKey: "date_debut",
        header: "Du",
        cell: ({ getValue }) => formatDate(String(getValue())),
      },
      {
        accessorKey: "date_fin",
        header: "Au",
        cell: ({ getValue }) => formatDate(String(getValue())),
      },
      {
        accessorKey: "statut",
        header: "Statut",
        cell: ({ getValue }) => {
          const v = String(getValue());
          return <Badge tone={statusTone(v)}>{labelize(v)}</Badge>;
        },
      },
      {
        accessorKey: "bulletins_count",
        header: "Bulletins",
        cell: ({ getValue }) => String(getValue() ?? 0),
      },
      {
        id: "actions",
        header: "",
        enableSorting: false,
        cell: ({ row }) => {
          const canDelete =
            canManage &&
            row.original.statut === "brouillon" &&
            (row.original.bulletins_count ?? 0) === 0;
          return (
            <div className="flex items-center justify-end gap-2">
              <Button
                size="sm"
                variant={
                  selectedPeriodeId === row.original.id
                    ? "primary"
                    : "secondary"
                }
                onClick={() => selectPeriode(row.original.id)}
              >
                Voir bulletins
              </Button>
              {canDelete ? (
                <TableActions
                  canEdit={false}
                  canDelete
                  onDelete={() => setPeriodeToDelete(row.original)}
                />
              ) : null}
            </div>
          );
        },
      },
    ],
    [canManage, selectedPeriodeId],
  );

  const bulletinColumns = useMemo<ColumnDef<BulletinPaie>[]>(
    () => [
      {
        id: "agent",
        header: "Agent",
        cell: ({ row }) =>
          row.original.agent
            ? `${row.original.agent.prenom} ${row.original.agent.nom}`
            : "—",
      },
      {
        id: "matricule",
        header: "Matricule",
        cell: ({ row }) => row.original.agent?.matricule ?? "—",
      },
      {
        id: "grade",
        header: "Grade",
        cell: ({ row }) => row.original.agent?.grade?.libelle ?? "—",
      },
      {
        id: "telephone",
        header: "Téléphone",
        cell: ({ row }) => row.original.agent?.telephone ?? "—",
      },
      ...(showSalaire
        ? ([
            {
              accessorKey: "salaire_brut",
              header: "Brut",
              cell: ({ getValue }) =>
                formatSalaire(getValue() as string | number | null, user),
            },
            {
              accessorKey: "salaire_net",
              header: "Net / perçu",
              cell: ({ getValue, row }) => (
                <span className="inline-flex flex-col gap-0.5">
                  <span>
                    {formatSalaire(
                      getValue() as string | number | null,
                      user,
                    )}
                  </span>
                  {row.original.salaire_renseigne ? (
                    <span className="text-[10px] font-medium uppercase text-teal">
                      Perçu saisi
                    </span>
                  ) : null}
                </span>
              ),
            },
          ] as ColumnDef<BulletinPaie>[])
        : ([
            {
              id: "salaire_renseigne",
              header: "Salaire perçu",
              cell: ({ row }) =>
                row.original.statut === "paye" ? (
                  <Badge tone="success">Payé</Badge>
                ) : row.original.salaire_renseigne ? (
                  <Badge tone="info">Saisi par RH</Badge>
                ) : (
                  <Badge tone="warning">En attente RH</Badge>
                ),
            },
          ] as ColumnDef<BulletinPaie>[])),
      {
        accessorKey: "statut",
        header: "Statut",
        cell: ({ getValue }) => {
          const v = String(getValue());
          return <Badge tone={statusTone(v)}>{labelize(v)}</Badge>;
        },
      },
      {
        accessorKey: "paye_le",
        header: "Payé le",
        cell: ({ getValue }) => formatDate(getValue() as string | null),
      },
      {
        accessorKey: "mode_paiement",
        header: "Mode",
        cell: ({ getValue, row }) =>
          row.original.statut === "paye"
            ? String(getValue() ?? "—").toUpperCase()
            : "—",
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
                key: "pdf",
                label: "Générer PDF",
                icon: FileText,
                hidden:
                  !canManage ||
                  !showSalaire ||
                  Boolean(row.original.pdf_url),
                onClick: () =>
                  void runPeriodeAction(
                    () => genererPdf.mutateAsync(row.original.id),
                    "PDF généré.",
                  ),
              },
              {
                key: "download",
                label: "Télécharger PDF",
                icon: Download,
                hidden: !showSalaire || !row.original.pdf_url,
                onClick: async () => {
                  try {
                    await downloadBulletinPdf(row.original.id);
                  } catch (err) {
                    toast(
                      getApiErrorMessage(err, "Téléchargement impossible."),
                      "danger",
                    );
                  }
                },
              },
              {
                key: "salaire",
                label: row.original.salaire_renseigne
                  ? "Modifier salaire perçu"
                  : "Saisir salaire perçu",
                hidden:
                  !canManage ||
                  !showSalaire ||
                  row.original.statut === "paye",
                onClick: () => {
                  setSalaireBulletin(row.original);
                  const net = Number(row.original.salaire_net);
                  setSalaireForm({
                    salaire_net:
                      Number.isFinite(net) && net > 0 ? String(net) : "",
                  });
                  setSalaireError(null);
                },
              },
              {
                key: "paye",
                label: "Marquer payé",
                tone: "success",
                hidden:
                  !canPayer ||
                  row.original.statut === "paye" ||
                  !row.original.salaire_renseigne,
                onClick: () => {
                  setPayeBulletin(row.original);
                  setPayeForm({
                    mode: modeOptions[0]?.value ?? "virement",
                    compte_tresorerie_id: comptes[0]?.id ?? "",
                    reference: "",
                  });
                  setPayeError(null);
                },
              },
            ]}
          />
        ),
      },
    ],
    [
      canManage,
      canPayer,
      comptes,
      genererPdf,
      modeOptions,
      runPeriodeAction,
      showSalaire,
      toast,
      user,
    ],
  );

  const moisOptions = MOIS_LABELS.map((label, i) => ({
    value: String(i + 1),
    label,
  }));

  const totalPeriodes = periodes.data?.meta.total ?? 0;
  const countBrouillon = periodesBrouillon.data?.meta.total ?? 0;
  const countValidee = periodesValidees.data?.meta.total ?? 0;
  const countCloturee = periodesCloturees.data?.meta.total ?? 0;
  const bulletinsTotal = bulletins.data?.meta.total ?? 0;
  const bulletinsPayes = useMemo(
    () =>
      (bulletins.data?.data ?? []).filter((b) => b.statut === "paye").length,
    [bulletins.data?.data],
  );

  return (
    <PermissionGate permission={["paie.manage", "paie.view", "paie.payer"]} title="Paie">
      <div className="space-y-6">
        <PageHeader
          title="Paie"
          description="Périodes mensuelles : la RH saisit les salaires perçus, le comptable marque payé."
          actions={
            <div className="flex flex-wrap gap-2">
              <Link href="/rh">
                <Button variant="secondary" size="sm">
                  <ArrowLeft className="size-4" />
                  Contrats &amp; Absences
                </Button>
              </Link>
              {canManage ? (
                <Button size="sm" onClick={() => setPeriodeOpen(true)}>
                  <Plus className="size-4" />
                  Nouvelle période
                </Button>
              ) : null}
            </div>
          }
        />

        {/* Synthèse */}
        <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <button
            type="button"
            className="text-left transition hover:opacity-90"
            onClick={() => {
              setFilterStatut("");
              setPage(1);
            }}
          >
            <StatCard
              label="Périodes"
              value={totalPeriodes}
              hint={filterStatut ? `Filtre : ${labelize(filterStatut)}` : "Toutes"}
              icon={<CalendarRange className="size-4" />}
              className={cn(!filterStatut && "ring-1 ring-teal/30")}
            />
          </button>
          <button
            type="button"
            className="text-left transition hover:opacity-90"
            onClick={() => setStatutFilter("brouillon")}
          >
            <StatCard
              label="Brouillon"
              value={countBrouillon}
              hint="À générer / valider"
              icon={<FileText className="size-4" />}
              className={cn(filterStatut === "brouillon" && "ring-1 ring-teal/30")}
            />
          </button>
          <button
            type="button"
            className="text-left transition hover:opacity-90"
            onClick={() => setStatutFilter("validee")}
          >
            <StatCard
              label="Validées"
              value={countValidee}
              hint="Prêtes à clôturer"
              icon={<CheckCircle2 className="size-4" />}
              className={cn(filterStatut === "validee" && "ring-1 ring-teal/30")}
            />
          </button>
          <button
            type="button"
            className="text-left transition hover:opacity-90"
            onClick={() => setStatutFilter("cloturee")}
          >
            <StatCard
              label="Clôturées"
              value={countCloturee}
              hint="Verrouillées"
              icon={<Lock className="size-4" />}
              className={cn(filterStatut === "cloturee" && "ring-1 ring-teal/30")}
            />
          </button>
        </section>

        {/* Périodes + panneau latéral */}
        <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
          <Card className="min-w-0 overflow-hidden">
            <CardHeader
              title="Périodes de paie"
              description="Sélectionnez une période pour afficher ses bulletins."
              action={
                canManage ? (
                  <Button size="sm" onClick={() => setPeriodeOpen(true)}>
                    <Plus className="size-4" />
                    Nouvelle
                  </Button>
                ) : undefined
              }
            />
            <CardBody>
              <DataTable
                data={periodes.data?.data ?? []}
                columns={periodeColumns}
                isLoading={periodes.isLoading}
                search={{
                  value: periodeQ,
                  onChange: (value) => {
                    setPeriodeQ(value);
                    setPage(1);
                  },
                  placeholder: "Rechercher une période…",
                }}
                toolbar={
                  <div className="flex flex-wrap items-center gap-2">
                    <Select
                      className="min-w-[8.5rem]"
                      value={filterAnnee}
                      options={anneeOptions}
                      onChange={(event) => {
                        setFilterAnnee(event.target.value);
                        setPage(1);
                      }}
                    />
                    <Select
                      className="min-w-[9rem]"
                      value={filterMois}
                      options={moisFilterOptions}
                      onChange={(event) => {
                        setFilterMois(event.target.value);
                        setPage(1);
                      }}
                    />
                    <Select
                      className="min-w-[10rem]"
                      value={filterStatut}
                      options={STATUT_PERIODE_OPTIONS}
                      onChange={(event) => {
                        setFilterStatut(event.target.value);
                        setPage(1);
                      }}
                    />
                  </div>
                }
                pagination={{
                  page,
                  perPage: periodes.data?.meta.per_page ?? perPage,
                  total: periodes.data?.meta.total ?? 0,
                  onPageChange: setPage,
                  onPerPageChange: (n) => {
                    setPerPage(n);
                    setPage(1);
                  },
                }}
                emptyTitle="Aucune période de paie"
                emptyAction={
                  canManage ? (
                    <Button size="sm" onClick={() => setPeriodeOpen(true)}>
                      <Plus className="size-4" />
                      Nouvelle période
                    </Button>
                  ) : undefined
                }
              />
            </CardBody>
          </Card>

          <aside className="space-y-4 xl:sticky xl:top-4 xl:self-start">
            <Card>
              <CardHeader
                title="Période sélectionnée"
                description={
                  selectedPeriode
                    ? labelMoisAnnee(selectedPeriode.mois, selectedPeriode.annee)
                    : "Aucune sélection"
                }
              />
              <CardBody className="space-y-3">
                {!selectedPeriode ? (
                  <p className="text-sm text-ink-faint">
                    Aucune période sur cette page. Cliquez sur « Voir bulletins »
                    pour en ouvrir une autre.
                  </p>
                ) : (
                  <>
                    <div className="space-y-1 text-sm">
                      <p className="text-ink-muted">
                        {formatDate(selectedPeriode.date_debut)} →{" "}
                        {formatDate(selectedPeriode.date_fin)}
                      </p>
                      <Badge tone={statusTone(selectedPeriode.statut)}>
                        {labelize(selectedPeriode.statut)}
                      </Badge>
                      <p className="pt-1 text-xs text-ink-faint">
                        {bulletinsTotal} bulletin(s)
                        {bulletinsPayes > 0
                          ? ` · ${bulletinsPayes} payé(s) (page)`
                          : ""}
                      </p>
                    </div>

                    {canManage ? (
                      <div className="flex flex-col gap-2">
                        {selectedPeriode.statut !== "cloturee" ? (
                          <Button
                            size="sm"
                            variant="secondary"
                            loading={genererBulletins.isPending}
                            onClick={() =>
                              void runPeriodeAction(
                                () =>
                                  genererBulletins.mutateAsync(
                                    selectedPeriode.id,
                                  ),
                                "Bulletins générés.",
                              )
                            }
                          >
                            Générer bulletins
                          </Button>
                        ) : (
                          <Alert tone="info">
                            Période clôturée — génération verrouillée.
                          </Alert>
                        )}
                        {selectedPeriode.statut === "brouillon" ? (
                          <Button
                            size="sm"
                            loading={validerPeriode.isPending}
                            onClick={() =>
                              void runPeriodeAction(
                                () =>
                                  validerPeriode.mutateAsync(
                                    selectedPeriode.id,
                                  ),
                                "Période validée.",
                              )
                            }
                          >
                            Valider la période
                          </Button>
                        ) : null}
                        {selectedPeriode.statut === "validee" ? (
                          <Button
                            size="sm"
                            loading={cloturerPeriode.isPending}
                            onClick={() => setPeriodeToCloturer(selectedPeriode)}
                          >
                            Clôturer
                          </Button>
                        ) : null}
                        {selectedPeriode.statut === "brouillon" &&
                        (selectedPeriode.bulletins_count ?? bulletinsTotal) ===
                          0 ? (
                          <Button
                            size="sm"
                            variant="danger"
                            onClick={() => setPeriodeToDelete(selectedPeriode)}
                          >
                            Supprimer la période
                          </Button>
                        ) : null}
                      </div>
                    ) : null}
                  </>
                )}
              </CardBody>
            </Card>

            <Card>
              <CardHeader
                title="Circuit paie"
                description="Ordre recommandé des étapes"
              />
              <CardBody>
                <ol className="space-y-2 text-xs text-ink-muted">
                  <li className="flex gap-2">
                    <span className="font-mono font-semibold text-teal">1</span>
                    Créer une période (brouillon)
                  </li>
                  <li className="flex gap-2">
                    <span className="font-mono font-semibold text-teal">2</span>
                    Générer les bulletins
                  </li>
                  <li className="flex gap-2">
                    <span className="font-mono font-semibold text-teal">3</span>
                    Valider la période
                  </li>
                  <li className="flex gap-2">
                    <span className="font-mono font-semibold text-teal">4</span>
                    RH : saisir les salaires perçus (jours travaillés)
                  </li>
                  <li className="flex gap-2">
                    <span className="font-mono font-semibold text-teal">5</span>
                    Comptable : marquer payé (mode + compte), puis clôturer
                  </li>
                </ol>
              </CardBody>
            </Card>

            <Card className="border-teal/20 bg-teal/[0.03]">
              <CardBody className="space-y-2">
                <p className="flex items-center gap-2 text-sm font-semibold text-ink">
                  <Wallet className="size-4 text-teal" />
                  Module RH
                </p>
                <p className="text-xs text-ink-muted">
                  Contrats, absences et alertes d’échéance.
                </p>
                <Link href="/rh" className="block">
                  <Button size="sm" variant="secondary" className="w-full">
                    Retour RH
                  </Button>
                </Link>
              </CardBody>
            </Card>
          </aside>
        </div>

        {/* Bulletins */}
        <Card className="min-w-0 overflow-hidden">
          <CardHeader
            title={
              selectedPeriode
                ? `Bulletins — ${labelMoisAnnee(selectedPeriode.mois, selectedPeriode.annee)}`
                : "Bulletins"
            }
            description={
              selectedPeriode
                ? `${formatDate(selectedPeriode.date_debut)} → ${formatDate(selectedPeriode.date_fin)}`
                : "Sélectionnez une période pour afficher les bulletins."
            }
            action={
              selectedPeriode ? (
                <Badge tone={statusTone(selectedPeriode.statut)}>
                  {labelize(selectedPeriode.statut)}
                </Badge>
              ) : undefined
            }
          />
          <CardBody>
            {selectedPeriode ? (
              <DataTable
                data={bulletinsList}
                columns={bulletinColumns}
                isLoading={bulletins.isLoading}
                search={{
                  value: bulletinsQ,
                  onChange: (value) => {
                    setBulletinsQ(value);
                    setBulletinsPage(1);
                  },
                  placeholder: "Agent, matricule, grade, téléphone…",
                }}
                onSelectionChange={setSelectedBulletinIds}
                toolbar={
                  <div className="flex flex-wrap items-center gap-2">
                    <div className="w-40 sm:w-48">
                      <Select
                        aria-label="Filtrer par grade"
                        options={gradeFilterOptions}
                        value={filterBulletinGradeId}
                        onChange={(e) => {
                          setFilterBulletinGradeId(e.target.value);
                          setBulletinsPage(1);
                        }}
                      />
                    </div>
                    <div className="w-40 sm:w-48">
                      <Select
                        aria-label="Filtrer par ville"
                        options={villeFilterOptions}
                        value={filterBulletinVilleId}
                        onChange={(e) => {
                          setFilterBulletinVilleId(e.target.value);
                          setBulletinsPage(1);
                        }}
                      />
                    </div>
                    <div className="w-36 sm:w-44">
                      <Select
                        aria-label="Filtrer par statut"
                        options={STATUT_BULLETIN_OPTIONS}
                        value={filterBulletinStatut}
                        onChange={(e) => {
                          setFilterBulletinStatut(e.target.value);
                          setBulletinsPage(1);
                        }}
                      />
                    </div>
                    <div className="w-36 sm:w-44">
                      <Select
                        aria-label="Filtrer par mode de paiement"
                        options={modeFilterOptions}
                        value={filterBulletinMode}
                        onChange={(e) => {
                          setFilterBulletinMode(e.target.value);
                          setBulletinsPage(1);
                        }}
                      />
                    </div>
                    <Button
                      size="sm"
                      variant="secondary"
                      loading={pdfListeLoading || exportBulletinsPdf.isPending}
                      onClick={() => void handleExportBulletinsPdf()}
                    >
                      <FileDown className="size-4" />
                      Exporter PDF
                    </Button>
                    {canManage && unpaidNeedSalaireSelected.length > 0 ? (
                      <Button
                        size="sm"
                        variant="secondary"
                        onClick={() =>
                          openBulkSalaire(unpaidNeedSalaireSelected)
                        }
                      >
                        Saisir salaires ({unpaidNeedSalaireSelected.length})
                      </Button>
                    ) : null}
                    {canManage && unpaidTotal > 0 ? (
                      <Button
                        size="sm"
                        variant="secondary"
                        loading={bulkSalaireBusy && !bulkSalaireOpen}
                        onClick={() => void openBulkSalaireAllUnpaid()}
                      >
                        Saisir tous les salaires non renseignés
                      </Button>
                    ) : null}
                    {canPayer && unpaidReadySelected.length > 0 ? (
                      <Button
                        size="sm"
                        onClick={() => openBulkPay(unpaidReadySelected)}
                      >
                        <Wallet className="size-4" />
                        Marquer payé ({unpaidReadySelected.length})
                      </Button>
                    ) : null}
                    {canPayer && unpaidTotal > 0 ? (
                      <Button
                        size="sm"
                        variant="secondary"
                        loading={bulkPayBusy && !bulkPayOpen}
                        onClick={() => void openBulkPayAllUnpaid()}
                      >
                        Régler tous les prêts ({unpaidTotal})
                      </Button>
                    ) : null}
                  </div>
                }
                pagination={{
                  page: bulletinsPage,
                  perPage: bulletins.data?.meta.per_page ?? bulletinsPerPage,
                  total: bulletins.data?.meta.total ?? 0,
                  onPageChange: setBulletinsPage,
                  onPerPageChange: (n) => {
                    setBulletinsPerPage(n);
                    setBulletinsPage(1);
                  },
                }}
                emptyTitle="Aucun bulletin pour cette période"
              />
            ) : (
              <Alert tone="info">
                Sélectionnez une période dans le tableau ci-dessus pour afficher
                et gérer les bulletins.
              </Alert>
            )}
          </CardBody>
        </Card>

        <Modal
          open={periodeOpen}
          onClose={closePeriodeModal}
          preventClose={periodeBusy}
          title="Nouvelle période de paie"
          description="Créez une période mensuelle pour générer les bulletins."
          footer={
            <>
              <Button
                variant="secondary"
                onClick={closePeriodeModal}
                disabled={periodeBusy}
              >
                Annuler
              </Button>
              <Button type="submit" form="periode-form" loading={periodeBusy}>
                Créer
              </Button>
            </>
          }
        >
          <form
            id="periode-form"
            className="space-y-3"
            onSubmit={handleSubmit(async (values) => {
              setFormError(null);
              try {
                const result = await createPeriode.mutateAsync({
                  mois: Number(values.mois),
                  annee: Number(values.annee),
                  commentaire: values.commentaire || null,
                });
                toast("Période créée.");
                selectPeriode(result.data.id);
                closePeriodeModal();
              } catch (err) {
                setFormError(
                  getApiErrorMessage(err, "Échec de la création."),
                );
              }
            })}
          >
            <RequiredFieldsLegend />
            {formError ? <Alert tone="danger">{formError}</Alert> : null}
            <div className="grid gap-3 sm:grid-cols-2">
              <Select
                label="Mois"
                requiredMark
                options={moisOptions}
                error={errors.mois?.message}
                {...register("mois")}
              />
              <Input
                label="Année"
                requiredMark
                type="number"
                min={2000}
                max={2100}
                error={errors.annee?.message}
                {...register("annee")}
              />
            </div>
            <Textarea
              label="Commentaire"
              optionalMark
              error={errors.commentaire?.message}
              {...register("commentaire")}
            />
          </form>
        </Modal>

        <Modal
          open={Boolean(salaireBulletin)}
          onClose={() =>
            !renseignerSalaire.isPending && setSalaireBulletin(null)
          }
          preventClose={renseignerSalaire.isPending}
          title="Salaire perçu"
          description={
            salaireBulletin
              ? `${
                  salaireBulletin.agent
                    ? `${salaireBulletin.agent.prenom} ${salaireBulletin.agent.nom} · `
                    : ""
                }Indiquez le salaire réellement perçu ce mois (jours travaillés). Le comptable réglera ensuite sans voir le montant.`
              : undefined
          }
          footer={
            <>
              <Button
                variant="secondary"
                disabled={renseignerSalaire.isPending}
                onClick={() => setSalaireBulletin(null)}
              >
                Annuler
              </Button>
              <Button
                loading={renseignerSalaire.isPending}
                onClick={async () => {
                  if (!salaireBulletin) return;
                  const net = Number(salaireForm.salaire_net);
                  if (!Number.isFinite(net) || net <= 0) {
                    setSalaireError("Indiquez le salaire perçu ce mois (> 0).");
                    return;
                  }
                  setSalaireError(null);
                  try {
                    await renseignerSalaire.mutateAsync({
                      id: salaireBulletin.id,
                      salaire_net: net,
                    });
                    toast("Salaire perçu enregistré.");
                    setSalaireBulletin(null);
                  } catch (err) {
                    setSalaireError(
                      getApiErrorMessage(err, "Échec de l’enregistrement."),
                    );
                  }
                }}
              >
                Enregistrer
              </Button>
            </>
          }
        >
          <div className="space-y-3">
            {salaireError ? <Alert tone="danger">{salaireError}</Alert> : null}
            {salaireBulletin && Number(salaireBulletin.salaire_net) > 0 ? (
              <p className="text-xs text-ink-faint">
                Net calculé (contrat) :{" "}
                {formatSalaire(salaireBulletin.salaire_net, user)} — à ajuster
                si prorata jours travaillés.
              </p>
            ) : (
              <Alert tone="info">
                Le net calculé est à 0. Saisissez le salaire réellement perçu
                ce mois (jours travaillés).
              </Alert>
            )}
            <Input
              label="Salaire perçu ce mois (FCFA)"
              requiredMark
              type="number"
              min={1}
              step={1}
              value={salaireForm.salaire_net}
              placeholder="Montant net versé"
              onChange={(e) =>
                setSalaireForm({ salaire_net: e.target.value })
              }
            />
          </div>
        </Modal>

        <Modal
          open={Boolean(payeBulletin)}
          onClose={() =>
            !marquerPaye.isPending && setPayeBulletin(null)
          }
          preventClose={marquerPaye.isPending}
          title="Régler le bulletin"
          description={
            payeBulletin
              ? `${
                  payeBulletin.agent
                    ? `${payeBulletin.agent.prenom} ${payeBulletin.agent.nom} · `
                    : ""
                }Choisissez le mode et le compte. Le salaire perçu a déjà été saisi par la RH.`
              : undefined
          }
          footer={
            <>
              <Button
                variant="secondary"
                disabled={marquerPaye.isPending}
                onClick={() => setPayeBulletin(null)}
              >
                Annuler
              </Button>
              <Button
                loading={marquerPaye.isPending}
                onClick={async () => {
                  if (!payeBulletin) return;
                  if (!payeForm.mode) {
                    setPayeError("Choisissez un mode de paiement.");
                    return;
                  }
                  if (!payeForm.compte_tresorerie_id) {
                    setPayeError("Choisissez un compte de trésorerie.");
                    return;
                  }
                  setPayeError(null);
                  try {
                    await marquerPaye.mutateAsync({
                      id: payeBulletin.id,
                      mode: payeForm.mode,
                      compte_tresorerie_id: payeForm.compte_tresorerie_id,
                      reference: payeForm.reference || undefined,
                    });
                    toast("Bulletin marqué payé.");
                    setPayeBulletin(null);
                  } catch (err) {
                    setPayeError(
                      getApiErrorMessage(err, "Échec du règlement."),
                    );
                  }
                }}
              >
                Confirmer le paiement
              </Button>
            </>
          }
        >
          <div className="space-y-3">
            {payeError ? <Alert tone="danger">{payeError}</Alert> : null}
            <Alert tone="info">
              Le montant débité est celui saisi par la RH (non affiché ici).
            </Alert>
            <Select
              label="Mode de paiement"
              requiredMark
              value={payeForm.mode}
              options={modeOptions}
              onChange={(e) =>
                setPayeForm((f) => ({ ...f, mode: e.target.value }))
              }
            />
            <Select
              label="Compte débité"
              requiredMark
              value={payeForm.compte_tresorerie_id}
              options={comptes.map((c) => ({
                value: c.id,
                label: `${c.libelle}${c.solde != null ? ` · ${formatFcfa(c.solde)}` : ""}`,
              }))}
              placeholder="Choisir un compte"
              onChange={(e) =>
                setPayeForm((f) => ({
                  ...f,
                  compte_tresorerie_id: e.target.value,
                }))
              }
            />
            <Input
              label="Référence"
              optionalMark
              value={payeForm.reference}
              placeholder="Auto si vide"
              hint="Laissée vide → générée automatiquement (ex. PAIE-20260925-A3F2K)."
              onChange={(e) =>
                setPayeForm((f) => ({ ...f, reference: e.target.value }))
              }
            />
          </div>
        </Modal>

        <Modal
          open={bulkSalaireOpen}
          onClose={closeBulkSalaire}
          preventClose={bulkSalaireBusy}
          title="Saisir les salaires perçus"
          description={`${bulkSalaireBulletins.length} agent(s) — montants ajustables par ligne.`}
          footer={
            <>
              <Button
                variant="secondary"
                disabled={bulkSalaireBusy}
                onClick={closeBulkSalaire}
              >
                Annuler
              </Button>
              <Button
                loading={bulkSalaireBusy}
                onClick={() => void executeBulkSalaire()}
              >
                Enregistrer ({bulkSalaireBulletins.length})
              </Button>
            </>
          }
        >
          <div className="space-y-4">
            {bulkSalaireError ? (
              <Alert tone="danger">{bulkSalaireError}</Alert>
            ) : null}

            <div className="space-y-2 rounded-md border border-border bg-teal/[0.03] p-3">
              <p className="text-sm font-medium text-ink">
                Remplir tous les salaires perçus
              </p>
              <p className="text-xs text-ink-faint">
                Cliquez une proposition (nets calculés du lot) ou saisissez un
                montant unique pour les {bulkSalaireBulletins.length} lignes.
              </p>
              {salaireProposes.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                  {salaireProposes.map(({ montant, count }) => (
                    <Button
                      key={montant}
                      type="button"
                      size="sm"
                      variant={
                        bulkSalaireApplyAll === String(montant)
                          ? "primary"
                          : "secondary"
                      }
                      disabled={bulkSalaireBusy}
                      onClick={() => applySalaireToAll(montant)}
                    >
                      {formatSalaire(montant, user)}
                      <span className="opacity-70">· {count}</span>
                    </Button>
                  ))}
                </div>
              ) : (
                <p className="text-xs text-ink-faint">
                  Aucun net calculé &gt; 0 — saisissez un montant ci-dessous.
                </p>
              )}
              <div className="flex flex-wrap items-end gap-2">
                <div className="min-w-[10rem] flex-1">
                  <Input
                    label="Montant libre (FCFA)"
                    type="number"
                    min={1}
                    step={1}
                    value={bulkSalaireApplyAll}
                    placeholder="Ex. 90000"
                    onChange={(e) => setBulkSalaireApplyAll(e.target.value)}
                  />
                </div>
                <Button
                  type="button"
                  size="sm"
                  variant="secondary"
                  disabled={bulkSalaireBusy || !bulkSalaireApplyAll.trim()}
                  onClick={() => applySalaireToAll(bulkSalaireApplyAll)}
                >
                  Appliquer à tous
                </Button>
              </div>
            </div>

            <div className="max-h-64 space-y-2 overflow-y-auto rounded-md border border-border p-2">
              <p className="sticky top-0 z-10 bg-white px-1 pb-1 text-xs font-medium text-ink-muted">
                Détail par agent
              </p>
              {bulkSalaireBulletins.map((b) => {
                const label = b.agent
                  ? `${b.agent.prenom} ${b.agent.nom}`
                  : "Agent";
                const matricule = b.agent?.matricule ?? "—";
                return (
                  <div
                    key={b.id}
                    className="grid grid-cols-[1fr_auto] items-end gap-2 sm:grid-cols-[1.4fr_1fr]"
                  >
                    <div className="min-w-0">
                      <p className="truncate text-sm font-medium text-ink">
                        {label}
                      </p>
                      <p className="text-xs text-ink-faint">{matricule}</p>
                    </div>
                    <Input
                      label="Salaire perçu"
                      requiredMark
                      type="number"
                      min={1}
                      step={1}
                      value={bulkSalaireMontants[b.id] ?? ""}
                      onChange={(e) =>
                        setBulkSalaireMontants((m) => ({
                          ...m,
                          [b.id]: e.target.value,
                        }))
                      }
                    />
                  </div>
                );
              })}
            </div>
          </div>
        </Modal>

        <Modal
          open={bulkPayOpen}
          onClose={closeBulkPay}
          preventClose={bulkPayBusy}
          title="Régler plusieurs bulletins"
          description={`${bulkPayBulletins.length} agent(s) prêts — même mode et compte (montants déjà saisis par la RH).`}
          footer={
            <>
              <Button
                variant="secondary"
                disabled={bulkPayBusy}
                onClick={closeBulkPay}
              >
                Annuler
              </Button>
              <Button
                loading={bulkPayBusy}
                onClick={() => {
                  if (!validateBulkPay()) return;
                  setBulkPayConfirmOpen(true);
                }}
              >
                Confirmer ({bulkPayBulletins.length})
              </Button>
            </>
          }
        >
          <div className="space-y-4">
            {bulkPayError ? <Alert tone="danger">{bulkPayError}</Alert> : null}
            <Alert tone="info">
              Les montants perçus ont été saisis par la RH et ne sont pas
              affichés. Seuls les bulletins prêts sont inclus.
            </Alert>
            <div className="grid gap-3 sm:grid-cols-2">
              <Select
                label="Mode de paiement"
                requiredMark
                value={bulkPayForm.mode}
                options={modeOptions}
                onChange={(e) =>
                  setBulkPayForm((f) => ({ ...f, mode: e.target.value }))
                }
              />
              <Select
                label="Compte débité"
                requiredMark
                value={bulkPayForm.compte_tresorerie_id}
                options={comptes.map((c) => ({
                  value: c.id,
                  label: `${c.libelle}${c.solde != null ? ` · ${formatFcfa(c.solde)}` : ""}`,
                }))}
                placeholder="Choisir un compte"
                onChange={(e) =>
                  setBulkPayForm((f) => ({
                    ...f,
                    compte_tresorerie_id: e.target.value,
                  }))
                }
              />
            </div>
            <Input
              label="Référence (commune, optionnel)"
              optionalMark
              value={bulkPayForm.reference}
              placeholder="Auto si vide (une ref. par bulletin)"
              hint="Laissée vide → une référence auto par bulletin."
              onChange={(e) =>
                setBulkPayForm((f) => ({ ...f, reference: e.target.value }))
              }
            />
            <ul className="max-h-40 space-y-1 overflow-y-auto rounded-md border border-border p-2 text-sm">
              {bulkPayBulletins.map((b) => (
                <li key={b.id} className="text-ink-muted">
                  {b.agent
                    ? `${b.agent.prenom} ${b.agent.nom}`
                    : b.id.slice(0, 8)}
                  {b.agent?.matricule ? (
                    <span className="text-ink-faint"> · {b.agent.matricule}</span>
                  ) : null}
                </li>
              ))}
            </ul>
          </div>
        </Modal>

        <ConfirmDialog
          open={bulkPayConfirmOpen}
          onClose={() => {
            if (bulkPayBusy) return;
            setBulkPayConfirmOpen(false);
          }}
          loading={bulkPayBusy}
          title="Confirmer le règlement groupé"
          description={`Marquer payé ${bulkPayBulletins.length} bulletin(s) via ${bulkPayForm.mode.toUpperCase()} ? Les salaires perçus saisis par la RH seront débités du compte choisi.`}
          confirmLabel={`Oui, régler ${bulkPayBulletins.length}`}
          confirmVariant="primary"
          onConfirm={() => void executeBulkPay()}
        />

        <ConfirmDialog
          open={!!periodeToCloturer}
          onClose={() => {
            if (cloturerPeriode.isPending) return;
            setPeriodeToCloturer(null);
          }}
          loading={cloturerPeriode.isPending}
          title="Clôturer la période"
          description={
            periodeToCloturer
              ? `Confirmer la clôture de « ${labelMoisAnnee(periodeToCloturer.mois, periodeToCloturer.annee)} » ? La génération de bulletins sera verrouillée.`
              : ""
          }
          confirmLabel="Oui, clôturer"
          confirmVariant="primary"
          onConfirm={async () => {
            if (!periodeToCloturer) return;
            try {
              await cloturerPeriode.mutateAsync(periodeToCloturer.id);
              toast("Période clôturée.");
              setPeriodeToCloturer(null);
            } catch (err) {
              toast(
                getApiErrorMessage(err, "Échec de la clôture."),
                "danger",
              );
            }
          }}
        />

        <ConfirmDialog
          open={!!periodeToDelete}
          onClose={() => {
            if (deletePeriode.isPending) return;
            setPeriodeToDelete(null);
          }}
          loading={deletePeriode.isPending}
          title="Supprimer la période"
          description={
            periodeToDelete
              ? `Confirmer la suppression de « ${labelMoisAnnee(periodeToDelete.mois, periodeToDelete.annee)} » ? Réservé aux brouillons sans bulletins.`
              : ""
          }
          confirmLabel="Supprimer"
          onConfirm={async () => {
            if (!periodeToDelete) return;
            try {
              const deletedId = periodeToDelete.id;
              await deletePeriode.mutateAsync(deletedId);
              toast("Période supprimée.");
              setPeriodeToDelete(null);
              if (selectedPeriodeId === deletedId) {
                setSelectedPeriodeId(null);
              }
            } catch (err) {
              toast(
                getApiErrorMessage(err, "Échec de la suppression."),
                "danger",
              );
            }
          }}
        />
      </div>
    </PermissionGate>
  );
}
