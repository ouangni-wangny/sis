export type TypeClient = "entreprise" | "particulier";
export type StatutClient = "actif" | "resilie" | "suspendu";

export type Client = {
  id: string;
  type: TypeClient;
  raison_sociale: string;
  nom_responsable: string | null;
  personne_contact: string | null;
  telephone: string | null;
  email: string | null;
  adresse: string | null;
  statut: StatutClient;
  sites_count?: number;
  created_at?: string;
};

export type Zone = {
  id: string;
  nom: string;
  description: string | null;
  sites_count?: number;
  controleurs_count?: number;
  controleurs?: Array<{
    id: string;
    nom: string;
    prenom: string;
    matricule: string;
    statut?: string;
    indice_releve?: number | null;
    releve_depuis?: string | null;
    releve_jusque?: string | null;
  }>;
  created_at?: string;
};

export type TypeAgent = "agent" | "controleur" | "administration";
export type StatutAgent =
  "disponible" | "en_activite" | "conge" | "malade" | "suspendu" | "archive";

export type Grade = {
  id: string;
  libelle: string;
  type_agent: TypeAgent;
  description?: string | null;
};

export type Ville = {
  id: string;
  libelle: string;
};

export type Site = {
  id: string;
  nom: string;
  adresse: string | null;
  responsable: string | null;
  tarif_mensuel: string | number | null;
  latitude: number | null;
  longitude: number | null;
  rayon_metres: number | null;
  interne?: boolean;
  client_id: string;
  zone_id: string;
  client?: Pick<Client, "id" | "raison_sociale" | "type">;
  zone?: Pick<Zone, "id" | "nom">;
  postes?: Poste[];
  checkpoints?: Checkpoint[];
};

export type JourSemaine =
  "lundi" | "mardi" | "mercredi" | "jeudi" | "vendredi" | "samedi" | "dimanche";

export type Agent = {
  id: string;
  user_id: string | null;
  grade_id: string | null;
  type: TypeAgent;
  nom: string;
  prenom: string;
  civilite: "monsieur" | "madame" | "mademoiselle" | null;
  date_naissance: string | null;
  lieu_naissance: string | null;
  situation_matrimoniale: "celibataire" | "marie" | "divorce" | "veuf" | null;
  nombre_enfants: number | null;
  nationalite: string | null;
  telephone: string | null;
  numero_cni: string | null;
  ville_id: string | null;
  ville: string | null;
  domicile: string | null;
  matricule: string;
  cnps: string | null;
  date_embauche: string | null;
  date_expiration_permis: string | null;
  statut: StatutAgent;
  /** Rattaché en permanence au siège, pointé chaque jour, utilisé comme remplaçant en cas d'absence ailleurs. */
  pool_siege?: boolean;
  poste_siege_id?: string | null;
  poste_siege?: {
    id: string;
    nom: string;
    site: { id: string; nom: string } | null;
  } | null;
  jour_repos?: JourSemaine | null;
  has_pin?: boolean | null;
  email?: string | null;
  plain_pin?: string;
  conges_acquis_annuel?: string | number | null;
  solde_conges_jours?: string | number | null;
  grade?: Grade | null;
  ville_ref?: Ville | null;
  perimetres?: RondierPerimetre[];
  perimetre_sites_count?: number;
  perimetre_agents_count?: number;
  contrat_actif?: {
    id: string;
    type: string;
    reference: string | null;
    date_debut: string;
    date_fin: string | null;
    periode_essai_mois: number | null;
    salaire_brut: string | number | null;
    salaire_net: string | number | null;
    salaire: string | number | null;
    statut: string;
  } | null;
};

export type Poste = {
  id: string;
  site_id: string;
  nom: string;
  agents_requis: number;
  /** Sans quarts + effectif ≥ 2 : ensemble (en même temps) | alternance (tour de rôle). */
  mode_effectif?: "ensemble" | "alternance" | null;
  heure_debut: string | null;
  heure_fin: string | null;
  /** Quart nuit — un poste est "24h" (jour + nuit) dès que ces deux champs sont renseignés. */
  heure_debut_nuit: string | null;
  heure_fin_nuit: string | null;
  site?: Pick<Site, "id" | "nom">;
};

export type Checkpoint = {
  id: string;
  site_id: string;
  nom: string;
  code_qr: string | null;
  latitude: number | null;
  longitude: number | null;
  ordre: number | null;
};

export type RondierPerimetre = {
  id: string;
  agent_id: string;
  zone_id: string | null;
  /** Binôme relève 48h : 0 ou 1 (null = seul sur la zone). */
  indice_releve?: number | null;
  /** Début du cycle / période (Y-m-d). */
  releve_depuis?: string | null;
  /** Fin de la période planifiée (Y-m-d). */
  releve_jusque?: string | null;
  zone?: Pick<Zone, "id" | "nom"> | null;
};

export type Offre = {
  id: string;
  libelle: string;
  description: string | null;
  prix_mensuel: string | number;
  actif: boolean;
};

export type Abonnement = {
  id: string;
  client_id: string;
  offre_id: string | null;
  designation?: string | null;
  site_id: string | null;
  periodicite: "mensuel" | "trimestriel" | "annuel" | string;
  date_debut: string;
  date_fin: string | null;
  prochaine_facture_le?: string | null;
  statut: string;
  created_at?: string;
  updated_at?: string;
  client?:
    | (Pick<Client, "id" | "raison_sociale"> &
        Partial<
          Pick<
            Client,
            | "type"
            | "personne_contact"
            | "telephone"
            | "email"
            | "adresse"
            | "statut"
          >
        >)
    | null;
  offre?:
    | (Pick<Offre, "id" | "libelle"> & {
        prix_mensuel?: string | number | null;
      })
    | null;
  site?: (Pick<Site, "id" | "nom"> & { adresse?: string | null }) | null;
  lignes?: {
    id: string;
    offre_id?: string | null;
    description: string;
    quantite: string | number;
    prix_unitaire: string | number;
    montant: string | number;
    ordre?: number;
    offre?: Pick<Offre, "id" | "libelle"> | null;
  }[];
  montant_ht?: string | number | null;
  factures?: {
    id: string;
    numero: string;
    date_emission: string;
    montant_ht: string | number;
    montant_ttc: string | number;
    statut: string;
    periodicite?: string | null;
  }[];
};

export type Absence = {
  id: string;
  agent_id: string;
  type: string;
  /** Origine : rh (octroyée) ou controle (constatée terrain). */
  source?: "rh" | "controle" | null;
  controle_id?: string | null;
  date_debut: string;
  date_fin: string | null;
  motif: string | null;
  statut: string;
  vacations_marquees_a_recouvrir?: number;
  vacations_restaurees?: number;
  agent?: Pick<Agent, "id" | "nom" | "prenom" | "matricule"> | null;
};

export type Contrat = {
  id: string;
  agent_id: string;
  type: string;
  reference: string | null;
  date_debut: string;
  date_fin: string | null;
  duree_mois: number | null;
  periode_essai_mois: number | null;
  salaire_base: string | number | null;
  indemnite_fonction: string | number | null;
  prime_responsabilite: string | number | null;
  prime_transport: string | number | null;
  prime_entretien_tenue: string | number | null;
  sursalaire: string | number | null;
  nombre_enfants: number | null;
  parts_igr: string | number | null;
  montant_igr: string | number | null;
  retenue_cnps: string | number | null;
  salaire_brut: string | number | null;
  salaire_net: string | number | null;
  salaire: string | number | null;
  statut: string;
  agent?:
    | (Pick<Agent, "id" | "nom" | "prenom" | "matricule"> & {
        situation_matrimoniale?: Agent["situation_matrimoniale"];
        nombre_enfants?: number | null;
      })
    | null;
};

export type ContratAlerte = {
  contrat_id: string;
  agent_id: string;
  agent_nom: string;
  matricule: string;
  type: string;
  reference: string | null;
  alerte: "fin_essai" | "fin_contrat" | string;
  date_reference: string;
  jours_restants: number;
};

export type PeriodePaie = {
  id: string;
  mois: number;
  annee: number;
  date_debut: string;
  date_fin: string;
  statut: "brouillon" | "validee" | "cloturee" | string;
  commentaire: string | null;
  bulletins_count?: number;
};

export type BulletinPaie = {
  id: string;
  periode_paie_id: string;
  agent_id: string;
  contrat_id: string;
  salaire_brut: string | number | null;
  retenue_cnps: string | number | null;
  montant_igr: string | number | null;
  salaire_net: string | number | null;
  statut: "brouillon" | "valide" | "paye" | string;
  paye_le: string | null;
  details: Record<string, unknown> | null;
  pdf_url?: string | null;
  agent?: Pick<Agent, "id" | "nom" | "prenom" | "matricule"> | null;
  periode_paie?: { mois: number; annee: number } | null;
};

export type SoldeCongesMouvement = {
  id: string;
  agent_id: string;
  absence_id: string | null;
  type: string;
  jours: string | number;
  solde_apres: string | number;
  motif: string | null;
  user_id: string | null;
  created_at?: string;
};

export type SisNotification = {
  id: string;
  type: string;
  titre: string;
  message: string;
  meta: Record<string, unknown> | null;
  lue_le: string | null;
  created_at: string;
};

export type DashboardRhStats = {
  contrats_actifs: number;
  masse_salariale: number;
  absences_en_attente: number;
  contrats_alertes_count: number;
  contrats_alertes: ContratAlerte[];
};

export type RapportExport = {
  id: string;
  type: string;
  format: string;
  statut: string;
  filtres: Record<string, unknown> | null;
  erreur: string | null;
  export?: { url: string } | null;
  created_at: string;
};

export type StatutVacation =
  | "planifiee"
  | "en_cours"
  | "terminee"
  | "annulee"
  | "a_recouvrir";

export type Vacation = {
  id: string;
  agent_id: string;
  site_id: string;
  poste_id: string | null;
  date_debut: string;
  date_fin: string | null;
  heure_debut: string;
  heure_fin: string;
  statut: StatutVacation;
  agent?: Pick<Agent, "id" | "nom" | "prenom" | "matricule">;
  site?: Pick<Site, "id" | "nom">;
  poste?: Pick<
    Poste,
    | "id"
    | "nom"
    | "heure_debut"
    | "heure_fin"
    | "heure_debut_nuit"
    | "heure_fin_nuit"
  > | null;
};

export type StatutRonde = "planifiee" | "en_cours" | "terminee" | "annulee";

export type Ronde = {
  id: string;
  agent_id: string;
  site_id: string;
  vacation_id: string | null;
  demarree_at: string | null;
  terminee_at: string | null;
  statut: StatutRonde;
  progression: number | null;
  agent?: Pick<Agent, "id" | "nom" | "prenom" | "matricule">;
  site?: Pick<Site, "id" | "nom">;
};

export type TypeAnomalie =
  "intrusion" | "vol" | "incendie" | "technique" | "comportement" | "autre";

export type GraviteAnomalie = "basse" | "moyenne" | "haute" | "critique";
export type StatutAnomalie = "ouverte" | "en_cours" | "resolue";

export type Anomalie = {
  id: string;
  client_uuid: string | null;
  signale_par_id: string | null;
  assigne_a_id: string | null;
  site_id: string | null;
  type: TypeAnomalie;
  gravite: GraviteAnomalie;
  statut: StatutAnomalie;
  commentaire: string | null;
  signale_at: string;
  resolue_at: string | null;
  site?: Pick<Site, "id" | "nom"> | null;
};

export type ControleResultat = "present" | "absent" | "enregistre";

export type Controle = {
  id: string;
  client_uuid: string | null;
  agent_id: string | null;
  enregistre_par_user_id?: string | null;
  site_id: string | null;
  poste_id: string | null;
  controle_agent_id: string | null;
  ronde_id: string | null;
  effectue_at: string;
  latitude: number | null;
  longitude: number | null;
  commentaire: string | null;
  /** Présent | Absent, saisi directement par le contrôleur au moment du contrôle. */
  resultat: ControleResultat;
  /** Contrôleur qui a effectué le contrôle. */
  agent?: Pick<Agent, "id" | "nom" | "prenom" | "matricule"> | null;
  /** Utilisateur Opération (contrôle siège). */
  enregistre_par?: {
    id: string;
    nom: string | null;
    prenom: string | null;
    email: string | null;
    full_name?: string | null;
  } | null;
  /** Agent posté contrôlé. */
  controle_agent?: Pick<Agent, "id" | "nom" | "prenom" | "matricule"> | null;
  site?: Pick<Site, "id" | "nom" | "interne"> | null;
  poste?: Pick<Poste, "id" | "nom"> | null;
  photos?: { url: string; thumb?: string }[];
};

export type StatutFacture = "en_attente" | "valide" | "annule";

export type StatutPaiementFacture = "non_payee" | "partiel" | "soldee";

export type ModePaiement =
  "especes" | "virement" | "cheque" | "mobile_money" | "autre";

export type Paiement = {
  id: string;
  facture_id: string;
  montant: string | number;
  date_paiement: string;
  mode: ModePaiement | string;
  reference: string | null;
  notes: string | null;
  created_at?: string;
  facture?: {
    id: string;
    numero: string;
    montant_ttc: string | number;
    statut: StatutFacture | string;
    client_id: string;
    client?: Pick<Client, "id" | "raison_sociale"> | null;
  } | null;
};

export type Facture = {
  id: string;
  client_id: string | null;
  abonnement_id?: string | null;
  site_id?: string | null;
  numero: string;
  date_emission: string;
  date_echeance: string | null;
  periode_debut?: string | null;
  periode_fin?: string | null;
  periodicite?: string | null;
  date_debut_service?: string | null;
  date_fin_service?: string | null;
  montant_ht: string | number;
  montant_tva: string | number;
  montant_ttc: string | number;
  montant_paye?: string | number | null;
  solde?: string | number | null;
  statut_paiement?: StatutPaiementFacture | null;
  devise: string;
  statut: StatutFacture;
  lieu_emission?: string | null;
  affaire_suivie_par?: string | null;
  telephone_commercial?: string | null;
  taux_tva?: string | number | null;
  client_nom?: string | null;
  client_adresse?: string | null;
  client_telephone?: string | null;
  client_email?: string | null;
  notes?: string | null;
  conditions_paiement?: string | null;
  delai_paiement_jours?: number | null;
  delai_validite?: string | null;
  duree_contrat_min?: string | null;
  signataire_nom?: string | null;
  signataire_fonction?: string | null;
  montant_ttc_lettres?: string | null;
  client?: Pick<Client, "id" | "raison_sociale"> | null;
  abonnement?: Pick<
    Abonnement,
    "id" | "periodicite" | "date_debut" | "date_fin" | "statut"
  > | null;
  lignes?: LigneFacture[];
  pdf?: { url: string } | null;
};

export type LigneFacture = {
  id?: string;
  offre_id?: string | null;
  code_article?: string | null;
  description: string;
  quantite: string | number;
  prix_unitaire: string | number;
  montant: string | number;
  ordre?: number;
};

export type User = {
  id: string;
  nom: string;
  prenom: string;
  email: string | null;
  matricule: string | null;
  type: string;
  statut: string;
  roles?: string[];
  permissions?: string[];
  agent?: Pick<
    Agent,
    "id" | "nom" | "prenom" | "matricule" | "type" | "statut" | "perimetres"
  > | null;
  last_login_at?: string | null;
  created_at?: string;
};

export type FeatureFlag = {
  id: string;
  key: string;
  label: string;
  description: string | null;
  enabled: boolean;
  group?: string;
  critical?: boolean;
  updated_at?: string | null;
  updated_by?: {
    id: string;
    nom: string | null;
    prenom: string | null;
    email: string | null;
  } | null;
};

export type MenuOverride = {
  id?: string;
  nav_key: string;
  visible: boolean;
  updated_at?: string | null;
};

export type RuntimeConfig = {
  feature_flags: Record<string, boolean>;
  menu_overrides: Record<string, boolean>;
};

export type SystemSetting = {
  key: string;
  label: string;
  description: string;
  type: "boolean" | "integer" | "float" | "string" | "text";
  group: string;
  value: string | number | boolean | null;
  default: string | number | boolean | null;
  overridden: boolean;
};

export type SystemPermission = {
  name: string;
  label: string;
  group: string;
};

export type SystemRole = {
  id: string;
  name: string;
  protected: boolean;
  users_count: number;
  permissions: string[];
  created_at?: string | null;
  updated_at?: string | null;
};

export type SystemHistoryEntry = {
  id: string;
  action: string;
  auditable_type: string;
  auditable_id: string | null;
  ancien: Record<string, unknown> | null;
  nouveau: Record<string, unknown> | null;
  created_at: string;
  user?: {
    id: string;
    nom: string | null;
    prenom: string | null;
    email: string | null;
  } | null;
};

export type JournalAudit = {
  id: string;
  user_id: string | null;
  action: string;
  action_label?: string | null;
  action_explication?: string | null;
  auditable_type: string | null;
  auditable_type_label?: string | null;
  auditable_id: string | null;
  auditable_label?: string | null;
  ancien: Record<string, unknown> | null;
  nouveau: Record<string, unknown> | null;
  changes?: Array<{
    field: string;
    field_label?: string;
    before: unknown;
    after: unknown;
    before_label?: string;
    after_label?: string;
  }>;
  resume?: string | null;
  explication?: string | null;
  contexte?: Record<string, unknown> | null;
  contexte_labels?: Record<string, string> | null;
  ip: string | null;
  created_at: string;
  acteur?: string | null;
  user?: (Pick<User, "id" | "nom" | "prenom" | "email"> & {
    label?: string;
  }) | null;
};

export type StatutCouverture =
  | "ok"
  | "sous_effectif"
  | "sur_effectif"
  | "non_couvert";

export type PosteCoverage = {
  poste_id: string;
  nom: string;
  agents_requis: number;
  /** Capacité journalière (1 en alternance, sinon agents_requis). */
  capacite_jour?: number;
  planifies: number;
  manquant: number;
  statut: StatutCouverture;
  site?: Pick<Site, "id" | "nom"> | null;
  couverture_24h?: boolean;
  jour?: { planifies: number; manquant: number };
  nuit?: { planifies: number; manquant: number };
};

export type DashboardCommercialStats = {
  proformas_en_attente: number;
  factures_validees: number;
  abonnements_actifs: number;
  montant_facture_ttc: number;
  montant_paye: number;
  montant_impaye: number;
  factures_par_paiement: {
    non_payee: number;
    partiel: number;
    soldee: number;
  };
  factures_par_periodicite: Record<
    string,
    { count: number; montant_ttc: number; montant_paye: number }
  >;
  encaissements_7j: { date: string; montant: number }[];
  abonnements_echeance_30j: number;
};

export type DashboardStats = {
  agents_disponibles: number;
  agents_actifs: number;
  vacations_actives: number;
  rondes_du_jour: number;
  controles_du_jour: number;
  anomalies_ouvertes: number;
  sites_surveilles: number;
  postes_sous_effectif?: number;
  docs_expirants_count?: number;
  controles_7j: { date: string; count: number }[];
  controle_coverage?: {
    sites_actifs_aujourdhui: number;
    sites_controles_aujourdhui: number;
    taux_couverture_pct: number;
    sites_sans_controle: Array<{ id: string; nom: string }>;
  };
  agents_par_statut: Record<string, number>;
  anomalies_par_type: Record<string, number>;
  commercial?: DashboardCommercialStats;
  rh?: DashboardRhStats;
  aujourdhui?: {
    anomalies_a_traiter: Array<{
      id: string;
      type: string;
      gravite: string;
      statut: string;
      signale_at: string;
      site?: Pick<Site, "id" | "nom"> | null;
    }>;
    vacations: Array<{
      id: string;
      heure_debut: string;
      heure_fin: string;
      statut: string;
      agent?: Pick<Agent, "id" | "nom" | "prenom" | "matricule"> | null;
      site?: Pick<Site, "id" | "nom"> | null;
      poste?: { id: string; nom: string } | null;
    }>;
    couverture_problemes: PosteCoverage[];
    docs_expirants: Array<{
      id: string;
      matricule: string;
      nom: string;
      prenom: string;
      date_expiration_contrat?: string | null;
      date_expiration_permis?: string | null;
    }>;
  };
};

export type AuthLoginResult = {
  token: string;
  user: User;
};
