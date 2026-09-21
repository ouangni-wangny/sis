import { api } from "@/infrastructure/http/apiClient";
import type { DataResponse } from "@/domain/types/api";
import type { AuthLoginResult, User } from "@/domain/types/entities";

export const authApi = {
  login: (payload: {
    email: string;
    password: string;
    device_name?: string;
  }) =>
    api.post<DataResponse<AuthLoginResult>>("/auth/login", {
      device_name: "web",
      ...payload,
    }),
  loginTerrain: (payload: {
    matricule: string;
    pin: string;
    device_name?: string;
  }) =>
    api.post<DataResponse<AuthLoginResult>>("/auth/mobile-login", {
      device_name: "web-terrain",
      ...payload,
    }),
  me: () => api.get<DataResponse<User>>("/auth/me"),
  logout: () => api.post<{ message: string }>("/auth/logout"),
};
