"use client";

import { useCallback, useState } from "react";
import {
  Alert,
  AlertDescription,
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
  Progress,
  Tabs,
  TabsList,
  TabsTrigger,
} from "@cognify/ui";
import { Upload, FileSpreadsheet } from "lucide-react";
import { toast } from "sonner";
import { useUploadPaymentImport } from "../hooks/use-ap-payment-import";
import { PaymentImportPreviewPanel } from "./payment-import-preview-panel";
import { PaymentImportReconciliationSummary } from "./payment-import-reconciliation-summary";

export function PaymentImportPage() {
  const [activeTab, setActiveTab] = useState("upload");
  const [batchId, setBatchId] = useState<string | null>(null);

  return (
    <section className="space-y-6">
      <header className="space-y-2">
        <h1 className="text-2xl font-semibold tracking-normal">Payment import</h1>
        <p className="text-sm text-muted-foreground">
          Upload, review, and reconcile payment imports from external sources.
        </p>
      </header>

      <Tabs
        value={activeTab}
        onValueChange={setActiveTab}
      >
        <TabsList aria-label="Import workflow steps" className="flex h-auto w-full flex-wrap justify-start">
          <TabsTrigger value="upload" className="min-h-11 px-3">
            Upload
          </TabsTrigger>
          <TabsTrigger value="review" className="min-h-11 px-3" disabled={!batchId}>
            Review
          </TabsTrigger>
          <TabsTrigger value="reconcile" className="min-h-11 px-3" disabled={!batchId}>
            Reconcile
          </TabsTrigger>
        </TabsList>

        <div className="mt-4">
          {activeTab === "upload" && (
            <PaymentImportUploadPanel
              onUploadSuccess={(id) => {
                setBatchId(id);
                setActiveTab("review");
              }}
            />
          )}
          {activeTab === "review" && batchId && (
            <PaymentImportPreviewPanel
              batchId={batchId}
              onReconcile={() => setActiveTab("reconcile")}
            />
          )}
          {activeTab === "reconcile" && batchId && (
            <PaymentImportReconciliationSummary batchId={batchId} />
          )}
        </div>
      </Tabs>
    </section>
  );
}

interface PaymentImportUploadPanelProps {
  onUploadSuccess: (batchId: string) => void;
}

export function PaymentImportUploadPanel({
  onUploadSuccess,
}: PaymentImportUploadPanelProps) {
  const [isDragging, setIsDragging] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [error, setError] = useState<string | null>(null);
  const uploadMutation = useUploadPaymentImport();

  const handleFile = useCallback(
    (file: File) => {
      setError(null);
      setUploadProgress(0);

      const validTypes = [
        "text/csv",
        "application/vnd.ms-excel",
        "application/json",
        "text/plain",
      ];

      const isValidType =
        validTypes.includes(file.type) ||
        file.name.endsWith(".csv") ||
        file.name.endsWith(".json");

      if (!isValidType) {
        setError("Please upload a CSV or JSON file.");
        return;
      }

      uploadMutation.mutate(
        { file },
        {
          onSuccess: (data) => {
            toast.success("File uploaded successfully");
            onUploadSuccess(data.batchId);
          },
          onError: (err) => {
            const message = errorToMessage(err) ?? "Failed to upload file.";
            setError(message);
          },
        },
      );
    },
    [uploadMutation, onUploadSuccess],
  );

  function handleDrop(event: React.DragEvent<HTMLDivElement>) {
    event.preventDefault();
    setIsDragging(false);
    const file = event.dataTransfer.files[0];
    if (file) {
      handleFile(file);
    }
  }

  function handleDragOver(event: React.DragEvent<HTMLDivElement>) {
    event.preventDefault();
    setIsDragging(true);
  }

  function handleDragLeave(event: React.DragEvent<HTMLDivElement>) {
    event.preventDefault();
    setIsDragging(false);
  }

  function handleInputChange(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    if (file) {
      handleFile(file);
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Upload payment file</CardTitle>
        <CardDescription>
          Drag and drop a CSV or JSON file containing payment records.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div
          onDrop={handleDrop}
          onDragOver={handleDragOver}
          onDragLeave={handleDragLeave}
          className={`flex flex-col items-center justify-center rounded-lg border-2 border-dashed p-12 transition-colors ${
            isDragging
              ? "border-primary bg-primary/5"
              : "border-muted-foreground/25 hover:border-muted-foreground/50"
          }`}
          role="button"
          tabIndex={0}
          aria-label="Drop zone for file upload"
          onKeyDown={(e) => {
            if (e.key === "Enter" || e.key === " ") {
              document.getElementById("file-upload")?.click();
            }
          }}
        >
          <div className="flex flex-col items-center gap-3 text-muted-foreground">
            {uploadMutation.isPending ? (
              <>
                <FileSpreadsheet className="h-10 w-10 animate-pulse" />
                <p className="text-sm font-medium">Uploading...</p>
              </>
            ) : (
              <>
                <Upload className="h-10 w-10" />
                <p className="text-sm font-medium">
                  Drag and drop a file here, or click to browse
                </p>
                <p className="text-xs">Supported formats: CSV, JSON</p>
              </>
            )}
          </div>

          <input
            id="file-upload"
            type="file"
            accept=".csv,.json,text/csv,application/json"
            className="hidden"
            onChange={handleInputChange}
            disabled={uploadMutation.isPending}
          />
        </div>

        {uploadMutation.isPending && (
          <div className="space-y-2" aria-live="polite">
            <Progress value={uploadProgress} className="w-full" aria-label="Upload progress" />
            <p className="text-xs text-muted-foreground">Processing upload...</p>
          </div>
        )}

        {error && (
          <Alert variant="destructive">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}
      </CardContent>
    </Card>
  );
}

function errorToMessage(error: unknown): string | null {
  if (typeof error === "object" && error !== null && "error" in error) {
    const apiError = (error as { error?: { message?: string } }).error;
    if (apiError?.message) return apiError.message;
  }
  if (error instanceof Error) return error.message;
  return null;
}
