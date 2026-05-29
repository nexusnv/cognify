"use client";

import { Button, Popover, PopoverContent, PopoverTrigger } from "@cognify/ui";
import type { RequisitionItemSuggestion } from "../types/requisition-view-model";
import { useRequisitionLineItemSuggestions } from "../hooks/use-requisition-line-item-suggestions";

export function RequisitionLineItemSuggestionCombobox({
  search,
  currency,
  disabled = false,
  onSelect,
}: {
  search: string;
  currency: string;
  disabled?: boolean;
  onSelect: (suggestion: RequisitionItemSuggestion) => void;
}) {
  const suggestions = useRequisitionLineItemSuggestions(disabled ? "" : search, currency);

  if (disabled || search.trim().length < 2 || suggestions.isError || !suggestions.data?.length) return null;

  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button type="button" size="sm" variant="outline">Suggestions</Button>
      </PopoverTrigger>
      <PopoverContent className="w-[24rem] p-2" align="start" side="bottom">
        <div className="space-y-1" aria-label="Line item suggestions">
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
      </PopoverContent>
    </Popover>
  );
}
