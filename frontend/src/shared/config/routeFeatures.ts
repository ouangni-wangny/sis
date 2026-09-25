/** Préfixes de routes → feature flag (plus spécifique en premier). */
export const ROUTE_FEATURES: Array<{ prefix: string; feature: string }> = [
  { prefix: "/rh/paie", feature: "module.paie" },
  { prefix: "/rh", feature: "module.rh" },
  { prefix: "/controles/siege", feature: "module.controles_siege" },
  { prefix: "/controles", feature: "module.controles" },
  { prefix: "/planning-controleurs", feature: "module.planning_controleurs" },
  { prefix: "/perimetres", feature: "module.planning_controleurs" },
  { prefix: "/vacations", feature: "module.vacations" },
  { prefix: "/anomalies", feature: "module.anomalies" },
  { prefix: "/zones", feature: "module.zones" },
  { prefix: "/sites", feature: "module.sites" },
  { prefix: "/postes", feature: "module.sites" },
  { prefix: "/agents", feature: "module.agents" },
  { prefix: "/rapports", feature: "module.rapports" },
  { prefix: "/clients", feature: "module.clients" },
  { prefix: "/offres", feature: "module.offres" },
  { prefix: "/abonnements", feature: "module.abonnements" },
  { prefix: "/factures", feature: "module.factures" },
  { prefix: "/recouvrement", feature: "module.factures" },
  { prefix: "/paiements", feature: "module.paiements" },
  { prefix: "/tresorerie", feature: "module.tresorerie" },
  { prefix: "/users", feature: "module.users" },
  { prefix: "/audit", feature: "module.audit" },
  { prefix: "/parametres", feature: "module.parametres" },
  { prefix: "/", feature: "module.dashboard" },
];

/** Routes jamais bloquées par un feature flag. */
export const FEATURE_GATE_EXEMPT = ["/developpeur", "/login", "/terrain"];

export function featureForPath(pathname: string): string | null {
  if (FEATURE_GATE_EXEMPT.some((p) => pathname === p || pathname.startsWith(`${p}/`))) {
    return null;
  }

  for (const row of ROUTE_FEATURES) {
    if (row.prefix === "/") {
      if (pathname === "/") return row.feature;
      continue;
    }
    if (pathname === row.prefix || pathname.startsWith(`${row.prefix}/`)) {
      return row.feature;
    }
  }

  return null;
}
