"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { authApi } from "@/infrastructure/http/authApi";
import { systemApi } from "@/infrastructure/http/resources";
import { clearToken, getToken, setToken } from "@/infrastructure/auth/token";
import type { User } from "@/domain/types/entities";
import { isTerrainUser } from "@/shared/lib/can";

type AuthContextValue = {
  user: User | null;
  token: string | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  isTerrain: boolean;
  featureFlags: Record<string, boolean>;
  menuOverrides: Record<string, boolean>;
  login: (email: string, password: string) => Promise<User>;
  loginTerrain: (matricule: string, pin: string) => Promise<User>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
  refreshRuntime: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [token, setTokenState] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [featureFlags, setFeatureFlags] = useState<Record<string, boolean>>(
    {},
  );
  const [menuOverrides, setMenuOverrides] = useState<Record<string, boolean>>(
    {},
  );

  const loadRuntime = useCallback(async () => {
    try {
      const res = await systemApi.runtimeConfig();
      setFeatureFlags(res.data.feature_flags ?? {});
      setMenuOverrides(res.data.menu_overrides ?? {});
    } catch {
      setFeatureFlags({});
      setMenuOverrides({});
    }
  }, []);

  const refreshUser = useCallback(async () => {
    const stored = getToken();
    if (!stored) {
      setUser(null);
      setTokenState(null);
      setFeatureFlags({});
      setMenuOverrides({});
      return;
    }
    setTokenState(stored);
    const res = await authApi.me();
    setUser(res.data);
    await loadRuntime();
  }, [loadRuntime]);

  useEffect(() => {
    let mounted = true;
    (async () => {
      try {
        const stored = getToken();
        if (!stored) return;
        setTokenState(stored);
        const res = await authApi.me();
        if (mounted) setUser(res.data);
        if (mounted) await loadRuntime();
      } catch {
        clearToken();
        if (mounted) {
          setUser(null);
          setTokenState(null);
          setFeatureFlags({});
          setMenuOverrides({});
        }
      } finally {
        if (mounted) setIsLoading(false);
      }
    })();
    return () => {
      mounted = false;
    };
  }, [loadRuntime]);

  const login = useCallback(
    async (email: string, password: string) => {
      const res = await authApi.login({ email, password, device_name: "web" });
      setToken(res.data.token);
      setTokenState(res.data.token);
      setUser(res.data.user);
      await loadRuntime();
      return res.data.user;
    },
    [loadRuntime],
  );

  const loginTerrain = useCallback(
    async (matricule: string, pin: string) => {
      const res = await authApi.loginTerrain({
        matricule,
        pin,
        device_name: "web-terrain",
      });
      setToken(res.data.token);
      setTokenState(res.data.token);
      setUser(res.data.user);
      await loadRuntime();
      return res.data.user;
    },
    [loadRuntime],
  );

  const logout = useCallback(async () => {
    try {
      await authApi.logout();
    } catch {
      // ignore network errors on logout
    } finally {
      clearToken();
      setTokenState(null);
      setUser(null);
      setFeatureFlags({});
      setMenuOverrides({});
    }
  }, []);

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      token,
      isLoading,
      isAuthenticated: Boolean(token),
      isTerrain: isTerrainUser(user),
      featureFlags,
      menuOverrides,
      login,
      loginTerrain,
      logout,
      refreshUser,
      refreshRuntime: loadRuntime,
    }),
    [
      user,
      token,
      isLoading,
      featureFlags,
      menuOverrides,
      login,
      loginTerrain,
      logout,
      refreshUser,
      loadRuntime,
    ],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error("useAuth doit être utilisé dans AuthProvider");
  }
  return ctx;
}
