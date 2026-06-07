"use client";

import { useState } from "react";
import {
  Alert,
  AlertDescription,
  Button,
  Empty,
  EmptyDescription,
  EmptyHeader,
  EmptyTitle,
  Form,
  Skeleton,
  Textarea,
} from "@cognify/ui";
import { toast } from "sonner";
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
    return (
      <div className="space-y-3">
        <Skeleton className="h-16 w-full" />
        <Skeleton className="h-16 w-full" />
        <Skeleton className="h-28 w-full" />
      </div>
    );
  }

  if (commentsQuery.isError) {
    return (
      <Alert variant="destructive">
        <AlertDescription>Comments could not be loaded.</AlertDescription>
      </Alert>
    );
  }

  return (
    <div className="space-y-4">
      <div className="space-y-3">
        {(commentsQuery.data ?? []).length === 0 ? (
          <Empty className="rounded-lg border">
            <EmptyHeader>
              <EmptyTitle>No comments yet.</EmptyTitle>
              <EmptyDescription>Record the context or next action for this approval task.</EmptyDescription>
            </EmptyHeader>
          </Empty>
        ) : null}
        {(commentsQuery.data ?? []).map((comment) => (
          <article key={comment.id} className="rounded-lg border p-3 text-sm">
            <div>
              <p className="font-medium">{comment.author.name}</p>
              <p className="text-xs text-muted-foreground">
                {formatTimestamp(comment.createdAt)}
              </p>
            </div>
            <p className="mt-2 whitespace-pre-wrap">{comment.body}</p>
          </article>
        ))}
      </div>
      <Form
        className="space-y-3 rounded-lg border p-4"
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
        <div className="space-y-2">
          <label htmlFor="approval-comment" className="block text-sm font-medium">
            Comment
          </label>
          <Textarea
            id="approval-comment"
            aria-label="Comment"
            value={body}
            onChange={(event) => setBody(event.target.value)}
          />
        </div>
        <Button
          type="submit"
          disabled={createComment.isPending || body.trim().length === 0}
        >
          {createComment.isPending ? "Posting" : "Post comment"}
        </Button>
      </Form>
    </div>
  );
}
