"use client";

import { useVendorPicker } from "../hooks/use-vendor-picker";
import type { VendorPickerViewModel } from "../types/vendor-view-model";

export function VendorPicker({
  search,
  selectedVendorIds,
  onSearchChange,
  onToggleVendor,
}: {
  search: string;
  selectedVendorIds: string[];
  onSearchChange: (value: string) => void;
  onToggleVendor: (vendorId: string) => void;
}) {
  const vendorQuery = useVendorPicker(search);
  const normalizedSearch = search.trim();

  return (
    <div className="space-y-3">
      <label className="block text-sm font-medium">
        Search vendors
        <input
          aria-label="Search vendors"
          className="mt-1 min-h-11 w-full rounded-md border bg-background px-3 text-base font-normal outline-none transition-colors focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring"
          placeholder="Search by vendor name, category, or contact"
          value={search}
          onChange={(event) => onSearchChange(event.target.value)}
        />
      </label>

      {vendorQuery.isLoading ? (
        <div className="rounded-md border p-4 text-sm text-muted-foreground">Loading vendors...</div>
      ) : vendorQuery.isError ? (
        <div role="alert" className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">
          Unable to load vendors.
        </div>
      ) : vendorQuery.vendors.length > 0 ? (
        <div className="max-h-80 overflow-y-auto rounded-md border">
          {vendorQuery.vendors.map((vendor) => (
            <VendorRow
              key={vendor.id}
              vendor={vendor}
              selected={selectedVendorIds.includes(vendor.id)}
              onToggle={onToggleVendor}
            />
          ))}
        </div>
      ) : (
        <div className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
          <p className="font-medium text-foreground">No vendors match {normalizedSearch ? `"${normalizedSearch}".` : "your search."}</p>
          <p className="mt-1">Try another search term or clear the search.</p>
        </div>
      )}
    </div>
  );
}

function VendorRow({
  vendor,
  selected,
  onToggle,
}: {
  vendor: VendorPickerViewModel;
  selected: boolean;
  onToggle: (vendorId: string) => void;
}) {
  return (
    <label
      className={`flex cursor-pointer gap-3 border-b px-4 py-3 last:border-b-0 ${selected ? "bg-muted/40" : ""}`}
    >
      <input
        aria-label={vendor.name}
        checked={selected}
        className="mt-1 h-4 w-4 rounded border"
        type="checkbox"
        onChange={() => onToggle(vendor.id)}
      />
      <div className="min-w-0 flex-1 space-y-1">
        <div className="flex flex-wrap items-center gap-2">
          <span className="font-medium text-foreground">{vendor.name}</span>
          <span className="text-xs text-muted-foreground">{vendor.status}</span>
        </div>
        <p className="text-sm text-muted-foreground">{vendor.category ?? "No category"}</p>
        <p className="text-sm text-muted-foreground">{vendor.contactSummary}</p>
        <p className="text-xs text-muted-foreground">Risk rating: {vendor.riskRating ?? "Unknown"}</p>
      </div>
    </label>
  );
}
