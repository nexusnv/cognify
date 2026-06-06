import { Badge, Card, CardContent, CardHeader, CardTitle, NativeSelect, RadioGroup, RadioGroupItem } from "@cognify/ui";
import type { RfqAwardRecommendationVendorOption } from "@cognify/api-client/schemas";

type Props = {
  options: RfqAwardRecommendationVendorOption[];
  selectedVendorId: string | null;
  readOnly?: boolean;
  onSelect: (vendorId: string) => void;
};

export function RfqAwardVendorOptionList({ options, selectedVendorId, readOnly = false, onSelect }: Props) {
  return (
    <Card aria-label="Vendor options">
      <CardHeader>
        <CardTitle className="text-base">Recommended vendor</CardTitle>
      </CardHeader>
      <CardContent>
      {options.length === 0 ? (
        <p className="text-sm text-muted-foreground">No vendor quotations are available for recommendation.</p>
      ) : (
        <RadioGroup className="space-y-3" value={selectedVendorId ?? ""} onValueChange={onSelect}>
          {options.map((option) => {
            const rowKey = option.vendorId;
            const selected = selectedVendorId === option.vendorId;
            const score = option.scorecard?.weightedTotal ?? "N/A";
            const missingScores = option.scorecard?.missingRequiredCount ?? 0;

            return (
              <Card key={rowKey}>
                <CardHeader className="pb-2">
                  <label className="flex items-center justify-between gap-3">
                    <CardTitle className="text-base">{option.vendorName}</CardTitle>
                    <RadioGroupItem value={option.vendorId} disabled={readOnly} aria-label={option.vendorName} />
                  </label>
                </CardHeader>
                <CardContent className="space-y-1 text-sm">
                  <p>{option.currency} {option.totalAmount}</p>
                  <p>Lead time: {option.leadTimeDays} days</p>
                  <p>Readiness: <Badge variant={option.readiness === "ready" ? "default" : "secondary"}>{option.readiness}</Badge></p>
                  <p>Weighted score: {score}</p>
                  <p>Missing scores: {missingScores}</p>
                </CardContent>
              </Card>
            );
          })}
        </RadioGroup>
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
      </CardContent>
    </Card>
  );
}
