import { render, screen, within } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { RecordWorkspaceLayout } from "./record-workspace-layout";

describe("record workspace layout", () => {
  it("renders title, status, metadata, actions, sections, main content, and sidebar", () => {
    render(
      <RecordWorkspaceLayout
        backHref="/requisitions"
        backLabel="Back to requisitions"
        eyebrow="REQ-2026-000001"
        title="Laptop refresh"
        status={<span>Draft</span>}
        primaryActions={<button type="button">Review and submit</button>}
        secondaryActions={<button type="button">Edit draft</button>}
        metadata={[
          { id: "estimated-total", label: "Estimated total", value: "MYR 3,600.00" },
          { id: "needed-by", label: "Needed by", value: "2026-06-15" },
          { id: "requester", label: "Requester", value: "Test User" },
        ]}
        sections={[
          { id: "overview", label: "Overview" },
          { id: "line-items", label: "Line items" },
          { id: "activity", label: "Activity" },
        ]}
        sidebar={<aside aria-label="Readiness">Checklist</aside>}
      >
        <section id="overview">
          <h2>Overview</h2>
        </section>
      </RecordWorkspaceLayout>,
    );

    expect(screen.getByRole("link", { name: "Back to requisitions" })).toHaveAttribute(
      "href",
      "/requisitions",
    );
    expect(screen.getByRole("heading", { name: "Laptop refresh", level: 1 })).toBeInTheDocument();
    expect(screen.getByText("REQ-2026-000001")).toBeInTheDocument();
    expect(screen.getByText("Draft")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Review and submit" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Edit draft" })).toBeInTheDocument();

    const metadata = screen.getByRole("group", { name: "Record metadata" });
    [
      ["Estimated total", "MYR 3,600.00"],
      ["Needed by", "2026-06-15"],
      ["Requester", "Test User"],
    ].forEach(([label, value]) => {
      expect(within(metadata).getByText(label)).toBeInTheDocument();
      expect(within(metadata).getByText(value)).toBeInTheDocument();
    });

    const sections = screen.getByRole("navigation", { name: "Record sections" });
    [
      ["Overview", "#overview"],
      ["Line items", "#line-items"],
      ["Activity", "#activity"],
    ].forEach(([label, href]) => {
      expect(within(sections).getByRole("link", { name: label })).toHaveAttribute("href", href);
    });
    expect(screen.getByRole("complementary", { name: "Record sidebar" })).toHaveTextContent(
      "Checklist",
    );
    expect(screen.getByRole("heading", { name: "Overview", level: 2 })).toBeInTheDocument();
  });
});
