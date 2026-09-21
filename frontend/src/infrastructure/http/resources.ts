import { api } from "@/infrastructure/http/apiClient";
import type {
  DataResponse,
  ListParams,
  PaginatedResponse,
} from "@/domain/types/api";
import type {
  Absence,
  Abonnement,
  Agent,
  Anomalie,
  BulletinPaie,
  Checkpoint,
  Client,
  Contrat,
  ContratAlerte,
  Controle,
  DashboardStats,
  Facture,
  FeatureFlag,
  Grade,
  JournalAudit,
  MenuOverride,
  Offre,
  Paiement,
  PeriodePaie,
  Poste,
  PosteCoverage,
  RapportExport,
  RuntimeConfig,
  SisNotification,
  Site,
  SoldeCongesMouvement,
  StatutAnomalie,
  StatutVacation,
  SystemHistoryEntry,
  SystemPermission,
  SystemRole,
  SystemSetting,
  User,
  Vacation,
  Ville,
  Zone,
} from "@/domain/types/entities";

function toQuery(params?: ListParams) {
  const q: Record<string, string | number | boolean> = {};
  if (!params) return q;
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== "") {
      q[key] = value;
    }
  }
  return q;
}

export const clientsApi = {
  list: (params?: ListParams & { type?: string; statut?: string }) =>
    api.get<PaginatedResponse<Client>>("/clients", { params: toQuery(params) }),
  create: (payload: Partial<Client>) =>
    api.post<DataResponse<Client>>("/clients", payload),
  update: (id: string, payload: Partial<Client>) =>
    api.put<DataResponse<Client>>(`/clients/${id}`, payload),
  destroy: (id: string) => api.delete<void>(`/clients/${id}`),
  show: (id: string) => api.get<DataResponse<Client>>(`/clients/${id}`),
};

export const zonesApi = {
  list: (params?: ListParams) =>
    api.get<PaginatedResponse<Zone>>("/zones", { params: toQuery(params) }),
  create: (payload: { nom: string; description?: string }) =>
    api.post<DataResponse<Zone>>("/zones", payload),
  update: (
    id: string,
    payload: { nom?: string; description?: string | null },
  ) => api.put<DataResponse<Zone>>(`/zones/${id}`, payload),
  syncControleurs: (id: string, agentIds: string[]) =>
    api.put<DataResponse<Zone>>(`/zones/${id}/controleurs`, {
      agent_ids: agentIds,
    }),
  syncReleve: (
    id: string,
    payload: {
      releve_depuis: string;
      releve_jusque: string;
      ordre_agent_ids?: string[];
    },
  ) => api.put<DataResponse<Zone>>(`/zones/${id}/releve`, payload),
  destroy: (id: string) => api.delete<void>(`/zones/${id}`),
};

export type SiteCreatePayload = Partial<Site> & {
  postes?: Array<{
    nom: string;
    agents_requis?: number;
    heure_debut?: string;
    heure_fin?: string;
  }>;
};

export const sitesApi = {
  list: (
    params?: ListParams & {
      client_id?: string;
      zone_id?: string;
      interne?: number | boolean;
    },
  ) =>
    api.get<PaginatedResponse<Site>>("/sites", { params: toQuery(params) }),
  create: (payload: SiteCreatePayload) =>
    api.post<DataResponse<Site>>("/sites", payload),
  update: (id: string, payload: Partial<Site>) =>
    api.put<DataResponse<Site>>(`/sites/${id}`, payload),
  destroy: (id: string) => api.delete<void>(`/sites/${id}`),
  show: (id: string) => api.get<DataResponse<Site>>(`/sites/${id}`),
};

export const agentsApi = {
  list: (
    params?: ListParams & {
      type?: string;
      statut?: string;
      grade_id?: string;
      ville_id?: string;
      all?: boolean;
    },
  ) =>
    api.get<PaginatedResponse<Agent>>("/agents", { params: toQuery(params) }),
  create: (
    payload: Partial<Agent> & {
      pin?: string;
      email?: string;
      contrat?: {
        type: string;
        reference?: string | null;
        date_debut: string;
        date_fin?: string | null;
        periode_essai_mois?: number | null;
        salaire_brut?: number | null;
        salaire_net?: number | null;
        statut?: string;
      };
    },
  ) =>
    api.post<DataResponse<Agent & { plain_pin?: string }>>("/agents", payload),
  update: (
    id: string,
    payload: Partial<Agent> & {
      pin?: string;
      email?: string;
      contrat?: {
        type: string;
        reference?: string | null;
        date_debut: string;
        date_fin?: string | null;
        periode_essai_mois?: number | null;
        salaire_brut?: number | null;
        salaire_net?: number | null;
        statut?: string;
      };
    },
  ) => api.put<DataResponse<Agent>>(`/agents/${id}`, payload),
  destroy: (id: string) => api.delete<void>(`/agents/${id}`),
  show: (id: string) => api.get<DataResponse<Agent>>(`/agents/${id}`),
  uploadDocument: (
    id: string,
    collection: "piece_identite" | "permis",
    file: File,
  ) => {
    const formData = new FormData();
    formData.append("file", file);
    return api.post<DataResponse<Agent>>(
      `/agents/${id}/documents/${collection}`,
      formData,
      { headers: { "Content-Type": "multipart/form-data" } },
    );
  },
  downloadDocument: (id: string, collection: "piece_identite" | "permis") =>
    `/agents/${id}/documents/${collection}`,
  congesMouvements: (id: string, params?: ListParams) =>
    api.get<PaginatedResponse<SoldeCongesMouvement>>(
      `/agents/${id}/conges/mouvements`,
      { params: toQuery(params) },
    ),
  bulletins: (id: string, params?: ListParams & { statut?: string }) =>
    api.get<PaginatedResponse<BulletinPaie>>(`/agents/${id}/bulletins`, {
      params: toQuery(params),
    }),
  accorderConges: (id: string, payload?: { jours?: number }) =>
    api.post<DataResponse<Agent>>(`/agents/${id}/conges/acquisition`, payload ?? {}),
};

export const vacationsApi = {
  list: (
    params?: ListParams & {
      agent_id?: string;
      site_id?: string;
      poste_id?: string;
      statut?: string;
      en_poste?: number | boolean;
      date?: string;
      site_interne?: number | boolean;
    },
  ) =>
    api.get<PaginatedResponse<Vacation>>("/vacations", {
      params: toQuery(params),
    }),
  create: (payload: {
    agent_id: string;
    site_id: string;
    poste_id?: string | null;
    date_debut: string;
    date_fin?: string | null;
    heure_debut: string;
    heure_fin: string;
    statut?: StatutVacation;
    annuler_conflits?: boolean;
  }) => api.post<DataResponse<Vacation>>("/vacations", payload),
  createBulk: (payload: {
    vacations: Array<{
      agent_id: string;
      site_id: string;
      poste_id?: string | null;
      date_debut: string;
      date_fin?: string | null;
      heure_debut: string;
      heure_fin: string;
    }>;
    annuler_conflits?: boolean;
    replace?: {
      poste_id: string;
      date_debut: string;
      date_fin: string;
      agent_ids?: string[];
      /** poste = vide tout le poste sur la période (Planifier). */
      scope?: "agents" | "poste";
    };
  }) =>
    api.post<
      DataResponse<{
        created: number;
        removed: number;
      }>
    >("/vacations/bulk", payload),
  update: (id: string, payload: Partial<Vacation>) =>
    api.put<DataResponse<Vacation>>(`/vacations/${id}`, payload),
  destroy: (id: string) => api.delete<void>(`/vacations/${id}`),
  recouvrir: (id: string, payload: { agent_id: string }) =>
    api.post<DataResponse<Vacation>>(`/vacations/${id}/recouvrir`, payload),
  conflits: (params: {
    agent_id: string;
    date_debut: string;
    date_fin?: string | null;
    heure_debut: string;
    heure_fin: string;
  }) =>
    api.get<{ data: Vacation[]; meta: { count: number } }>(
      "/vacations/conflits",
      { params: toQuery(params as ListParams) },
    ),
};

export const anomaliesApi = {
  list: (
    params?: ListParams & {
      statut?: string;
      gravite?: string;
      type?: string;
      site_id?: string;
      a_traiter?: boolean;
      recent_30j?: boolean;
      q?: string;
    },
  ) =>
    api.get<PaginatedResponse<Anomalie>>("/anomalies", {
      params: toQuery(params),
    }),
  create: (payload: {
    signale_par_id: string;
    site_id: string;
    type: string;
    gravite?: string;
    commentaire?: string;
    client_uuid?: string;
  }) => api.post<DataResponse<Anomalie>>("/anomalies", payload),
  updateStatut: (
    id: string,
    payload: { statut: StatutAnomalie; assigne_a_id?: string | null },
  ) => api.patch<DataResponse<Anomalie>>(`/anomalies/${id}/statut`, payload),
};

export const controlesApi = {
  list: (
    params?: ListParams & {
      site_id?: string;
      controle_agent_id?: string;
      resultat?: string;
      q?: string;
      aujourd_hui?: number | boolean;
      mes_controles?: number | boolean;
      recent_30j?: boolean;
    },
  ) =>
    api.get<PaginatedResponse<Controle>>("/controles", {
      params: toQuery(params),
    }),
  createPresence: (payload: {
    agent_id?: string | null;
    controle_agent_id: string;
    site_id: string;
    poste_id?: string | null;
    latitude?: number | null;
    longitude?: number | null;
    resultat: "present" | "absent";
    commentaire?: string;
    client_uuid?: string;
    photo_base64?: string;
  }) => api.post<DataResponse<Controle>>("/controles", payload),
};

export const facturesApi = {
  list: (params?: ListParams & {
    client_id?: string;
    statut?: string;
    periodicite?: string;
    statut_paiement?: string;
    echeance_30j?: boolean;
  }) =>
    api.get<PaginatedResponse<Facture>>("/factures", {
      params: toQuery(params),
    }),
  createProforma: (payload: {
    client_id?: string | null;
    client_nom?: string | null;
    client_adresse?: string | null;
    client_telephone?: string | null;
    client_email?: string | null;
    abonnement_id?: string | null;
    creer_abonnement?: boolean;
    offre_id?: string | null;
    offre_ids?: string[];
    site_id?: string | null;
    periodicite?: string | null;
    date_debut_service?: string | null;
    date_fin_service?: string | null;
    delai_paiement_jours?: number;
    notes?: string | null;
    conditions_paiement?: string | null;
    delai_validite?: string | null;
    duree_contrat_min?: string | null;
    signataire_nom?: string | null;
    signataire_fonction?: string | null;
    lignes: {
      offre_id?: string | null;
      code_article?: string | null;
      description: string;
      quantite: number;
      prix_unitaire: number;
    }[];
  }) => api.post<DataResponse<Facture>>("/factures/proforma", payload),
  updateProforma: (
    id: string,
    payload: {
      client_id?: string | null;
      client_nom?: string | null;
      client_adresse?: string | null;
      client_telephone?: string | null;
      client_email?: string | null;
      abonnement_id?: string | null;
      site_id?: string | null;
      periodicite?: string | null;
      date_debut_service?: string | null;
      date_fin_service?: string | null;
      delai_paiement_jours?: number;
      notes?: string | null;
      conditions_paiement?: string | null;
      delai_validite?: string | null;
      duree_contrat_min?: string | null;
      signataire_nom?: string | null;
      signataire_fonction?: string | null;
      lignes: {
        offre_id?: string | null;
        code_article?: string | null;
        description: string;
        quantite: number;
        prix_unitaire: number;
      }[];
    },
  ) => api.put<DataResponse<Facture>>(`/factures/${id}/proforma`, payload),
  show: (id: string) => api.get<DataResponse<Facture>>(`/factures/${id}`),
  updateStatut: (id: string, statut: string) =>
    api.patch<DataResponse<Facture>>(`/factures/${id}/statut`, { statut }),
  generer: (payload: {
    client_id: string;
    date_debut: string;
    date_fin: string;
  }) => api.post<DataResponse<Facture>>("/factures/generer", payload),
  genererPdf: (id: string) =>
    api.post<DataResponse<Facture>>(`/factures/${id}/generer-pdf`),
};

export const postesApi = {
  list: (
    params?: ListParams & { site_id?: string; site_interne?: boolean | number },
  ) => api.get<PaginatedResponse<Poste>>("/postes", { params: toQuery(params) }),
  coverage: (params?: { date?: string; site_id?: string }) =>
    api.get<{ data: PosteCoverage[]; meta: { date: string; count: number } }>(
      "/postes/coverage",
      { params: toQuery(params) },
    ),
  create: (
    siteId: string,
    payload: {
      nom: string;
      agents_requis?: number | string | null;
      mode_effectif?: "ensemble" | "alternance" | null;
      heure_debut?: string | null;
      heure_fin?: string | null;
      heure_debut_nuit?: string | null;
      heure_fin_nuit?: string | null;
    },
  ) => api.post<DataResponse<Poste>>(`/sites/${siteId}/postes`, payload),
  update: (
    siteId: string,
    posteId: string,
    payload: {
      nom?: string;
      agents_requis?: number | string | null;
      mode_effectif?: "ensemble" | "alternance" | null;
      heure_debut?: string | null;
      heure_fin?: string | null;
      heure_debut_nuit?: string | null;
      heure_fin_nuit?: string | null;
    },
  ) =>
    api.put<DataResponse<Poste>>(`/sites/${siteId}/postes/${posteId}`, payload),
  destroy: (siteId: string, posteId: string) =>
    api.delete<void>(`/sites/${siteId}/postes/${posteId}`),
};

export const checkpointsApi = {
  list: (siteId: string) =>
    api.get<{ data: Checkpoint[] }>(`/sites/${siteId}/checkpoints`),
  create: (
    siteId: string,
    payload: {
      nom: string;
      code_qr?: string | null;
      latitude?: number | string | null;
      longitude?: number | string | null;
      ordre?: number | string | null;
    },
  ) =>
    api.post<DataResponse<Checkpoint>>(`/sites/${siteId}/checkpoints`, payload),
  destroy: (siteId: string, checkpointId: string) =>
    api.delete<void>(`/sites/${siteId}/checkpoints/${checkpointId}`),
};

export const perimetresApi = {
  list: (params?: ListParams & {
    statut?: string;
    zone_id?: string;
    sans_zone?: boolean;
    q?: string;
  }) =>
    api.get<DataResponse<Agent[]> | Agent[]>("/perimetres", {
      params: toQuery(params),
    }),
  sync: (agentId: string, payload: { items: Array<{ zone_id: string }> }) =>
    api.put<DataResponse<Agent>>(`/agents/${agentId}/perimetre`, payload),
};

export const offresApi = {
  list: (params?: ListParams) =>
    api.get<PaginatedResponse<Offre>>("/offres", { params: toQuery(params) }),
  create: (
    payload: Partial<Offre> & {
      libelle: string;
      prix_mensuel: string | number;
    },
  ) => api.post<DataResponse<Offre>>("/offres", payload),
  update: (id: string, payload: Partial<Offre>) =>
    api.put<DataResponse<Offre>>(`/offres/${id}`, payload),
  destroy: (id: string) => api.delete<void>(`/offres/${id}`),
};

export const abonnementsApi = {
  list: (params?: ListParams & {
    client_id?: string;
    statut?: string;
    periodicite?: string;
    echeance_30j?: boolean;
  }) =>
    api.get<PaginatedResponse<Abonnement>>("/abonnements", {
      params: toQuery(params),
    }),
  show: (id: string) => api.get<DataResponse<Abonnement>>(`/abonnements/${id}`),
  create: (payload: {
    client_id: string;
    offre_id: string;
    site_id?: string | null;
    periodicite: string;
    date_debut: string;
    date_fin?: string | null;
    statut?: string;
  }) => api.post<DataResponse<Abonnement>>("/abonnements", payload),
  update: (
    id: string,
    payload: Partial<{
      client_id: string;
      offre_id: string;
      site_id: string | null;
      periodicite: string;
      date_debut: string;
      date_fin: string | null;
      statut: string;
    }>,
  ) => api.put<DataResponse<Abonnement>>(`/abonnements/${id}`, payload),
  destroy: (id: string) => api.delete<void>(`/abonnements/${id}`),
};

export const paiementsApi = {
  list: (params?: ListParams & {
    facture_id?: string;
    client_id?: string;
    mode?: string;
    recent_30j?: boolean;
  }) =>
    api.get<PaginatedResponse<Paiement>>("/paiements", {
      params: toQuery(params),
    }),
  show: (id: string) => api.get<DataResponse<Paiement>>(`/paiements/${id}`),
  create: (payload: {
    facture_id: string;
    montant: number;
    date_paiement: string;
    mode: string;
    reference?: string | null;
    notes?: string | null;
  }) => api.post<DataResponse<Paiement>>("/paiements", payload),
  update: (
    id: string,
    payload: Partial<{
      facture_id: string;
      montant: number;
      date_paiement: string;
      mode: string;
      reference: string | null;
      notes: string | null;
    }>,
  ) => api.put<DataResponse<Paiement>>(`/paiements/${id}`, payload),
  destroy: (id: string) => api.delete<void>(`/paiements/${id}`),
};

export const usersApi = {
  list: (params?: ListParams) =>
    api.get<PaginatedResponse<User>>("/users", { params: toQuery(params) }),
  create: (payload: {
    nom: string;
    prenom: string;
    email: string;
    password: string;
    role?: string;
    statut?: string;
  }) => api.post<DataResponse<User>>("/users", payload),
  update: (
    id: string,
    payload: Partial<{
      nom: string;
      prenom: string;
      email: string;
      password: string;
      role: string;
      statut: string;
    }>,
  ) => api.put<DataResponse<User>>(`/users/${id}`, payload),
  destroy: (id: string) => api.delete<void>(`/users/${id}`),
};

export const auditApi = {
  list: (
    params?: ListParams & {
      action?: string;
      auditable_type?: string;
      user_id?: string;
    },
  ) =>
    api.get<PaginatedResponse<JournalAudit>>("/journal-audit", {
      params: toQuery(params),
    }),
  show: (id: string) =>
    api.get<DataResponse<JournalAudit>>(`/journal-audit/${id}`),
};

export const gradesApi = {
  list: (params?: ListParams) =>
    api.get<PaginatedResponse<Grade>>("/grades", { params: toQuery(params) }),
  create: (payload: {
    libelle: string;
    type_agent: string;
    description?: string | null;
  }) => api.post<DataResponse<Grade>>("/grades", payload),
  update: (
    id: string,
    payload: Partial<{
      libelle: string;
      type_agent: string;
      description: string | null;
    }>,
  ) => api.put<DataResponse<Grade>>(`/grades/${id}`, payload),
  destroy: (id: string) => api.delete<void>(`/grades/${id}`),
};

export const villesApi = {
  list: (params?: ListParams) =>
    api.get<PaginatedResponse<Ville>>("/villes", { params: toQuery(params) }),
  create: (payload: { libelle: string }) =>
    api.post<DataResponse<Ville>>("/villes", payload),
  update: (id: string, payload: Partial<{ libelle: string }>) =>
    api.put<DataResponse<Ville>>(`/villes/${id}`, payload),
  destroy: (id: string) => api.delete<void>(`/villes/${id}`),
};

export const absencesApi = {
  list: (params?: ListParams & { agent_id?: string; statut?: string; type?: string }) =>
    api.get<PaginatedResponse<Absence>>("/absences", {
      params: toQuery(params),
    }),
  create: (payload: {
    agent_id: string;
    type: string;
    date_debut: string;
    date_fin: string;
    motif?: string | null;
    statut?: string;
  }) => api.post<DataResponse<Absence>>("/absences", payload),
  update: (
    id: string,
    payload: Partial<{
      type: string;
      date_debut: string;
      date_fin: string;
      motif: string | null;
      statut: string;
    }>,
  ) => api.put<DataResponse<Absence>>(`/absences/${id}`, payload),
  destroy: (id: string) => api.delete<DataResponse<Absence>>(`/absences/${id}`),
};

export const contratsApi = {
  list: (params?: ListParams & { agent_id?: string; type?: string; statut?: string; surveillance?: boolean; valide?: boolean }) =>
    api.get<PaginatedResponse<Contrat>>("/contrats", {
      params: toQuery(params),
    }),
  create: (payload: {
    agent_id: string;
    type: string;
    reference?: string | null;
    date_debut: string;
    date_fin?: string | null;
    periode_essai_mois?: number | null;
    salaire_base?: number | string | null;
    indemnite_fonction?: number | string | null;
    prime_responsabilite?: number | string | null;
    prime_transport?: number | string | null;
    prime_entretien_tenue?: number | string | null;
    sursalaire?: number | string | null;
    nombre_enfants?: number | null;
    parts_igr?: number | string | null;
    salaire_brut?: number | string | null;
    salaire_net?: number | string | null;
    salaire?: number | string | null;
    statut?: string;
  }) => api.post<DataResponse<Contrat>>("/contrats", payload),
  update: (
    id: string,
    payload: Partial<{
      reference: string | null;
      date_debut: string;
      date_fin: string | null;
      periode_essai_mois: number | null;
      salaire_base: number | string | null;
      indemnite_fonction: number | string | null;
      prime_responsabilite: number | string | null;
      prime_transport: number | string | null;
      prime_entretien_tenue: number | string | null;
      sursalaire: number | string | null;
      nombre_enfants: number | null;
      parts_igr: number | string | null;
      salaire_brut: number | string | null;
      salaire_net: number | string | null;
      salaire: number | string | null;
      statut: string;
    }>,
  ) => api.put<DataResponse<Contrat>>(`/contrats/${id}`, payload),
  destroy: (id: string) => api.delete<void>(`/contrats/${id}`),
  alerts: () => api.get<{ data: ContratAlerte[] }>("/contrats/alerts"),
};

export const periodesPaieApi = {
  list: (
    params?: ListParams & {
      annee?: number | string;
      mois?: number | string;
      statut?: string;
    },
  ) =>
    api.get<PaginatedResponse<PeriodePaie>>("/periodes-paie", {
      params: toQuery(params),
    }),
  create: (payload: {
    mois: number;
    annee: number;
    commentaire?: string | null;
  }) => api.post<DataResponse<PeriodePaie>>("/periodes-paie", payload),
  show: (id: string) =>
    api.get<DataResponse<PeriodePaie>>(`/periodes-paie/${id}`),
  genererBulletins: (id: string) =>
    api.post<DataResponse<PeriodePaie>>(
      `/periodes-paie/${id}/generer-bulletins`,
    ),
  bulletins: (
    id: string,
    params?: ListParams & { statut?: string },
  ) =>
    api.get<PaginatedResponse<BulletinPaie>>(
      `/periodes-paie/${id}/bulletins`,
      { params: toQuery(params) },
    ),
  valider: (id: string) =>
    api.post<DataResponse<PeriodePaie>>(`/periodes-paie/${id}/valider`),
  cloturer: (id: string) =>
    api.post<DataResponse<PeriodePaie>>(`/periodes-paie/${id}/cloturer`),
};

export const bulletinsPaieApi = {
  show: (id: string) =>
    api.get<DataResponse<BulletinPaie>>(`/bulletins-paie/${id}`),
  genererPdf: (id: string) =>
    api.post<DataResponse<BulletinPaie>>(`/bulletins-paie/${id}/generer-pdf`),
  downloadPdf: (id: string) => `/bulletins-paie/${id}/pdf`,
  marquerPaye: (id: string) =>
    api.post<DataResponse<BulletinPaie>>(`/bulletins-paie/${id}/marquer-paye`),
};

export const notificationsApi = {
  list: (params?: ListParams & { non_lues?: boolean | number }) =>
    api.get<PaginatedResponse<SisNotification>>("/notifications", {
      params: toQuery(params),
    }),
  marquerLue: (id: string) =>
    api.post<DataResponse<SisNotification>>(`/notifications/${id}/lue`),
  marquerToutesLues: () =>
    api.post<void>("/notifications/marquer-toutes-lues"),
};

export const rapportsApi = {
  list: (params?: ListParams) =>
    api.get<PaginatedResponse<RapportExport>>("/rapports", {
      params: toQuery(params),
    }),
  generate: (
    type: string,
    payload?: { format?: string; filtres?: Record<string, unknown> },
  ) =>
    api.post<DataResponse<RapportExport>>(`/rapports/${type}`, payload ?? {}),
  show: (id: string) =>
    api.get<DataResponse<RapportExport>>(`/rapports/jobs/${id}`),
  downloadUrl: (id: string) => `/rapports/jobs/${id}/download`,
};

export const dashboardApi = {
  stats: (params?: {
    from?: string;
    to?: string;
    zone_id?: string;
    client_id?: string;
  }) =>
    api.get<DataResponse<DashboardStats>>("/dashboard/stats", {
      params: toQuery(params),
    }),
};

export const systemApi = {
  runtimeConfig: () =>
    api.get<DataResponse<RuntimeConfig>>("/system/runtime-config"),
  featureFlags: () =>
    api.get<DataResponse<FeatureFlag[]>>("/system/feature-flags"),
  updateFeatureFlag: (key: string, enabled: boolean) =>
    api.patch<DataResponse<FeatureFlag>>(`/system/feature-flags/${key}`, {
      enabled,
    }),
  updateFeatureFlagGroup: (group: string, enabled: boolean) =>
    api.patch<DataResponse<FeatureFlag[]>>(
      `/system/feature-flags/group/${encodeURIComponent(group)}`,
      { enabled },
    ),
  resetFeatureFlags: () =>
    api.post<DataResponse<FeatureFlag[]>>("/system/feature-flags/reset"),
  menuOverrides: () =>
    api.get<DataResponse<MenuOverride[]>>("/system/menu-overrides"),
  syncMenuOverrides: (items: Array<{ nav_key: string; visible: boolean }>) =>
    api.put<DataResponse<MenuOverride[]>>("/system/menu-overrides", { items }),
  history: () =>
    api.get<DataResponse<SystemHistoryEntry[]>>("/system/history"),
  settings: () =>
    api.get<DataResponse<SystemSetting[]>>("/system/settings"),
  updateSettings: (
    settings: Array<{ key: string; value: string | number | boolean | null }>,
  ) =>
    api.put<DataResponse<SystemSetting[]>>("/system/settings", { settings }),
  resetSettings: () =>
    api.post<DataResponse<SystemSetting[]>>("/system/settings/reset"),
  permissions: () =>
    api.get<DataResponse<SystemPermission[]>>("/system/permissions"),
  roles: () => api.get<DataResponse<SystemRole[]>>("/system/roles"),
  createRole: (payload: { name: string; permissions: string[] }) =>
    api.post<DataResponse<SystemRole>>("/system/roles", payload),
  updateRole: (
    id: string,
    payload: Partial<{ name: string; permissions: string[] }>,
  ) => api.patch<DataResponse<SystemRole>>(`/system/roles/${id}`, payload),
  deleteRole: (id: string) => api.delete(`/system/roles/${id}`),
};
