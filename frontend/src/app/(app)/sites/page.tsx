"use client";

import Link from "next/link";
import { useCallback, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { type ColumnDef } from "@tanstack/react-table";
import { MapPin, Pencil, Plus, Trash2, X, Eye } from "lucide-react";
import { useClients } from "@/application/hooks/useClients";
import { useDebouncedValue } from "@/application/hooks/useDebouncedValue";
import {
  useCheckpoints,
  useCreateCheckpoint,
  useCreatePoste,
  useDeleteCheckpoint,
  useDeletePoste,
  useDeleteSite,
  usePostes,
  useSites,
  useUpdatePoste,
  useUpdateSite,
} from "@/application/hooks/useResources";
import { useZones } from "@/application/hooks/useZones";
import {
  checkpointSchema,
  type CheckpointFormValues,
} from "@/domain/schemas/checkpoint";
import {
  siteSchema,
  toPosteHoursPayload,
  type SiteFormValues,
  type SitePosteDraft,
} from "@/domain/schemas/site";
import { normalizePosteHoraires } from "@/domain/schemas/poste-horaires";
import type { Checkpoint, Poste, Site } from "@/domain/types/entities";
import { DataTable } from "@/presentation/components/tables/DataTable";
import { TableActions } from "@/presentation/components/tables/TableActions";
import { Alert } from "@/presentation/components/ui/Alert";
import { Button } from "@/presentation/components/ui/Button";
import { ConfirmDialog } from "@/presentation/components/ui/ConfirmDialog";
import { RequiredFieldsLegend } from "@/presentation/components/ui/FieldLabel";
import { Input } from "@/presentation/components/ui/Input";
import { Modal } from "@/presentation/components/ui/Modal";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { Select } from "@/presentation/components/ui/Select";
import { Spinner } from "@/presentation/components/ui/Spinner";
import { Textarea } from "@/presentation/components/ui/Textarea";
import { PosteDraftFields } from "@/presentation/components/ui/PosteHorairesField";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { useToast } from "@/presentation/providers/ToastProvider";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { can } from "@/shared/lib/can";

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
  postes: [],
};

const emptyCheckpointDefaults: CheckpointFormValues = {
  site_id: "",
  nom: "",
  code_qr: "",
  latitude: "",
  longitude: "",
  ordre: "",
};

function formatTimeDisplay(value: string | null | undefined) {
  if (!value) return "—";
  return value.slice(0, 5);
}

export default function SitesPage() {
  const router = useRouter();
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [q, setQ] = useState("");
  const search = useDebouncedValue(q);
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<Site | null>(null);
  const [toDelete, setToDelete] = useState<Site | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const [checkpointSite, setCheckpointSite] = useState<Site | null>(null);
  const [checkpointFormError, setCheckpointFormError] = useState<string | null>(
    null,
  );
  const [checkpointToDelete, setCheckpointToDelete] =
    useState<Checkpoint | null>(null);
  const [posteToDelete, setPosteToDelete] = useState<Poste | null>(null);
  const [editPosteDraft, setEditPosteDraft] =
    useState<SitePosteDraft>(emptyPosteDraft);
  const [addingPoste, setAddingPoste] = useState(false);
  const [editingPosteId, setEditingPosteId] = useState<string | null>(null);
  const { toast } = useToast();
  const { user } = useAuth();
  const canCreate = can(user, "sites.create");
  const canUpdate = can(user, "sites.update");
  const canDelete = can(user, "sites.delete");
  const canManageCheckpoints = can(user, "checkpoints.manage");
  const canManagePostes = can(user, "postes.manage");

  const { data, isLoading } = useSites({
    page,
    per_page: perPage,
    q: search || undefined,
  });
  const { data: clientsData } = useClients({ all: true });
  const { data: zonesData } = useZones({ all: true });
  const updateSite = useUpdateSite();
  const deleteSite = useDeleteSite();
  const createPoste = useCreatePoste();
  const updatePoste = useUpdatePoste();
  const deletePoste = useDeletePoste();

  const { data: checkpointsData, isLoading: checkpointsLoading } =
    useCheckpoints(checkpointSite?.id);
  const createCheckpoint = useCreateCheckpoint();
  const deleteCheckpoint = useDeleteCheckpoint();

  const {
    register: registerCheckpoint,
    handleSubmit: handleSubmitCheckpoint,
    reset: resetCheckpoint,
    formState: { errors: checkpointErrors, isSubmitting: checkpointSubmitting },
  } = useForm<CheckpointFormValues>({
    resolver: zodResolver(checkpointSchema),
    mode: "onBlur",
    reValidateMode: "onChange",
    defaultValues: emptyCheckpointDefaults,
  });

  const checkpointBusy = checkpointSubmitting || createCheckpoint.isPending;

  const openCheckpoints = useCallback(
    (site: Site) => {
      if (!canManageCheckpoints) {
        toast("Vous n’avez pas le droit de gérer les checkpoints.", "danger");
        return;
      }
      setCheckpointSite(site);
      resetCheckpoint({ ...emptyCheckpointDefaults, site_id: site.id });
      setCheckpointFormError(null);
    },
    [canManageCheckpoints, resetCheckpoint, toast],
  );

  const closeCheckpoints = () => {
    if (checkpointBusy || deleteCheckpoint.isPending) return;
    setCheckpointSite(null);
    setCheckpointFormError(null);
    resetCheckpoint(emptyCheckpointDefaults);
  };

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<SiteFormValues>({
    resolver: zodResolver(siteSchema),
    mode: "onBlur",
    reValidateMode: "onChange",
    defaultValues: emptyDefaults,
  });

  const busy = isSubmitting || updateSite.isPending || addingPoste;

  const { data: sitePostesData, isLoading: sitePostesLoading } = usePostes(
    { site_id: editing?.id, all: true },
    { enabled: open && !!editing?.id },
  );
  const sitePostes = sitePostesData?.data ?? [];

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

  const closeModal = () => {
    if (busy || addingPoste || deletePoste.isPending) return;
    setOpen(false);
    setEditing(null);
    setFormError(null);
    setEditPosteDraft(emptyPosteDraft);
    setEditingPosteId(null);
    setPosteToDelete(null);
    reset(emptyDefaults);
    updateSite.reset();
  };

  const openEdit = useCallback(
    (site: Site) => {
      if (!canUpdate) {
        toast("Vous n’avez pas le droit de modifier un site.", "danger");
        return;
      }
      setEditing(site);
      setFormError(null);
      setEditPosteDraft(emptyPosteDraft);
      setEditingPosteId(null);
      reset({
        client_id: site.client_id,
        zone_id: site.zone_id,
        nom: site.nom,
        adresse: site.adresse ?? "",
        interne: Boolean(site.interne),
        postes: [],
      });
      setOpen(true);
    },
    [canUpdate, reset, toast],
  );

  const startEditPoste = (poste: Poste) => {
    setEditingPosteId(poste.id);
    const hours = normalizePosteHoraires({
      heure_debut: poste.heure_debut?.slice(0, 5) ?? "",
      heure_fin: poste.heure_fin?.slice(0, 5) ?? "",
      heure_debut_nuit: poste.heure_debut_nuit?.slice(0, 5) ?? "",
      heure_fin_nuit: poste.heure_fin_nuit?.slice(0, 5) ?? "",
    });
    setEditPosteDraft({
      nom: poste.nom,
      agents_requis: String(poste.agents_requis ?? "1"),
      mode_effectif:
        poste.mode_effectif === "alternance" ? "alternance" : "ensemble",
      ...hours,
    });
  };

  const cancelEditPoste = () => {
    setEditingPosteId(null);
    setEditPosteDraft(emptyPosteDraft);
  };

  /** Persiste le brouillon poste (création ou édition) — true si OK / rien à faire. */
  const flushPosteDraft = async (): Promise<boolean> => {
    if (!editing || !canManagePostes) return true;
    const hasNom = editPosteDraft.nom.trim().length > 0;
    if (!editingPosteId && !hasNom) return true;
    if (!hasNom) {
      toast("Indiquez le nom du poste.", "danger");
      return false;
    }

    setAddingPoste(true);
    const hours = toPosteHoursPayload(editPosteDraft);
    const payload = {
      nom: editPosteDraft.nom.trim(),
      agents_requis:
        editPosteDraft.agents_requis === ""
          ? undefined
          : Number(editPosteDraft.agents_requis),
      ...hours,
    };
    try {
      if (editingPosteId) {
        await updatePoste.mutateAsync({
          siteId: editing.id,
          posteId: editingPosteId,
          payload,
        });
      } else {
        await createPoste.mutateAsync({ siteId: editing.id, payload });
      }
      setEditPosteDraft(emptyPosteDraft);
      setEditingPosteId(null);
      return true;
    } catch (err) {
      toast(
        getApiErrorMessage(
          err,
          editingPosteId
            ? "Échec de la mise à jour du poste."
            : "Échec de l’ajout du poste.",
        ),
        "danger",
      );
      return false;
    } finally {
      setAddingPoste(false);
    }
  };

  const addEditPoste = async () => {
    const wasEdit = Boolean(editingPosteId);
    if (!wasEdit && !editPosteDraft.nom.trim()) {
      toast("Indiquez le nom du poste.", "danger");
      return;
    }
    const ok = await flushPosteDraft();
    if (ok) {
      toast(wasEdit ? "Poste mis à jour." : "Poste ajouté.");
    }
  };

  const columns = useMemo<ColumnDef<Site>[]>(
    () => [
      {
        accessorKey: "nom",
        header: "Site",
        cell: ({ row }) => (
          <Link
            href={`/sites/${row.original.id}`}
            className="font-medium text-ink transition hover:text-teal"
          >
            {row.original.nom}
          </Link>
        ),
      },
      {
        id: "client",
        header: "Client",
        cell: ({ row }) => row.original.client?.raison_sociale ?? "—",
      },
      {
        id: "zone",
        header: "Zone",
        cell: ({ row }) => row.original.zone?.nom ?? "—",
      },
      {
        accessorKey: "adresse",
        header: "Adresse",
        cell: ({ getValue }) => (
          <span className="line-clamp-2 max-w-xs text-ink-muted">
            {(getValue() as string | null) || "—"}
          </span>
        ),
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
                key: "view",
                label: "Voir le détail",
                icon: Eye,
                tone: "accent",
                onClick: () => router.push(`/sites/${row.original.id}`),
              },
              ...(canUpdate
                ? [
                    {
                      key: "edit",
                      label: "Modifier",
                      icon: Pencil,
                      tone: "primary" as const,
                      onClick: () => openEdit(row.original),
                    },
                  ]
                : []),
              ...(canManageCheckpoints
                ? [
                    {
                      key: "checkpoints",
                      label: "Checkpoints",
                      icon: MapPin,
                      tone: "accent" as const,
                      onClick: () => openCheckpoints(row.original),
                    },
                  ]
                : []),
              ...(canDelete
                ? [
                    {
                      key: "delete",
                      label: "Supprimer",
                      icon: Trash2,
                      tone: "danger" as const,
                      onClick: () => setToDelete(row.original),
                    },
                  ]
                : []),
            ]}
          />
        ),
      },
    ],
    [
      canUpdate,
      canDelete,
      canManageCheckpoints,
      openEdit,
      openCheckpoints,
      router,
    ],
  );

  return (
    <PermissionGate permission="sites.view" title="Sites">
      <div>
        <PageHeader
          title="Sites"
          description="Sites sous surveillance — client, zone et postes."
        />
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
            placeholder: "Rechercher un site…",
          }}
          toolbar={
            canCreate ? (
              <Button onClick={() => router.push("/sites/nouveau")}>
                <Plus className="size-4" />
                Nouveau site
              </Button>
            ) : null
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
          emptyTitle="Aucun site"
          emptyAction={
            canCreate ? (
              <Button size="sm" onClick={() => router.push("/sites/nouveau")}>
                <Plus className="size-4" />
                Nouveau site
              </Button>
            ) : undefined
          }
        />

        <Modal
          open={open}
          onClose={closeModal}
          preventClose={busy}
          size="xl"
          title="Modifier le site"
          description="Mettez à jour les informations du site et gérez ses postes."
          footer={
            <>
              <Button variant="secondary" onClick={closeModal} disabled={busy}>
                Annuler
              </Button>
              <Button type="submit" form="site-form" loading={busy}>
                {busy ? "Enregistrement…" : "Enregistrer"}
              </Button>
            </>
          }
        >
          <form
            id="site-form"
            className="space-y-3"
            noValidate
            onSubmit={handleSubmit(async (values) => {
              if (!editing) return;
              setFormError(null);
              const pendingPoste =
                Boolean(editingPosteId) ||
                editPosteDraft.nom.trim().length > 0;
              if (pendingPoste) {
                const ok = await flushPosteDraft();
                if (!ok) return;
              }
              const payload = {
                client_id: values.client_id,
                zone_id: values.zone_id,
                nom: values.nom,
                adresse: values.adresse || null,
                interne: Boolean(values.interne),
              };
              try {
                await updateSite.mutateAsync({ id: editing.id, payload });
                toast(
                  pendingPoste
                    ? "Site et poste enregistrés."
                    : "Site mis à jour avec succès.",
                );
                setEditPosteDraft(emptyPosteDraft);
                setEditingPosteId(null);
                setEditing(null);
                setOpen(false);
                reset(emptyDefaults);
              } catch (err) {
                const message = getApiErrorMessage(
                  err,
                  "Échec de la modification du site.",
                );
                setFormError(message);
                toast(message, "danger");
              }
            })}
          >
            <RequiredFieldsLegend className="mb-1" />

            <Input
              label="Nom du site"
              requiredMark
              placeholder="ex. Siège Orange CI"
              error={errors.nom?.message}
              {...register("nom")}
            />

            <div className="grid gap-3 sm:grid-cols-2">
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

            <Textarea
              label="Adresse"
              optionalMark
              rows={2}
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
                <span className="font-medium">Site interne (siège)</span>
                <span className="mt-0.5 block text-xs text-ink-muted">
                  Permet au rôle Opération de contrôler les agents présents
                  ici, sans périmètre contrôleur.
                </span>
              </span>
            </label>

            <div className="space-y-2 border-t border-border pt-3">
              <p className="text-sm font-medium text-ink">Postes du site</p>
              <p className="text-xs text-ink-muted">
                Nom, effectif requis et créneau horaire par défaut de chaque
                poste — sert de référence, pré-remplit l’horaire lors de la
                planification, mais chaque affectation peut être ajustée
                individuellement.
              </p>

              {sitePostesLoading ? (
                <div className="flex justify-center py-3">
                  <Spinner className="size-5" />
                </div>
              ) : sitePostes.length === 0 ? (
                <p className="text-sm text-ink-muted">
                  Aucun poste pour ce site.
                </p>
              ) : (
                <ul className="space-y-1.5">
                  {sitePostes.map((poste) => (
                    <li
                      key={poste.id}
                      className={`flex items-center gap-2 rounded-md border px-2.5 py-2 ${
                        editingPosteId === poste.id
                          ? "border-teal bg-teal/5"
                          : "border-border bg-paper-muted/40"
                      }`}
                    >
                      <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-medium text-ink">
                          {poste.nom}
                        </p>
                        <p className="text-xs text-ink-faint">
                          {poste.agents_requis} agent
                          {poste.agents_requis > 1 ? "s" : ""} ·{" "}
                          {poste.heure_debut_nuit && poste.heure_fin_nuit ? (
                            <>
                              jour {formatTimeDisplay(poste.heure_debut)} →{" "}
                              {formatTimeDisplay(poste.heure_fin)} · nuit{" "}
                              {formatTimeDisplay(poste.heure_debut_nuit)} →{" "}
                              {formatTimeDisplay(poste.heure_fin_nuit)}
                            </>
                          ) : (
                            <>
                              {formatTimeDisplay(poste.heure_debut)} →{" "}
                              {formatTimeDisplay(poste.heure_fin)}
                              {poste.heure_debut &&
                              poste.heure_fin &&
                              poste.heure_debut.slice(0, 5) ===
                                poste.heure_fin.slice(0, 5)
                                ? " · 24h"
                                : ""}
                            </>
                          )}
                        </p>
                      </div>
                      {canManagePostes ? (
                        <>
                          <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            aria-label="Modifier le poste"
                            onClick={() => startEditPoste(poste)}
                          >
                            <Pencil className="size-3.5 text-ink-muted" />
                          </Button>
                          <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            aria-label="Supprimer le poste"
                            onClick={() => setPosteToDelete(poste)}
                          >
                            <Trash2 className="size-3.5 text-danger" />
                          </Button>
                        </>
                      ) : null}
                    </li>
                  ))}
                </ul>
              )}

              {canManagePostes ? (
                <div className="space-y-3 rounded-lg border border-dashed border-border p-3">
                  <p className="text-xs font-medium text-ink-muted">
                    {editingPosteId ? "Modifier le poste" : "Ajouter un poste"}
                  </p>
                  <PosteDraftFields
                    nomRequired
                    nomId="edit-poste-nom"
                    effectifId="edit-poste-effectif"
                    values={{
                      nom: editPosteDraft.nom,
                      agents_requis: String(
                        editPosteDraft.agents_requis ?? "1",
                      ),
                      heure_debut: editPosteDraft.heure_debut ?? "",
                      heure_fin: editPosteDraft.heure_fin ?? "",
                      heure_debut_nuit:
                        editPosteDraft.heure_debut_nuit ?? "",
                      heure_fin_nuit: editPosteDraft.heure_fin_nuit ?? "",
                      mode_effectif:
                        editPosteDraft.mode_effectif === "alternance"
                          ? "alternance"
                          : "ensemble",
                    }}
                    onChange={setEditPosteDraft}
                  />

                  <div className="flex flex-wrap items-center gap-2">
                    {editingPosteId ? (
                      <>
                        <p className="flex-1 text-xs text-ink-muted">
                          Modifiez le poste puis cliquez sur{" "}
                          <span className="font-medium text-ink">
                            Enregistrer
                          </span>{" "}
                          en bas de la fenêtre.
                        </p>
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          disabled={addingPoste}
                          onClick={cancelEditPoste}
                        >
                          <X className="size-4" />
                          Annuler
                        </Button>
                      </>
                    ) : (
                      <Button
                        type="button"
                        size="sm"
                        loading={addingPoste}
                        onClick={() => void addEditPoste()}
                      >
                        <Plus className="size-4" />
                        Ajouter le poste
                      </Button>
                    )}
                  </div>
                </div>
              ) : null}
            </div>

            {formError ? <Alert tone="danger">{formError}</Alert> : null}
          </form>
        </Modal>

        <ConfirmDialog
          open={!!toDelete}
          onClose={() => {
            if (deleteSite.isPending) return;
            setToDelete(null);
          }}
          loading={deleteSite.isPending}
          title="Supprimer le site"
          description={
            toDelete ? `Confirmer la suppression de « ${toDelete.nom} » ?` : ""
          }
          onConfirm={async () => {
            if (!toDelete) return;
            try {
              await deleteSite.mutateAsync(toDelete.id);
              toast("Site supprimé.");
              setToDelete(null);
            } catch (err) {
              toast(
                getApiErrorMessage(err, "Échec de la suppression du site."),
                "danger",
              );
            }
          }}
        />

        <ConfirmDialog
          open={!!posteToDelete}
          onClose={() => {
            if (deletePoste.isPending) return;
            setPosteToDelete(null);
          }}
          loading={deletePoste.isPending}
          title="Supprimer le poste"
          description={
            posteToDelete
              ? `Confirmer la suppression du poste « ${posteToDelete.nom} » ?`
              : ""
          }
          onConfirm={async () => {
            if (!posteToDelete || !editing) return;
            try {
              await deletePoste.mutateAsync({
                siteId: editing.id,
                posteId: posteToDelete.id,
              });
              if (editingPosteId === posteToDelete.id) {
                cancelEditPoste();
              }
              toast("Poste supprimé.");
              setPosteToDelete(null);
            } catch (err) {
              toast(
                getApiErrorMessage(err, "Échec de la suppression du poste."),
                "danger",
              );
            }
          }}
        />

        <Modal
          open={!!checkpointSite}
          onClose={closeCheckpoints}
          preventClose={checkpointBusy || deleteCheckpoint.isPending}
          title="Checkpoints"
          description={
            checkpointSite
              ? `Points QR du site « ${checkpointSite.nom} » (historique).`
              : undefined
          }
          footer={
            <Button variant="secondary" onClick={closeCheckpoints}>
              Fermer
            </Button>
          }
        >
          <div className="space-y-4">
            {checkpointsLoading ? (
              <div className="flex justify-center py-4">
                <Spinner className="size-5" />
              </div>
            ) : (checkpointsData?.data ?? []).length === 0 ? (
              <p className="text-sm text-ink-muted">
                Aucun checkpoint pour ce site.
              </p>
            ) : (
              <ul className="space-y-1.5">
                {(checkpointsData?.data ?? []).map((cp) => (
                  <li
                    key={cp.id}
                    className="flex items-center justify-between gap-3 rounded-md border border-border bg-paper-muted/40 px-3 py-2"
                  >
                    <div className="min-w-0">
                      <p className="truncate text-sm font-medium text-ink">
                        {cp.nom}
                      </p>
                      <p className="font-mono text-xs text-ink-faint">
                        {cp.code_qr ?? "—"}
                      </p>
                    </div>
                    {canManageCheckpoints ? (
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        aria-label="Supprimer le checkpoint"
                        title="Supprimer le checkpoint"
                        className="text-danger hover:bg-danger/5 hover:text-danger"
                        onClick={() => setCheckpointToDelete(cp)}
                      >
                        <Trash2 className="size-3.5" />
                      </Button>
                    ) : null}
                  </li>
                ))}
              </ul>
            )}

            {canManageCheckpoints ? (
              <form
                className="space-y-3 border-t border-border pt-4"
                onSubmit={handleSubmitCheckpoint(async (values) => {
                  if (!checkpointSite) return;
                  setCheckpointFormError(null);
                  try {
                    await createCheckpoint.mutateAsync({
                      siteId: checkpointSite.id,
                      payload: {
                        nom: values.nom,
                        code_qr: values.code_qr || null,
                        latitude:
                          values.latitude === ""
                            ? null
                            : Number(values.latitude),
                        longitude:
                          values.longitude === ""
                            ? null
                            : Number(values.longitude),
                        ordre:
                          values.ordre === "" ? null : Number(values.ordre),
                      },
                    });
                    toast("Checkpoint ajouté.");
                    resetCheckpoint({
                      ...emptyCheckpointDefaults,
                      site_id: checkpointSite.id,
                    });
                  } catch (err) {
                    setCheckpointFormError(
                      getApiErrorMessage(
                        err,
                        "Échec de l’ajout du checkpoint.",
                      ),
                    );
                  }
                })}
              >
                <p className="text-xs font-semibold uppercase tracking-wide text-ink-muted">
                  Ajouter un checkpoint
                </p>
                {checkpointFormError ? (
                  <Alert tone="danger">{checkpointFormError}</Alert>
                ) : null}
                <input type="hidden" {...registerCheckpoint("site_id")} />
                <Input
                  label="Nom"
                  requiredMark
                  placeholder="ex. Porte arrière"
                  error={checkpointErrors.nom?.message}
                  {...registerCheckpoint("nom")}
                />
                <Input
                  label="Code QR"
                  optionalMark
                  hint="Généré automatiquement si laissé vide."
                  error={checkpointErrors.code_qr?.message}
                  {...registerCheckpoint("code_qr")}
                />
                <div className="grid gap-3 sm:grid-cols-3">
                  <Input
                    label="Latitude"
                    optionalMark
                    type="number"
                    step="any"
                    error={checkpointErrors.latitude?.message}
                    {...registerCheckpoint("latitude")}
                  />
                  <Input
                    label="Longitude"
                    optionalMark
                    type="number"
                    step="any"
                    error={checkpointErrors.longitude?.message}
                    {...registerCheckpoint("longitude")}
                  />
                  <Input
                    label="Ordre"
                    optionalMark
                    type="number"
                    min={0}
                    error={checkpointErrors.ordre?.message}
                    {...registerCheckpoint("ordre")}
                  />
                </div>
                <Button
                  type="submit"
                  loading={checkpointBusy}
                  className="w-full"
                >
                  <Plus className="size-4" />
                  {checkpointBusy ? "Ajout…" : "Ajouter le checkpoint"}
                </Button>
              </form>
            ) : null}
          </div>
        </Modal>

        <ConfirmDialog
          open={!!checkpointToDelete}
          onClose={() => {
            if (deleteCheckpoint.isPending) return;
            setCheckpointToDelete(null);
          }}
          loading={deleteCheckpoint.isPending}
          title="Supprimer le checkpoint"
          description={
            checkpointToDelete
              ? `Confirmer la suppression de « ${checkpointToDelete.nom} » ?`
              : ""
          }
          onConfirm={async () => {
            if (!checkpointToDelete || !checkpointSite) return;
            try {
              await deleteCheckpoint.mutateAsync({
                siteId: checkpointSite.id,
                checkpointId: checkpointToDelete.id,
              });
              toast("Checkpoint supprimé.");
              setCheckpointToDelete(null);
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
