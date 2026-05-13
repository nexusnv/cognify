import { render, screen, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { DataTable } from "./data-table";
import { useDataTableState } from "./use-data-table-state";
import type { DataTableColumn } from "./data-table-types";

type Row = {
  id: string;
  number: string;
  title: string;
  status: string;
  total: string;
};

const rows: Row[] = [
  {
    id: "req-1",
    number: "REQ-2026-000001",
    title: "Field laptop refresh",
    status: "Draft",
    total: "MYR 3,600.00",
  },
  {
    id: "req-2",
    number: "REQ-2026-000002",
    title: "Office chairs",
    status: "Submitted",
    total: "MYR 1,200.00",
  },
];

const columns: DataTableColumn<Row>[] = [
  { id: "number", header: "Number", cell: (row) => row.number, widthClassName: "w-36" },
  { id: "title", header: "Title", cell: (row) => row.title, sortable: true },
  { id: "status", header: "Status", cell: (row) => row.status, hideOnMobile: false },
  { id: "internal", header: "Internal note", cell: (row) => `Internal ${row.id}`, hideOnMobile: true },
  { id: "total", header: "Estimated total", cell: (row) => row.total, align: "right" },
];

function SortHarness() {
  const tableState = useDataTableState({ initialSort: { columnId: "title", direction: "asc" } });

  return (
    <DataTable
      caption="Requisitions"
      rows={rows}
      columns={columns}
      getRowId={(row) => row.id}
      sort={tableState.sort}
      onSortChange={tableState.setSort}
      renderMobileRow={(row) => (
        <a href={`/requisitions/${row.id}`}>
          {row.title}
          <span>{row.total}</span>
        </a>
      )}
    />
  );
}

describe("DataTable", () => {
  it("renders desktop table rows with a caption and row actions", () => {
    render(
      <DataTable
        caption="Requisitions"
        rows={rows}
        columns={columns}
        getRowId={(row) => row.id}
        renderRowActions={(row) => <a href={`/requisitions/${row.id}`}>Open {row.number}</a>}
        renderMobileRow={(row) => <a href={`/requisitions/${row.id}`}>{row.title}</a>}
      />,
    );

    expect(screen.getByRole("table", { name: "Requisitions" })).toBeInTheDocument();
    expect(screen.getByText("REQ-2026-000001")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "Open REQ-2026-000001" })).toHaveAttribute(
      "href",
      "/requisitions/req-1",
    );
  });

  it("renders loading, error, and empty states", () => {
    const retry = vi.fn();
    const { rerender } = render(
      <DataTable
        caption="Requisitions"
        rows={[]}
        columns={columns}
        getRowId={(row) => row.id}
        state="loading"
        loadingLabel="Loading requisitions"
      />,
    );
    expect(screen.getByLabelText("Loading requisitions")).toBeInTheDocument();

    rerender(
      <DataTable
        caption="Requisitions"
        rows={[]}
        columns={columns}
        getRowId={(row) => row.id}
        state="error"
        errorTitle="Requisitions could not be loaded."
        onRetry={retry}
      />,
    );
    screen.getByRole("button", { name: "Retry" }).click();
    expect(retry).toHaveBeenCalledTimes(1);

    rerender(
      <DataTable
        caption="Requisitions"
        rows={[]}
        columns={columns}
        getRowId={(row) => row.id}
        state="empty"
        emptyTitle="No requisitions yet"
        emptyDescription="Create the first draft requisition for this tenant."
      />,
    );
    expect(screen.getByText("No requisitions yet")).toBeInTheDocument();
  });

  it("toggles sort state for sortable headers", async () => {
    const user = userEvent.setup();
    render(<SortHarness />);

    const titleHeader = screen.getByRole("columnheader", { name: /Title/ });
    expect(titleHeader).toHaveAttribute("aria-sort", "ascending");

    await user.click(within(titleHeader).getByRole("button", { name: "Sort by Title descending" }));
    expect(screen.getByRole("columnheader", { name: /Title/ })).toHaveAttribute(
      "aria-sort",
      "descending",
    );
  });

  it("renders a default mobile fallback that hides mobile-only columns", () => {
    render(
      <DataTable
        caption="Requisitions"
        rows={rows}
        columns={columns}
        getRowId={(row) => row.id}
      />,
    );

    const mobileList = screen.getByRole("list");
    expect(within(mobileList).getByText("Field laptop refresh")).toBeInTheDocument();
    expect(within(mobileList).getByText("REQ-2026-000001")).toBeInTheDocument();
    expect(within(mobileList).queryByText("Internal req-1")).not.toBeInTheDocument();
  });

  it("renders pagination controls when callbacks are provided", async () => {
    const user = userEvent.setup();
    const previousPage = vi.fn();
    const nextPage = vi.fn();

    render(
      <DataTable
        caption="Requisitions"
        rows={rows}
        columns={columns}
        getRowId={(row) => row.id}
        pagination={{ currentPage: 1, perPage: 10, total: 20, lastPage: 2 }}
        onPreviousPage={previousPage}
        onNextPage={nextPage}
      />,
    );

    const previousButton = screen.getByRole("button", { name: "Previous page" });
    const nextButton = screen.getByRole("button", { name: "Next page" });
    expect(previousButton).toBeDisabled();
    expect(nextButton).not.toBeDisabled();

    await user.click(nextButton);
    expect(nextPage).toHaveBeenCalledTimes(1);
    expect(previousPage).not.toHaveBeenCalled();
  });
});
