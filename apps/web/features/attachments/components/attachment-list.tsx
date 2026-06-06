"use client";

import { AlertCircle, Download, Eye, FileText, Loader2, MoreHorizontal, Trash2 } from "lucide-react";
import { useState } from "react";
import { AttachmentPreviewPanel } from "./attachment-preview-panel";
import { downloadAttachmentBlob } from "../api/attachments-api";
import { useAttachments, useAttachmentDelete } from "../hooks/use-attachments";
import type { AttachmentViewModel } from "../types/attachment-view-model";
import { isPreviewableAttachment } from "../types/attachment-view-model";
import {
  Alert,
  AlertDescription,
  AlertTitle,
  Badge,
  Button,
  Card,
  CardContent,
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
  ScrollArea,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@cognify/ui";

function formatFileSize(bytes: number) {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function AttachmentList({ requisitionId }: { requisitionId: string }) {
  const { data: attachments, isLoading, isError } = useAttachments(requisitionId);
  const deleteMutation = useAttachmentDelete(requisitionId);
  const [downloadError, setDownloadError] = useState<string | null>(null);
  const [previewAttachment, setPreviewAttachment] = useState<AttachmentViewModel | null>(null);

  async function handleDownload(attachment: AttachmentViewModel) {
    setDownloadError(null);

    try {
      const blob = await downloadAttachmentBlob(attachment.id);
      const objectUrl = URL.createObjectURL(blob);
      const link = document.createElement("a");

      link.href = objectUrl;
      link.download = attachment.filename;
      link.rel = "noopener";
      document.body.appendChild(link);
      link.click();
      link.remove();

      URL.revokeObjectURL(objectUrl);
    } catch {
      setDownloadError(`Could not download ${attachment.filename}.`);
    }
  }

  if (isLoading) {
    return (
      <Card>
        <CardContent className="flex items-center justify-center py-6 text-sm text-muted-foreground">
          <Loader2 className="mr-2 h-4 w-4 animate-spin" aria-hidden="true" />
          Loading attachments
        </CardContent>
      </Card>
    );
  }

  if (isError) {
    return (
      <Alert role="alert">
        <AlertCircle className="h-4 w-4" aria-hidden="true" />
        <AlertTitle>Could not load attachments</AlertTitle>
        <AlertDescription>Could not load attachments.</AlertDescription>
      </Alert>
    );
  }

  if (!attachments || attachments.length === 0) {
    return (
      <Card>
        <CardContent className="py-4 text-sm text-muted-foreground">
          No evidence files have been uploaded yet.
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="space-y-3">
      {downloadError ? (
        <Alert role="alert">
          <AlertCircle className="h-4 w-4" aria-hidden="true" />
          <AlertTitle>Download failed</AlertTitle>
          <AlertDescription>{downloadError}</AlertDescription>
        </Alert>
      ) : null}

      <Card>
        <CardContent className="p-0">
          <ScrollArea className="max-h-[32rem]">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>File</TableHead>
                  <TableHead>Details</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="w-16 text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {attachments.map((attachment) => (
                  <TableRow key={attachment.id}>
                    <TableCell className="max-w-0">
                      <div className="flex min-w-0 items-center gap-3">
                        <FileText className="h-4 w-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                        <div className="min-w-0">
                          <p className="truncate font-medium">{attachment.filename}</p>
                          <p className="text-xs text-muted-foreground">{attachment.mimeType}</p>
                        </div>
                      </div>
                    </TableCell>
                    <TableCell className="text-sm text-muted-foreground">
                      {formatFileSize(attachment.sizeBytes)}
                      {attachment.uploadedBy ? ` · by ${attachment.uploadedBy.name}` : null}
                    </TableCell>
                    <TableCell>
                      <Badge variant={attachment.permissions.canDelete ? "secondary" : "outline"}>
                        {attachment.permissions.canDelete ? "Editable" : "Read only"}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button type="button" variant="ghost" size="icon" aria-label={`Open actions for ${attachment.filename}`}>
                            <MoreHorizontal className="h-4 w-4" aria-hidden="true" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuLabel>{attachment.filename}</DropdownMenuLabel>
                          {attachment.permissions.canPreview && isPreviewableAttachment(attachment) ? (
                            <DropdownMenuItem onSelect={() => setPreviewAttachment(attachment)}>
                              <Eye className="h-4 w-4" aria-hidden="true" />
                              Preview
                            </DropdownMenuItem>
                          ) : null}
                          {attachment.permissions.canDownload ? (
                            <DropdownMenuItem onSelect={() => void handleDownload(attachment)}>
                              <Download className="h-4 w-4" aria-hidden="true" />
                              Download
                            </DropdownMenuItem>
                          ) : null}
                          {attachment.permissions.canPreview || attachment.permissions.canDownload ? (
                            <DropdownMenuSeparator />
                          ) : null}
                          {attachment.permissions.canDelete ? (
                            <DropdownMenuItem
                              variant="destructive"
                              onSelect={() => deleteMutation.mutate(attachment.id)}
                            >
                              <Trash2 className="h-4 w-4" aria-hidden="true" />
                              Delete
                            </DropdownMenuItem>
                          ) : null}
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </ScrollArea>
        </CardContent>
      </Card>

      <Dialog open={previewAttachment !== null} onOpenChange={(open) => !open && setPreviewAttachment(null)}>
        <DialogContent className="max-h-[90vh] overflow-hidden sm:max-w-4xl">
          {previewAttachment ? (
            <>
              <DialogHeader className="text-left">
                <DialogTitle>{previewAttachment.filename}</DialogTitle>
                <DialogDescription>
                  {formatFileSize(previewAttachment.sizeBytes)}
                  {previewAttachment.uploadedBy ? ` · Uploaded by ${previewAttachment.uploadedBy.name}` : null}
                </DialogDescription>
              </DialogHeader>
              <ScrollArea className="max-h-[75vh] pr-3">
                <AttachmentPreviewPanel attachment={previewAttachment} />
              </ScrollArea>
            </>
          ) : null}
        </DialogContent>
      </Dialog>
    </div>
  );
}
