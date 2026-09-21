"use client";

import { useCallback, useMemo, useState } from "react";
import Link from "next/link";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { type ColumnDef } from "@tanstack/react-table";
import { Plus } from "lucide-react";
import { useDebouncedValue } from "@/application/hooks/useDebouncedValue";
import { useAgents } from "@/application/hooks/useResources";
import {
  useCreateZone,
  useDeleteZone,
  useSyncZoneControleurs,
  useUpdateZone,
  useZones,
} from "@/application/hooks/useZones";
import { zoneSchema, type ZoneFormValues } from "@/domain/schemas/zone";
import type { Zone } from "@/domain/types/entities";
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
import { Textarea } from "@/presentation/components/ui/Textarea";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { useToast } from "@/presentation/providers/ToastProvider";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { can } from "@/shared/lib/can";

const emptyDefaults: ZoneFormValues = { nom: "", description: "" };

export default function ZonesPage() {
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [q, setQ] = useState("");
  const search = useDebouncedValue(q);
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<Zone | null>(null);
  const [toDelete, setToDelete] = useState<Zone | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const [controleur1, setControleur1] = useState("");
  const [controleur2, setControleur2] = useState("");
  const { toast } = useToast();
  const { user } = useAuth();
  const canCreate = can(user, ["zones.create", "zones.manage"]);
  const canUpdate = can(user, ["zones.update", "zones.manage"]);
  const canDelete = can(user, ["zones.delete", "zones.manage"]);

  const { data, isLoading } = useZones({
    page,
    per_page: perPage,
    q: search || undefined,
  });
  const { data: controleursData } = useAgents({
    all: true,
    type: "controleur",
    contrat_valide: true,
  });
  const createZone = useCreateZone();
  const updateZone = useUpdateZone();
  const syncControleurs = useSyncZoneControleurs();
  const deleteZone = useDeleteZone();

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<ZoneFormValues>({
    resolver: zodResolver(zoneSchema),
    mode: "onBlur",
    reValidateMode: "onChange",
    defaultValues: emptyDefaults,
  });

  const busy =
    isSubmitting ||
    createZone.isPending ||
    updateZone.isPending ||
    syncControleurs.isPending;

  const assignedElsewhere = useMemo(() => {
    const map = new Map<string, string>();
    for (const zone of data?.data ?? []) {
      if (editing && zone.id === editing.id) continue;
      for (const c of zone.controleurs ?? []) {
        map.set(c.id, zone.nom);
      }
    }
    return map;
  }, [data, editing]);

  const controleurOptions = useMemo(() => {
    return (controleursData?.data ?? [])
      .filter(
        (a) => a.statut !== "archive" && a.statut !== "suspendu",
      )
      .map((a) => {
        const elsewhere = assignedElsewhere.get(a.id);
        return {
          value: a.id,
          label: elsewhere
            ? `${a.prenom} ${a.nom} (${a.matricule}) — ${elsewhere}`
            : `${a.prenom} ${a.nom} (${a.matricule})`,
        };
      });
  }, [controleursData, assignedElsewhere]);

  const optionsControleur1 = useMemo(
    () => controleurOptions.filter((o) => o.value !== controleur2),
    [controleurOptions, controleur2],
  );
  const optionsControleur2 = useMemo(
    () => controleurOptions.filter((o) => o.value !== controleur1),
    [controleurOptions, controleur1],
  );

  const openEdit = useCallback(
    (zone: Zone) => {
      if (!canUpdate) {
        toast("Vous n’avez pas le droit de modifier une zone.", "danger");
        return;
      }
      setEditing(zone);
      setFormError(null);
      const ids = (zone.controleurs ?? []).map((c) => c.id);
      setControleur1(ids[0] ?? "");
      setControleur2(ids[1] ?? "");
      reset({
        nom: zone.nom,
        description: zone.description ?? "",
      });
      setOpen(true);
    },
    [canUpdate, reset, toast],
  );

  const columns = useMemo<ColumnDef<Zone>[]>(
    () => [
      {
        accessorKey: "nom",
        header: "Nom",
        cell: ({ row }) => (
          <span className="font-medium">{row.original.nom}</span>
        ),
      },
      {
        accessorKey: "description",
        header: "Description",
        cell: ({ getValue }) => (
          <span className="line-clamp-2 max-w-md text-ink-muted">
            {(getValue() as string | null) || "—"}
          </span>
        ),
      },
      {
        id: "controleurs",
        header: "Contrôleurs",
        cell: ({ row }) => {
          const list = row.original.controleurs ?? [];
          if (!list.length) {
            return <span className="text-ink-faint">Aucun</span>;
          }
          return (
            <div className="flex flex-col gap-0.5">
              {list.map((c) => (
                <span key={c.id} className="text-sm">
                  {c.prenom} {c.nom}
                </span>
              ))}
            </div>
          );
        },
      },
      {
        accessorKey: "sites_count",
        header: "Sites",
        cell: ({ getValue }) => (
          <span className="font-mono tabular-nums">
            {Number(getValue() ?? 0)}
          </span>
        ),
      },
      {
        id: "actions",
        header: "Actions",
        enableSorting: false,
        cell: ({ row }) => (
          <TableActions
            canEdit={canUpdate}
            canDelete={canDelete}
            onEdit={() => openEdit(row.original)}
            onDelete={() => setToDelete(row.original)}
          />
        ),
      },
    ],
    [canUpdate, canDelete, openEdit],
  );

  const closeModal = () => {
    if (busy) return;
    setOpen(false);
    setEditing(null);
    setFormError(null);
    setControleur1("");
    setControleur2("");
    reset(emptyDefaults);
    createZone.reset();
    updateZone.reset();
    syncControleurs.reset();
  };

  const openCreate = () => {
    if (!canCreate) {
      toast("Vous n’avez pas le droit de créer une zone.", "danger");
      return;
    }
    setEditing(null);
    setControleur1("");
    setControleur2("");
    reset(emptyDefaults);
    setFormError(null);
    setOpen(true);
  };

  return (
    <PermissionGate permission={["zones.view", "zones.manage"]} title="Zones">
      <div>
        <PageHeader
          title="Zones"
          description="Découpage géographique — jusqu’à 2 contrôleurs par zone."
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
            placeholder: "Rechercher une zone…",
          }}
          toolbar={
            canCreate ? (
              <Button onClick={openCreate}>
                <Plus className="size-4" />
                Nouvelle zone
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
          emptyTitle="Aucune zone"
          emptyDescription="Ajoutez une zone pour organiser vos sites et contrôleurs."
          emptyAction={
            canCreate ? (
              <Button size="sm" onClick={openCreate}>
                <Plus className="size-4" />
                Nouvelle zone
              </Button>
            ) : undefined
          }
        />

        <Modal
          open={open}
          onClose={closeModal}
          preventClose={busy}
          title={editing ? "Modifier la zone" : "Nouvelle zone"}
          description="Nommez la zone et assignez jusqu’à 2 contrôleurs."
          footer={
            <>
              <Button variant="secondary" onClick={closeModal} disabled={busy}>
                Annuler
              </Button>
              <Button type="submit" form="zone-form" loading={busy}>
                {busy ? "Enregistrement…" : "Enregistrer"}
              </Button>
            </>
          }
        >
          <form
            id="zone-form"
            className="space-y-3"
            noValidate
            onSubmit={handleSubmit(async (values) => {
              setFormError(null);
              if (controleur1 && controleur2 && controleur1 === controleur2) {
                setFormError("Choisissez deux contrôleurs distincts.");
                return;
              }
              const payload = {
                nom: values.nom,
                description: values.description || undefined,
              };
              const agentIds = [controleur1, controleur2].filter(Boolean);
              try {
                if (editing) {
                  await updateZone.mutateAsync({
                    id: editing.id,
                    payload,
                  });
                  await syncControleurs.mutateAsync({
                    id: editing.id,
                    agentIds,
                  });
                  toast("Zone mise à jour avec succès.");
                } else {
                  const created = await createZone.mutateAsync(payload);
                  if (agentIds.length) {
                    await syncControleurs.mutateAsync({
                      id: created.data.id,
                      agentIds,
                    });
                  }
                  toast("Zone créée avec succès.");
                }
                reset(emptyDefaults);
                setControleur1("");
                setControleur2("");
                setEditing(null);
                setOpen(false);
              } catch (err) {
                const message = getApiErrorMessage(
                  err,
                  editing
                    ? "Échec de la modification de la zone."
                    : "Échec de la création de la zone.",
                );
                setFormError(message);
                toast(message, "danger");
              }
            })}
          >
            <RequiredFieldsLegend className="mb-1" />
            <Input
              label="Nom"
              requiredMark
              placeholder="ex. Abidjan Centre"
              error={errors.nom?.message}
              {...register("nom")}
            />
            <Textarea
              label="Description"
              optionalMark
              placeholder="Quartiers / périmètre couvert…"
              hint="Facultatif — utile pour les équipes terrain."
              error={errors.description?.message}
              {...register("description")}
            />
            <p className="pt-1 text-xs font-semibold uppercase tracking-wide text-ink-muted">
              Contrôleurs
            </p>
            <div className="grid gap-3 sm:grid-cols-2">
              <Select
                label="Contrôleur 1"
                optionalMark
                searchable
                placeholder="Aucun"
                options={[
                  { value: "", label: "Aucun" },
                  ...optionsControleur1,
                ]}
                value={controleur1}
                onChange={(e) => setControleur1(e.target.value)}
              />
              <Select
                label="Contrôleur 2"
                optionalMark
                searchable
                placeholder="Aucun"
                options={[
                  { value: "", label: "Aucun" },
                  ...optionsControleur2,
                ]}
                value={controleur2}
                onChange={(e) => setControleur2(e.target.value)}
              />
            </div>
            <p className="text-xs text-ink-muted">
              Affectation à la zone uniquement. Le planning des contrôleurs se
              gère dans{" "}
              <Link
                href="/planning-controleurs"
                className="text-teal hover:underline"
              >
                Planning contrôleurs
              </Link>
              .
            </p>
            {formError ? <Alert tone="danger">{formError}</Alert> : null}
          </form>
        </Modal>

        <ConfirmDialog
          open={!!toDelete}
          onClose={() => {
            if (deleteZone.isPending) return;
            setToDelete(null);
          }}
          loading={deleteZone.isPending}
          title="Supprimer la zone"
          description={
            toDelete
              ? `Confirmer la suppression de « ${toDelete.nom} » ?`
              : ""
          }
          onConfirm={async () => {
            if (!toDelete) return;
            try {
              await deleteZone.mutateAsync(toDelete.id);
              toast("Zone supprimée.");
              setToDelete(null);
            } catch (err) {
              toast(
                getApiErrorMessage(err, "Échec de la suppression de la zone."),
                "danger",
              );
            }
          }}
        />
      </div>
    </PermissionGate>
  );
}
