"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useFieldArray, useForm, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { ArrowLeft, CheckCircle2, FileText, Plus, Trash2 } from "lucide-react";
import { useClients } from "@/application/hooks/useClients";
import {
  useAbonnements,
  useCreateProformaFacture,
  useOffres,
  useSites,
  useUpdateFactureStatut,
} from "@/application/hooks/useResources";
import {
  proformaFactureSchema,
  type ProformaFactureFormInput,
  type ProformaFactureFormValues,
} from "@/domain/schemas/facture";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { Alert } from "@/presentation/components/ui/Alert";
import { Button } from "@/presentation/components/ui/Button";
import { DatePicker } from "@/presentation/components/ui/DatePicker";
import { RequiredFieldsLegend } from "@/presentation/components/ui/FieldLabel";
import { Input } from "@/presentation/components/ui/Input";
import { Select } from "@/presentation/components/ui/Select";
import { Textarea } from "@/presentation/components/ui/Textarea";
import { useToast } from "@/presentation/providers/ToastProvider";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { formatFcfa, labelize } from "@/shared/lib/format";
import {
  isAutoNoteAbonnement,
  noteAbonnement,
} from "@/shared/lib/note-abonnement";

const TVA = 0.18;

const delaiPaiementOptions = [
  { value: "0", label: "Comptant (avant mise en place)" },
  { value: "15", label: "15 jours net" },
  { value: "30", label: "30 jours net" },
  { value: "45", label: "45 jours net" },
  { value: "60", label: "60 jours net" },
];

const periodiciteOptions = [
  { value: "mensuel", label: "Mensuel" },
  { value: "trimestriel", label: "Trimestriel" },
  { value: "annuel", label: "Annuel" },
];

function multiplicateurPeriodicite(periodicite: string | undefined | null) {
  if (periodicite === "trimestriel") return 3;
  if (periodicite === "annuel") return 12;
  return 1;
}

function prixPourPeriode(
  prixMensuel: string | number,
  periodicite: string | undefined | null,
) {
  return Math.round(Number(prixMensuel) * multiplicateurPeriodicite(periodicite));
}

const emptyDefaults: ProformaFactureFormValues = {
  client_id: "",
  client_nom: "",
  client_adresse: "",
  client_telephone: "",
  client_email: "",
  abonnement_id: "",
  creer_abonnement: false,
  site_id: "",
  periodicite: "mensuel",
  date_debut_service: "",
  date_fin_service: "",
  delai_paiement_jours: 0,
  notes: "",
  conditions_paiement: "Paiement au comptant avant la mise en place",
  delai_validite: "1 mois",
  duree_contrat_min:
    "Tous nos contrats sont conclus pour une durée minimum d’un an.",
  signataire_nom: "",
  signataire_fonction: "La Direction Commerciale",
  lignes: [
    {
      offre_id: "",
      code_article: "",
      description: "",
      quantite: 1,
      prix_unitaire: 0,
    },
  ],
};

function SectionTitle({
  step,
  title,
  hint,
}: {
  step: string;
  title: string;
  hint?: string;
}) {
  return (
    <div className="mb-5 flex items-start gap-3">
      <span className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-md bg-teal/10 text-xs font-semibold tabular-nums text-teal">
        {step}
      </span>
      <div>
        <h2 className="text-base font-semibold tracking-tight text-ink">
          {title}
        </h2>
        {hint ? (
          <p className="mt-0.5 text-sm text-ink-muted">{hint}</p>
        ) : null}
      </div>
    </div>
  );
}

export default function NouvelleFactureProformaPage() {
  const router = useRouter();
  const { toast } = useToast();
  const [formError, setFormError] = useState<string | null>(null);
  const [submitMode, setSubmitMode] = useState<"draft" | "validate" | null>(
    null,
  );
  const { data: clientsData } = useClients({ all: true });
  const { data: offresData } = useOffres({ all: true });
  const { data: sitesData } = useSites({ all: true });
  const createProforma = useCreateProformaFacture();
  const updateStatut = useUpdateFactureStatut();

  const {
    register,
    control,
    handleSubmit,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<ProformaFactureFormInput, unknown, ProformaFactureFormValues>({
    resolver: zodResolver(proformaFactureSchema),
    mode: "onBlur",
    reValidateMode: "onChange",
    defaultValues: emptyDefaults,
  });

  const { fields, append, remove } = useFieldArray({
    control,
    name: "lignes",
  });

  const clientId = useWatch({ control, name: "client_id" });
  const lignes = useWatch({ control, name: "lignes" });
  const delaiPaiement = useWatch({ control, name: "delai_paiement_jours" });
  const abonnementId = useWatch({ control, name: "abonnement_id" });
  const periodicite = useWatch({ control, name: "periodicite" });

  const { data: abonnementsData } = useAbonnements({
    all: true,
    client_id: clientId || undefined,
  });

  const busy =
    isSubmitting ||
    createProforma.isPending ||
    updateStatut.isPending ||
    submitMode !== null;

  const todayIso = () => new Date().toISOString().slice(0, 10);

  const buildPayload = (values: ProformaFactureFormValues) => ({
    client_id: values.client_id || null,
    client_nom: values.client_id
      ? null
      : values.client_nom?.trim() || null,
    client_adresse: values.client_id
      ? null
      : values.client_adresse?.trim() || null,
    client_telephone: values.client_id
      ? null
      : values.client_telephone?.trim() || null,
    client_email: values.client_id
      ? null
      : values.client_email?.trim() || null,
    abonnement_id: values.client_id
      ? values.abonnement_id || null
      : null,
    creer_abonnement: false,
    offre_ids: [
      ...new Set(
        values.lignes
          .map((l) => l.offre_id)
          .filter((id): id is string => Boolean(id)),
      ),
    ],
    offre_id: values.lignes.find((l) => l.offre_id)?.offre_id || null,
    site_id: values.client_id ? values.site_id || null : null,
    periodicite: values.periodicite || null,
    date_debut_service: values.date_debut_service || null,
    date_fin_service: values.date_fin_service || null,
    delai_paiement_jours: Number(values.delai_paiement_jours),
    notes: values.notes || null,
    conditions_paiement: values.conditions_paiement || null,
    delai_validite: values.delai_validite || null,
    duree_contrat_min: values.duree_contrat_min || null,
    signataire_nom: values.signataire_nom || null,
    signataire_fonction: values.signataire_fonction || null,
    lignes: values.lignes.map((l) => ({
      offre_id: l.offre_id || null,
      code_article: l.code_article || null,
      description: l.description,
      quantite: Number(l.quantite),
      prix_unitaire: Number(l.prix_unitaire),
    })),
  });

  const submitProforma = (mode: "draft" | "validate") =>
    handleSubmit(async (values) => {
      setFormError(null);

      if (mode === "validate") {
        if (!values.periodicite) {
          const message =
            "Indiquez une périodicité avant de créer et valider (abonnement).";
          setFormError(message);
          toast(message, "danger");
          return;
        }
        if (!values.client_id && !values.client_nom?.trim()) {
          const message =
            "Indiquez un client ou le nom du destinataire avant de valider.";
          setFormError(message);
          toast(message, "danger");
          return;
        }
      }

      setSubmitMode(mode);
      try {
        const payload = buildPayload(values);
        if (mode === "validate") {
          payload.date_debut_service =
            values.date_debut_service || todayIso();
        }

        const created = await createProforma.mutateAsync(payload);

        if (mode === "validate") {
          await updateStatut.mutateAsync({
            id: created.data.id,
            statut: "valide",
          });
          toast(
            !values.client_id
              ? "Proforma créée et validée — client et abonnement créés."
              : "Proforma créée et validée — abonnement créé.",
          );
          router.push("/factures?tab=factures");
          return;
        }

        toast("Facture proforma créée.");
        router.push("/factures?tab=proformas");
      } catch (err) {
        const message = getApiErrorMessage(
          err,
          mode === "validate"
            ? "Échec de la création / validation de la proforma."
            : "Échec de la création de la facture proforma.",
        );
        setFormError(message);
        toast(message, "danger");
      } finally {
        setSubmitMode(null);
      }
    })();
  const modeExistant = Boolean(abonnementId);

  useEffect(() => {
    const jours = Number(delaiPaiement);
    const labels: Record<number, string> = {
      0: "Paiement au comptant avant la mise en place",
      15: "Paiement à 15 jours net",
      30: "Paiement à 30 jours net",
      45: "Paiement à 45 jours net",
      60: "Paiement à 60 jours net",
    };
    if (labels[jours] !== undefined) {
      setValue("conditions_paiement", labels[jours]);
    }
  }, [delaiPaiement, setValue]);

  useEffect(() => {
    // Devis d’abord : les abonnements sont créés à l’acceptation (statut Validé).
    setValue("creer_abonnement", false);
  }, [modeExistant, setValue]);

  // Recalcule les prix des lignes liées à une offre quand la périodicité change
  useEffect(() => {
    const offres = offresData?.data ?? [];
    (lignes ?? []).forEach((ligne, index) => {
      if (!ligne?.offre_id) return;
      const offre = offres.find((o) => o.id === ligne.offre_id);
      if (!offre) return;
      const pu = prixPourPeriode(offre.prix_mensuel, periodicite);
      if (Number(ligne.prix_unitaire) !== pu) {
        setValue(`lignes.${index}.prix_unitaire`, pu);
      }
      const expected = `${offre.libelle} — abonnement ${labelize(periodicite || "mensuel")}`;
      if (ligne.description !== expected) {
        setValue(`lignes.${index}.description`, expected);
      }
    });
  }, [periodicite, offresData, setValue]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (!abonnementId) return;
    const abonnement = (abonnementsData?.data ?? []).find(
      (a) => a.id === abonnementId,
    );
    const offres = offresData?.data ?? [];
    if (!abonnement) return;

    const peri = abonnement.periodicite || periodicite || "mensuel";
    setValue(
      "periodicite",
      peri as ProformaFactureFormValues["periodicite"],
    );
    setValue("date_debut_service", abonnement.date_debut?.slice(0, 10) ?? "");
    setValue("date_fin_service", abonnement.date_fin?.slice(0, 10) ?? "");
    setValue("site_id", abonnement.site_id ?? "");

    // Abonnement mono-offre : préremplit une ligne. Combiné : lignes inchangées.
    if (!abonnement.offre_id) return;
    const offre = offres.find((o) => o.id === abonnement.offre_id);
    if (!offre) return;

    const pu = prixPourPeriode(offre.prix_mensuel, peri);
    setValue("lignes", [
      {
        offre_id: offre.id,
        code_article: "",
        description: `${offre.libelle} — abonnement ${labelize(peri)}`,
        quantite: 1,
        prix_unitaire: pu,
      },
    ]);
  }, [abonnementId, abonnementsData, offresData, setValue]); // eslint-disable-line react-hooks/exhaustive-deps

  const applyOffreToLigne = (index: number, offreId: string) => {
    setValue(`lignes.${index}.offre_id`, offreId);
    if (!offreId) return;
    const offre = (offresData?.data ?? []).find((o) => o.id === offreId);
    if (!offre) return;
    const peri = periodicite || "mensuel";
    const pu = prixPourPeriode(offre.prix_mensuel, peri);
    setValue(
      `lignes.${index}.description`,
      `${offre.libelle} — abonnement ${labelize(peri)}`,
    );
    setValue(`lignes.${index}.prix_unitaire`, pu);
    if (!lignes?.[index]?.quantite) {
      setValue(`lignes.${index}.quantite`, 1);
    }
  };

  const clientOptions = useMemo(
    () => [
      { value: "", label: "— Hors fichier clients —" },
      ...(clientsData?.data ?? []).map((c) => ({
        value: c.id,
        label: c.raison_sociale,
      })),
    ],
    [clientsData],
  );

  const selectedClient = useMemo(
    () => (clientsData?.data ?? []).find((c) => c.id === clientId),
    [clientsData, clientId],
  );

  const abonnementOptions = useMemo(
    () => [
      { value: "", label: "— Nouveau (offres sur les lignes) —" },
      ...(abonnementsData?.data ?? []).map((a) => ({
        value: a.id,
        label: `${a.designation ?? a.offre?.libelle ?? "Offre"} · ${labelize(a.periodicite)} · ${a.date_debut?.slice(0, 10) ?? ""}`,
      })),
    ],
    [abonnementsData],
  );

  const siteOptions = useMemo(() => {
    const sites = sitesData?.data ?? [];
    const filtered = clientId
      ? sites.filter((s) => s.client_id === clientId)
      : sites;
    return [
      { value: "", label: "— Aucun site —" },
      ...filtered.map((s) => ({ value: s.id, label: s.nom })),
    ];
  }, [sitesData, clientId]);

  const offreSelectOptions = useMemo(
    () => [
      { value: "", label: "— Libre (sans offre) —" },
      ...(offresData?.data ?? [])
        .filter((o) => o.actif)
        .map((o) => ({
          value: o.id,
          label: `${o.libelle} — ${formatFcfa(Number(o.prix_mensuel))}/mois`,
        })),
    ],
    [offresData],
  );

  const totals = useMemo(() => {
    const ht = (lignes ?? []).reduce((sum, l) => {
      const q = Number(l?.quantite) || 0;
      const pu = Number(l?.prix_unitaire) || 0;
      return sum + q * pu;
    }, 0);
    const tva = Math.round(ht * TVA);
    const ttc = ht + tva;
    return { ht, tva, ttc };
  }, [lignes]);

  const notes = useWatch({ control, name: "notes" });

  useEffect(() => {
    if (totals.ht <= 0) return;
    if (!isAutoNoteAbonnement(notes)) return;
    const next = noteAbonnement(periodicite || "mensuel", totals.ht);
    if (notes !== next) {
      setValue("notes", next);
    }
  }, [totals.ht, periodicite, notes, setValue]);

  return (
    <PermissionGate permission="factures.create" title="Facture proforma">
      <div className="-m-6 min-h-full bg-white px-6 py-6 pb-8 max-lg:pb-28">
        <div className="mx-auto max-w-screen-2xl">
        <Link
          href="/factures?tab=proformas"
          className="mb-6 inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-teal"
        >
          <ArrowLeft className="size-4" />
          Factures
        </Link>

        <header className="mb-8 flex flex-col gap-4 border-b border-border pb-6 sm:flex-row sm:items-end sm:justify-between">
          <div className="flex items-start gap-3">
            <span className="flex size-11 shrink-0 items-center justify-center rounded-lg bg-teal text-white">
              <FileText className="size-5" />
            </span>
            <div>
              <p className="text-xs font-medium uppercase tracking-[0.14em] text-teal">
                Commercial
              </p>
              <h1 className="mt-1 text-2xl font-semibold tracking-tight text-ink">
                Facture proforma
              </h1>
              <p className="mt-1 max-w-xl text-sm text-ink-muted">
                Devis multi-offres. Tant que le client n’a pas accepté, aucun
                abonnement n’est créé.
              </p>
            </div>
          </div>
          <RequiredFieldsLegend className="sm:text-right" />
        </header>

        <form
          id="proforma-form"
          className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_280px]"
          noValidate
          onSubmit={(e) => {
            e.preventDefault();
            void submitProforma("draft");
          }}
        >
          <div className="space-y-10 rounded-xl border border-border bg-white p-6 shadow-md sm:p-8">
            {formError ? <Alert tone="danger">{formError}</Alert> : null}
            {errors.lignes?.root?.message || errors.lignes?.message ? (
              <Alert tone="danger">
                {errors.lignes?.root?.message || errors.lignes?.message}
              </Alert>
            ) : null}

            <section>
              <SectionTitle
                step="01"
                title="Destinataire"
                hint="Client du fichier SIS, ou nom libre pour un prospect / tiers."
              />
              <div className="space-y-4">
                <Select
                  label="Client du système"
                  optionalMark
                  options={clientOptions}
                  placeholder="Choisir un client (optionnel)"
                  error={errors.client_id?.message}
                  hint="Laissez vide pour un destinataire hors fichier clients."
                  {...register("client_id", {
                    onChange: (e) => {
                      if (e.target.value) {
                        setValue("client_nom", "");
                        setValue("client_adresse", "");
                        setValue("client_telephone", "");
                        setValue("client_email", "");
                      } else {
                        setValue("abonnement_id", "");
                        setValue("site_id", "");
                      }
                    },
                  })}
                />
                {selectedClient ? (
                  <div className="rounded-lg border border-border bg-white px-4 py-3">
                    <div className="space-y-1 text-sm">
                      <p className="font-medium text-ink">
                        {selectedClient.raison_sociale}
                      </p>
                      {selectedClient.adresse ? (
                        <p className="text-ink-muted">{selectedClient.adresse}</p>
                      ) : (
                        <p className="text-ink-faint">Adresse non renseignée</p>
                      )}
                      <p className="text-ink-muted">
                        {[selectedClient.telephone, selectedClient.email]
                          .filter(Boolean)
                          .join(" · ") || "Contact non renseigné"}
                      </p>
                    </div>
                  </div>
                ) : (
                  <div className="grid gap-4 sm:grid-cols-2">
                    <Input
                      label="Nom du destinataire"
                      requiredMark
                      placeholder="Raison sociale ou nom"
                      error={errors.client_nom?.message}
                      {...register("client_nom")}
                    />
                    <Input
                      label="Téléphone"
                      optionalMark
                      error={errors.client_telephone?.message}
                      {...register("client_telephone")}
                    />
                    <Input
                      label="Adresse"
                      optionalMark
                      error={errors.client_adresse?.message}
                      {...register("client_adresse")}
                    />
                    <Input
                      label="Email"
                      optionalMark
                      type="email"
                      error={errors.client_email?.message}
                      {...register("client_email")}
                    />
                  </div>
                )}
              </div>
            </section>

            <section className="border-t border-border pt-10">
              <SectionTitle
                step="02"
                title="Conditions & paiement"
                hint="Devis seulement : l’abonnement sera créé quand vous passerez la facture en Validé (client a accepté)."
              />
              <div className="grid gap-4 sm:grid-cols-2">
                <Select
                  label="Abonnement déjà existant ?"
                  optionalMark
                  options={abonnementOptions}
                  disabled={!clientId}
                  hint="Uniquement pour facturer un contrat déjà en cours. Sinon laissez vide."
                  error={errors.abonnement_id?.message}
                  {...register("abonnement_id")}
                />
                <Select
                  label="Délai de paiement"
                  requiredMark
                  options={delaiPaiementOptions}
                  error={errors.delai_paiement_jours?.message}
                  {...register("delai_paiement_jours", { valueAsNumber: true })}
                />
                <Select
                  label="Périodicité"
                  requiredMark
                  options={periodiciteOptions}
                  hint="Recalcule le PU des lignes liées à une offre."
                  error={errors.periodicite?.message}
                  {...register("periodicite")}
                />
                <Select
                  label="Site (optionnel)"
                  optionalMark
                  options={siteOptions}
                  error={errors.site_id?.message}
                  {...register("site_id")}
                />
                <DatePicker
                  label="Début du service"
                  optionalMark
                  hint="Optionnel sur le devis — à préciser à l’acceptation si besoin."
                  error={errors.date_debut_service?.message}
                  {...register("date_debut_service")}
                />
                <DatePicker
                  label="Fin / expiration"
                  optionalMark
                  hint="Vide = reconduction tacite."
                  error={errors.date_fin_service?.message}
                  {...register("date_fin_service")}
                />
              </div>
              {!modeExistant ? (
                <p className="mt-3 text-xs text-ink-faint">
                  À l’acceptation (statut Validé), un seul abonnement combiné
                  est créé pour l’ensemble des lignes du devis.
                </p>
              ) : null}
            </section>

            <section className="border-t border-border pt-10">
              <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
                <div className="flex items-start gap-3">
                  <span className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-md bg-teal/10 text-xs font-semibold tabular-nums text-teal">
                    03
                  </span>
                  <div>
                    <h2 className="text-base font-semibold tracking-tight text-ink">
                      Lignes du devis
                    </h2>
                    <p className="mt-0.5 text-sm text-ink-muted">
                      Sélectionnez une offre par ligne — désignation et prix se
                      remplissent automatiquement.
                    </p>
                  </div>
                </div>
                <Button
                  type="button"
                  size="sm"
                  variant="secondary"
                  onClick={() =>
                    append({
                      offre_id: "",
                      code_article: "",
                      description: "",
                      quantite: 1,
                      prix_unitaire: 0,
                    })
                  }
                >
                  <Plus className="size-4" />
                  Ligne
                </Button>
              </div>

              <div className="overflow-hidden rounded-lg border border-border">
                <div className="hidden grid-cols-[minmax(160px,1.3fr)_1fr_72px_110px_110px_40px] gap-2 border-b border-border bg-white px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-ink-muted md:grid">
                  <span>Offre</span>
                  <span>Désignation</span>
                  <span className="text-right">Qté</span>
                  <span className="text-right">PU HT</span>
                  <span className="text-right">Montant</span>
                  <span />
                </div>

                <div className="divide-y divide-border">
                  {fields.map((field, index) => {
                    const q = Number(lignes?.[index]?.quantite) || 0;
                    const pu = Number(lignes?.[index]?.prix_unitaire) || 0;
                    const lineHt = q * pu;
                    return (
                      <div
                        key={field.id}
                        className="grid gap-3 px-3 py-3 md:grid-cols-[minmax(160px,1.3fr)_1fr_72px_110px_110px_40px] md:items-start md:gap-2"
                      >
                        <Select
                          aria-label={`Offre ligne ${index + 1}`}
                          options={offreSelectOptions}
                          searchable
                          value={lignes?.[index]?.offre_id ?? ""}
                          onChange={(e) =>
                            applyOffreToLigne(index, e.target.value)
                          }
                        />
                        <input
                          type="hidden"
                          {...register(`lignes.${index}.offre_id`)}
                        />
                        <Input
                          aria-label={`Désignation ligne ${index + 1}`}
                          placeholder="Désignation…"
                          error={errors.lignes?.[index]?.description?.message}
                          {...register(`lignes.${index}.description`)}
                        />
                        <Input
                          aria-label={`Quantité ligne ${index + 1}`}
                          type="number"
                          step="1"
                          min="0"
                          className="md:text-right"
                          error={errors.lignes?.[index]?.quantite?.message}
                          {...register(`lignes.${index}.quantite`, {
                            valueAsNumber: true,
                          })}
                        />
                        <Input
                          aria-label={`Prix unitaire ligne ${index + 1}`}
                          type="number"
                          step="1"
                          min="0"
                          className="md:text-right"
                          error={errors.lignes?.[index]?.prix_unitaire?.message}
                          {...register(`lignes.${index}.prix_unitaire`, {
                            valueAsNumber: true,
                          })}
                        />
                        <div className="flex h-10 items-center justify-between md:justify-end">
                          <span className="text-xs text-ink-muted md:hidden">
                            Montant HT
                          </span>
                          <span className="text-sm font-medium tabular-nums text-ink">
                            {formatFcfa(lineHt)}
                          </span>
                        </div>
                        <div className="flex h-10 items-center justify-end">
                          {fields.length > 1 ? (
                            <button
                              type="button"
                              aria-label="Supprimer la ligne"
                              className="rounded-md p-2 text-ink-faint transition hover:bg-danger/5 hover:text-danger"
                              onClick={() => remove(index)}
                            >
                              <Trash2 className="size-4" />
                            </button>
                          ) : (
                            <span className="size-8" />
                          )}
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            </section>

            <section className="border-t border-border pt-10">
              <SectionTitle
                step="04"
                title="Conditions devis"
                hint="Validité du devis et mentions PDF (distinctes du délai de paiement)."
              />
              <div className="grid gap-4 sm:grid-cols-2">
                <Textarea
                  label="Note / NB"
                  optionalMark
                  rows={3}
                  hint="Recalculée avec le HT tant que vous ne la personnalisez pas."
                  placeholder="Laissé vide = généré depuis la périodicité et le HT."
                  error={errors.notes?.message}
                  {...register("notes")}
                />
                <Textarea
                  label="Conditions de paiement"
                  optionalMark
                  rows={3}
                  hint="Remplies automatiquement selon le délai choisi."
                  error={errors.conditions_paiement?.message}
                  {...register("conditions_paiement")}
                />
                <Input
                  label="Délai de validité du devis"
                  optionalMark
                  placeholder="1 mois"
                  error={errors.delai_validite?.message}
                  {...register("delai_validite")}
                />
                <Input
                  label="Durée minimale de contrat (mention)"
                  optionalMark
                  error={errors.duree_contrat_min?.message}
                  {...register("duree_contrat_min")}
                />
                <Input
                  label="Signataire"
                  optionalMark
                  placeholder="M. …"
                  error={errors.signataire_nom?.message}
                  {...register("signataire_nom")}
                />
                <Input
                  label="Fonction"
                  optionalMark
                  error={errors.signataire_fonction?.message}
                  {...register("signataire_fonction")}
                />
              </div>
            </section>
          </div>

          <aside className="lg:sticky lg:top-6 lg:self-start">
            <div className="overflow-hidden rounded-xl border border-border bg-white shadow-sm">
              <div className="border-b border-border bg-teal px-4 py-3 text-white">
                <p className="text-[11px] uppercase tracking-[0.12em] text-white/70">
                  Récapitulatif
                </p>
                <p className="mt-1 text-sm font-medium">Total à payer TTC</p>
                <p className="mt-2 text-2xl font-semibold tabular-nums tracking-tight">
                  {formatFcfa(totals.ttc)}
                </p>
              </div>
              <div className="space-y-2.5 px-4 py-4 text-sm">
                <div className="flex justify-between text-ink-muted">
                  <span>Lignes</span>
                  <span className="tabular-nums text-ink">{fields.length}</span>
                </div>
                <div className="flex justify-between text-ink-muted">
                  <span>Montant HT</span>
                  <span className="tabular-nums text-ink">
                    {formatFcfa(totals.ht)}
                  </span>
                </div>
                <div className="flex justify-between text-ink-muted">
                  <span>TVA 18 %</span>
                  <span className="tabular-nums text-ink">
                    {formatFcfa(totals.tva)}
                  </span>
                </div>
                <div className="border-t border-border pt-2.5">
                  <p className="text-xs leading-relaxed text-ink-faint">
                    Numéro attribué automatiquement (SC-ABJ-AAAA-NNNN). « Créer »
                    laisse la proforma en attente ; « Créer et valider » crée
                    aussi l’abonnement (début = aujourd’hui si non renseigné).
                  </p>
                </div>
              </div>
              <div className="hidden gap-2 border-t border-border p-4 lg:flex lg:flex-col">
                <Button
                  type="button"
                  loading={submitMode === "draft"}
                  disabled={busy}
                  onClick={() => void submitProforma("draft")}
                >
                  {submitMode === "draft" ? "Création…" : "Créer la proforma"}
                </Button>
                <Button
                  type="button"
                  variant="secondary"
                  disabled={busy}
                  loading={submitMode === "validate"}
                  onClick={() => void submitProforma("validate")}
                >
                  <CheckCircle2 className="size-4" />
                  {submitMode === "validate"
                    ? "Validation…"
                    : "Créer et valider"}
                </Button>
                <Button
                  type="button"
                  variant="secondary"
                  disabled={busy}
                  onClick={() => router.push("/factures?tab=proformas")}
                >
                  Annuler
                </Button>
              </div>
            </div>
          </aside>
        </form>

        <div className="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-white px-4 py-3 lg:hidden">
          <div className="mx-auto flex max-w-screen-2xl items-center justify-between gap-3">
            <div>
              <p className="text-[11px] uppercase tracking-wide text-ink-faint">
                TTC
              </p>
              <p className="text-base font-semibold tabular-nums text-ink">
                {formatFcfa(totals.ttc)}
              </p>
            </div>
            <div className="flex gap-2">
              <Button
                type="button"
                variant="secondary"
                size="sm"
                disabled={busy}
                onClick={() => router.push("/factures?tab=proformas")}
              >
                Annuler
              </Button>
              <Button
                type="button"
                variant="secondary"
                size="sm"
                disabled={busy}
                loading={submitMode === "draft"}
                onClick={() => void submitProforma("draft")}
              >
                Créer
              </Button>
              <Button
                type="button"
                size="sm"
                disabled={busy}
                loading={submitMode === "validate"}
                onClick={() => void submitProforma("validate")}
              >
                Valider
              </Button>
            </div>
          </div>
        </div>
        </div>
      </div>
    </PermissionGate>
  );
}
