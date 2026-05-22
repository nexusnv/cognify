"use client";

import { useState } from "react";
import { Button, NativeSelect, Textarea } from "@cognify/ui";
import type {
  QuotationComparisonNote,
  QuotationComparisonNoteGroup,
  SaveQuotationComparisonNoteRequest,
} from "@cognify/api-client/schemas";
import { SaveQuotationComparisonNoteRequestSection } from "@cognify/api-client/schemas";

const noteSections = Object.values(SaveQuotationComparisonNoteRequestSection);

export function QuotationComparisonNotesPanel({
  notes,
  noteGroups,
  canManage,
  isPending,
  onCreate,
  onUpdate,
  onDelete,
}: {
  notes: QuotationComparisonNote[];
  noteGroups: QuotationComparisonNoteGroup[];
  canManage: boolean;
  isPending: boolean;
  onCreate: (payload: SaveQuotationComparisonNoteRequest) => Promise<void>;
  onUpdate: (noteId: string, payload: SaveQuotationComparisonNoteRequest) => Promise<void>;
  onDelete: (noteId: string) => Promise<void>;
}) {
  const [section, setSection] = useState<SaveQuotationComparisonNoteRequest["section"]>("overall");
  const [noteText, setNoteText] = useState("");
  const [editingNote, setEditingNote] = useState<QuotationComparisonNote | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function submitNote() {
    const payload = { section, note: noteText.trim() };
    if (!payload.note) return;

    setError(null);

    try {
      if (editingNote) {
        await onUpdate(editingNote.id, {
          ...payload,
          quotationId: editingNote.quotationId,
          vendorId: editingNote.vendorId,
          rfqLineItemId: editingNote.rfqLineItemId,
        });
        setEditingNote(null);
      } else {
        await onCreate(payload);
      }

      setSection(SaveQuotationComparisonNoteRequestSection.overall);
      setNoteText("");
    } catch (caught) {
      setError(errorMessage(caught));
    }
  }

  function startEditing(note: QuotationComparisonNote) {
    setEditingNote(note);
    setSection(note.section);
    setNoteText(note.note);
  }

  return (
    <section className="rounded-md border p-4">
      <h2 className="text-base font-semibold">Comparison notes</h2>
      <p className="mt-2 text-xs text-muted-foreground">
        Comparison notes are annotations only. They do not score vendors, recommend awards, or change RFQ status.
      </p>
      {!canManage ? (
        <p className="mt-3 rounded-md border bg-muted/40 p-3 text-sm text-muted-foreground">
          Note controls are unavailable for this RFQ.
        </p>
      ) : (
        <div className="mt-4 space-y-3">
          <label className="block text-sm font-medium">
            Note section
            <NativeSelect
              className="mt-1"
              value={section}
              onChange={(event) => setSection(event.target.value as SaveQuotationComparisonNoteRequest["section"])}
            >
              {noteSections.map((value) => (
                <option key={value} value={value}>{labelSection(value)}</option>
              ))}
            </NativeSelect>
          </label>
          <label className="block text-sm font-medium">
            Comparison note
            <Textarea
              className="mt-1"
              value={noteText}
              onChange={(event) => setNoteText(event.target.value)}
              placeholder="Add non-decision comparison context"
            />
          </label>
          <div className="flex gap-2">
            <Button type="button" onClick={() => void submitNote()} disabled={isPending || !noteText.trim()}>
              {editingNote ? "Save note" : "Add note"}
            </Button>
            {editingNote ? (
              <Button
                type="button"
                variant="outline"
                onClick={() => {
                  setEditingNote(null);
                  setSection(SaveQuotationComparisonNoteRequestSection.overall);
                  setNoteText("");
                  setError(null);
                }}
              >
                Cancel edit
              </Button>
            ) : null}
          </div>
          {error ? <p role="alert" className="text-sm text-red-700">{error}</p> : null}
        </div>
      )}

      <div className="mt-5 space-y-3">
        {noteGroups.length === 0 && notes.length === 0 ? (
          <p className="text-sm text-muted-foreground">No comparison notes yet.</p>
        ) : null}
        {(noteGroups.length > 0 ? noteGroups.flatMap((group) => group.notes) : notes).map((note) => (
          <article key={note.id} data-testid="comparison-note" className="rounded-md border bg-background p-3 text-sm">
            <div className="flex items-center justify-between gap-3">
              <p className="font-medium">{labelSection(note.section)}</p>
              {canManage ? (
                <div className="flex gap-2">
                  <Button type="button" size="sm" variant="outline" onClick={() => startEditing(note)}>
                    Edit note
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => {
                      setError(null);
                      onDelete(note.id).catch((caught) => setError(errorMessage(caught)));
                    }}
                    disabled={isPending}
                  >
                    Delete note
                  </Button>
                </div>
              ) : null}
            </div>
            <p className="mt-2 text-muted-foreground">{note.note}</p>
          </article>
        ))}
      </div>
    </section>
  );
}

function labelSection(section: string) {
  return section
    .split("_")
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(" ");
}

function errorMessage(error: unknown): string {
  if (typeof error === "object" && error !== null && "error" in error) {
    const payload = (error as { error?: { message?: unknown } }).error;
    if (typeof payload?.message === "string") return payload.message;
  }

  return error instanceof Error ? error.message : "Comparison note could not be saved.";
}
