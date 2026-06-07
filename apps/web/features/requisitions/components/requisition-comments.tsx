"use client";

import { useState } from "react";
import { toast } from "sonner";
import { Button, Form, Textarea } from "@cognify/ui";
import {
  useCreateRequisitionComment,
  useRequisitionComments,
  useRequisitionMentionCandidates,
} from "../hooks/use-requisition-comments";
import { RequisitionMentionInput } from "./requisition-mention-input";

function formatTimestamp(value: string) {
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

export function RequisitionComments({
  requisitionId,
  canComment,
  canMention,
}: {
  requisitionId: string;
  canComment: boolean;
  canMention: boolean;
}) {
  const commentsQuery = useRequisitionComments(requisitionId);
  const mentionCandidatesQuery = useRequisitionMentionCandidates(requisitionId, canMention);
  const createComment = useCreateRequisitionComment(requisitionId);
  const [body, setBody] = useState("");
  const [selectedMentionIds, setSelectedMentionIds] = useState<string[]>([]);

  if (commentsQuery.isLoading) {
    return <p className="text-sm text-muted-foreground">Loading comments</p>;
  }

  if (commentsQuery.isError) {
    return <p className="text-sm text-red-700">Comments could not be loaded.</p>;
  }

  return (
    <div className="space-y-4">
      <div className="space-y-3">
        {(commentsQuery.data ?? []).length === 0 ? (
          <p className="text-sm text-muted-foreground">No comments yet.</p>
        ) : null}
        {(commentsQuery.data ?? []).map((comment) => (
          <article key={comment.id} className="rounded-md border p-3 text-sm">
            <div className="flex items-start justify-between gap-3">
              <div>
                <p className="font-medium">{comment.author.name}</p>
                <p className="text-xs text-muted-foreground">{formatTimestamp(comment.createdAt)}</p>
              </div>
            </div>
            <p className="mt-2 whitespace-pre-wrap">{comment.body}</p>
            {comment.mentions.length > 0 ? (
              <p className="mt-2 text-xs text-muted-foreground">
                Mentioned: {comment.mentions.map((mention) => mention.mentionedUser.name).join(", ")}
              </p>
            ) : null}
          </article>
        ))}
      </div>
      {canComment ? (
        <Form
          className="space-y-3 rounded-md border p-4"
          onSubmit={(event) => {
            event.preventDefault();
            if (!body.trim()) return;

            createComment.mutate(
              { body, mentionedUserIds: selectedMentionIds },
              {
                onSuccess: () => {
                  setBody("");
                  setSelectedMentionIds([]);
                  toast.success("Comment posted");
                },
                onError: (error) => {
                  const message =
                    error instanceof Error ? error.message : "Unable to post comment right now.";
                  toast.error(message);
                },
              },
            );
          }}
        >
          <label className="block text-sm font-medium">
            Comment
            <Textarea
              aria-label="Comment"
              className="mt-1 min-h-24"
              value={body}
              onChange={(event) => setBody(event.target.value)}
            />
          </label>
          {canMention ? (
            <RequisitionMentionInput
              candidates={mentionCandidatesQuery.data ?? []}
              selectedIds={selectedMentionIds}
              onChange={setSelectedMentionIds}
            />
          ) : null}
          <Button
            type="submit"
            disabled={createComment.isPending}
          >
            {createComment.isPending ? "Posting" : "Post comment"}
          </Button>
        </Form>
      ) : (
        <p className="text-sm text-muted-foreground">
          Comments are locked for this requisition state.
        </p>
      )}
    </div>
  );
}
