import * as SecureStore from "expo-secure-store";
import { API_URL } from "./config";

export class ApiError extends Error {
  status: number;
  errors?: Record<string, string[]>;

  constructor(message: string, status: number, errors?: Record<string, string[]>) {
    super(message);
    this.status = status;
    this.errors = errors;
  }
}

type RequestOptions = {
  method?: string;
  body?: unknown;
  token?: string | null;
  formData?: FormData;
};

async function parseJson(res: Response) {
  const text = await res.text();
  if (!text) return null;
  try {
    return JSON.parse(text);
  } catch {
    // Laravel peut préfixer du HTML (warnings PHP) avant le JSON.
    const start = text.indexOf("{");
    const end = text.lastIndexOf("}");
    if (start >= 0 && end > start) {
      try {
        return JSON.parse(text.slice(start, end + 1));
      } catch {
        return null;
      }
    }
    return null;
  }
}

export async function apiRequest<T>(
  path: string,
  { method = "GET", body, token, formData }: RequestOptions = {},
): Promise<T> {
  const headers: Record<string, string> = {
    Accept: "application/json",
  };

  if (!formData) {
    headers["Content-Type"] = "application/json";
  }

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  const res = await fetch(`${API_URL}${path}`, {
    method,
    headers,
    body: formData ? formData : body !== undefined ? JSON.stringify(body) : undefined,
  });

  const json = await parseJson(res);

  if (!res.ok) {
    const fromErrors = json?.errors
      ? Object.values(json.errors as Record<string, string[]>)
          .flat()
          .filter(Boolean)
          .join(" · ")
      : null;
    const message = fromErrors || json?.message || `Erreur ${res.status}`;
    throw new ApiError(String(message), res.status, json?.errors);
  }

  return json as T;
}

const TOKEN_KEY = "sis_mobile_token";

export async function saveToken(token: string) {
  await SecureStore.setItemAsync(TOKEN_KEY, token);
}

export async function getToken() {
  return SecureStore.getItemAsync(TOKEN_KEY);
}

export async function clearToken() {
  await SecureStore.deleteItemAsync(TOKEN_KEY);
}
