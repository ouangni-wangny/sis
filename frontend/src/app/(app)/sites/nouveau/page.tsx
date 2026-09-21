"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useFieldArray, useForm, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import {
  ArrowLeft,
  Building2,
  MapPin,
  Plus,
  Trash2,
} from "lucide-react";
import { useClients } from "@/application/hooks/useClients";
import { useCreateSite } from "@/application/hooks/useResources";
import { useZones } from "@/application/hooks/useZones";
import {
  siteSchema,
  toPostesPayload,
  type SiteFormValues,
  type SitePosteDraft,
} from "@/domain/schemas/site";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { Alert } from "@/presentation/components/ui/Alert";
import { Button } from "@/presentation/components/ui/Button";
import { Card, CardBody, CardHeader } from "@/presentation/components/ui/Card";
import { RequiredFieldsLegend } from "@/presentation/components/ui/FieldLabel";
import { Input } from "@/presentation/components/ui/Input";
import { Select } from "@/presentation/components/ui/Select";
import { Textarea } from "@/presentation/components/ui/Textarea";
import { PosteDraftFields } from "@/presentation/components/ui/PosteHorairesField";
import { shiftDurationHours } from "@/presentation/components/ui/TimeRangeField";
import { normalizePosteHoraires } from "@/domain/schemas/poste-horaires";
import { useToast } from "@/presentation/providers/ToastProvider";
import { getApiErrorMessage } from "@/shared/lib/api-error";

const emptyPosteDraft: SitePosteDraft = {
  nom: "",
  agents_requis: "1",
  heure_debut: "07:00",
  heure_fin: "19:00",
  heure_debut_nuit: "",
  heure_fin_nuit: "",
  mode_effectif: "ensemble",
};

const emptyDefaults: SiteFormValues = {
  client_id: "",
  zone_id: "",
  nom: "",
  adresse: "",
  interne: false,
  postes: [{ ...emptyPosteDraft }],
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

export default function NouveauSitePage() {
  const router = useRouter();
  const { toast } = useToast();
  const [formError, setFormError] = useState<string | null>(null);

  const { data: clientsData } = useClients({ all: true });
  const { data: zonesData } = useZones({ all: true });
  const createSite = useCreateSite();

  const {
    register,
    control,
    handleSubmit,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<SiteFormValues>({
    resolver: zodResolver(siteSchema),
    mode: "onBlur",
    reValidateMode: "onChange",
    defaultValues: emptyDefaults,
  });

  const { fields, append, remove } = useFieldArray({
    control,
    name: "postes",
  });

  const postesWatch = useWatch({ control, name: "postes" });
  const nom = useWatch({ control, name: "nom" });
  const clientId = useWatch({ control, name: "client_id" });
  const zoneId = useWatch({ control, name: "zone_id" });
  const adresse = useWatch({ control, name: "adresse" });

  const busy = isSubmitting || createSite.isPending;

  const clientOptions = useMemo(
    () =>
      (clientsData?.data ?? []).map((c) => ({
        value: c.id,
        label: c.raison_sociale,
      })),
    [clientsData],
  );
  const zoneOptions = useMemo(
    () => (zonesData?.data ?? []).map((z) => ({ value: z.id, label: z.nom })),
    [zonesData],
  );

  const selectedClient = useMemo(
    () => (clientsData?.data ?? []).find((c) => c.id === clientId),
    [clientsData, clientId],
  );
  const selectedZone = useMemo(
    () => (zonesData?.data ?? []).find((z) => z.id === zoneId),
    [zonesData, zoneId],
  );

  const postesSummary = useMemo(() => {
    return (postesWatch ?? [])
      .filter((p) => p?.nom?.trim())
      .map((p) => {
        const agents = Number(p.agents_requis) || 1;
        const hours = normalizePosteHoraires({
          heure_debut: p.heure_debut || "",
          heure_fin: p.heure_fin || "",
          heure_debut_nuit: p.heure_debut_nuit || "",
          heure_fin_nuit: p.heure_fin_nuit || "",
        });
        const decoupage = Boolean(
          hours.heure_debut_nuit && hours.heure_fin_nuit,
        );
        const cycle24h =
          !decoupage &&
          Boolean(
            hours.heure_debut &&
              hours.heure_fin &&
              hours.heure_debut === hours.heure_fin,
          );
        const duration = shiftDurationHours(
          hours.heure_debut || "",
          cycle24h || decoupage
            ? hours.heure_debut
            : hours.heure_fin || "",
        );
        let plage = "Horaires à définir";
        if (hours.heure_debut && hours.heure_fin) {
          plage = decoupage
            ? `jour ${hours.heure_debut}–${hours.heure_fin} · nuit ${hours.heure_debut_nuit}–${hours.heure_fin_nuit}`
            : cycle24h
              ? `${hours.heure_debut} → ${hours.heure_fin} · 24h`
              : `${hours.heure_debut}–${hours.heure_fin}`;
        }
        return {
          nom: p.nom.trim(),
          agents,
          duration,
          is24h: decoupage || cycle24h,
          plage,
        };
      });
  }, [postesWatch]);

  const totalAgents = postesSummary.reduce((sum, p) => sum + p.agents, 0);

  return (
    <PermissionGate permission="sites.create" title="Nouveau site">
      <div className="-m-6 min-h-full bg-[linear-gradient(180deg,#f7f6f2_0%,#ffffff_42%)] px-6 py-6 pb-8 max-lg:pb-28">
        <div className="mx-auto max-w-screen-2xl">
          <Link
            href="/sites"
            className="mb-6 inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-teal"
          >
            <ArrowLeft className="size-4" />
            Sites
          </Link>

          <header className="mb-8 flex flex-col gap-4 border-b border-border/80 pb-6 sm:flex-row sm:items-end sm:justify-between">
            <div className="flex items-start gap-3">
              <span className="flex size-11 shrink-0 items-center justify-center rounded-lg bg-teal text-white shadow-sm">
                <Building2 className="size-5" />
              </span>
              <div>
                <p className="text-xs font-medium uppercase tracking-[0.14em] text-teal">
                  Opérations
                </p>
                <h1 className="mt-1 text-2xl font-semibold tracking-tight text-ink">
                  Nouveau site
                </h1>
                <p className="mt-1 max-w-xl text-sm text-ink-muted">
                  Rattachez le site à un client et une zone, puis définissez les
                  postes et leurs créneaux de référence.
                </p>
              </div>
            </div>
            <RequiredFieldsLegend className="sm:text-right" />
          </header>

          <form
            id="nouveau-site-form"
            className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_540px]"
            noValidate
            onSubmit={handleSubmit(async (values) => {
              setFormError(null);
              try {
                const postes = toPostesPayload(values.postes);
                await createSite.mutateAsync({
                  client_id: values.client_id,
                  zone_id: values.zone_id,
                  nom: values.nom,
                  adresse: values.adresse || null,
                  interne: Boolean(values.interne),
                  ...(postes.length > 0 ? { postes } : {}),
                });
                toast("Site créé avec succès.");
                router.push("/sites");
              } catch (err) {
                const message = getApiErrorMessage(
                  err,
                  "Échec de la création du site.",
                );
                setFormError(message);
                toast(message, "danger");
              }
            })}
          >
            <div className="space-y-6">
              <Card>
                <CardBody>
                  <SectionTitle
                    step="1"
                    title="Identification"
                    hint="Nom du site tel qu’il apparaîtra dans le planning et les rapports."
                  />
                  <div className="space-y-4">
                    <Input
                      label="Nom du site"
                      requiredMark
                      placeholder="ex. Siège Orange CI — Plateau"
                      error={errors.nom?.message}
                      {...register("nom")}
                    />
                    <Textarea
                      label="Adresse"
                      optionalMark
                      rows={3}
                      placeholder="Quartier, commune, ville…"
                      error={errors.adresse?.message}
                      {...register("adresse")}
                    />
                    <label className="flex items-start gap-2 rounded-md border border-border bg-paper-muted/40 px-3 py-2.5 text-sm text-ink">
                      <input
                        type="checkbox"
                        className="mt-0.5 size-4 rounded border-border text-teal focus:ring-teal/40"
                        {...register("interne")}
                      />
                      <span>
                        <span className="font-medium">
                          Site interne (siège)
                        </span>
                        <span className="mt-0.5 block text-xs text-ink-muted">
                          Permet au rôle Opération de contrôler les agents
                          présents ici, sans périmètre contrôleur.
                        </span>
                      </span>
                    </label>
                  </div>
                </CardBody>
              </Card>

              <Card>
                <CardBody>
                  <SectionTitle
                    step="2"
                    title="Rattachement"
                    hint="Client contractuel et zone opérationnelle de couverture."
                  />
                  <div className="grid gap-4 sm:grid-cols-2">
                    <Select
                      label="Client"
                      requiredMark
                      placeholder="Sélectionnez un client"
                      options={clientOptions}
                      error={errors.client_id?.message}
                      {...register("client_id")}
                    />
                    <Select
                      label="Zone"
                      requiredMark
                      placeholder="Sélectionnez une zone"
                      options={zoneOptions}
                      error={errors.zone_id?.message}
                      {...register("zone_id")}
                    />
                  </div>
                </CardBody>
              </Card>

              <Card>
                <CardHeader
                  title="Postes du site"
                  description="Effectif et horaires — préremplissent la planification."
                  action={
                    <span className="rounded-full bg-teal/10 px-2.5 py-1 text-xs font-semibold tabular-nums text-teal">
                      Étape 3
                    </span>
                  }
                />
                <CardBody className="space-y-5">
                  {fields.map((field, index) => {
                    const posteNom = postesWatch?.[index]?.nom?.trim();
                    const draft = postesWatch?.[index];
                    return (
                      <div
                        key={field.id}
                        className="overflow-hidden rounded-xl border border-border bg-white shadow-[0_1px_3px_rgba(47,58,36,0.06)]"
                      >
                        <div className="flex flex-wrap items-center gap-3 border-b border-border/80 bg-paper-muted/60 px-4 py-3">
                          <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-teal text-xs font-bold text-white">
                            {index + 1}
                          </span>
                          <div className="min-w-0 flex-1">
                            <p className="text-sm font-semibold text-ink">
                              {posteNom || `Poste ${index + 1}`}
                            </p>
                            <p className="text-[11px] text-ink-faint">
                              {(() => {
                                if (!draft?.heure_debut || !draft?.heure_fin) {
                                  return "Créneau à définir";
                                }
                                const hours = normalizePosteHoraires({
                                  heure_debut: draft.heure_debut,
                                  heure_fin: draft.heure_fin,
                                  heure_debut_nuit:
                                    draft.heure_debut_nuit ?? "",
                                  heure_fin_nuit: draft.heure_fin_nuit ?? "",
                                });
                                if (
                                  hours.heure_debut_nuit &&
                                  hours.heure_fin_nuit
                                ) {
                                  return `jour ${hours.heure_debut}–${hours.heure_fin} · nuit ${hours.heure_debut_nuit}–${hours.heure_fin_nuit}`;
                                }
                                if (hours.heure_debut === hours.heure_fin) {
                                  return `${hours.heure_debut} → ${hours.heure_fin} · 24h`;
                                }
                                return `${hours.heure_debut}–${hours.heure_fin}`;
                              })()}
                            </p>
                          </div>

                          {fields.length > 1 ? (
                            <Button
                              type="button"
                              variant="ghost"
                              size="sm"
                              aria-label="Retirer ce poste"
                              className="text-danger hover:bg-danger/5"
                              onClick={() => remove(index)}
                            >
                              <Trash2 className="size-3.5" />
                            </Button>
                          ) : null}
                        </div>

                        <div className="p-4">
                          <PosteDraftFields
                            nomRequired={index === 0}
                            values={{
                              nom: draft?.nom ?? "",
                              agents_requis: String(draft?.agents_requis ?? "1"),
                              heure_debut: draft?.heure_debut ?? "",
                              heure_fin: draft?.heure_fin ?? "",
                              heure_debut_nuit: draft?.heure_debut_nuit ?? "",
                              heure_fin_nuit: draft?.heure_fin_nuit ?? "",
                              mode_effectif:
                                draft?.mode_effectif === "alternance"
                                  ? "alternance"
                                  : "ensemble",
                            }}
                            onChange={(next) => {
                              setValue(`postes.${index}.nom`, next.nom, {
                                shouldDirty: true,
                                shouldValidate: true,
                              });
                              setValue(
                                `postes.${index}.agents_requis`,
                                next.agents_requis,
                                { shouldDirty: true, shouldValidate: true },
                              );
                              setValue(
                                `postes.${index}.heure_debut`,
                                next.heure_debut,
                                { shouldDirty: true, shouldValidate: true },
                              );
                              setValue(
                                `postes.${index}.heure_fin`,
                                next.heure_fin,
                                { shouldDirty: true, shouldValidate: true },
                              );
                              setValue(
                                `postes.${index}.heure_debut_nuit`,
                                next.heure_debut_nuit,
                                { shouldDirty: true, shouldValidate: true },
                              );
                              setValue(
                                `postes.${index}.heure_fin_nuit`,
                                next.heure_fin_nuit,
                                { shouldDirty: true, shouldValidate: true },
                              );
                              setValue(
                                `postes.${index}.mode_effectif`,
                                next.mode_effectif ?? "ensemble",
                                { shouldDirty: true, shouldValidate: true },
                              );
                            }}
                          />
                        </div>
                      </div>
                    );
                  })}

                  <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    className="w-full border-dashed sm:w-auto"
                    onClick={() => append({ ...emptyPosteDraft })}
                  >
                    <Plus className="size-4" />
                    Ajouter un poste
                  </Button>
                </CardBody>
              </Card>

              {formError ? <Alert tone="danger">{formError}</Alert> : null}
            </div>

            <aside className="space-y-4 lg:sticky lg:top-6 lg:self-start">
              <Card className="overflow-hidden">
                <div className="border-b border-border bg-teal px-5 py-4 text-white">
                  <p className="text-xs font-medium uppercase tracking-[0.12em] text-white/70">
                    Aperçu
                  </p>
                  <p className="mt-1 text-lg font-semibold tracking-tight">
                    {nom?.trim() || "Site sans nom"}
                  </p>
                </div>
                <CardBody className="space-y-4 text-sm">
                  <div className="space-y-2">
                    <div className="flex items-start gap-2 text-ink-muted">
                      <Building2 className="mt-0.5 size-3.5 shrink-0" />
                      <span>
                        {selectedClient?.raison_sociale ?? (
                          <span className="text-ink-faint">Client à choisir</span>
                        )}
                      </span>
                    </div>
                    <div className="flex items-start gap-2 text-ink-muted">
                      <MapPin className="mt-0.5 size-3.5 shrink-0" />
                      <span>
                        {selectedZone?.nom ?? (
                          <span className="text-ink-faint">Zone à choisir</span>
                        )}
                      </span>
                    </div>
                    {adresse?.trim() ? (
                      <p className="pl-5 text-xs text-ink-faint">{adresse}</p>
                    ) : null}
                  </div>

                  <div className="rounded-lg border border-border bg-paper-muted/50 p-3">
                    <div className="mb-2 flex items-center justify-between">
                      <p className="text-xs font-semibold uppercase tracking-wide text-ink-faint">
                        Postes
                      </p>
                      <p className="text-xs tabular-nums text-ink-muted">
                        {postesSummary.length} · {totalAgents} agent
                        {totalAgents > 1 ? "s" : ""}
                      </p>
                    </div>
                    {postesSummary.length === 0 ? (
                      <p className="text-xs text-ink-faint">
                        Aucun poste nommé pour l’instant.
                      </p>
                    ) : (
                      <ul className="space-y-2">
                        {postesSummary.map((p, i) => (
                          <li
                            key={`${p.nom}-${i}`}
                            className="rounded-md bg-white px-2.5 py-2 ring-1 ring-border/80"
                          >
                            <p className="truncate text-sm font-medium text-ink">
                              {p.nom}
                            </p>
                            <p className="text-[11px] text-ink-faint">
                              {p.agents} agent{p.agents > 1 ? "s" : ""} ·{" "}
                              {p.plage}
                              {p.is24h ? " · 24h" : ""}
                              {p.duration != null ? ` · ${p.duration} h` : ""}
                            </p>
                          </li>
                        ))}
                      </ul>
                    )}
                  </div>

                  <div className="hidden gap-2 lg:flex">
                    <Button
                      type="button"
                      variant="secondary"
                      className="flex-1"
                      disabled={busy}
                      onClick={() => router.push("/sites")}
                    >
                      Annuler
                    </Button>
                    <Button
                      type="submit"
                      className="flex-1"
                      loading={busy}
                    >
                      Créer le site
                    </Button>
                  </div>
                </CardBody>
              </Card>

              <p className="hidden text-xs leading-relaxed text-ink-faint lg:block">
                Vous pourrez ajouter des checkpoints et ajuster les postes après
                la création depuis la liste des sites.
              </p>
            </aside>

            <div className="fixed inset-x-0 bottom-0 z-20 border-t border-border bg-white/95 p-3 backdrop-blur lg:hidden">
              <div className="mx-auto flex max-w-screen-2xl gap-2">
                <Button
                  type="button"
                  variant="secondary"
                  className="flex-1"
                  disabled={busy}
                  onClick={() => router.push("/sites")}
                >
                  Annuler
                </Button>
                <Button type="submit" className="flex-1" loading={busy}>
                  Créer le site
                </Button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </PermissionGate>
  );
}
