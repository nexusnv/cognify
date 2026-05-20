import { useMemo } from "react";
import { useQuery } from "@tanstack/react-query";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import { fetchVendorPickerItems } from "../api/vendor-api";
import { matchesVendorSearch, type VendorPickerViewModel } from "../types/vendor-view-model";

export const vendorPickerKeys = {
  list: (tenantId: string | null = getStoredActiveTenantId(), category: string | null = null) =>
    ["sourcing", "vendor-picker", tenantId ?? "no-tenant", category ?? "all"] as const,
};

export function useVendorPicker(search: string, category: string | null = null) {
  const query = useQuery({
    queryKey: vendorPickerKeys.list(undefined, category),
    queryFn: () => fetchVendorPickerItems(category ? { category } : {}),
    enabled: true,
  });

  const vendors = useMemo(
    () =>
      (query.data ?? []).filter((vendor: VendorPickerViewModel) => matchesVendorSearch(vendor, search)),
    [query.data, search],
  );

  return {
    ...query,
    vendors,
  };
}
