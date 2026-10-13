const API_BASE = "/api/v1";

export class ApiError extends Error {
  constructor(public readonly status: number, message: string) {
    super(message);
    this.name = "ApiError";
  }
}

type ApiOptions = RequestInit & { skipAuth?: boolean; retrying?: boolean };

function tokens() {
  if (typeof window === "undefined") return {};
  return {
    access: sessionStorage.getItem("minevision.access_token"),
    refresh: sessionStorage.getItem("minevision.refresh_token"),
  };
}

async function refreshSession(): Promise<boolean> {
  const { refresh } = tokens();
  if (!refresh) return false;
  const response = await fetch(`${API_BASE}/auth/refresh`, {
    method: "POST",
    headers: { Accept: "application/json", "Content-Type": "application/json" },
    body: JSON.stringify({ refresh_token: refresh }),
  });
  if (!response.ok) return false;
  const payload = await response.json() as { data?: { access_token?: string; refresh_token?: string } };
  if (!payload.data?.access_token || !payload.data.refresh_token) return false;
  sessionStorage.setItem("minevision.access_token", payload.data.access_token);
  sessionStorage.setItem("minevision.refresh_token", payload.data.refresh_token);
  return true;
}

export async function api<T>(path: string, options: ApiOptions = {}): Promise<T> {
  if (!path.startsWith("/")) throw new Error("O caminho da API deve começar com /.");
  const headers = new Headers(options.headers);
  headers.set("Accept", "application/json");
  if (typeof options.body === "string" && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }
  const { skipAuth, retrying, ...requestOptions } = options;
  const { access } = tokens();
  if (access && !skipAuth) headers.set("Authorization", `Bearer ${access}`);
  const response = await fetch(`${API_BASE}${path}`, { ...requestOptions, headers });
  if (response.status === 401 && !skipAuth && !retrying && await refreshSession()) {
    return api<T>(path, { ...options, retrying: true });
  }
  if (!response.ok) {
    let message = `Falha na API (${response.status}).`;
    try {
      const payload = await response.json() as { error?: { message?: string } };
      message = payload.error?.message ?? message;
    } catch { /* resposta sem JSON */ }
    throw new ApiError(response.status, message);
  }
  if (response.status === 204) return undefined as T;
  const payload: unknown = await response.json();
  if (typeof payload === "object" && payload !== null && "data" in payload && !("meta" in payload)) {
    return (payload as { data: T }).data;
  }
  return payload as T;
}

export async function apiBlob(path: string, retrying = false): Promise<Blob> {
  if (!path.startsWith("/")) throw new Error("O caminho da API deve começar com /.");
  const { access } = tokens();
  const response = await fetch(`${API_BASE}${path}`, { headers: access ? { Authorization: `Bearer ${access}` } : {} });
  if (response.status === 401 && !retrying && await refreshSession()) {
    return apiBlob(path, true);
  }
  if (!response.ok) throw new ApiError(response.status, `Falha ao carregar arquivo (${response.status}).`);
  return response.blob();
}
