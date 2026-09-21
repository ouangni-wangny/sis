"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useFieldArray, useForm, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { ArrowLeft, FileText, Plus, Trash2 } from "lucide-react";
import { useClients } from "@/application/hooks/useClients";
import {
  useAbonnements,
  useFacture,
  useUpdateProformaFacture,
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
import { Spinner } from "@/presentation/components/ui/Spinner";
import { Textarea } from "@/presentation/components/ui/Textarea";
import { useToast } from "@/presentation/providers/ToastProvider";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { formatDate, formatFcfa, labelize } from "@/shared/lib/format";
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
  { value: "", label: "— Aucune —" },
  { value: "mensuel", label: "Mensuel" },
  { value: "trimestriel", label: "Trimestriel" },
  { value: "annuel", label: "Annuel" },
];

const emptyDefaults: ProformaFactureFormValues = {
  client_id: "",
  client_nom: "",
  client_adresse: "",
  client_telephone: "",
  client_email: "",
  abonnement_id: "",
  creer_abonnement: false,
  site_id: "",
  periodicite: "",
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

export default function ModifierFactureProformaPage() {
  const router = useRouter();
  const params = useParams();
  const id = params.id as string;
  const { toast } = useToast();
  const [formError, setFormError] = useState<string | null>(null);
  const { data: clientsData } = useClients({ all: true });
  const {
    data: factureResponse,
    isLoading,
    isError,
  } = useFacture(id);
  const facture = factureResponse?.data;
  const updateProforma = useUpdateProformaFacture();

  const {
    register,
    control,
    handleSubmit,
    reset,
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
  const periodicite = useWatch({ control, name: "periodicite" });
  const notes = useWatch({ control, name: "notes" });

  const { data: abonnementsData } = useAbonnements({
    all: true,
    client_id: clientId || undefined,
  });

  const locked = facture?.statut === "annule";
  const busy = isSubmitting || updateProforma.isPending;

  useEffect(() => {
    const jours = Number(delaiPaiement);
    const labels: Record<number, string> = {
      0: "Paiement au comptant avant la mise en place",
      15: "Paiement à 15 jours net",
      30: "Paiement à 30 jours net",
      45: "Paiement à 45 jours net",
      60: "Paiement à 60 jours net",
    };
    if (facture && labels[jours] !== undefined) {
      setValue("conditions_paiement", labels[jours]);
    }
  }, [delaiPaiement, setValue, facture]);

  useEffect(() => {
    if (!facture) return;

    reset({
      client_id: facture.client_id ?? "",
      client_nom: facture.client_id ? "" : (facture.client_nom ?? ""),
      client_adresse: facture.client_id ? "" : (facture.client_adresse ?? ""),
      client_telephone: facture.client_id
        ? ""
        : (facture.client_telephone ?? ""),
      client_email: facture.client_id ? "" : (facture.client_email ?? ""),
      abonnement_id: facture.abonnement_id ?? "",
      creer_abonnement: false,
      site_id: facture.site_id ?? "",
      periodicite: (facture.periodicite as ProformaFactureFormValues["periodicite"]) ?? "",
      date_debut_service:
        facture.date_debut_service?.slice(0, 10)
        ?? facture.periode_debut?.slice(0, 10)
        ?? "",
      date_fin_service: facture.date_fin_service?.slice(0, 10) ?? "",
      delai_paiement_jours: facture.delai_paiement_jours ?? 0,
      notes: facture.notes ?? "",
      conditions_paiement: facture.conditions_paiement ?? "",
      delai_validite: facture.delai_validite ?? "",
      duree_contrat_min: facture.duree_contrat_min ?? "",
      signataire_nom: facture.signataire_nom ?? "",
      signataire_fonction: facture.signataire_fonction ?? "",
      lignes:
        facture.lignes && facture.lignes.length > 0
          ? facture.lignes.map((l) => ({
              offre_id: l.offre_id ?? "",
              code_article: l.code_article ?? "",
              description: l.description,
              quantite: Number(l.quantite),
              prix_unitaire: Number(l.prix_unitaire),
            }))
          : emptyDefaults.lignes,
    });
  }, [facture, reset]);

  const abonnementOptions = useMemo(
    () => [
      { value: "", label: "— Aucun —" },
      ...(abonnementsData?.data ?? []).map((a) => ({
        value: a.id,
        label: `${a.designation ?? a.offre?.libelle ?? "Offre"} · ${labelize(a.periodicite)}`,
      })),
    ],
    [abonnementsData],
  );

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

  useEffect(() => {
    if (totals.ht <= 0) return;
    if (!isAutoNoteAbonnement(notes)) return;
    const next = noteAbonnement(periodicite || "mensuel", totals.ht);
    if (notes !== next) {
      setValue("notes", next);
    }
  }, [totals.ht, periodicite, notes, setValue]);

  return (
    <PermissionGate permission="factures.update" title="Modifier la proforma">
      <div className="-m-6 min-h-full bg-white px-6 py-6 pb-8 max-lg:pb-28">
        <div className="mx-auto max-w-screen-2xl">
          <Link
            href="/factures?tab=proformas"
            className="mb-6 inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-teal"
          >
            <ArrowLeft className="size-4" />
            Factures
          </Link>

          {isLoading ? (
            <div className="flex justify-center py-20">
              <Spinner className="size-8" />
            </div>
          ) : isError || !facture ? (
            <Alert tone="danger" title="Facture introuvable">
              Cette facture proforma n&apos;existe pas ou n&apos;est plus
              accessible.
            </Alert>
          ) : (
            <>
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
                      Modifier la proforma
                    </h1>
                    <p className="mt-1 max-w-xl text-sm text-ink-muted">
                      {facture.numero} — mise à jour des prestations et
                      conditions. Le PDF est régénéré à l&apos;enregistrement.
                    </p>
                  </div>
                </div>
                <RequiredFieldsLegend className="sm:text-right" />
              </header>

              <form
                id="proforma-form"
                className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_280px]"
                noValidate
                onSubmit={handleSubmit(async (values) => {
                  if (locked) return;
                  setFormError(null);
                  try {
                    await updateProforma.mutateAsync({
                      id,
                      payload: {
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
                        site_id: values.client_id
                          ? values.site_id || null
                          : null,
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
                      },
                    });
                    toast("Facture proforma enregistrée.");
                    router.push("/factures?tab=proformas");
                  } catch (err) {
                    const message = getApiErrorMessage(
                      err,
                      "Échec de la mise à jour de la facture proforma.",
                    );
                    setFormError(message);
                    toast(message, "danger");
                  }
                })}
              >
                <fieldset
                  disabled={locked}
                  className="min-w-0 space-y-10 rounded-xl border border-border bg-white p-6 shadow-md sm:p-8"
                >
                  {locked ? (
                    <Alert tone="warning" title="Facture annulée">
                      Cette facture proforma est annulée et ne peut plus être
                      modifiée.
                    </Alert>
                  ) : null}
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
                              <p className="text-ink-muted">
                                {selectedClient.adresse}
                              </p>
                            ) : (
                              <p className="text-ink-faint">
                                Adresse non renseignée
                              </p>
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
                      title="Engagement commercial"
                      hint="Périodicité, période facturée et délai de paiement."
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                      <Select
                        label="Abonnement lié"
                        optionalMark
                        options={abonnementOptions}
                        error={errors.abonnement_id?.message}
                        {...register("abonnement_id")}
                      />
                      <Select
                        label="Délai de paiement"
                        requiredMark
                        options={delaiPaiementOptions}
                        error={errors.delai_paiement_jours?.message}
                        {...register("delai_paiement_jours", {
                          valueAsNumber: true,
                        })}
                      />
                      <Select
                        label="Périodicité"
                        optionalMark
                        options={periodiciteOptions}
                        error={errors.periodicite?.message}
                        {...register("periodicite")}
                      />
                      <DatePicker
                        label="Début de période"
                        optionalMark
                        error={errors.date_debut_service?.message}
                        {...register("date_debut_service")}
                      />
                    </div>
                    {facture.periode_debut && facture.periode_fin ? (
                      <p className="mt-3 text-sm text-ink-muted">
                        Période actuelle : {formatDate(facture.periode_debut)} —{" "}
                        {formatDate(facture.periode_fin)}
                        {facture.date_echeance
                          ? ` · Échéance : ${formatDate(facture.date_echeance)}`
                          : ""}
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
                            Prestations
                          </h2>
                          <p className="mt-0.5 text-sm text-ink-muted">
                            Une ligne = un article / service facturé.
                          </p>
                        </div>
                      </div>
                      <Button
                        type="button"
                        size="sm"
                        variant="secondary"
                        onClick={() =>
                          append({
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
                      <div className="hidden grid-cols-[92px_1fr_72px_110px_110px_40px] gap-2 border-b border-border bg-white px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-ink-muted md:grid">
                        <span>Code</span>
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
                              className="grid gap-3 px-3 py-3 md:grid-cols-[92px_1fr_72px_110px_110px_40px] md:items-start md:gap-2"
                            >
                              <Input
                                aria-label={`Code ligne ${index + 1}`}
                                placeholder="AGSNAJ"
                                error={
                                  errors.lignes?.[index]?.code_article?.message
                                }
                                {...register(`lignes.${index}.code_article`)}
                              />
                              <Input
                                aria-label={`Désignation ligne ${index + 1}`}
                                placeholder="Agent de sécurité de jour…"
                                error={
                                  errors.lignes?.[index]?.description?.message
                                }
                                {...register(`lignes.${index}.description`)}
                              />
                              <Input
                                aria-label={`Quantité ligne ${index + 1}`}
                                type="number"
                                step="1"
                                min="0"
                                className="md:text-right"
                                error={
                                  errors.lignes?.[index]?.quantite?.message
                                }
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
                                error={
                                  errors.lignes?.[index]?.prix_unitaire?.message
                                }
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
                      hint="Validité du devis et mentions PDF."
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                      <Textarea
                        label="Note / NB"
                        optionalMark
                        rows={3}
                        hint="Recalculée avec le HT tant que vous ne la personnalisez pas."
                        placeholder="Ex. L’abonnement mensuel sera de…"
                        error={errors.notes?.message}
                        {...register("notes")}
                      />
                      <Textarea
                        label="Conditions de paiement"
                        optionalMark
                        rows={3}
                        error={errors.conditions_paiement?.message}
                        {...register("conditions_paiement")}
                      />
                      <Input
                        label="Délai de validité"
                        optionalMark
                        placeholder="1 mois"
                        error={errors.delai_validite?.message}
                        {...register("delai_validite")}
                      />
                      <Input
                        label="Durée minimale de contrat"
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
                </fieldset>

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
                        <span>Numéro</span>
                        <span className="font-mono text-xs tabular-nums text-ink">
                          {facture.numero}
                        </span>
                      </div>
                      <div className="flex justify-between text-ink-muted">
                        <span>Lignes</span>
                        <span className="tabular-nums text-ink">
                          {fields.length}
                        </span>
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
                    </div>
                    <div className="hidden gap-2 border-t border-border p-4 lg:flex lg:flex-col">
                      <Button
                        type="submit"
                        form="proforma-form"
                        loading={busy}
                        disabled={locked}
                      >
                        {busy ? "Enregistrement…" : "Enregistrer la proforma"}
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
                      type="submit"
                      form="proforma-form"
                      size="sm"
                      loading={busy}
                      disabled={locked}
                    >
                      Enregistrer
                    </Button>
                  </div>
                </div>
              </div>
            </>
          )}
        </div>
      </div>
    </PermissionGate>
  );
}
