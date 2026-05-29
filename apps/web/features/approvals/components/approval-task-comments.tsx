"use client";

import { useState } from "react";
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
      <form
        className="space-y-3 rounded-md border p-4"
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
          <textarea
            aria-label="Comment"
            className="mt-1 min-h-24 w-full rounded-md border px-3 py-2 text-base font-normal"
            value={body}
            onChange={(event) => setBody(event.target.value)}
          />
        </label>
        <button
          type="submit"
          className="min-h-11 rounded-md bg-foreground px-4 text-sm font-medium text-background disabled:opacity-50"
          disabled={createComment.isPending || body.trim().length === 0}
        >
          {createComment.isPending ? "Posting" : "Post comment"}
        </button>
      </form>
    </div>
  );
}
