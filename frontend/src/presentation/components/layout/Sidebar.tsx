"use client";

import { ChevronDown, X } from "lucide-react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useMemo, useState } from "react";
import { useAuth } from "@/presentation/providers/AuthProvider";
import { navigation } from "@/shared/config/navigation";
import { canAny } from "@/shared/lib/can";
import { cn } from "@/shared/lib/cn";
import { labelRole } from "@/shared/lib/format";

function isPathActive(href: string, pathname: string): boolean {
  if (href === "/") return pathname === "/";
  return pathname === href || pathname.startsWith(`${href}/`);
}

/** Préfère le lien le plus spécifique (ex. /rh/paie plutôt que /rh). */
function isNavItemActive(
  href: string,
  pathname: string,
  allHrefs: string[],
): boolean {
  if (!isPathActive(href, pathname)) return false;
  return !allHrefs.some(
    (other) =>
      other !== href &&
      other.length > href.length &&
      isPathActive(other, pathname),
  );
}

function groupContainsPath(
  items: { href: string }[],
  pathname: string,
  allHrefs: string[],
): boolean {
  return items.some((item) => isNavItemActive(item.href, pathname, allHrefs));
}

export function Sidebar({
  open,
  onClose,
}: {
  open: boolean;
  onClose: () => void;
}) {
  const pathname = usePathname();
  const { user, featureFlags, menuOverrides } = useAuth();

  const groups = useMemo(
    () =>
      navigation
        .map((group) => ({
          ...group,
          items: group.items.filter((item) => {
            if (!canAny(user, [...item.permissions])) return false;
            if (menuOverrides[item.key] === false) return false;
            if (
              item.feature &&
              featureFlags[item.feature] === false
            ) {
              return false;
            }
            return true;
          }),
        }))
        .filter((group) => group.items.length > 0),
    [user, featureFlags, menuOverrides],
  );

  const allHrefs = useMemo(
    () => groups.flatMap((group) => group.items.map((item) => item.href)),
    [groups],
  );

  const allLabels = useMemo(() => groups.map((g) => g.label), [groups]);
  const [openLabels, setOpenLabels] = useState<string[]>(() =>
    navigation.map((g) => g.label),
  );

  useEffect(() => {
    setOpenLabels((current) => {
      const known = current.filter((label) => allLabels.includes(label));
      const missing = allLabels.filter((label) => !known.includes(label));
      return [...known, ...missing];
    });
  }, [allLabels]);

  const toggleGroup = (label: string) => {
    setOpenLabels((current) =>
      current.includes(label)
        ? current.filter((item) => item !== label)
        : [...current, label],
    );
  };

  return (
    <>
      {open ? (
        <button
          type="button"
          aria-label="Fermer le menu"
          onClick={onClose}
          className="fixed inset-0 z-40 bg-ink/50 backdrop-blur-[1px] lg:hidden"
        />
      ) : null}
      <aside
        className={cn(
          "fixed inset-y-0 left-0 z-50 flex h-full min-h-0 w-60 shrink-0 -translate-x-full flex-col overflow-hidden border-r border-border bg-ink text-white transition-transform duration-200 ease-out lg:static lg:translate-x-0",
          open && "translate-x-0",
        )}
      >
        <div className="flex shrink-0 items-center justify-between gap-2 border-b border-white/10 px-4 py-4">
          <Link href="/" className="inline-flex items-center">
            <img
              src="/logo.png"
              alt="SIS — Société Ivoirienne de Sécurité"
              width={72}
              height={72}
              className="size-[4.5rem] rounded-md bg-white object-contain p-0.5"
            />
          </Link>
          <button
            type="button"
            aria-label="Fermer le menu"
            onClick={onClose}
            className="rounded-md p-1.5 text-white/70 hover:bg-white/10 hover:text-white lg:hidden"
          >
            <X className="size-5" />
          </button>
        </div>

      <nav className="min-h-0 flex-1 overflow-y-auto px-3 py-3">
        <ul className="space-y-1">
          {groups.map((group) => {
            const open = openLabels.includes(group.label);
            const hasActive = groupContainsPath(group.items, pathname, allHrefs);

            return (
              <li key={group.label}>
                <button
                  type="button"
                  aria-expanded={open}
                  onClick={() => toggleGroup(group.label)}
                  className={cn(
                    "flex w-full items-center justify-between rounded-md px-2.5 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.14em] transition-colors",
                    hasActive
                      ? "text-teal-light"
                      : open
                        ? "bg-white/5 text-white/80"
                        : "text-white/45 hover:bg-white/5 hover:text-white/80",
                  )}
                >
                  <span className="flex items-center gap-2">
                    {hasActive ? (
                      <span className="size-1.5 rounded-full bg-teal-light" />
                    ) : null}
                    {group.label}
                  </span>
                  <ChevronDown
                    className={cn(
                      "size-3.5 shrink-0 opacity-70 transition-transform duration-200",
                      open && "rotate-180",
                    )}
                  />
                </button>

                <div
                  className={cn(
                    "grid transition-[grid-template-rows] duration-200 ease-out",
                    open ? "grid-rows-[1fr]" : "grid-rows-[0fr]",
                  )}
                >
                  <div className="overflow-hidden">
                    <ul className="space-y-0.5 pb-1 pt-1">
                      {group.items.map((item) => {
                        const active = isNavItemActive(
                          item.href,
                          pathname,
                          allHrefs,
                        );
                        const Icon = item.icon;
                        return (
                          <li key={item.href}>
                            <Link
                              href={item.href}
                              aria-current={active ? "page" : undefined}
                              className={cn(
                                "relative flex items-center gap-2.5 rounded-md px-2.5 py-1.5 text-sm transition-colors",
                                active
                                  ? "bg-teal text-white shadow-sm shadow-black/20"
                                  : "text-white/65 hover:bg-white/5 hover:text-white",
                              )}
                            >
                              {active ? (
                                <span className="absolute left-0 top-1/2 h-5 w-1 -translate-y-1/2 rounded-r-full bg-teal-light" />
                              ) : null}
                              <span
                                className={cn(
                                  "flex size-6 shrink-0 items-center justify-center rounded-md",
                                  active ? "bg-white/15" : "bg-transparent",
                                )}
                              >
                                <Icon
                                  className={cn(
                                    "size-3.5",
                                    active ? "opacity-100" : "opacity-80",
                                  )}
                                />
                              </span>
                              <span className={cn(active && "font-semibold")}>
                                {item.label}
                              </span>
                            </Link>
                          </li>
                        );
                      })}
                    </ul>
                  </div>
                </div>
              </li>
            );
          })}
        </ul>
      </nav>

      <div className="shrink-0 border-t border-white/10 px-4 py-3">
        {user ? (
          <div className="flex items-center gap-2.5">
            <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-teal text-xs font-semibold text-white">
              {(user.prenom?.[0] ?? "U").toUpperCase()}
              {(user.nom?.[0] ?? "").toUpperCase()}
            </span>
            <div className="min-w-0">
              <p className="truncate text-xs font-medium text-white">
                {user.prenom} {user.nom}
              </p>
              <p className="truncate text-[10px] uppercase tracking-wide text-white/45">
                {labelRole(user.roles?.[0])}
              </p>
            </div>
          </div>
        ) : (
          <p className="text-[11px] text-white/40">
            Back-office terrain · Côte d&apos;Ivoire
          </p>
        )}
      </div>
      </aside>
    </>
  );
}
