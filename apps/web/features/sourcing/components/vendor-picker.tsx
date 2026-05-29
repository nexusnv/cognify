"use client";

import { Alert, AlertDescription, Card, CardContent, CardHeader, CardTitle, Input } from "@cognify/ui";
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
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Vendor picker</CardTitle>
      </CardHeader>
      <CardContent className="space-y-3">
        <label className="block text-sm font-medium">
          Search vendors
          <Input
            aria-label="Search vendors"
            className="mt-1 text-base font-normal"
            placeholder="Search by vendor name, category, or contact"
            value={search}
            onChange={(event) => onSearchChange(event.target.value)}
          />
        </label>

        {vendorQuery.isLoading ? (
          <p className="text-sm text-muted-foreground">Loading vendors...</p>
        ) : vendorQuery.isError ? (
          <Alert variant="destructive">
            <AlertDescription>Unable to load vendors.</AlertDescription>
          </Alert>
        ) : vendorQuery.vendors.length > 0 ? (
          <div className="max-h-80 overflow-y-auto">
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
          <div className="space-y-1 rounded-lg bg-muted/30 p-4 text-sm text-muted-foreground">
            <p className="font-medium text-foreground">
              {normalizedSearch
                ? `No vendors match "${normalizedSearch}".`
                : "No active vendors are available for invitation."}
            </p>
            <p>
              {normalizedSearch
                ? "Try another search term or clear the search."
                : "Add active vendors before starting an invitation."}
            </p>
          </div>
        )}
      </CardContent>
    </Card>
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
      <Input
        aria-label={vendor.name}
        checked={selected}
        className="mt-0.5 size-4 shrink-0 p-0"
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
