"use client";

import {
  Button,
  Command,
  CommandEmpty,
  CommandGroup,
  CommandItem,
  CommandList,
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@cognify/ui";
import type { UserSummary } from "../types/requisition-view-model";

export function RequisitionMentionInput({
  candidates,
  selectedIds,
  onChange,
}: {
  candidates: UserSummary[];
  selectedIds: string[];
  onChange: (selectedIds: string[]) => void;
}) {
  const selectedCandidates = candidates.filter((candidate) => selectedIds.includes(candidate.id));
  const availableCandidates = candidates.filter((candidate) => !selectedIds.includes(candidate.id));

  return (
    <div className="space-y-2">
      <div className="space-y-1">
        <p className="text-sm font-medium">Mention</p>
        <Popover>
          <PopoverTrigger asChild>
            <Button type="button" variant="outline" className="min-h-11 w-full justify-between">
              Add a visible collaborator
            </Button>
          </PopoverTrigger>
          <PopoverContent className="w-[22rem] max-w-full p-1" align="start">
            <Command aria-label="Mention collaborators" shouldFilter={false}>
              <CommandList>
                <CommandEmpty>No collaborators available.</CommandEmpty>
                <CommandGroup heading="Visible collaborators">
                  {availableCandidates.map((candidate) => (
                    <CommandItem
                      key={candidate.id}
                      value={`${candidate.name} ${candidate.email}`}
                      onSelect={() => onChange([...selectedIds, candidate.id])}
                    >
                      <div className="flex flex-col">
                        <span>{candidate.name}</span>
                        <span className="text-muted-foreground">{candidate.email}</span>
                      </div>
                    </CommandItem>
                  ))}
                </CommandGroup>
              </CommandList>
            </Command>
          </PopoverContent>
        </Popover>
      </div>
      {selectedCandidates.length > 0 ? (
        <ul className="flex flex-wrap gap-2 text-sm">
          {selectedCandidates.map((candidate) => (
            <li key={candidate.id}>
              <Button
                type="button"
                variant="outline"
                onClick={() => onChange(selectedIds.filter((id) => id !== candidate.id))}
              >
                {candidate.name}
              </Button>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
