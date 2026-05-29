"use client";

import { useState } from "react";
import { toast } from "sonner";
import { Badge, Button, Card, CardContent, CardDescription, CardHeader, CardTitle, ScrollArea, Textarea } from "@cognify/ui";
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
    return <Card><CardContent className="pt-6 text-sm text-muted-foreground">Loading comments</CardContent></Card>;
  }

  if (commentsQuery.isError) {
    return <Card><CardContent className="pt-6 text-sm text-red-700">Comments could not be loaded.</CardContent></Card>;
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Comments</CardTitle>
        <CardDescription>Collaboration history and mention updates.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <ScrollArea className="max-h-80 pr-3">
          <div className="space-y-3">
            {(commentsQuery.data ?? []).length === 0 ? (
              <p className="text-sm text-muted-foreground">No comments yet.</p>
            ) : null}
            {(commentsQuery.data ?? []).map((comment) => (
              <article key={comment.id} className="rounded-md bg-muted/30 p-3 text-sm">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="font-medium">{comment.author.name}</p>
                    <p className="text-xs text-muted-foreground">{formatTimestamp(comment.createdAt)}</p>
                  </div>
                </div>
                <p className="mt-2 whitespace-pre-wrap">{comment.body}</p>
                {comment.mentions.length > 0 ? (
                  <div className="mt-2 flex flex-wrap items-center gap-2">
                    <span className="text-xs text-muted-foreground">Mentioned:</span>
                    {comment.mentions.map((mention) => (
                      <Badge key={mention.mentionedUser.id} variant="secondary">
                        {mention.mentionedUser.name}
                      </Badge>
                    ))}
                  </div>
                ) : null}
              </article>
            ))}
          </div>
        </ScrollArea>
      {canComment ? (
        <form
          className="space-y-3 rounded-md bg-muted/30 p-4"
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
          <div className="space-y-2">
            <p className="text-sm font-medium">Comment</p>
            <Textarea aria-label="Comment" className="min-h-24" value={body} onChange={(event) => setBody(event.target.value)} />
          </div>
          {canMention ? (
            <RequisitionMentionInput
              candidates={mentionCandidatesQuery.data ?? []}
              selectedIds={selectedMentionIds}
              onChange={setSelectedMentionIds}
            />
          ) : null}
          <Button type="submit" disabled={createComment.isPending}>
            {createComment.isPending ? "Posting" : "Post comment"}
          </Button>
        </form>
      ) : (
        <p className="text-sm text-muted-foreground">
          Comments are locked for this requisition state.
        </p>
      )}
      </CardContent>
    </Card>
  );
}
