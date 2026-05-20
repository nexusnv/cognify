import { listVendors as listVendorsEndpoint } from "@cognify/api-client/endpoints";
import type { ListVendorsParams, VendorPickerItem } from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import { toVendorPickerViewModel, type VendorPickerViewModel } from "../types/vendor-view-model";

function withActiveTenantHeader(): RequestInit | undefined {
  const tenantId = getStoredActiveTenantId();
  if (!tenantId) return undefined;

  return {
    headers: {
      "X-Tenant-Id": tenantId,
    },
  };
}

export async function fetchVendorPickerItems(params: ListVendorsParams = {}): Promise<VendorPickerViewModel[]> {
  const response = await listVendorsEndpoint({ ...params, status: "active" }, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;

  return [...response.data.data].map((vendor: VendorPickerItem) => toVendorPickerViewModel(vendor)).sort((left, right) =>
    left.name.localeCompare(right.name),
  );
}
