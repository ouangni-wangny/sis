import { apiRequest } from "./client";
import type {
  Controle,
  DataResponse,
  MobileUser,
  Paginated,
  Remplacant,
  Vacation,
} from "@/types/api";

export const authApi = {
  login: (matricule: string, pin: string) =>
    apiRequest<DataResponse<{ token: string; user: MobileUser }>>(
      "/auth/mobile-login",
      {
        method: "POST",
        body: { matricule, pin, device_name: "sis-mobile" },
      },
    ),

  me: (token: string) =>
    apiRequest<DataResponse<MobileUser>>("/auth/me", { token }).then(
      (res) => res.data,
    ),

  logout: (token: string) =>
    apiRequest<{ message: string }>("/auth/logout", {
      method: "POST",
      token,
    }),
};

export const vacationsApi = {
  /** Agents postés à contrôler aujourd’hui. */
  enPoste: (token: string) => {
    const q = new URLSearchParams({
      en_poste: "1",
      per_page: "50",
    });
    return apiRequest<Paginated<Vacation>>(`/vacations?${q}`, { token });
  },

  /** Cases à recouvrir d’aujourd’hui sur le périmètre du contrôleur. */
  aRecouvrir: (token: string, aujourdhui: string) => {
    const q = new URLSearchParams({
      statut: "a_recouvrir",
      date_from: aujourdhui,
      date_to: aujourdhui,
      per_page: "50",
    });
    return apiRequest<Paginated<Vacation>>(`/vacations?${q}`, { token });
  },

  remplacants: (token: string, vacationId: string) =>
    apiRequest<DataResponse<Remplacant[]>>(
      `/vacations/${vacationId}/remplacants`,
      { token },
    ),

  recouvrir: (token: string, vacationId: string, agentId: string) =>
    apiRequest<DataResponse<Vacation>>(`/vacations/${vacationId}/recouvrir`, {
      method: "POST",
      token,
      body: { agent_id: agentId },
    }),
};

export const controlesApi = {
  /** Contrôles du rondier connecté effectués aujourd’hui. */
  todayMine: (token: string) => {
    const q = new URLSearchParams({
      aujourd_hui: "1",
      mes_controles: "1",
      per_page: "100",
    });
    return apiRequest<Paginated<Controle>>(`/controles?${q}`, { token });
  },

  createPresence: (
    token: string,
    payload: {
      agent_id: string;
      controle_agent_id: string;
      site_id: string;
      poste_id?: string | null;
      latitude: number;
      longitude: number;
      resultat: "present" | "absent";
      commentaire?: string;
      client_uuid?: string;
      /** JPEG/PNG encodé base64 — obligatoire si présent, optionnel si absent. */
      photo_base64?: string;
    },
  ) =>
    apiRequest<DataResponse<Controle>>("/controles", {
      method: "POST",
      token,
      body: {
        agent_id: payload.agent_id,
        controle_agent_id: payload.controle_agent_id,
        site_id: payload.site_id,
        poste_id: payload.poste_id || undefined,
        latitude: payload.latitude,
        longitude: payload.longitude,
        resultat: payload.resultat,
        commentaire: payload.commentaire,
        client_uuid: payload.client_uuid,
        ...(payload.photo_base64
          ? { photo_base64: payload.photo_base64 }
          : {}),
      },
    }),
};

export const anomaliesApi = {
  create: (
    token: string,
    payload: {
      signale_par_id: string;
      site_id: string;
      type: string;
      gravite?: string;
      commentaire?: string;
      client_uuid?: string;
    },
  ) =>
    apiRequest<DataResponse<unknown>>("/anomalies", {
      method: "POST",
      token,
      body: payload,
    }),
};
