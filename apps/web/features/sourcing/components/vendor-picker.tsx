"use client";

import { useRef } from "react";
import { Alert, AlertDescription, Card, CardContent, Checkbox, Input } from "@cognify/ui";
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
        <Input
          aria-label="Search vendors"
          className="mt-1 h-11 w-full px-3 text-base font-normal"
          placeholder="Search by vendor name, category, or contact"
          value={search}
          onChange={(event) => onSearchChange(event.target.value)}
        />
      </label>

      {vendorQuery.isLoading ? (
        <Card className="py-0">
          <CardContent className="p-4 text-sm text-muted-foreground">Loading vendors...</CardContent>
        </Card>
      ) : vendorQuery.isError ? (
        <Alert variant="destructive">
          <AlertDescription>Unable to load vendors.</AlertDescription>
        </Alert>
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
          <p className="font-medium text-foreground">
            {normalizedSearch
              ? `No vendors match "${normalizedSearch}".`
              : "No active vendors are available for invitation."}
          </p>
          <p className="mt-1">
            {normalizedSearch
              ? "Try another search term or clear the search."
              : "Add active vendors before starting an invitation."}
          </p>
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
  const checkboxId = `vendor-${vendor.id}`;
  const checkboxRef = useRef<HTMLButtonElement | null>(null);

  return (
    <div className={`flex gap-3 border-b px-4 py-3 last:border-b-0 ${selected ? "bg-muted/40" : ""}`}>
      <Checkbox
        id={checkboxId}
        name={checkboxId}
        ref={checkboxRef}
        aria-label={vendor.name}
        checked={selected}
        onCheckedChange={() => onToggle(vendor.id)}
      />
      <div className="min-w-0 flex-1 space-y-1">
        <div className="flex flex-wrap items-center gap-2">
          <label htmlFor={checkboxId} className="cursor-pointer font-medium text-foreground">
            {vendor.name}
          </label>
          <span className="text-xs text-muted-foreground">{vendor.status}</span>
        </div>
        <p className="text-sm text-muted-foreground">{vendor.category ?? "No category"}</p>
        <p className="text-sm text-muted-foreground">{vendor.contactSummary}</p>
        <p className="text-xs text-muted-foreground">Risk rating: {vendor.riskRating ?? "Unknown"}</p>
      </div>
    </div>
  );
}
