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
    <div className="space-y-2">
      <Popover>
        <PopoverTrigger asChild>
          <Button type="button" size="sm" variant="outline">Suggestions</Button>
        </PopoverTrigger>
        <PopoverContent className="w-[24rem] p-2" align="start" side="bottom">
          <div className="space-y-1" aria-label="Line item suggestions">
            {suggestions.data.map((suggestion) => (
              <Button
                key={suggestion.id}
                type="button"
                variant="ghost"
                className="h-auto w-full justify-start px-2 py-2 text-left"
                onClick={() => onSelect(suggestion)}
              >
                <span className="font-medium">{suggestion.name}</span>
                <span className="ml-2 text-muted-foreground">
                  {suggestion.unit} · {suggestion.currency} {suggestion.estimatedUnitPrice}
                </span>
              </Button>
            ))}
          </div>
        </PopoverContent>
      </Popover>
      <div className="space-y-1" aria-label="Suggested line items">
        {suggestions.data.slice(0, 3).map((suggestion) => (
          <Button
            key={`${suggestion.id}-inline`}
            type="button"
            variant="outline"
            size="sm"
            className="mr-2 mb-1 h-auto px-2 py-1 text-left"
            onClick={() => onSelect(suggestion)}
          >
            {suggestion.name}
          </Button>
        ))}
      </div>
    </div>
  );
}
