import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { afterEach, beforeEach, describe, expect, it } from "vitest";
import { RightPanelProvider } from "@/components/right-panel/right-panel-provider";
import { RightPanelRoot } from "@/components/right-panel/right-panel-root";
import { server } from "@/tests/msw/server";
import { resetAttachmentMockState } from "../mocks/attachments-handlers";
import { AttachmentList } from "../components/attachment-list";
import { AttachmentUploader } from "../components/attachment-uploader";

function renderWithQuery(ui: React.ReactElement) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <RightPanelProvider>
        {ui}
        <RightPanelRoot />
      </RightPanelProvider>
    </QueryClientProvider>,
  );
}

describe("attachments workflow", () => {
  beforeEach(() => {
    window.localStorage.setItem("cognify.activeTenantId", "tenant-1");
  });

  afterEach(() => {
    resetAttachmentMockState();
    window.localStorage.removeItem("cognify.activeTenantId");
  });

  it("shows empty evidence state when no attachments exist", async () => {
    renderWithQuery(<AttachmentList requisitionId="req-empty" />);

    expect(
      await screen.findByText("No evidence files have been uploaded yet."),
    ).toBeInTheDocument();
  });

  it("renders populated attachment rows with metadata", async () => {
    renderWithQuery(<AttachmentList requisitionId="req-1" />);

    expect(await screen.findByText("supplier-quote.pdf")).toBeInTheDocument();
    expect(screen.getByText("spec-sheet.png")).toBeInTheDocument();
    expect(screen.getByLabelText("Preview supplier-quote.pdf")).toBeInTheDocument();
    expect(screen.getByLabelText("Download supplier-quote.pdf")).toBeInTheDocument();
    expect(screen.getByLabelText("Delete supplier-quote.pdf")).toBeInTheDocument();
  });

  it("renders a list error state when attachments fail to load", async () => {
    server.use(
      http.get("/api/requisitions/req-error/attachments", () => {
        return HttpResponse.json(
          {
            error: {
              code: "server_error",
              message: "Could not load attachments.",
              details: {},
              requestId: null,
            },
          },
          { status: 500 },
        );
      }),
    );

    renderWithQuery(<AttachmentList requisitionId="req-error" />);

    expect(await screen.findByText("Could not load attachments.")).toBeInTheDocument();
  });

  it("hides attachment actions when permissions do not allow them", async () => {
    renderWithQuery(<AttachmentList requisitionId="req-readonly" />);

    expect(await screen.findByText("readonly-evidence.pdf")).toBeInTheDocument();
    expect(screen.queryByLabelText("Preview readonly-evidence.pdf")).not.toBeInTheDocument();
    expect(screen.queryByLabelText("Download readonly-evidence.pdf")).not.toBeInTheDocument();
    expect(screen.queryByLabelText("Delete readonly-evidence.pdf")).not.toBeInTheDocument();
  });

  it("opens a preview in the right panel", async () => {
    const user = userEvent.setup();

    renderWithQuery(<AttachmentList requisitionId="req-1" />);

    expect(await screen.findByText("supplier-quote.pdf")).toBeInTheDocument();

    await user.click(screen.getByLabelText("Preview supplier-quote.pdf"));

    const panel = await screen.findByRole("dialog", { name: "supplier-quote.pdf" });
    expect(panel).toBeInTheDocument();
    expect(
      within(panel).getByRole("heading", { name: "supplier-quote.pdf", level: 2 }),
    ).toBeInTheDocument();
    expect(await within(panel).findByTitle("Preview of supplier-quote.pdf")).toBeInTheDocument();
  });

  it("opens a preview panel for previewable attachments", async () => {
    const user = userEvent.setup();

    renderWithQuery(<AttachmentList requisitionId="req-1" />);

    await user.click(await screen.findByLabelText("Preview supplier-quote.pdf"));

    const panel = await screen.findByRole("dialog", { name: "supplier-quote.pdf" });
    expect(within(panel).getByTitle("Preview of supplier-quote.pdf")).toBeInTheDocument();
  });

  it("uploads a new attachment and renders it in the list", async () => {
    const user = userEvent.setup();

    renderWithQuery(
      <div>
        <AttachmentUploader requisitionId="req-1" />
        <AttachmentList requisitionId="req-1" />
      </div>,
    );

    const fileInput = screen.getByLabelText("Upload evidence");
    const file = new File(["test content"], "new-quote.pdf", { type: "application/pdf" });
    await user.upload(fileInput, file);

    const uploadButton = screen.getByLabelText("Upload selected file");
    await user.click(uploadButton);

    await waitFor(() => {
      expect(screen.getByText("new-quote.pdf")).toBeInTheDocument();
    });
  });

  it("shows upload validation error for unsupported files", async () => {
    const user = userEvent.setup();

    renderWithQuery(
      <div>
        <AttachmentUploader requisitionId="req-1" />
        <AttachmentList requisitionId="req-1" />
      </div>,
    );

    const fileInput = screen.getByLabelText("Upload evidence");
    const file = new File(["script content"], "script.exe", { type: "application/x-msdownload" });
    await user.upload(fileInput, file);

    const uploadButton = screen.getByLabelText("Upload selected file");
    await user.click(uploadButton);

    expect(await screen.findByRole("alert")).toHaveTextContent("File type not supported.");
  });

  it("shows a download error when the file cannot be downloaded", async () => {
    const user = userEvent.setup();
    server.use(
      http.get("/api/attachments/att-1/download", () => {
        return HttpResponse.json(
          {
            error: {
              code: "server_error",
              message: "Download failed.",
              details: {},
              requestId: null,
            },
          },
          { status: 500 },
        );
      }),
    );

    renderWithQuery(<AttachmentList requisitionId="req-1" />);

    expect(await screen.findByText("supplier-quote.pdf")).toBeInTheDocument();
    await user.click(screen.getByLabelText("Download supplier-quote.pdf"));

    expect(await screen.findByRole("alert")).toHaveTextContent(
      "Could not download supplier-quote.pdf.",
    );
  });

  it("deletes an attachment and removes it from the list", async () => {
    const user = userEvent.setup();

    renderWithQuery(<AttachmentList requisitionId="req-1" />);

    expect(await screen.findByText("supplier-quote.pdf")).toBeInTheDocument();

    await user.click(screen.getByLabelText("Delete supplier-quote.pdf"));

    await waitFor(() => {
      expect(screen.queryByText("supplier-quote.pdf")).not.toBeInTheDocument();
    });
  });
});
