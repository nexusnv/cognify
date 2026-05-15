export type ApiClientConfig = {
  baseUrl: string;
  getAccessToken?: () => string | null | Promise<string | null>;
  getTenantId?: () => string | null | Promise<string | null>;
};

export type ApiClientError<TData = unknown> = {
  data: TData;
  status: number;
  headers: Headers;
};

export function createApiClientConfig(config: ApiClientConfig): ApiClientConfig {
  return config;
}

export async function cognifyFetch<TResponse>(
  url: string,
  init?: RequestInit,
  config?: ApiClientConfig,
): Promise<TResponse> {
  const baseUrl = config?.baseUrl ?? "";
  const token = await config?.getAccessToken?.();
  const tenantId = await config?.getTenantId?.();
  const headers = new Headers(init?.headers);

  if (token) {
    headers.set("Authorization", `Bearer ${token}`);
  }

  if (tenantId) {
    headers.set("X-Tenant-Id", tenantId);
  }

  const xsrfToken = getXsrfToken();
  if (xsrfToken && isStateChangingMethod(init?.method) && !headers.has("X-XSRF-TOKEN")) {
    headers.set("X-XSRF-TOKEN", xsrfToken);
  }

  const response = await fetch(`${baseUrl}${url}`, {
    ...init,
    credentials: "include",
    headers,
  });

  const contentType = response.headers.get("content-type") ?? "";
  const isJsonResponse = contentType.includes("application/json") || contentType.includes("+json");
  const data =
    response.status === 204
      ? undefined
      : response.ok && !isJsonResponse
        ? await response.blob()
        : !response.ok && !isJsonResponse
          ? await response.text()
          : await response.json();

  if (!response.ok) {
    throw {
      data,
      status: response.status,
      headers: response.headers,
    } satisfies ApiClientError;
  }

  return {
    data,
    status: response.status,
    headers: response.headers,
  } as TResponse;
}

function isStateChangingMethod(method: string | undefined): boolean {
  return !["GET", "HEAD", "OPTIONS"].includes((method ?? "GET").toUpperCase());
}

function getXsrfToken(): string | null {
  if (typeof document === "undefined") return null;

  const cookie = document.cookie
    .split(";")
    .map((part) => part.trim())
    .find((part) => part.startsWith("XSRF-TOKEN="));

  if (!cookie) return null;

  return decodeURIComponent(cookie.slice("XSRF-TOKEN=".length));
}
