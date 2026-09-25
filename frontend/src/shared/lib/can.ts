import type { User } from "@/domain/types/entities";

function isDeveloppeur(user: User | null | undefined): boolean {
  return Boolean(user?.roles?.includes("developpeur"));
}

function isSuperAdmin(user: User | null | undefined): boolean {
  return Boolean(user?.roles?.includes("super-admin"));
}

/**
 * Vérifie une permission Spatie.
 * developpeur = tout ; super-admin = tout sauf system.* (sauf celles explicitement assignées,
 * ex. system.roles.manage).
 */
export function can(
  user: User | null | undefined,
  permission: string | string[],
): boolean {
  if (!user) return false;
  if (isDeveloppeur(user)) return true;

  const needed = Array.isArray(permission) ? permission : [permission];
  const perms = user.permissions ?? [];

  if (isSuperAdmin(user)) {
    const metierNeeded = needed.filter((p) => !p.startsWith("system."));
    if (metierNeeded.length > 0) return true;
    // Uniquement des permissions system.* → vérifier la liste assignée
  }

  return needed.some((p) => {
    if (perms.includes(p)) return true;
    if (p.startsWith("zones.") && perms.includes("zones.manage")) return true;
    return false;
  });
}

export function canAny(
  user: User | null | undefined,
  permissions: string[],
): boolean {
  return can(user, permissions);
}

export function hasRole(
  user: User | null | undefined,
  role: string | string[],
): boolean {
  if (!user?.roles?.length) return false;
  const roles = Array.isArray(role) ? role : [role];
  if (isDeveloppeur(user) || isSuperAdmin(user)) return true;
  return roles.some((r) => user.roles!.includes(r));
}

export function isDeveloppeurUser(user: User | null | undefined): boolean {
  return isDeveloppeur(user);
}

/** Compte terrain (contrôleur / agent mobile) → UI type app mobile. */
export function isTerrainUser(user: User | null | undefined): boolean {
  if (!user) return false;
  // Ne pas utiliser hasRole() ici : developpeur/super-admin y sont « tous les rôles ».
  if (user.type === "mobile") return true;
  const roles = user.roles ?? [];
  return (
    roles.includes("controleur") ||
    roles.includes("rondier") || // legacy
    roles.includes("agent")
  );
}

export function homePathForUser(user: User | null | undefined): string {
  return isTerrainUser(user) ? "/terrain" : "/";
}

export function isFeatureEnabled(
  flags: Record<string, boolean> | null | undefined,
  key: string,
  defaultEnabled = true,
): boolean {
  if (!flags || !(key in flags)) return defaultEnabled;
  return Boolean(flags[key]);
}

export function isMenuVisible(
  overrides: Record<string, boolean> | null | undefined,
  navKey: string,
  defaultVisible = true,
): boolean {
  if (!overrides || !(navKey in overrides)) return defaultVisible;
  return Boolean(overrides[navKey]);
}

/** Salaires visibles uniquement pour la RH (gestion contrats). */
export function canSeeSalaire(user: User | null | undefined): boolean {
  return can(user, "contrats.manage");
}

export function canViewContrats(user: User | null | undefined): boolean {
  return can(user, ["contrats.manage", "contrats.view", "contrats.alerts"]);
}

export function canManageContrats(user: User | null | undefined): boolean {
  return can(user, "contrats.manage");
}

export function canViewAbsences(user: User | null | undefined): boolean {
  return can(user, ["absences.manage", "absences.view"]);
}

export function canManageAbsences(user: User | null | undefined): boolean {
  return can(user, "absences.manage");
}

export function canViewPaie(user: User | null | undefined): boolean {
  return can(user, ["paie.manage", "paie.view", "paie.payer"]);
}

export function canManagePaie(user: User | null | undefined): boolean {
  return can(user, "paie.manage");
}

/** Marquer les bulletins comme payés (comptable). */
export function canPayerPaie(user: User | null | undefined): boolean {
  return can(user, "paie.payer");
}
