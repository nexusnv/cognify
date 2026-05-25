import { Button, Textarea } from "@cognify/ui";
import type { RfqScorecard } from "@cognify/api-client/schemas";
import type { UpdateScoreEntryInput } from "../api/quotation-scoring-api";

export function RfqScorecardMatrix({
  scorecard,
  drafts,
  readOnly = false,
  isSaving = false,
  onDraftChange,
  onSave,
}: {
  scorecard: RfqScorecard;
  drafts: Record<string, { score: string; note: string }>;
  readOnly?: boolean;
  isSaving?: boolean;
  onDraftChange: (key: string, draft: { score: string; note: string }) => void;
  onSave: (entries: UpdateScoreEntryInput[]) => void;
}) {
  const hasInvalidScores = hasInvalidDraft(scorecard, drafts);

  return (
    <section className="space-y-3" aria-label="Score matrix">
      <div className="overflow-x-auto rounded-md border">
        <table className="w-full min-w-[900px] text-left text-sm">
          <thead className="bg-muted/40 text-xs uppercase text-muted-foreground">
            <tr>
              <th className="px-4 py-3">Criterion</th>
              {scorecard.vendors.map((vendor) => (
                <th key={vendor.vendorId} className="px-4 py-3">{vendor.vendorName}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {scorecard.criteria.map((criterion) => (
              <tr key={criterion.id} className="border-t align-top">
                <td className="w-64 px-4 py-3">
                  <div className="font-medium">{criterion.label}</div>
                  <div className="mt-1 text-xs text-muted-foreground">
                    Weight {criterion.weight} / max {criterion.maxScore}
                  </div>
                  {criterion.required ? <div className="mt-1 text-xs text-red-700">Required</div> : null}
                </td>
                {scorecard.vendors.map((vendor) => {
                  const key = cellKey(criterion.id, vendor.vendorId);
                  const draft = drafts[key] ?? { score: "", note: "" };
                  const state = scoreState(draft.score);

                  return (
                    <td key={key} className="w-72 px-4 py-3">
                      <label className="grid gap-1 text-xs font-medium">
                        Score
                        <input
                          className="min-h-10 w-24 rounded-md border px-3 text-sm"
                          disabled={readOnly}
                          inputMode="decimal"
                          value={draft.score}
                          onChange={(event) => onDraftChange(key, { ...draft, score: event.target.value })}
                        />
                      </label>
                      <label className="mt-2 grid gap-1 text-xs font-medium">
                        Note
                        <Textarea
                          className="min-h-20"
                          disabled={readOnly}
                          value={draft.note}
                          onChange={(event) => onDraftChange(key, { ...draft, note: event.target.value })}
                        />
                      </label>
                      {criterion.required && state === "missing" ? (
                        <p className="mt-1 text-xs text-red-700">Missing required score</p>
                      ) : null}
                      {state === "invalid" ? <p className="mt-1 text-xs text-red-700">Invalid score</p> : null}
                    </td>
                  );
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <Button disabled={readOnly || isSaving || hasInvalidScores} onClick={() => onSave(toEntries(scorecard, drafts))}>
        Save scores
      </Button>
    </section>
  );
}

export function scorecardDrafts(scorecard: RfqScorecard): Record<string, { score: string; note: string }> {
  const drafts: Record<string, { score: string; note: string }> = {};
  for (const vendor of scorecard.vendors) {
    for (const criterion of scorecard.criteria) {
      const entry = scorecard.entries.find((item) => item.vendorId === vendor.vendorId && item.criterionId === criterion.id);
      drafts[cellKey(criterion.id, vendor.vendorId)] = {
        score: entry?.score ?? "",
        note: entry?.note ?? "",
      };
    }
  }

  return drafts;
}

function toEntries(scorecard: RfqScorecard, drafts: Record<string, { score: string; note: string }>): UpdateScoreEntryInput[] {
  return scorecard.vendors.flatMap((vendor) =>
    scorecard.criteria.map((criterion) => {
      const draft = drafts[cellKey(criterion.id, vendor.vendorId)] ?? { score: "", note: "" };

      return {
        criterionId: criterion.id,
        vendorId: vendor.vendorId,
        quotationId: vendor.quotationId,
        quotationVersionId: vendor.quotationVersionId,
        score: scoreState(draft.score) === "missing" ? null : Number(draft.score.trim()),
        note: draft.note.trim() || null,
      };
    }),
  );
}

function cellKey(criterionId: string, vendorId: string): string {
  return `${criterionId}:${vendorId}`;
}

function scoreState(score: string): "missing" | "invalid" | "valid" {
  const trimmed = score.trim();
  if (trimmed === "") return "missing";

  return Number.isFinite(Number(trimmed)) ? "valid" : "invalid";
}

function hasInvalidDraft(scorecard: RfqScorecard, drafts: Record<string, { score: string; note: string }>): boolean {
  return scorecard.vendors.some((vendor) => {
    return scorecard.criteria.some((criterion) => {
      const draft = drafts[cellKey(criterion.id, vendor.vendorId)] ?? { score: "", note: "" };
      return scoreState(draft.score) === "invalid";
    });
  });
}
