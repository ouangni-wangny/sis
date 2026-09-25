"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { Pencil, Plus, Search, Trash2 } from "lucide-react";
import { systemApi } from "@/infrastructure/http/resources";
import type {
  SystemPermission,
  SystemRole,
} from "@/domain/types/entities";
import { Alert } from "@/presentation/components/ui/Alert";
import { Badge } from "@/presentation/components/ui/Badge";
import { Button } from "@/presentation/components/ui/Button";
import { ConfirmDialog } from "@/presentation/components/ui/ConfirmDialog";
import { EmptyState } from "@/presentation/components/ui/EmptyState";
import { FieldLabel } from "@/presentation/components/ui/FieldLabel";
import { Input } from "@/presentation/components/ui/Input";
import { Modal } from "@/presentation/components/ui/Modal";
import { Spinner } from "@/presentation/components/ui/Spinner";
import { useToast } from "@/presentation/providers/ToastProvider";
import { getApiErrorMessage } from "@/shared/lib/api-error";

export function PermissionsTab() {
  const [permissions, setPermissions] = useState<SystemPermission[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [query, setQuery] = useState("");

  useEffect(() => {
    (async () => {
      setLoading(true);
      setError(null);
      try {
        const res = await systemApi.permissions();
        setPermissions(res.data ?? []);
      } catch (err) {
        setError(
          getApiErrorMessage(err, "Impossible de charger les permissions."),
        );
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return permissions;
    return permissions.filter(
      (p) =>
        p.label.toLowerCase().includes(q) || p.name.toLowerCase().includes(q),
    );
  }, [permissions, query]);

  const byGroup = useMemo(() => {
    const map = new Map<string, SystemPermission[]>();
    for (const p of filtered) {
      map.set(p.group, [...(map.get(p.group) ?? []), p]);
    }
    return map;
  }, [filtered]);

  if (loading) {
    return (
      <div className="flex justify-center py-16">
        <Spinner className="size-6" />
      </div>
    );
  }

  if (error) return <Alert tone="danger">{error}</Alert>;

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div className="relative w-full max-w-xs">
          <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-faint" />
          <Input
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Rechercher une permission…"
            className="h-10 pl-9"
            aria-label="Rechercher une permission"
          />
        </div>
        <p className="text-xs text-ink-faint">
          {permissions.length} permission(s) au total
        </p>
      </div>

      {filtered.length === 0 ? (
        <EmptyState
          title="Aucune permission"
          description="Aucune permission ne correspond à cette recherche."
        />
      ) : (
        Array.from(byGroup.entries()).map(([group, perms]) => (
          <div
            key={group}
            className="overflow-hidden rounded-lg border border-border bg-white"
          >
            <div className="flex items-center justify-between gap-3 border-b border-border bg-paper-muted/40 px-4 py-2.5">
              <p className="text-xs font-semibold uppercase tracking-wide text-ink-muted">
                {group}
              </p>
              <span className="text-[11px] text-ink-faint">
                {perms.length} permission(s)
              </span>
            </div>
            <ul className="divide-y divide-border">
              {perms.map((p) => (
                <li
                  key={p.name}
                  className="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
                >
                  <div className="min-w-0">
                    <p className="font-medium text-ink">{p.label}</p>
                    <p className="mt-0.5 font-mono text-[11px] text-ink-faint">
                      {p.name}
                    </p>
                  </div>
                </li>
              ))}
            </ul>
          </div>
        ))
      )}
    </div>
  );
}

function RolePermissionChecklist({
  permissions,
  selected,
  onChange,
  disabled,
}: {
  permissions: SystemPermission[];
  selected: Set<string>;
  onChange: (next: Set<string>) => void;
  disabled?: boolean;
}) {
  const byGroup = useMemo(() => {
    const map = new Map<string, SystemPermission[]>();
    for (const p of permissions) {
      map.set(p.group, [...(map.get(p.group) ?? []), p]);
    }
    return map;
  }, [permissions]);

  const toggle = (name: string) => {
    const next = new Set(selected);
    if (next.has(name)) next.delete(name);
    else next.add(name);
    onChange(next);
  };

  const toggleGroup = (perms: SystemPermission[], checked: boolean) => {
    const next = new Set(selected);
    for (const p of perms) {
      if (checked) next.add(p.name);
      else next.delete(p.name);
    }
    onChange(next);
  };

  return (
    <div className="max-h-[min(50vh,420px)] space-y-3 overflow-y-auto pr-1">
      {Array.from(byGroup.entries()).map(([group, perms]) => {
        const allChecked = perms.every((p) => selected.has(p.name));
        return (
          <div key={group} className="overflow-hidden rounded-lg border border-border">
            <div className="flex items-center justify-between gap-3 border-b border-border bg-paper-muted/40 px-3 py-2">
              <p className="text-xs font-semibold uppercase tracking-wide text-ink-muted">
                {group}
              </p>
              <button
                type="button"
                disabled={disabled}
                className="text-xs font-medium text-teal-dark hover:underline disabled:opacity-50"
                onClick={() => toggleGroup(perms, !allChecked)}
              >
                {allChecked ? "Tout décocher" : "Tout cocher"}
              </button>
            </div>
            <ul className="divide-y divide-border">
              {perms.map((p) => (
                <li key={p.name} className="flex items-center gap-3 px-3 py-2">
                  <input
                    type="checkbox"
                    id={`perm-${p.name}`}
                    disabled={disabled}
                    checked={selected.has(p.name)}
                    onChange={() => toggle(p.name)}
                    className="size-4 shrink-0 rounded border-border text-teal focus:ring-2 focus:ring-teal/20"
                  />
                  <label
                    htmlFor={`perm-${p.name}`}
                    className="min-w-0 flex-1 cursor-pointer text-sm text-ink"
                  >
                    {p.label}
                    <span className="ml-2 font-mono text-[11px] text-ink-faint">
                      {p.name}
                    </span>
                  </label>
                </li>
              ))}
            </ul>
          </div>
        );
      })}
    </div>
  );
}

function RoleFormModal({
  open,
  onClose,
  role,
  permissions,
  onSaved,
}: {
  open: boolean;
  onClose: () => void;
  role: SystemRole | null;
  permissions: SystemPermission[];
  onSaved: (role: SystemRole) => void;
}) {
  const { toast } = useToast();
  const [name, setName] = useState("");
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!open) return;
    setName(role?.name ?? "");
    setSelected(new Set(role?.permissions ?? []));
    setError(null);
  }, [open, role]);

  const isEdit = !!role;
  const nameLocked = !!role?.protected;

  const submit = async () => {
    setSaving(true);
    setError(null);
    try {
      const res = isEdit
        ? await systemApi.updateRole(role.id, {
            ...(nameLocked ? {} : { name: name.trim() }),
            permissions: Array.from(selected),
          })
        : await systemApi.createRole({
            name: name.trim(),
            permissions: Array.from(selected),
          });
      onSaved(res.data);
      toast(
        isEdit
          ? `Rôle « ${res.data.name} » mis à jour.`
          : `Rôle « ${res.data.name} » créé.`,
      );
      onClose();
    } catch (err) {
      setError(getApiErrorMessage(err, "Échec de l’enregistrement du rôle."));
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      open={open}
      onClose={() => {
        if (!saving) onClose();
      }}
      preventClose={saving}
      title={isEdit ? `Modifier le rôle « ${role.name} »` : "Nouveau rôle"}
      description="Sélectionnez les permissions accordées à ce rôle."
      size="lg"
      scrollable={false}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={saving}>
            Annuler
          </Button>
          <Button
            onClick={() => void submit()}
            loading={saving}
            disabled={!name.trim()}
          >
            {saving ? "Enregistrement…" : "Enregistrer"}
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        {error ? <Alert tone="danger">{error}</Alert> : null}
        <div className="space-y-1.5">
          <FieldLabel htmlFor="role-name" required>
            Nom du rôle
          </FieldLabel>
          <Input
            id="role-name"
            value={name}
            onChange={(e) => setName(e.target.value)}
            disabled={nameLocked}
            placeholder="ex : chef-projet"
          />
          <p className="text-[11px] text-ink-faint">
            {nameLocked
              ? "Ce rôle est intégré au système : le nom ne peut pas être modifié."
              : "Minuscules et tirets uniquement (ex : chef-projet)."}
          </p>
        </div>
        <RolePermissionChecklist
          permissions={permissions}
          selected={selected}
          onChange={setSelected}
          disabled={saving}
        />
      </div>
    </Modal>
  );
}

export function RolesTab() {
  const { toast } = useToast();
  const [roles, setRoles] = useState<SystemRole[]>([]);
  const [permissions, setPermissions] = useState<SystemPermission[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [formOpen, setFormOpen] = useState(false);
  const [editingRole, setEditingRole] = useState<SystemRole | null>(null);
  const [pendingDelete, setPendingDelete] = useState<SystemRole | null>(null);
  const [deleting, setDeleting] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [rolesRes, permsRes] = await Promise.all([
        systemApi.roles(),
        systemApi.permissions(),
      ]);
      setRoles(rolesRes.data ?? []);
      setPermissions(permsRes.data ?? []);
    } catch (err) {
      setError(getApiErrorMessage(err, "Impossible de charger les rôles."));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const openCreate = () => {
    setEditingRole(null);
    setFormOpen(true);
  };

  const openEdit = (role: SystemRole) => {
    setEditingRole(role);
    setFormOpen(true);
  };

  const handleSaved = (role: SystemRole) => {
    setRoles((prev) => {
      const exists = prev.some((r) => r.id === role.id);
      const next = exists
        ? prev.map((r) => (r.id === role.id ? role : r))
        : [...prev, role];
      return next.sort((a, b) => a.name.localeCompare(b.name));
    });
  };

  if (loading) {
    return (
      <div className="flex justify-center py-16">
        <Spinner className="size-6" />
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {error ? <Alert tone="danger">{error}</Alert> : null}

      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="text-sm text-ink-muted">
          {roles.length} rôle(s) — assignez des permissions précises à chaque
          profil métier.
        </p>
        <Button onClick={openCreate}>
          <Plus className="size-4" />
          Nouveau rôle
        </Button>
      </div>

      {roles.length === 0 ? (
        <EmptyState
          title="Aucun rôle"
          description="Créez un rôle pour regrouper un ensemble de permissions."
        />
      ) : (
        <div className="grid gap-3 sm:grid-cols-2">
          {roles.map((role) => (
            <div
              key={role.id}
              className="flex flex-col gap-3 rounded-lg border border-border bg-white p-4"
            >
              <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                  <div className="flex flex-wrap items-center gap-2">
                    <p className="font-mono text-sm font-semibold text-ink">
                      {role.name}
                    </p>
                    {role.protected ? <Badge tone="info">Système</Badge> : null}
                  </div>
                  <p className="mt-0.5 text-xs text-ink-faint">
                    {role.permissions.length} permission(s) ·{" "}
                    {role.users_count} utilisateur(s)
                  </p>
                </div>
              </div>

              <div className="flex flex-wrap gap-1">
                {role.permissions.length === 0 ? (
                  <span className="text-xs text-ink-faint">
                    Aucune permission
                  </span>
                ) : (
                  role.permissions
                    .slice(0, 6)
                    .map((p) => (
                      <Badge key={p} tone="neutral">
                        {p}
                      </Badge>
                    ))
                )}
                {role.permissions.length > 6 ? (
                  <Badge tone="neutral">+{role.permissions.length - 6}</Badge>
                ) : null}
              </div>

              <div className="mt-auto flex items-center justify-end gap-2 pt-2">
                <Button
                  variant="secondary"
                  size="sm"
                  onClick={() => openEdit(role)}
                >
                  <Pencil className="size-3.5" />
                  Permissions
                </Button>
                <Button
                  variant="danger"
                  size="sm"
                  disabled={role.protected || role.users_count > 0}
                  title={
                    role.protected
                      ? "Rôle système : suppression impossible"
                      : role.users_count > 0
                        ? "Réassignez les utilisateurs avant suppression"
                        : undefined
                  }
                  onClick={() => setPendingDelete(role)}
                >
                  <Trash2 className="size-3.5" />
                  Supprimer
                </Button>
              </div>
            </div>
          ))}
        </div>
      )}

      <RoleFormModal
        open={formOpen}
        onClose={() => setFormOpen(false)}
        role={editingRole}
        permissions={permissions}
        onSaved={handleSaved}
      />

      <ConfirmDialog
        open={!!pendingDelete}
        onClose={() => {
          if (!deleting) setPendingDelete(null);
        }}
        loading={deleting}
        title="Supprimer le rôle"
        confirmLabel="Supprimer"
        description={
          pendingDelete
            ? `Le rôle « ${pendingDelete.name} » sera définitivement supprimé.`
            : ""
        }
        onConfirm={async () => {
          if (!pendingDelete) return;
          setDeleting(true);
          try {
            await systemApi.deleteRole(pendingDelete.id);
            setRoles((prev) => prev.filter((r) => r.id !== pendingDelete.id));
            toast(`Rôle « ${pendingDelete.name} » supprimé.`);
            setPendingDelete(null);
          } catch (err) {
            toast(
              getApiErrorMessage(err, "Échec de la suppression du rôle."),
              "danger",
            );
          } finally {
            setDeleting(false);
          }
        }}
      />
    </div>
  );
}
