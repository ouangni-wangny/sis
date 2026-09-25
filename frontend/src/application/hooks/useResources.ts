"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  absencesApi,
  abonnementsApi,
  agentsApi,
  anomaliesApi,
  auditApi,
  bulletinsPaieApi,
  categoriesDepenseApi,
  checkpointsApi,
  contratsApi,
  controlesApi,
  dashboardApi,
  facturesApi,
  gradesApi,
  modesPaiementApi,
  notificationsApi,
  offresApi,
  paiementsApi,
  periodesPaieApi,
  perimetresApi,
  postesApi,
  rapportsApi,
  sitesApi,
  tresorerieApi,
  usersApi,
  vacationsApi,
  villesApi,
} from "@/infrastructure/http/resources";
import type { SiteCreatePayload } from "@/infrastructure/http/resources";
import type { ListParams } from "@/domain/types/api";
import type {
  Agent,
  Site,
  StatutAnomalie,
  Vacation,
} from "@/domain/types/entities";

export function useAgents(
  params?: ListParams & {
    type?: string;
    statut?: string;
    grade_id?: string;
    ville_id?: string;
    pool_siege?: boolean;
    contrat_valide?: boolean;
    all?: boolean;
  },
  options?: { enabled?: boolean; refetchOnMount?: boolean | "always" },
) {
  return useQuery({
    queryKey: ["agents", params],
    queryFn: () => agentsApi.list(params),
    enabled: options?.enabled ?? true,
    refetchOnMount: options?.refetchOnMount,
  });
}

export function useExportAgentsPdf() {
  return useMutation({
    mutationFn: (
      params?: ListParams & {
        type?: string;
        statut?: string;
        grade_id?: string;
        ville_id?: string;
        pool_siege?: boolean;
        contrat_valide?: boolean;
      },
    ) => agentsApi.exportPdf(params),
  });
}

export function useAgent(id: string | undefined) {
  return useQuery({
    queryKey: ["agents", "detail", id],
    queryFn: () => agentsApi.show(id as string),
    enabled: !!id,
  });
}

function invalidateAgents(qc: ReturnType<typeof useQueryClient>) {
  void qc.invalidateQueries({ queryKey: ["agents"] });
  void qc.invalidateQueries({ queryKey: ["perimetres"] });
}

export function useCreateAgent() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Partial<Agent> & { pin?: string; email?: string }) =>
      agentsApi.create(payload),
    onSuccess: () => invalidateAgents(qc),
  });
}

export function useUpdateAgent() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string;
      payload: Partial<Agent> & { pin?: string; email?: string };
    }) => agentsApi.update(id, payload),
    onSuccess: () => invalidateAgents(qc),
  });
}

export function useDeleteAgent() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => agentsApi.destroy(id),
    onSuccess: () => invalidateAgents(qc),
  });
}

export function useSites(
  params?: ListParams & {
    client_id?: string;
    zone_id?: string;
    interne?: number | boolean;
  },
  options?: { enabled?: boolean },
) {
  return useQuery({
    queryKey: ["sites", params],
    queryFn: () => sitesApi.list(params),
    enabled: options?.enabled ?? true,
  });
}

export function useSite(id: string | undefined, options?: { enabled?: boolean }) {
  return useQuery({
    queryKey: ["sites", id],
    queryFn: () => sitesApi.show(id as string),
    enabled: (options?.enabled ?? true) && !!id,
  });
}

function invalidateSites(qc: ReturnType<typeof useQueryClient>) {
  void qc.invalidateQueries({ queryKey: ["sites"] });
  void qc.invalidateQueries({ queryKey: ["postes"] });
}

export function useCreateSite() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: SiteCreatePayload) => sitesApi.create(payload),
    onSuccess: () => invalidateSites(qc),
  });
}

export function useUpdateSite() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: Partial<Site> }) =>
      sitesApi.update(id, payload),
    onSuccess: () => invalidateSites(qc),
  });
}

export function useDeleteSite() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => sitesApi.destroy(id),
    onSuccess: () => invalidateSites(qc),
  });
}

export function useVacations(
  params?: ListParams & {
    agent_id?: string;
    site_id?: string;
    poste_id?: string;
    statut?: string;
    en_poste?: number | boolean;
    date?: string;
    site_interne?: number | boolean;
  },
  options?: { enabled?: boolean },
) {
  return useQuery({
    queryKey: ["vacations", params],
    queryFn: () => vacationsApi.list(params),
    enabled: options?.enabled ?? true,
  });
}

function invalidateVacations(qc: ReturnType<typeof useQueryClient>) {
  void qc.invalidateQueries({ queryKey: ["vacations"] });
  void qc.invalidateQueries({ queryKey: ["postes", "coverage"] });
  void qc.invalidateQueries({ queryKey: ["dashboard"] });
  void qc.invalidateQueries({ queryKey: ["agents"] });
}

export function useCreateVacation() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: {
      agent_id: string;
      site_id: string;
      poste_id?: string | null;
      date_debut: string;
      date_fin?: string | null;
      heure_debut: string;
      heure_fin: string;
      statut?: Vacation["statut"];
      annuler_conflits?: boolean;
    }) => vacationsApi.create(payload),
    onSuccess: () => invalidateVacations(qc),
  });
}

export function useCreateVacationsBulk() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Parameters<typeof vacationsApi.createBulk>[0]) =>
      vacationsApi.createBulk(payload),
    onSuccess: () => invalidateVacations(qc),
  });
}

export function useUpdateVacation() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: Partial<Vacation> }) =>
      vacationsApi.update(id, payload),
    onSuccess: () => invalidateVacations(qc),
  });
}

export function useRecouvrirVacation() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      id,
      agent_id,
    }: {
      id: string;
      agent_id: string;
    }) => vacationsApi.recouvrir(id, { agent_id }),
    onSuccess: () => invalidateVacations(qc),
  });
}

export function useDeleteVacation() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => vacationsApi.destroy(id),
    onSuccess: () => invalidateVacations(qc),
  });
}

/** Supprime plusieurs vacations puis invalide le cache une fois. */
export function useDeleteVacationsBulk() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (ids: string[]) => {
      for (const id of ids) {
        await vacationsApi.destroy(id);
      }
      return ids.length;
    },
    onSuccess: () => invalidateVacations(qc),
  });
}

export function useVacationConflits() {
  return useMutation({
    mutationFn: (params: {
      agent_id: string;
      date_debut: string;
      date_fin?: string | null;
      heure_debut: string;
      heure_fin: string;
    }) => vacationsApi.conflits(params),
  });
}

export function useAnomalies(
  params?: ListParams & {
    statut?: string;
    gravite?: string;
    type?: string;
    site_id?: string;
    a_traiter?: boolean;
    recent_30j?: boolean;
    q?: string;
  },
) {
  return useQuery({
    queryKey: ["anomalies", params],
    queryFn: () => anomaliesApi.list(params),
  });
}

export function useUpdateAnomalieStatut() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string;
      payload: { statut: StatutAnomalie; assigne_a_id?: string | null };
    }) => anomaliesApi.updateStatut(id, payload),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["anomalies"] });
      void qc.invalidateQueries({ queryKey: ["dashboard"] });
    },
  });
}

export function useControles(
  params?: ListParams & {
    site_id?: string;
    controle_agent_id?: string;
    resultat?: string;
    q?: string;
    aujourd_hui?: number | boolean;
    mes_controles?: number | boolean;
    recent_30j?: boolean;
  },
  options?: { enabled?: boolean },
) {
  return useQuery({
    queryKey: ["controles", params],
    queryFn: () => controlesApi.list(params),
    enabled: options?.enabled ?? true,
  });
}

export function useCreateControlePresence() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: controlesApi.createPresence,
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["controles"] });
      void qc.invalidateQueries({ queryKey: ["vacations"] });
      void qc.invalidateQueries({ queryKey: ["anomalies"] });
    },
  });
}

export function useFactures(
  params?: ListParams & {
    client_id?: string;
    statut?: string;
    periodicite?: string;
    statut_paiement?: string;
    echeance_30j?: boolean;
    a_recouvrer?: boolean;
    retard?: boolean;
  },
) {
  return useQuery({
    queryKey: ["factures", params],
    queryFn: () => facturesApi.list(params),
  });
}

export function useFacture(id?: string) {
  return useQuery({
    queryKey: ["factures", id],
    queryFn: () => facturesApi.show(id!),
    enabled: !!id,
  });
}

export function useCreateProformaFacture() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: facturesApi.createProforma,
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["factures"] }),
  });
}

export function useUpdateProformaFacture() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string;
      payload: Parameters<typeof facturesApi.updateProforma>[1];
    }) => facturesApi.updateProforma(id, payload),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["factures"] }),
  });
}

export function useUpdateFactureStatut() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, statut }: { id: string; statut: string }) =>
      facturesApi.updateStatut(id, statut),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["factures"] });
      void qc.invalidateQueries({ queryKey: ["abonnements"] });
      void qc.invalidateQueries({ queryKey: ["clients"] });
    },
  });
}

export function useGenererFacture() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: facturesApi.generer,
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["factures"] }),
  });
}

export function useGenererFacturePdf() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => facturesApi.genererPdf(id),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["factures"] }),
  });
}

export function usePostes(
  params?: ListParams & { site_id?: string; site_interne?: boolean | number },
  options?: { enabled?: boolean; refetchOnMount?: boolean | "always" },
) {
  return useQuery({
    queryKey: ["postes", params],
    queryFn: () => postesApi.list(params),
    enabled: options?.enabled ?? true,
    refetchOnMount: options?.refetchOnMount,
  });
}

export function usePosteCoverage(
  params?: { date?: string; site_id?: string },
  options?: { enabled?: boolean },
) {
  return useQuery({
    queryKey: ["postes", "coverage", params],
    queryFn: () => postesApi.coverage(params),
    enabled: options?.enabled ?? true,
  });
}

function invalidatePostes(qc: ReturnType<typeof useQueryClient>) {
  void qc.invalidateQueries({ queryKey: ["postes"] });
}

export function useCreatePoste() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      siteId,
      payload,
    }: {
      siteId: string;
      payload: Parameters<typeof postesApi.create>[1];
    }) => postesApi.create(siteId, payload),
    onSuccess: () => invalidatePostes(qc),
  });
}

export function useUpdatePoste() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      siteId,
      posteId,
      payload,
    }: {
      siteId: string;
      posteId: string;
      payload: Parameters<typeof postesApi.update>[2];
    }) => postesApi.update(siteId, posteId, payload),
    onSuccess: () => invalidatePostes(qc),
  });
}

export function useDeletePoste() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ siteId, posteId }: { siteId: string; posteId: string }) =>
      postesApi.destroy(siteId, posteId),
    onSuccess: () => invalidatePostes(qc),
  });
}

export function useCheckpoints(siteId?: string) {
  return useQuery({
    queryKey: ["checkpoints", siteId],
    queryFn: () => checkpointsApi.list(siteId as string),
    enabled: !!siteId,
  });
}

export function useCreateCheckpoint() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      siteId,
      payload,
    }: {
      siteId: string;
      payload: Parameters<typeof checkpointsApi.create>[1];
    }) => checkpointsApi.create(siteId, payload),
    onSuccess: (_data, variables) => {
      void qc.invalidateQueries({
        queryKey: ["checkpoints", variables.siteId],
      });
    },
  });
}

export function useDeleteCheckpoint() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      siteId,
      checkpointId,
    }: {
      siteId: string;
      checkpointId: string;
    }) => checkpointsApi.destroy(siteId, checkpointId),
    onSuccess: (_data, variables) => {
      void qc.invalidateQueries({
        queryKey: ["checkpoints", variables.siteId],
      });
    },
  });
}

export function usePerimetres(
  params?: ListParams & {
    statut?: string;
    zone_id?: string;
    sans_zone?: boolean;
    q?: string;
  },
) {
  return useQuery({
    queryKey: ["perimetres", params],
    queryFn: async () => {
      const res = await perimetresApi.list(params);
      const data = Array.isArray(res) ? res : (res.data ?? []);
      return { data: data as Agent[] };
    },
  });
}

export function useSyncPerimetre() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      agentId,
      items,
    }: {
      agentId: string;
      items: Array<{ zone_id: string }>;
    }) => perimetresApi.sync(agentId, { items }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["perimetres"] });
      void qc.invalidateQueries({ queryKey: ["agents"] });
    },
  });
}

export function useOffres(params?: ListParams) {
  return useQuery({
    queryKey: ["offres", params],
    queryFn: () => offresApi.list(params),
  });
}

export function useCreateOffre() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: offresApi.create,
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["offres"] }),
  });
}

export function useUpdateOffre() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string;
      payload: Parameters<typeof offresApi.update>[1];
    }) => offresApi.update(id, payload),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["offres"] }),
  });
}

export function useDeleteOffre() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => offresApi.destroy(id),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["offres"] }),
  });
}

export function useAbonnements(
  params?: ListParams & {
    client_id?: string;
    statut?: string;
    periodicite?: string;
    echeance_30j?: boolean;
  },
) {
  return useQuery({
    queryKey: ["abonnements", params],
    queryFn: () => abonnementsApi.list(params),
  });
}

export function useAbonnement(id: string | undefined) {
  return useQuery({
    queryKey: ["abonnements", id],
    queryFn: () => abonnementsApi.show(id!),
    enabled: Boolean(id),
  });
}

export function useCreateAbonnement() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: abonnementsApi.create,
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["abonnements"] }),
  });
}

export function useUpdateAbonnement() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string;
      payload: Parameters<typeof abonnementsApi.update>[1];
    }) => abonnementsApi.update(id, payload),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["abonnements"] }),
  });
}

export function useDeleteAbonnement() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => abonnementsApi.destroy(id),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["abonnements"] }),
  });
}

export function usePaiements(
  params?: ListParams & {
    facture_id?: string;
    client_id?: string;
    mode?: string;
    recent_30j?: boolean;
  },
) {
  return useQuery({
    queryKey: ["paiements", params],
    queryFn: () => paiementsApi.list(params),
  });
}

export function useCreatePaiement() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: paiementsApi.create,
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["paiements"] });
      void qc.invalidateQueries({ queryKey: ["factures"] });
      void qc.invalidateQueries({ queryKey: ["tresorerie"] });
    },
  });
}

export function useUpdatePaiement() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string;
      payload: Parameters<typeof paiementsApi.update>[1];
    }) => paiementsApi.update(id, payload),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["paiements"] });
      void qc.invalidateQueries({ queryKey: ["factures"] });
      void qc.invalidateQueries({ queryKey: ["tresorerie"] });
    },
  });
}

export function useDeletePaiement() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => paiementsApi.destroy(id),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["paiements"] });
      void qc.invalidateQueries({ queryKey: ["factures"] });
      void qc.invalidateQueries({ queryKey: ["tresorerie"] });
    },
  });
}

export function useUsers(params?: ListParams) {
  return useQuery({
    queryKey: ["users", params],
    queryFn: () => usersApi.list(params),
  });
}

export function useCreateUser() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: usersApi.create,
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["users"] }),
  });
}

export function useUpdateUser() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string;
      payload: Parameters<typeof usersApi.update>[1];
    }) => usersApi.update(id, payload),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["users"] }),
  });
}

export function useDeleteUser() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => usersApi.destroy(id),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["users"] }),
  });
}

export function useAudit(
  params?: ListParams & {
    action?: string;
    auditable_type?: string;
    user_id?: string;
  },
) {
  return useQuery({
    queryKey: ["audit", params],
    queryFn: () => auditApi.list(params),
  });
}

export function useAuditEntry(id: string | undefined) {
  return useQuery({
    queryKey: ["audit", "detail", id],
    queryFn: () => auditApi.show(id as string),
    enabled: !!id,
  });
}

export function useGrades(params?: ListParams) {
  return useQuery({
    queryKey: ["grades", params],
    queryFn: () => gradesApi.list(params),
  });
}

export function useCreateGrade() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: gradesApi.create,
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["grades"] }),
  });
}

export function useUpdateGrade() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string;
      payload: Parameters<typeof gradesApi.update>[1];
    }) => gradesApi.update(id, payload),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["grades"] }),
  });
}

export function useDeleteGrade() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => gradesApi.destroy(id),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["grades"] }),
  });
}

export function useVilles(params?: ListParams) {
  return useQuery({
    queryKey: ["villes", params],
    queryFn: () => villesApi.list(params),
  });
}

export function useCreateVille() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: villesApi.create,
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["villes"] }),
  });
}

export function useUpdateVille() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string;
      payload: Parameters<typeof villesApi.update>[1];
    }) => villesApi.update(id, payload),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["villes"] }),
  });
}

export function useDeleteVille() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => villesApi.destroy(id),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["villes"] }),
  });
}

export function useCategoriesDepenseList(
  params?: ListParams & { actif_only?: boolean | number },
) {
  return useQuery({
    queryKey: ["categories-depense", params],
    queryFn: () => categoriesDepenseApi.list(params),
  });
}

export function useCreateCategorieDepense() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: categoriesDepenseApi.create,
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["categories-depense"] });
      void qc.invalidateQueries({ queryKey: ["tresorerie", "categories-depense"] });
    },
  });
}

export function useUpdateCategorieDepense() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string;
      payload: Parameters<typeof categoriesDepenseApi.update>[1];
    }) => categoriesDepenseApi.update(id, payload),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["categories-depense"] });
      void qc.invalidateQueries({ queryKey: ["tresorerie", "categories-depense"] });
    },
  });
}

export function useDeleteCategorieDepense() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => categoriesDepenseApi.destroy(id),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["categories-depense"] });
      void qc.invalidateQueries({ queryKey: ["tresorerie", "categories-depense"] });
    },
  });
}

export function useModesPaiement(
  params?: ListParams & { actif_only?: boolean | number },
) {
  return useQuery({
    queryKey: ["modes-paiement", params],
    queryFn: () => modesPaiementApi.list(params),
  });
}

export function useModesPaiementOptions(options?: { enabled?: boolean }) {
  return useQuery({
    queryKey: ["modes-paiement", "options"],
    queryFn: () => modesPaiementApi.listAll({ actif_only: 1 }),
    enabled: options?.enabled ?? true,
  });
}

export function useCreateModePaiement() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: modesPaiementApi.create,
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["modes-paiement"] }),
  });
}

export function useUpdateModePaiement() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string;
      payload: Parameters<typeof modesPaiementApi.update>[1];
    }) => modesPaiementApi.update(id, payload),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["modes-paiement"] }),
  });
}

export function useDeleteModePaiement() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => modesPaiementApi.destroy(id),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["modes-paiement"] }),
  });
}

export function useCreateCompteTresorerie() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: tresorerieApi.createCompte,
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["tresorerie"] }),
  });
}

export function useUpdateCompteTresorerie() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string;
      payload: Parameters<typeof tresorerieApi.updateCompte>[1];
    }) => tresorerieApi.updateCompte(id, payload),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["tresorerie"] }),
  });
}

export function useDeleteCompteTresorerie() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => tresorerieApi.destroyCompte(id),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["tresorerie"] }),
  });
}

function invalidateAbsenceSideEffects(qc: ReturnType<typeof useQueryClient>) {
  void qc.invalidateQueries({ queryKey: ["absences"] });
  void qc.invalidateQueries({ queryKey: ["vacations"] });
  void qc.invalidateQueries({ queryKey: ["postes", "coverage"] });
  void qc.invalidateQueries({ queryKey: ["agents"] });
  void qc.invalidateQueries({ queryKey: ["dashboard"] });
}

export function useAbsences(
  params?: ListParams & { agent_id?: string; statut?: string; type?: string },
  options?: { enabled?: boolean },
) {
  return useQuery({
    queryKey: ["absences", params],
    queryFn: () => absencesApi.list(params),
    enabled: options?.enabled ?? true,
  });
}

export function useCreateAbsence() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: absencesApi.create,
    onSuccess: () => invalidateAbsenceSideEffects(qc),
  });
}

export function useUpdateAbsence() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string;
      payload: Parameters<typeof absencesApi.update>[1];
    }) => absencesApi.update(id, payload),
    onSuccess: () => invalidateAbsenceSideEffects(qc),
  });
}

export function useDeleteAbsence() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => absencesApi.destroy(id),
    onSuccess: () => invalidateAbsenceSideEffects(qc),
  });
}

export function useContrats(
  params?: ListParams & { agent_id?: string; type?: string; statut?: string; surveillance?: boolean; valide?: boolean },
  options?: { enabled?: boolean },
) {
  return useQuery({
    queryKey: ["contrats", params],
    queryFn: () => contratsApi.list(params),
    enabled: options?.enabled ?? true,
  });
}

export function useCreateContrat() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: contratsApi.create,
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["contrats"] }),
  });
}

export function useUpdateContrat() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string;
      payload: Parameters<typeof contratsApi.update>[1];
    }) => contratsApi.update(id, payload),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["contrats"] }),
  });
}

export function useDeleteContrat() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => contratsApi.destroy(id),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["contrats"] });
      void qc.invalidateQueries({ queryKey: ["contrats", "alerts"] });
      void qc.invalidateQueries({ queryKey: ["dashboard"] });
    },
  });
}

export function useContratAlerts(options?: { enabled?: boolean }) {
  return useQuery({
    queryKey: ["contrats", "alerts"],
    queryFn: () => contratsApi.alerts(),
    enabled: options?.enabled ?? true,
  });
}

export function useExportContratsPdf() {
  return useMutation({
    mutationFn: (
      params?: ListParams & {
        agent_id?: string;
        type?: string;
        statut?: string;
        surveillance?: boolean;
        valide?: boolean;
      },
    ) => contratsApi.exportPdf(params),
  });
}

export function usePeriodesPaie(
  params?: ListParams & {
    annee?: number | string;
    mois?: number | string;
    statut?: string;
  },
) {
  return useQuery({
    queryKey: ["periodes-paie", params],
    queryFn: () => periodesPaieApi.list(params),
  });
}

export function useCreatePeriodePaie() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: periodesPaieApi.create,
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["periodes-paie"] }),
  });
}

export function useGenererBulletinsPaie() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => periodesPaieApi.genererBulletins(id),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["periodes-paie"] });
      void qc.invalidateQueries({ queryKey: ["bulletins-paie"] });
    },
  });
}

export function useValiderPeriodePaie() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => periodesPaieApi.valider(id),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["periodes-paie"] }),
  });
}

export function useCloturerPeriodePaie() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => periodesPaieApi.cloturer(id),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["periodes-paie"] }),
  });
}

export function useDeletePeriodePaie() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => periodesPaieApi.destroy(id),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["periodes-paie"] });
      void qc.invalidateQueries({ queryKey: ["bulletins-paie"] });
    },
  });
}

export function useBulletinsPaie(
  periodeId: string | undefined,
  params?: ListParams & {
    statut?: string;
    non_payes?: boolean | number;
  },
) {
  return useQuery({
    queryKey: ["bulletins-paie", periodeId, params],
    queryFn: () => periodesPaieApi.bulletins(periodeId as string, params),
    enabled: !!periodeId,
  });
}

export function useGenererBulletinPdf() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => bulletinsPaieApi.genererPdf(id),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["bulletins-paie"] }),
  });
}

export function useRenseignerSalairePercu() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      id,
      salaire_net,
    }: {
      id: string;
      salaire_net: number;
    }) => bulletinsPaieApi.renseignerSalaire(id, { salaire_net }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["bulletins-paie"] });
      void qc.invalidateQueries({ queryKey: ["periodes-paie"] });
    },
  });
}

export function useRenseignerSalairePercuBulk() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (items: Array<{ id: string; salaire_net: number }>) =>
      bulletinsPaieApi.renseignerSalaireBulk(items),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["bulletins-paie"] });
      void qc.invalidateQueries({ queryKey: ["periodes-paie"] });
    },
  });
}

export function useMarquerBulletinPaye() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      id,
      ...payload
    }: {
      id: string;
      mode: string;
      compte_tresorerie_id: string;
      reference?: string;
      paye_le?: string;
    }) => bulletinsPaieApi.marquerPaye(id, payload),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["bulletins-paie"] });
      void qc.invalidateQueries({ queryKey: ["periodes-paie"] });
      void qc.invalidateQueries({ queryKey: ["tresorerie"] });
    },
  });
}

export function useMarquerBulletinPayeBulk() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: {
      bulletin_ids: string[];
      mode: string;
      compte_tresorerie_id: string;
      reference?: string;
      paye_le?: string;
    }) => bulletinsPaieApi.marquerPayeBulk(payload),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ["bulletins-paie"] });
      void qc.invalidateQueries({ queryKey: ["periodes-paie"] });
      void qc.invalidateQueries({ queryKey: ["tresorerie"] });
    },
  });
}

export function useComptesTresorerieOptions(options?: { enabled?: boolean }) {
  return useQuery({
    queryKey: ["tresorerie", "comptes-options"],
    queryFn: () => tresorerieApi.comptesOptions({ actif_only: 1 }),
    enabled: options?.enabled ?? true,
  });
}

export function useTresorerieStats(params?: { mois?: number; annee?: number }) {
  return useQuery({
    queryKey: ["tresorerie", "stats", params],
    queryFn: () => tresorerieApi.stats(params),
  });
}

export function useComptesTresorerie(params?: { actif_only?: boolean | number }) {
  return useQuery({
    queryKey: ["tresorerie", "comptes", params],
    queryFn: () => tresorerieApi.comptes(params),
  });
}

export function useMouvementsTresorerie(
  params?: ListParams & {
    compte_id?: string;
    direction?: string;
    source_type?: string;
    from?: string;
    to?: string;
  },
) {
  return useQuery({
    queryKey: ["tresorerie", "mouvements", params],
    queryFn: () => tresorerieApi.mouvements(params),
  });
}

export function useCreateAjustementTresorerie() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: tresorerieApi.ajustement,
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["tresorerie"] }),
  });
}

export function useCreateTransfertTresorerie() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: tresorerieApi.transfert,
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["tresorerie"] }),
  });
}

export function useCategoriesDepense() {
  return useQuery({
    queryKey: ["tresorerie", "categories-depense"],
    queryFn: () => tresorerieApi.categoriesDepense({ actif_only: 1 }),
  });
}

export function useDepenses(
  params?: ListParams & {
    categorie_id?: string;
    compte_id?: string;
    from?: string;
    to?: string;
    statut?: string;
  },
) {
  return useQuery({
    queryKey: ["tresorerie", "depenses", params],
    queryFn: () => tresorerieApi.depenses(params),
  });
}

export function useCreateDepense() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Record<string, unknown>) =>
      tresorerieApi.createDepense(payload),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["tresorerie"] }),
  });
}

export function useDeleteDepense() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => tresorerieApi.deleteDepense(id),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["tresorerie"] }),
  });
}

export function useUploadAgentDocument() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      agentId,
      collection,
      file,
    }: {
      agentId: string;
      collection: "piece_identite" | "permis";
      file: File;
    }) => agentsApi.uploadDocument(agentId, collection, file),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["agents"] }),
  });
}

export function useCongesMouvements(
  agentId: string | undefined,
  params?: ListParams,
  options?: { enabled?: boolean },
) {
  return useQuery({
    queryKey: ["conges-mouvements", agentId, params],
    queryFn: () => agentsApi.congesMouvements(agentId as string, params),
    enabled: (options?.enabled ?? true) && !!agentId,
  });
}

export function useAgentBulletins(
  agentId: string | undefined,
  params?: ListParams & { statut?: string },
  options?: { enabled?: boolean },
) {
  return useQuery({
    queryKey: ["agent-bulletins", agentId, params],
    queryFn: () => agentsApi.bulletins(agentId as string, params),
    enabled: (options?.enabled ?? true) && !!agentId,
  });
}

export function useAccorderConges() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      agentId,
      jours,
    }: {
      agentId: string;
      jours?: number;
    }) => agentsApi.accorderConges(agentId, jours != null ? { jours } : undefined),
    onSuccess: (_data, variables) => {
      void qc.invalidateQueries({ queryKey: ["agents"] });
      void qc.invalidateQueries({
        queryKey: ["conges-mouvements", variables.agentId],
      });
    },
  });
}

export function useNotifications(
  params?: ListParams & { non_lues?: boolean },
  options?: { enabled?: boolean },
) {
  return useQuery({
    queryKey: ["notifications", params],
    queryFn: () => notificationsApi.list(params),
    enabled: options?.enabled ?? true,
  });
}

export function useMarquerNotificationLue() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => notificationsApi.marquerLue(id),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["notifications"] }),
  });
}

export function useRapports(
  params?: ListParams,
  options?: { enabled?: boolean },
) {
  return useQuery({
    queryKey: ["rapports", params],
    queryFn: () => rapportsApi.list(params),
    enabled: options?.enabled ?? true,
  });
}

export function useGenerateRapport() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      type,
      format,
      filtres,
    }: {
      type: string;
      format?: string;
      filtres?: Record<string, unknown>;
    }) =>
      rapportsApi.generate(type, {
        format: format ?? "pdf",
        filtres,
      }),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["rapports"] }),
  });
}

export function useDashboardStats(params?: {
  from?: string;
  to?: string;
  zone_id?: string;
  client_id?: string;
}) {
  return useQuery({
    queryKey: ["dashboard", "stats", params],
    queryFn: () => dashboardApi.stats(params),
  });
}
