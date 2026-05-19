import type { VendorPickerItem } from "@cognify/api-client/schemas";

export type VendorPickerViewModel = VendorPickerItem & {
  contactSummary: string;
  searchText: string;
};

export function toVendorPickerViewModel(vendor: VendorPickerItem): VendorPickerViewModel {
  const contactName = vendor.defaultContact.name ?? "";
  const contactEmail = vendor.defaultContact.email ?? "";
  const contactSummary = [contactName, contactEmail].filter(Boolean).join(" · ");

  return {
    ...vendor,
    contactSummary: contactSummary || "No default contact recorded",
    searchText: [vendor.name, vendor.category, vendor.status, vendor.riskRating, contactName, contactEmail]
      .filter(Boolean)
      .join(" ")
      .toLowerCase(),
  };
}

export function matchesVendorSearch(vendor: VendorPickerViewModel, search: string) {
  const normalizedSearch = search.trim().toLowerCase();
  if (!normalizedSearch) return true;

  return vendor.searchText.includes(normalizedSearch);
}
