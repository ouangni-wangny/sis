import {
  AlertTriangle,
  Building2,
  CalendarDays,
  Camera,
  ClipboardCheck,
  Code2,
  FileBarChart,
  FileText,
  HandCoins,
  LayoutDashboard,
  MapPinned,
  Repeat,
  Route,
  Settings2,
  Shield,
  ShieldCheck,
  ScrollText,
  Tags,
  UserCog,
  UserRound,
  Users,
  Wallet,
  Banknote,
  type LucideIcon,
} from "lucide-react";

export type NavItem = {
  /** Clé stable pour menu_overrides / console développeur */
  key: string;
  href: string;
  label: string;
  icon: LucideIcon;
  /** Au moins une de ces permissions est requise pour voir l’entrée */
  permissions: string[];
  /** Feature flag module lié (si désactivé → masqué) */
  feature?: string;
};

export type NavGroup = {
  label: string;
  items: NavItem[];
};

/**
 * Structure métier back-office alignée sur les rôles :
 * Opération · RH · Commercial · Admin
 */
export const navigation: NavGroup[] = [
  {
    label: "Pilotage",
    items: [
      {
        key: "pilotage.dashboard",
        href: "/",
        label: "Tableau de bord",
        icon: LayoutDashboard,
        permissions: ["dashboard.view"],
        feature: "module.dashboard",
      },
      {
        key: "pilotage.rapports",
        href: "/rapports",
        label: "Reporting",
        icon: FileBarChart,
        permissions: ["rapports.generate"],
        feature: "module.rapports",
      },
    ],
  },
  {
    label: "Opérations",
    items: [
      {
        key: "ops.zones",
        href: "/zones",
        label: "Zones",
        icon: MapPinned,
        permissions: ["zones.view", "zones.manage"],
        feature: "module.zones",
      },
      {
        key: "ops.sites",
        href: "/sites",
        label: "Sites",
        icon: Shield,
        permissions: ["sites.view"],
        feature: "module.sites",
      },
      {
        key: "ops.postes",
        href: "/postes",
        label: "Postes",
        icon: ClipboardCheck,
        permissions: ["postes.view", "sites.view"],
        feature: "module.sites",
      },
      {
        key: "ops.agents",
        href: "/agents",
        label: "Personnel",
        icon: UserRound,
        permissions: ["agents.view"],
        feature: "module.agents",
      },
      {
        key: "ops.perimetres",
        href: "/perimetres",
        label: "Périmètre contrôleurs",
        icon: Route,
        permissions: ["perimetres.view"],
        feature: "module.planning_controleurs",
      },
      {
        key: "ops.vacations",
        href: "/vacations",
        label: "Planning postes",
        icon: CalendarDays,
        permissions: ["vacations.view"],
        feature: "module.vacations",
      },
      {
        key: "ops.planning_controleurs",
        href: "/planning-controleurs",
        label: "Planning contrôleurs",
        icon: Repeat,
        permissions: ["zones.view", "perimetres.view", "zones.manage"],
        feature: "module.planning_controleurs",
      },
      {
        key: "ops.controles",
        href: "/controles",
        label: "Contrôles",
        icon: Camera,
        permissions: ["controles.view"],
        feature: "module.controles",
      },
      {
        key: "ops.anomalies",
        href: "/anomalies",
        label: "Anomalies",
        icon: AlertTriangle,
        permissions: ["anomalies.view"],
        feature: "module.anomalies",
      },
    ],
  },
  {
    label: "RH",
    items: [
      {
        key: "rh.contrats",
        href: "/rh",
        label: "Contrats & Absences",
        icon: Users,
        permissions: [
          "contrats.manage",
          "contrats.view",
          "contrats.alerts",
          "absences.manage",
          "absences.view",
        ],
        feature: "module.rh",
      },
      {
        key: "rh.paie",
        href: "/rh/paie",
        label: "Paie",
        icon: Wallet,
        permissions: ["paie.manage", "paie.view", "paie.payer"],
        feature: "module.paie",
      },
    ],
  },
  {
    label: "Commercial",
    items: [
      {
        key: "com.clients",
        href: "/clients",
        label: "Clients",
        icon: Building2,
        permissions: ["clients.view"],
        feature: "module.clients",
      },
      {
        key: "com.offres",
        href: "/offres",
        label: "Offres",
        icon: Tags,
        permissions: ["offres.view"],
        feature: "module.offres",
      },
      {
        key: "com.abonnements",
        href: "/abonnements",
        label: "Abonnements",
        icon: Repeat,
        permissions: ["abonnements.view"],
        feature: "module.abonnements",
      },
      {
        key: "com.factures",
        href: "/factures",
        label: "Factures",
        icon: FileText,
        permissions: ["factures.view"],
        feature: "module.factures",
      },
      {
        key: "com.recouvrement",
        href: "/recouvrement",
        label: "À recouvrer",
        icon: HandCoins,
        permissions: ["factures.view"],
        feature: "module.factures",
      },
      {
        key: "com.paiements",
        href: "/paiements",
        label: "Paiements",
        icon: Wallet,
        permissions: ["paiements.view"],
        feature: "module.paiements",
      },
    ],
  },
  {
    label: "Trésorerie",
    items: [
      {
        key: "treso.dashboard",
        href: "/tresorerie",
        label: "Soldes & mouvements",
        icon: Banknote,
        permissions: ["tresorerie.view", "tresorerie.manage"],
        feature: "module.tresorerie",
      },
      {
        key: "treso.depenses",
        href: "/tresorerie/depenses",
        label: "Dépenses",
        icon: Wallet,
        permissions: [
          "depenses.view",
          "depenses.manage",
          "tresorerie.view",
          "tresorerie.manage",
        ],
        feature: "module.tresorerie",
      },
    ],
  },
  {
    label: "Administration",
    items: [
      {
        key: "admin.users",
        href: "/users",
        label: "Utilisateurs",
        icon: UserCog,
        permissions: ["users.view"],
        feature: "module.users",
      },
      {
        key: "admin.audit",
        href: "/audit",
        label: "Journal d’audit",
        icon: ScrollText,
        permissions: ["audit.view"],
        feature: "module.audit",
      },
      {
        key: "admin.parametres",
        href: "/parametres",
        label: "Paramètres",
        icon: Settings2,
        permissions: ["grades.manage"],
        feature: "module.parametres",
      },
      {
        key: "admin.roles",
        href: "/roles",
        label: "Rôles & permissions",
        icon: ShieldCheck,
        permissions: ["system.roles.manage"],
      },
      {
        key: "admin.developpeur",
        href: "/developpeur",
        label: "Console développeur",
        icon: Code2,
        permissions: ["system.features.manage"],
      },
    ],
  },
];

/** Liste plate des items (console menus). */
export function flatNavigationItems(): NavItem[] {
  return navigation.flatMap((g) => g.items);
}
