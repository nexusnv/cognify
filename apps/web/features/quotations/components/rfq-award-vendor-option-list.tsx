import { NativeSelect } from "@cognify/ui";
import type { RfqAwardRecommendationVendorOption } from "@cognify/api-client/schemas";

type Props = {
  options: RfqAwardRecommendationVendorOption[];
  selectedVendorId: string | null;
  readOnly?: boolean;
  onSelect: (vendorId: string) => void;
};

export function RfqAwardVendorOptionList({ options, selectedVendorId, readOnly = false, onSelect }: Props) {
  return (
    <section className="rounded-md border p-4" aria-label="Vendor options">
      <h2 className="text-base font-semibold">Recommended vendor</h2>
      {options.length === 0 ? (
        <p className="mt-2 text-sm text-muted-foreground">No vendor quotations are available for recommendation.</p>
      ) : (
        <ul className="mt-3 space-y-3">
          {options.map((option) => {
            const rowKey = option.vendorId;
            const selected = selectedVendorId === option.vendorId;
            const score = option.scorecard?.weightedTotal ?? "N/A";
            const missingScores = option.scorecard?.missingRequiredCount ?? 0;

            return (
              <li key={rowKey} className="rounded-md border p-3">
                <label className="flex cursor-pointer flex-col gap-2 text-sm">
                  <span className="flex items-center justify-between gap-3">
                    <span className="font-medium">{option.vendorName}</span>
                    <input
                      type="radio"
                      name="recommended-vendor"
                      checked={selected}
                      disabled={readOnly}
                      onChange={() => onSelect(option.vendorId)}
                    />
                  </span>
                  <span>{option.currency} {option.totalAmount}</span>
                  <span>Lead time: {option.leadTimeDays} days</span>
                  <span>Readiness: {option.readiness}</span>
                  <span>Weighted score: {score}</span>
                  <span>Missing scores: {missingScores}</span>
                </label>
              </li>
            );
          })}
        </ul>
      )}
      <NativeSelect
        className="mt-3"
        aria-label="Recommended vendor select"
        value={selectedVendorId ?? ""}
        disabled={readOnly || options.length === 0}
        onChange={(event) => {
          if (event.target.value) onSelect(event.target.value);
        }}
      >
        <option value="">Select recommended vendor</option>
        {options.map((option) => (
          <option key={option.vendorId} value={option.vendorId}>
            {option.vendorName}
          </option>
        ))}
      </NativeSelect>
    </section>
  );
}
