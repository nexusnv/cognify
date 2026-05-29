"use client";

import { useState } from "react";
import { toast } from "sonner";
import { Button, Card, CardContent, CardHeader, CardTitle, ScrollArea, Textarea } from "@cognify/ui";
import {
  useApprovalTaskComments,
  useCreateApprovalTaskComment,
} from "../hooks/use-approval-task-comments";

function formatTimestamp(value: string) {
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

export function ApprovalTaskComments({ taskId }: { taskId: string }) {
  const commentsQuery = useApprovalTaskComments(taskId);
  const createComment = useCreateApprovalTaskComment(taskId);
  const [body, setBody] = useState("");

  if (commentsQuery.isLoading) {
    return <p className="text-sm text-muted-foreground">Loading comments</p>;
  }

  if (commentsQuery.isError) {
    return <p className="text-sm text-red-700">Comments could not be loaded.</p>;
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Comments</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        <ScrollArea className="max-h-80 pr-4">
          <div className="space-y-3">
            {(commentsQuery.data ?? []).length === 0 ? (
              <p className="text-sm text-muted-foreground">No comments yet.</p>
            ) : null}
            {(commentsQuery.data ?? []).map((comment) => (
              <Card key={comment.id}>
                <CardContent className="space-y-1 p-3 text-sm">
                <div className="space-y-1">
                  <p className="font-medium">{comment.author.name}</p>
                  <p className="text-xs text-muted-foreground">{formatTimestamp(comment.createdAt)}</p>
                </div>
                <p className="mt-2 whitespace-pre-wrap">{comment.body}</p>
                </CardContent>
              </Card>
            ))}
          </div>
        </ScrollArea>
        <form
          className="space-y-3"
          onSubmit={(event) => {
            event.preventDefault();
            const trimmedBody = body.trim();
            if (!trimmedBody) return;

            createComment.mutate(
              { body: trimmedBody, mentionedUserIds: [] },
              {
                onSuccess: () => {
                  setBody("");
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
              className="mt-1"
              value={body}
              onChange={(event) => setBody(event.target.value)}
            />
          </label>
          <div className="flex justify-end">
            <Button
              type="submit"
              disabled={createComment.isPending || body.trim().length === 0}
            >
              {createComment.isPending ? "Posting" : "Post comment"}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  );
}
