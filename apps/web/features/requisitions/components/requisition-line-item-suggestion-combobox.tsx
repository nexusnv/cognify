"use client";

import type { RequisitionItemSuggestion } from "../types/requisition-view-model";
import { useRequisitionLineItemSuggestions } from "../hooks/use-requisition-line-item-suggestions";

export function RequisitionLineItemSuggestionCombobox({
  search,
  currency,
  onSelect,
}: {
  search: string;
  currency: string;
  onSelect: (suggestion: RequisitionItemSuggestion) => void;
}) {
  const suggestions = useRequisitionLineItemSuggestions(search, currency);

  if (search.trim().length < 2 || suggestions.isError || !suggestions.data?.length) return null;

  return (
    <div className="mt-2 rounded-md border bg-background p-2" aria-label="Line item suggestions">
      <div className="space-y-1">
        {suggestions.data.map((suggestion) => (
          <button
            key={suggestion.id}
            type="button"
            className="block w-full rounded px-2 py-2 text-left text-sm hover:bg-muted"
            onClick={() => onSelect(suggestion)}
          >
            <span className="font-medium">{suggestion.name}</span>
            <span className="ml-2 text-muted-foreground">
              {suggestion.unit} · {suggestion.currency} {suggestion.estimatedUnitPrice}
            </span>
          </button>
        ))}
      </div>
    </div>
  );
}
