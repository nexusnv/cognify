"use client";

import { NativeSelect } from "@cognify/ui";
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
      <label className="block text-sm font-medium">
        Mention
        <NativeSelect
          className="mt-1"
          value=""
          onChange={(event) => {
            const value = event.target.value;
            if (value && !selectedIds.includes(value)) {
              onChange([...selectedIds, value]);
            }
          }}
        >
          <option value="">Select a visible collaborator</option>
          {candidates.map((candidate) => (
            <option key={candidate.id} value={candidate.id}>
              {candidate.name}
            </option>
          ))}
        </NativeSelect>
      </label>
      {selectedCandidates.length > 0 ? (
        <ul className="flex flex-wrap gap-2 text-sm">
          {selectedCandidates.map((candidate) => (
            <li key={candidate.id}>
              <button
                type="button"
                className="min-h-11 rounded-md border px-3"
                onClick={() => onChange(selectedIds.filter((id) => id !== candidate.id))}
              >
                {candidate.name}
              </button>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
