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

  const response = await fetch(`${baseUrl}${url}`, {
    ...init,
    credentials: "include",
    headers,
  });

  const data = response.status === 204 ? undefined : await response.json();

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
