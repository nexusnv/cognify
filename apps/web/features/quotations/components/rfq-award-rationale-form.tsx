import { Textarea } from "@cognify/ui";

type Props = {
  rationale: string;
  tradeoffSummary: string;
  riskSummary: string;
  exceptionSummary: string;
  readOnly?: boolean;
  onChange: (field: "rationale" | "tradeoffSummary" | "riskSummary" | "exceptionSummary", value: string) => void;
};

export function RfqAwardRationaleForm({
  rationale,
  tradeoffSummary,
  riskSummary,
  exceptionSummary,
  readOnly = false,
  onChange,
}: Props) {
  return (
    <section className="rounded-md border p-4" aria-label="Rationale form">
      <h2 className="text-base font-semibold">Decision rationale</h2>
      <div className="mt-3 space-y-3">
        <Textarea aria-label="Rationale" value={rationale} disabled={readOnly} onChange={(e) => onChange("rationale", e.target.value)} />
        <Textarea aria-label="Tradeoff summary" value={tradeoffSummary} disabled={readOnly} onChange={(e) => onChange("tradeoffSummary", e.target.value)} />
        <Textarea aria-label="Risk summary" value={riskSummary} disabled={readOnly} onChange={(e) => onChange("riskSummary", e.target.value)} />
        <Textarea aria-label="Exception summary" value={exceptionSummary} disabled={readOnly} onChange={(e) => onChange("exceptionSummary", e.target.value)} />
      </div>
    </section>
  );
}
