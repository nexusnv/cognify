export type ApiClientConfig = {
  baseUrl: string;
  getAccessToken?: () => string | null | Promise<string | null>;
  getTenantId?: () => string | null | Promise<string | null>;
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

  const response = await fetch(`${baseUrl}${url}`, {
    ...init,
    credentials: "include",
    headers,
  });

  if (!response.ok) {
    throw new Error(`Cognify API request failed with status ${response.status}`);
  }

  return response.json() as Promise<TResponse>;
}
