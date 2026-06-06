"use client";

import { useEffect, useMemo, useRef, useState, type ChangeEvent } from "react";
import { getApiErrorMessage } from "@cognify/api-client";
import type { SaveQuotationManualEntryRequest } from "@cognify/api-client/schemas";
import { Alert, AlertDescription, Button, Card, CardContent, CardHeader, CardTitle, Input } from "@cognify/ui";
import { useSaveQuotationManualEntry } from "../hooks/use-quotation-manual-entry";
import {
  useQuotationAttachments,
  useRfqInvitationQuotation,
  useRfqInvitationQuotationUpload,
} from "../hooks/use-quotation-upload";
import { useQuotationVersions } from "../hooks/use-quotation-versions";
import { QuotationManualEntryPanel } from "./quotation-manual-entry-panel";
import { QuotationVersionDetail } from "./quotation-version-detail";
import { QuotationVersionHistory } from "./quotation-version-history";

const uploadableInvitationStatuses = new Set(["sent", "acknowledged"]);

export function QuotationEvidencePanel({
  invitationId,
  invitationStatus,
}: {
  invitationId: string;
  invitationStatus: string;
}) {
  const quotationQuery = useRfqInvitationQuotation(invitationId);
  const quotation = quotationQuery.data ?? null;
  const attachmentsQuery = useQuotationAttachments(quotation?.id ?? null);
  const versionsQuery = useQuotationVersions(quotation?.id ?? null);
  const refetchVersions = versionsQuery.refetch;
  const uploadMutation = useRfqInvitationQuotationUpload(invitationId);
  const createManualEntryMutation = useSaveQuotationManualEntry(invitationId, quotation?.id);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [selectedVersionId, setSelectedVersionId] = useState<string | null>(null);
  const versions = useMemo(() => {
    const nextVersions = [...(versionsQuery.data ?? [])];
    nextVersions.sort((left, right) => {
      if (left.isCurrent !== right.isCurrent) {
        return left.isCurrent ? -1 : 1;
      }

      return right.versionNumber - left.versionNumber;
    });

    return nextVersions;
  }, [versionsQuery.data]);
  const selectedVersion = useMemo(
    () => versions.find((version) => version.id === selectedVersionId) ?? null,
    [selectedVersionId, versions],
  );
  const latestVersionId = versions[0]?.id ?? null;
  const activeSelectedVersionId = selectedVersion?.id ?? latestVersionId;
  const activeSelectedVersion = selectedVersion ?? versions.find((version) => version.id === latestVersionId) ?? null;
  const previousSyncRef = useRef<{ quotationId: string | null; versionCount: number | null }>({
    quotationId: null,
    versionCount: null,
  });

  const canUpload = uploadableInvitationStatuses.has(invitationStatus);
  const attachments = attachmentsQuery.data ?? quotation?.attachments ?? [];
  const hasQuotation = quotation !== null;
  const fileCount = quotation?.fileCount ?? attachments.length;
  const errorMessage = resolveQuotationError({
    quotationQuery,
    uploadMutation,
    createManualEntryMutation,
  });

  useEffect(() => {
    if (!quotation?.id) {
      previousSyncRef.current = { quotationId: null, versionCount: null };
      return;
    }

    const currentVersionCount = quotation.versionCount ?? null;
    const shouldRefetch =
      previousSyncRef.current.quotationId === quotation.id &&
      previousSyncRef.current.versionCount !== currentVersionCount;
    previousSyncRef.current = { quotationId: quotation.id, versionCount: currentVersionCount };

    if (shouldRefetch) {
      void refetchVersions();
    }
  }, [quotation?.id, quotation?.versionCount, refetchVersions]);

  function handleFileSelect(event: ChangeEvent<HTMLInputElement>) {
    setSelectedFile(event.target.files?.[0] ?? null);
    uploadMutation.reset();
  }

  async function handleUpload() {
    if (!selectedFile || !canUpload || uploadMutation.isPending) return;

    try {
      await uploadMutation.mutateAsync(selectedFile);
      setSelectedFile(null);
      if (fileInputRef.current) {
        fileInputRef.current.value = "";
      }
    } catch {
      return;
    }
  }

  async function handleCreateStructuredQuotation() {
    const payload: SaveQuotationManualEntryRequest = {
      lineItems: [],
    };

    try {
      await createManualEntryMutation.mutateAsync(payload);
    } catch {
      return;
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-lg">{hasQuotation ? "Quotation received" : "Quotation evidence"}</CardTitle>
        {hasQuotation ? (
          <p className="text-sm text-muted-foreground">
            {fileCount} file{fileCount === 1 ? "" : "s"} received
          </p>
        ) : (
          <p className="text-sm text-muted-foreground">No quotation files received yet.</p>
        )}
      </CardHeader>
      <CardContent className="space-y-4">
        {attachments.length > 0 ? (
          <ul className="space-y-1 text-sm">
            {attachments.map((attachment) => (
              <li key={attachment.id} className="truncate text-foreground">
                {attachment.filename}
              </li>
            ))}
          </ul>
        ) : null}

        <div className="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
          <label className="block text-sm font-medium">
            Buyer-received quotation file
            <Input
              ref={fileInputRef}
              type="file"
              className="mt-1 text-sm file:mr-2 file:rounded-md file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary-foreground hover:file:cursor-pointer"
              onChange={handleFileSelect}
              disabled={!canUpload || uploadMutation.isPending}
            />
          </label>

          <p className="text-xs text-muted-foreground sm:col-start-1">Upload one file at a time.</p>
          <Button type="button" onClick={() => void handleUpload()} disabled={!selectedFile || !canUpload || uploadMutation.isPending}>
            Upload buyer-received quotation
          </Button>
        </div>

        {selectedFile ? <p className="text-xs text-muted-foreground">Selected file: {selectedFile.name}</p> : null}

        {errorMessage ? (
          <Alert variant="destructive">
            <AlertDescription>{errorMessage}</AlertDescription>
          </Alert>
        ) : null}

        {quotation ? (
          <div className="space-y-3">
            <QuotationManualEntryPanel
              invitationId={invitationId}
              invitationStatus={invitationStatus}
              quotation={quotation}
            />

            <div className="space-y-3">
              <QuotationVersionHistory
                versions={versions}
                selectedVersionId={activeSelectedVersionId}
                onSelectVersion={setSelectedVersionId}
              />
              <QuotationVersionDetail version={activeSelectedVersion ?? null} />
            </div>
          </div>
        ) : (
          <div className="space-y-3 rounded-lg bg-muted/30 p-3">
            <p className="text-sm text-muted-foreground">
              Upload a quotation file or create structured quotation data to start the response record.
            </p>
            <Button
              type="button"
              variant="outline"
              onClick={() => void handleCreateStructuredQuotation()}
              disabled={!canUpload || createManualEntryMutation.isPending}
            >
              Create structured quotation
            </Button>
          </div>
        )}
      </CardContent>
    </Card>
  );
}

function resolveQuotationError({
  quotationQuery,
  uploadMutation,
  createManualEntryMutation,
}: {
  quotationQuery: ReturnType<typeof useRfqInvitationQuotation>;
  uploadMutation: ReturnType<typeof useRfqInvitationQuotationUpload>;
  createManualEntryMutation: ReturnType<typeof useSaveQuotationManualEntry>;
}): string | null {
  if (quotationQuery.isError) {
    return getApiErrorMessage(quotationQuery.error);
  }

  if (uploadMutation.isError) {
    return getApiErrorMessage(uploadMutation.error);
  }

  if (createManualEntryMutation.isError) {
    return getApiErrorMessage(createManualEntryMutation.error);
  }

  return null;
}
