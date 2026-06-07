"use client";

import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandItem,
  CommandList,
  Popover,
  PopoverAnchor,
  PopoverContent,
} from "@cognify/ui";
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
    <Popover open>
      <PopoverAnchor asChild>
        <div className="mt-2 h-px w-full" aria-hidden="true" />
      </PopoverAnchor>
      <PopoverContent className="w-[26rem] max-w-full p-1" align="start" sideOffset={8}>
        <Command aria-label="Line item suggestions" shouldFilter={false}>
          <CommandList>
            <CommandEmpty>No suggestions available.</CommandEmpty>
            <CommandGroup heading="Suggested items">
              {suggestions.data.map((suggestion) => (
                <CommandItem
                  key={suggestion.id}
                  value={`${suggestion.name} ${suggestion.unit} ${suggestion.currency} ${suggestion.estimatedUnitPrice}`}
                  onSelect={() => onSelect(suggestion)}
                >
                  <div className="flex flex-col">
                    <span className="font-medium">{suggestion.name}</span>
                    <span className="text-muted-foreground">
                      {suggestion.unit} · {suggestion.currency} {suggestion.estimatedUnitPrice}
                    </span>
                  </div>
                </CommandItem>
              ))}
            </CommandGroup>
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  );
}
