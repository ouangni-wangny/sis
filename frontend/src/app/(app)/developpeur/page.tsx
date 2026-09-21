"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { Code2, Pencil, Plus, RotateCcw, Search, Trash2 } from "lucide-react";
import { systemApi } from "@/infrastructure/http/resources";
import type {
  FeatureFlag,
  MenuOverride,
  SystemHistoryEntry,
  SystemPermission,
  SystemRole,
  SystemSetting,
} from "@/domain/types/entities";
import { PermissionGate } from "@/presentation/components/auth/PermissionGate";
import { Alert } from "@/presentation/components/ui/Alert";
import { Badge } from "@/presentation/components/ui/Badge";
import { Button } from "@/presentation/components/ui/Button";
import { ConfirmDialog } from "@/presentation/components/ui/ConfirmDialog";
import { EmptyState } from "@/presentation/components/ui/EmptyState";
import { FieldLabel } from "@/presentation/components/ui/FieldLabel";
import { Input } from "@/presentation/components/ui/Input";
import { Modal } from "@/presentation/components/ui/Modal";
import { PageHeader } from "@/presentation/components/ui/PageHeader";
import { Spinner } from "@/presentation/components/ui/Spinner";
import { TabPanel, Tabs } from "@/presentation/components/ui/Tabs";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { useToast } from "@/presentation/providers/ToastProvider";
import { flatNavigationItems } from "@/shared/config/navigation";
import { getApiErrorMessage } from "@/shared/lib/api-error";
import { cn } from "@/shared/lib/cn";
import { formatDateTime } from "@/shared/lib/format";

type DevTab =
  | "modules"
  | "menus"
  | "permissions"
  | "roles"
  | "parametres"
  | "historique";

type MenuRow = { nav_key: string; label: string; visible: boolean };

function Toggle({
  checked,
  onChange,
  busy,
  label,
}: {
  checked: boolean;
  onChange: () => void;
  busy?: boolean;
  label: string;
}) {
  return (
    <button
      type="button"
      disabled={busy}
      onClick={onChange}
      className={cn(
        "relative h-7 w-12 shrink-0 rounded-full transition",
        checked ? "bg-teal" : "bg-border",
        busy && "opacity-60",
      )}
      aria-label={label}
    >
      <span
        className={cn(
          "absolute top-0.5 size-6 rounded-full bg-white shadow transition",
          checked ? "left-5" : "left-0.5",
        )}
      />
    </button>
  );
}

function ModulesTab({
  flags,
  busyKey,
  busyGroup,
  onToggleFlag,
  onToggleGroup,
}: {
  flags: FeatureFlag[];
  busyKey: string | null;
  busyGroup: string | null;
  onToggleFlag: (flag: FeatureFlag) => void;
  onToggleGroup: (group: string, enabled: boolean) => void;
}) {
  const [query, setQuery] = useState("");
  const [groupFilter, setGroupFilter] = useState<string | null>(null);

  const groups = useMemo(
    () =>
      Array.from(new Set(flags.map((f) => f.group ?? "Autre"))).sort(),
    [flags],
  );

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    return flags.filter((f) => {
      if (groupFilter && (f.group ?? "Autre") !== groupFilter) return false;
      if (!q) return true;
      return (
        f.label.toLowerCase().includes(q) || f.key.toLowerCase().includes(q)
      );
    });
  }, [flags, query, groupFilter]);

  const byGroup = useMemo(() => {
    const map = new Map<string, FeatureFlag[]>();
    for (const flag of filtered) {
      const g = flag.group ?? "Autre";
      map.set(g, [...(map.get(g) ?? []), flag]);
    }
    return map;
  }, [filtered]);

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-2">
        <div className="relative w-full max-w-xs">
          <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-faint" />
          <Input
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Rechercher un module…"
            className="h-10 pl-9"
            aria-label="Rechercher un module"
          />
        </div>
        <div className="flex flex-wrap gap-1.5">
          <button
            type="button"
            onClick={() => setGroupFilter(null)}
            className={cn(
              "rounded-full border px-3 py-1 text-xs font-medium transition",
              groupFilter === null
                ? "border-teal bg-teal/10 text-teal-dark"
                : "border-border text-ink-muted hover:bg-paper-muted",
            )}
          >
            Tous
          </button>
          {groups.map((g) => (
            <button
              key={g}
              type="button"
              onClick={() => setGroupFilter(g)}
              className={cn(
                "rounded-full border px-3 py-1 text-xs font-medium transition",
                groupFilter === g
                  ? "border-teal bg-teal/10 text-teal-dark"
                  : "border-border text-ink-muted hover:bg-paper-muted",
              )}
            >
              {g}
            </button>
          ))}
        </div>
      </div>

      {filtered.length === 0 ? (
        <EmptyState
          title="Aucun module"
          description="Aucun module ne correspond à cette recherche."
        />
      ) : (
        Array.from(byGroup.entries()).map(([group, groupFlags]) => {
          const allEnabled = groupFlags.every((f) => f.enabled);
          return (
            <div
              key={group}
              className="overflow-hidden rounded-lg border border-border bg-white"
            >
              <div className="flex items-center justify-between gap-3 border-b border-border bg-paper-muted/40 px-4 py-2.5">
                <p className="text-xs font-semibold uppercase tracking-wide text-ink-muted">
                  {group}
                </p>
                <button
                  type="button"
                  disabled={busyGroup === group}
                  onClick={() => onToggleGroup(group, !allEnabled)}
                  className={cn(
                    "text-xs font-medium text-teal-dark hover:underline disabled:opacity-50",
                  )}
                >
                  {busyGroup === group
                    ? "…"
                    : allEnabled
                      ? "Tout désactiver"
                      : "Tout activer"}
                </button>
              </div>
              <ul className="divide-y divide-border">
                {groupFlags.map((flag) => (
                  <li
                    key={flag.key}
                    className="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
                  >
                    <div className="min-w-0">
                      <div className="flex flex-wrap items-center gap-2">
                        <p className="font-medium text-ink">{flag.label}</p>
                        <Badge tone={flag.enabled ? "success" : "neutral"}>
                          {flag.enabled ? "Actif" : "Off"}
                        </Badge>
                        {flag.critical ? (
                          <Badge tone="critical">Critique</Badge>
                        ) : null}
                      </div>
                      <p className="mt-0.5 font-mono text-[11px] text-ink-faint">
                        {flag.key}
                      </p>
                      {flag.description ? (
                        <p className="mt-1 text-sm text-ink-muted">
                          {flag.description}
                        </p>
                      ) : null}
                      {flag.updated_by ? (
                        <p className="mt-1 text-[11px] text-ink-faint">
                          Modifié par {flag.updated_by.prenom}{" "}
                          {flag.updated_by.nom} · {formatDateTime(flag.updated_at)}
                        </p>
                      ) : null}
                    </div>
                    <Toggle
                      checked={flag.enabled}
                      busy={busyKey === flag.key}
                      onChange={() => onToggleFlag(flag)}
                      label={
                        flag.enabled
                          ? `Désactiver ${flag.label}`
                          : `Activer ${flag.label}`
                      }
                    />
                  </li>
                ))}
              </ul>
            </div>
          );
        })
      )}
    </div>
  );
}

function MenusTab({
  rows,
  busyKey,
  onToggle,
}: {
  rows: MenuRow[];
  busyKey: string | null;
  onToggle: (navKey: string, visible: boolean) => void;
}) {
  const [query, setQuery] = useState("");

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return rows;
    return rows.filter(
      (r) =>
        r.label.toLowerCase().includes(q) ||
        r.nav_key.toLowerCase().includes(q),
    );
  }, [rows, query]);

  return (
    <div className="space-y-4">
      <Alert tone="info">
        Masquer un menu ne retire pas les permissions API : couplez avec le
        flag module correspondant pour couper aussi le backend.
      </Alert>

      <div className="relative w-full max-w-xs">
        <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-faint" />
        <Input
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder="Rechercher une entrée de menu…"
          className="h-10 pl-9"
          aria-label="Rechercher une entrée de menu"
        />
      </div>

      <div className="overflow-hidden rounded-lg border border-border bg-white">
        <ul className="divide-y divide-border">
          {filtered.map((row) => (
            <li
              key={row.nav_key}
              className="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
            >
              <div>
                <p className="font-medium text-ink">{row.label}</p>
                <p className="font-mono text-[11px] text-ink-faint">
                  {row.nav_key}
                </p>
              </div>
              <Toggle
                checked={row.visible}
                busy={busyKey === row.nav_key}
                onChange={() => onToggle(row.nav_key, !row.visible)}
                label={
                  row.visible ? `Masquer ${row.label}` : `Afficher ${row.label}`
                }
              />
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
}

function settingInputValue(setting: SystemSetting): string {
  if (setting.value === null || setting.value === undefined) return "";
  return String(setting.value);
}

function ParametresTab() {
  const { toast } = useToast();
  const [settings, setSettings] = useState<SystemSetting[]>([]);
  const [draft, setDraft] = useState<Record<string, string | boolean>>({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [resetOpen, setResetOpen] = useState(false);
  const [resetting, setResetting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await systemApi.settings();
      const rows = res.data ?? [];
      setSettings(rows);
      setDraft(
        Object.fromEntries(
          rows.map((s) => [
            s.key,
            s.type === "boolean" ? Boolean(s.value) : settingInputValue(s),
          ]),
        ),
      );
    } catch (err) {
      setError(
        getApiErrorMessage(err, "Impossible de charger les paramètres."),
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const dirty = useMemo(
    () =>
      settings.some((s) => {
        const current = draft[s.key];
        if (s.type === "boolean") return Boolean(s.value) !== current;
        return settingInputValue(s) !== current;
      }),
    [settings, draft],
  );

  const byGroup = useMemo(() => {
    const map = new Map<string, SystemSetting[]>();
    for (const s of settings) {
      map.set(s.group, [...(map.get(s.group) ?? []), s]);
    }
    return map;
  }, [settings]);

  const save = async () => {
    setSaving(true);
    try {
      const payload = settings.map((s) => ({
        key: s.key,
        value: draft[s.key] ?? null,
      }));
      const res = await systemApi.updateSettings(payload);
      const rows = res.data ?? [];
      setSettings(rows);
      setDraft(
        Object.fromEntries(
          rows.map((s) => [
            s.key,
            s.type === "boolean" ? Boolean(s.value) : settingInputValue(s),
          ]),
        ),
      );
      toast("Paramètres enregistrés.");
    } catch (err) {
      toast(getApiErrorMessage(err, "Échec de l’enregistrement."), "danger");
    } finally {
      setSaving(false);
    }
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
      <Alert tone="info">
        Ces réglages surchargent la configuration par défaut (config/sis.php)
        sans redéploiement.
      </Alert>

      {Array.from(byGroup.entries()).map(([group, rows]) => (
        <div
          key={group}
          className="overflow-hidden rounded-lg border border-border bg-white"
        >
          <div className="border-b border-border bg-paper-muted/40 px-4 py-2.5">
            <p className="text-xs font-semibold uppercase tracking-wide text-ink-muted">
              {group}
            </p>
          </div>
          <ul className="divide-y divide-border">
            {rows.map((setting) => (
              <li
                key={setting.key}
                className="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
              >
                <div className="min-w-0 max-w-sm">
                  <div className="flex items-center gap-2">
                    <p className="font-medium text-ink">{setting.label}</p>
                    {setting.overridden ? (
                      <Badge tone="info">Surchargé</Badge>
                    ) : null}
                  </div>
                  <p className="mt-1 text-sm text-ink-muted">
                    {setting.description}
                  </p>
                </div>
                {setting.type === "boolean" ? (
                  <Toggle
                    checked={Boolean(draft[setting.key])}
                    onChange={() =>
                      setDraft((prev) => ({
                        ...prev,
                        [setting.key]: !prev[setting.key],
                      }))
                    }
                    label={setting.label}
                  />
                ) : (
                  <Input
                    className="w-48"
                    type={
                      setting.type === "integer" || setting.type === "float"
                        ? "number"
                        : "text"
                    }
                    step={setting.type === "float" ? "0.1" : undefined}
                    value={String(draft[setting.key] ?? "")}
                    onChange={(e) =>
                      setDraft((prev) => ({
                        ...prev,
                        [setting.key]: e.target.value,
                      }))
                    }
                  />
                )}
              </li>
            ))}
          </ul>
        </div>
      ))}

      <div className="flex items-center justify-between">
        <Button
          variant="secondary"
          onClick={() => setResetOpen(true)}
          disabled={saving}
        >
          <RotateCcw className="size-4" />
          Réinitialiser aux valeurs par défaut
        </Button>
        <Button onClick={() => void save()} disabled={!dirty} loading={saving}>
          {saving ? "Enregistrement…" : "Enregistrer"}
        </Button>
      </div>

      <ConfirmDialog
        open={resetOpen}
        onClose={() => {
          if (resetting) return;
          setResetOpen(false);
        }}
        loading={resetting}
        title="Réinitialiser les paramètres"
        confirmLabel="Réinitialiser"
        description="Tous les réglages système reviendront à leur valeur par défaut (config/sis.php)."
        onConfirm={async () => {
          setResetting(true);
          try {
            await systemApi.resetSettings();
            await load();
            toast("Paramètres réinitialisés.");
            setResetOpen(false);
          } catch (err) {
            toast(getApiErrorMessage(err, "Échec de la réinitialisation."), "danger");
          } finally {
            setResetting(false);
          }
        }}
      />
    </div>
  );
}

function PermissionsTab() {
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

function RolesTab() {
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

const HISTORY_ACTION_TONE: Record<string, "success" | "warning" | "danger"> = {
  created: "success",
  updated: "warning",
  deleted: "danger",
};

const HISTORY_ACTION_LABEL: Record<string, string> = {
  created: "Création",
  updated: "Modification",
  deleted: "Suppression",
};

const HISTORY_ENTITY_LABEL: Record<string, string> = {
  FeatureFlag: "Module",
  MenuOverride: "Menu",
  SystemSetting: "Paramètre",
};

function historyDiff(entry: SystemHistoryEntry): string[] {
  const ancien = entry.ancien ?? {};
  const nouveau = entry.nouveau ?? {};
  const keys = new Set([...Object.keys(ancien), ...Object.keys(nouveau)]);
  keys.delete("updated_at");
  keys.delete("created_at");
  keys.delete("updated_by");

  return Array.from(keys)
    .filter((k) => JSON.stringify(ancien[k]) !== JSON.stringify(nouveau[k]))
    .map((k) => {
      const before = ancien[k];
      const after = nouveau[k];
      if (entry.action === "created") return `${k}: ${JSON.stringify(after)}`;
      if (entry.action === "deleted") return `${k}: ${JSON.stringify(before)}`;
      return `${k}: ${JSON.stringify(before)} → ${JSON.stringify(after)}`;
    });
}

function HistoriqueTab() {
  const [entries, setEntries] = useState<SystemHistoryEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    (async () => {
      setLoading(true);
      setError(null);
      try {
        const res = await systemApi.history();
        setEntries(res.data ?? []);
      } catch (err) {
        setError(
          getApiErrorMessage(err, "Impossible de charger l’historique."),
        );
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  if (loading) {
    return (
      <div className="flex justify-center py-16">
        <Spinner className="size-6" />
      </div>
    );
  }

  if (error) return <Alert tone="danger">{error}</Alert>;

  if (entries.length === 0) {
    return (
      <EmptyState
        title="Aucun historique"
        description="Aucun changement de module, menu ou paramètre pour le moment."
      />
    );
  }

  return (
    <div className="overflow-hidden rounded-lg border border-border bg-white">
      <ul className="divide-y divide-border">
        {entries.map((entry) => (
          <li key={entry.id} className="px-4 py-3">
            <div className="flex flex-wrap items-center gap-2">
              <Badge tone={HISTORY_ACTION_TONE[entry.action] ?? "neutral"}>
                {HISTORY_ACTION_LABEL[entry.action] ?? entry.action}
              </Badge>
              <span className="text-sm font-medium text-ink">
                {HISTORY_ENTITY_LABEL[entry.auditable_type] ??
                  entry.auditable_type}
              </span>
              <span className="text-xs text-ink-faint">
                {formatDateTime(entry.created_at)}
              </span>
            </div>
            <div className="mt-1 space-y-0.5 font-mono text-[11px] text-ink-muted">
              {historyDiff(entry).map((line) => (
                <p key={line}>{line}</p>
              ))}
            </div>
            {entry.user ? (
              <p className="mt-1 text-xs text-ink-faint">
                Par {entry.user.prenom} {entry.user.nom} ({entry.user.email})
              </p>
            ) : null}
          </li>
        ))}
      </ul>
    </div>
  );
}

export default function DeveloppeurPage() {
  const { toast } = useToast();
  const { refreshRuntime } = useAuth();
  const [tab, setTab] = useState<DevTab>("modules");
  const [flags, setFlags] = useState<FeatureFlag[]>([]);
  const [menuRows, setMenuRows] = useState<MenuRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [busyKey, setBusyKey] = useState<string | null>(null);
  const [busyGroup, setBusyGroup] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [resetOpen, setResetOpen] = useState(false);
  const [resetting, setResetting] = useState(false);
  const [pendingCritical, setPendingCritical] = useState<FeatureFlag | null>(
    null,
  );

  const navItems = useMemo(() => flatNavigationItems(), []);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [flagsRes, menusRes] = await Promise.all([
        systemApi.featureFlags(),
        systemApi.menuOverrides(),
      ]);
      setFlags(flagsRes.data ?? []);

      const overrides = new Map(
        (menusRes.data ?? []).map((m: MenuOverride) => [
          m.nav_key,
          Boolean(m.visible),
        ]),
      );
      setMenuRows(
        navItems
          .filter((item) => item.key !== "admin.developpeur")
          .map((item) => ({
            nav_key: item.key,
            label: item.label,
            visible: overrides.has(item.key)
              ? Boolean(overrides.get(item.key))
              : true,
          })),
      );
    } catch (err) {
      setError(
        getApiErrorMessage(err, "Impossible de charger la console système."),
      );
    } finally {
      setLoading(false);
    }
  }, [navItems]);

  useEffect(() => {
    void load();
  }, [load]);

  const applyFlagToggle = async (flag: FeatureFlag) => {
    setBusyKey(flag.key);
    try {
      const res = await systemApi.updateFeatureFlag(flag.key, !flag.enabled);
      setFlags((prev) =>
        prev.map((f) => (f.key === flag.key ? { ...f, ...res.data } : f)),
      );
      await refreshRuntime();
      toast(`${flag.label} ${res.data.enabled ? "activé" : "désactivé"}.`);
    } catch (err) {
      toast(getApiErrorMessage(err, "Échec de la mise à jour du module."), "danger");
    } finally {
      setBusyKey(null);
    }
  };

  const toggleFlag = (flag: FeatureFlag) => {
    if (flag.enabled && flag.critical) {
      setPendingCritical(flag);
      return;
    }
    void applyFlagToggle(flag);
  };

  const toggleGroup = async (group: string, enabled: boolean) => {
    setBusyGroup(group);
    try {
      const res = await systemApi.updateFeatureFlagGroup(group, enabled);
      const updated = new Map((res.data ?? []).map((f) => [f.key, f]));
      setFlags((prev) => prev.map((f) => updated.get(f.key) ?? f));
      await refreshRuntime();
      toast(`Groupe « ${group} » ${enabled ? "activé" : "désactivé"}.`);
    } catch (err) {
      toast(getApiErrorMessage(err, "Échec de la mise à jour du groupe."), "danger");
    } finally {
      setBusyGroup(null);
    }
  };

  const toggleMenu = async (navKey: string, visible: boolean) => {
    setBusyKey(navKey);
    const next = menuRows.map((row) =>
      row.nav_key === navKey ? { ...row, visible } : row,
    );
    setMenuRows(next);
    try {
      await systemApi.syncMenuOverrides(
        next.map((row) => ({ nav_key: row.nav_key, visible: row.visible })),
      );
      await refreshRuntime();
      toast(visible ? "Menu affiché." : "Menu masqué.");
    } catch (err) {
      toast(getApiErrorMessage(err, "Échec de la mise à jour du menu."), "danger");
      await load();
    } finally {
      setBusyKey(null);
    }
  };

  return (
    <PermissionGate
      permission="system.features.manage"
      title="Console développeur"
    >
      <div className="space-y-6">
        <PageHeader
          title="Console développeur"
          description="Activez ou désactivez des modules métier, masquez des entrées de menu et ajustez les réglages système pour tous les utilisateurs."
          actions={
            <Button variant="secondary" onClick={() => setResetOpen(true)}>
              <RotateCcw className="size-4" />
              Réinitialiser tout
            </Button>
          }
        />

        {error ? <Alert tone="danger">{error}</Alert> : null}

        <Tabs
          items={[
            { id: "modules", label: "Modules" },
            { id: "menus", label: "Menus" },
            { id: "permissions", label: "Permissions" },
            { id: "roles", label: "Rôles" },
            { id: "parametres", label: "Paramètres" },
            { id: "historique", label: "Historique" },
          ]}
          value={tab}
          onChange={(id) => setTab(id as DevTab)}
        />

        {loading ? (
          <div className="flex justify-center py-16">
            <Spinner className="size-6" />
          </div>
        ) : (
          <>
            <TabPanel when="modules" active={tab}>
              <ModulesTab
                flags={flags}
                busyKey={busyKey}
                busyGroup={busyGroup}
                onToggleFlag={toggleFlag}
                onToggleGroup={(group, enabled) =>
                  void toggleGroup(group, enabled)
                }
              />
            </TabPanel>

            <TabPanel when="menus" active={tab}>
              <MenusTab
                rows={menuRows}
                busyKey={busyKey}
                onToggle={(navKey, visible) => void toggleMenu(navKey, visible)}
              />
            </TabPanel>

            <TabPanel when="permissions" active={tab}>
              <PermissionsTab />
            </TabPanel>

            <TabPanel when="roles" active={tab}>
              <RolesTab />
            </TabPanel>

            <TabPanel when="parametres" active={tab}>
              <ParametresTab />
            </TabPanel>

            <TabPanel when="historique" active={tab}>
              <HistoriqueTab />
            </TabPanel>
          </>
        )}

        <p className="flex items-center gap-2 text-xs text-ink-faint">
          <Code2 className="size-3.5" />
          Réservé au rôle développeur — compte seed{" "}
          <span className="font-mono">developpeur@sis.ci</span>
        </p>
      </div>

      <ConfirmDialog
        open={!!pendingCritical}
        onClose={() => {
          if (busyKey === pendingCritical?.key) return;
          setPendingCritical(null);
        }}
        loading={busyKey === pendingCritical?.key}
        title="Désactiver un module critique"
        confirmLabel="Désactiver"
        description={
          pendingCritical
            ? `« ${pendingCritical.label} » est un module critique (${pendingCritical.key}). Le désactiver coupera immédiatement l’accès pour tous les utilisateurs, y compris l’API. Confirmer ?`
            : ""
        }
        onConfirm={async () => {
          if (!pendingCritical) return;
          await applyFlagToggle(pendingCritical);
          setPendingCritical(null);
        }}
      />

      <ConfirmDialog
        open={resetOpen}
        onClose={() => {
          if (resetting) return;
          setResetOpen(false);
        }}
        loading={resetting}
        title="Réinitialiser tout"
        confirmLabel="Réinitialiser"
        description="Tous les modules seront réactivés et toutes les entrées de menu réaffichées. Cette action ne concerne pas les paramètres système (à réinitialiser séparément)."
        onConfirm={async () => {
          setResetting(true);
          try {
            await systemApi.resetFeatureFlags();
            await load();
            await refreshRuntime();
            toast("Modules et menus réinitialisés.");
            setResetOpen(false);
          } catch (err) {
            toast(getApiErrorMessage(err, "Échec de la réinitialisation."), "danger");
          } finally {
            setResetting(false);
          }
        }}
      />
    </PermissionGate>
  );
}
