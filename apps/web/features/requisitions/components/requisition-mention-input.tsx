"use client";

import { Badge, Button, Popover, PopoverContent, PopoverTrigger } from "@cognify/ui";
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

  return (
    <div className="space-y-2">
      <Popover>
        <PopoverTrigger asChild>
          <Button type="button" variant="outline">Mention collaborator</Button>
        </PopoverTrigger>
        <PopoverContent className="w-64 p-2" align="start">
            <div className="space-y-1">
              {candidates.map((candidate) => (
                <Button
                  key={candidate.id}
                  type="button"
                  variant="ghost"
                  className="h-auto w-full justify-start px-2 py-1.5 text-left text-sm"
                  onClick={() => {
                    if (!selectedIds.includes(candidate.id)) onChange([...selectedIds, candidate.id]);
                  }}
                >
                  {candidate.name}
                </Button>
              ))}
            </div>
        </PopoverContent>
      </Popover>
      {selectedCandidates.length > 0 ? (
        <ul className="flex flex-wrap gap-2 text-sm">
          {selectedCandidates.map((candidate) => (
            <li key={candidate.id}>
              <Badge
                className="cursor-pointer"
                role="button"
                tabIndex={0}
                onClick={() => onChange(selectedIds.filter((id) => id !== candidate.id))}
                onKeyDown={(event) => {
                  if (event.key === "Enter" || event.key === " ") {
                    event.preventDefault();
                    onChange(selectedIds.filter((id) => id !== candidate.id));
                  }
                }}
              >
                {candidate.name}
              </Badge>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
