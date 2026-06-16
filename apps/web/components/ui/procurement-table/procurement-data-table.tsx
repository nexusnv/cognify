"use client";

// shadcn-factory-exception: TanStack Table state and procurement table density require shared glue beyond shadcn Table primitives; primitives=Table,Button,DropdownMenu,Checkbox,Alert,Empty,Skeleton,Spinner; routes=requisitions,projects,approvals,quotations,sourcing

import { ArrowDown, ArrowUp, ChevronsUpDown } from "lucide-react";
import type { ReactNode } from "react";
import {
  Alert,
  AlertDescription,
  AlertTitle,
  Button,
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyTitle,
  Skeleton,
  Spinner,
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@cognify/ui";
import type {
  DataTableColumn,
  DataTablePagination,
  DataTableSort,
  DataTableState,
} from "./data-table-types";

export function DataTable<TRow>({
  caption,
  rows,
  columns,
  getRowId,
  state = "idle",
  loadingLabel,
  errorTitle,
  emptyTitle,
  emptyDescription,
  onRetry,
  sort,
  onSortChange,
  pagination,
  onPreviousPage,
  onNextPage,
  renderRowActions,
  renderMobileRow,
}: {
  caption: string;
  rows: TRow[];
  columns: DataTableColumn<TRow>[];
  getRowId: (row: TRow) => string;
  state?: DataTableState;
  loadingLabel?: string;
  errorTitle?: string;
  emptyTitle?: string;
  emptyDescription?: string;
  onRetry?: () => void;
  sort?: DataTableSort;
  onSortChange?: (sort: DataTableSort) => void;
  pagination?: DataTablePagination;
  onPreviousPage?: () => void;
  onNextPage?: () => void;
  renderRowActions?: (row: TRow) => ReactNode;
  renderMobileRow?: (row: TRow) => ReactNode;
}) {
  if (state === "loading") return <DataTableLoading label={loadingLabel} />;

  if (state === "error") {
    return <DataTableError title={errorTitle ?? "Rows could not be loaded."} onRetry={onRetry} />;
  }

  if (state === "empty") {
    return <DataTableEmpty title={emptyTitle ?? "No rows found"} description={emptyDescription} />;
  }

  return (
    <div className="space-y-3">
      <div className="hidden overflow-auto rounded-md border md:block">
        <Table className="table-fixed text-left text-sm">
          <TableCaption className="sr-only">{caption}</TableCaption>
          <TableHeader className="bg-card text-xs uppercase text-muted-foreground">
            <TableRow>
              {columns.map((column) => {
                const activeSort = sort?.columnId === column.id ? sort.direction : undefined;
                const ariaSort =
                  activeSort === "asc"
                    ? "ascending"
                    : activeSort === "desc"
                      ? "descending"
                      : "none";

                return (
                  <TableHead
                    key={column.id}
                    scope="col"
                    aria-sort={column.sortable ? ariaSort : undefined}
                    className={`${column.widthClassName ?? ""} px-3 py-3 ${alignClassName(
                      column.align,
                    )}`}
                  >
                    {column.sortable && onSortChange ? (
                      <Button
                        type="button"
                        variant="ghost"
                        size="xs"
                        className="h-auto justify-start gap-1 px-0 font-medium uppercase tracking-wide"
                        onClick={() => onSortChange(nextSort(column.id, sort))}
                        aria-label={`Sort by ${column.header} ${
                          activeSort === "asc" ? "descending" : "ascending"
                        }`}
                      >
                        {column.header}
                        {activeSort === "asc" ? (
                          <ArrowUp className="size-3.5" aria-hidden="true" />
                        ) : activeSort === "desc" ? (
                          <ArrowDown className="size-3.5" aria-hidden="true" />
                        ) : (
                          <ChevronsUpDown className="size-3.5" aria-hidden="true" />
                        )}
                      </Button>
                    ) : (
                      column.header
                    )}
                  </TableHead>
                );
              })}
              {renderRowActions ? (
                <TableHead scope="col" className="w-32 px-3 py-3">
                  Actions
                </TableHead>
              ) : null}
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows.map((row) => (
              <TableRow key={getRowId(row)}>
                {columns.map((column) => (
                  <TableCell key={column.id} className={`px-3 py-4 ${alignClassName(column.align)}`}>
                    {column.cell(row)}
                  </TableCell>
                ))}
                {renderRowActions ? <TableCell className="px-3 py-4">{renderRowActions(row)}</TableCell> : null}
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      {renderMobileRow ? (
        <div className="space-y-3 md:hidden">
          {rows.map((row) => (
            <div key={getRowId(row)}>{renderMobileRow(row)}</div>
          ))}
        </div>
      ) : (
        <ul className="space-y-3 md:hidden">
          {rows.map((row) => {
            const mobileColumns = columns.filter((column) => column.hideOnMobile !== true);

            return (
              <li key={getRowId(row)}>
                <article className="rounded-md border p-3">
                  <dl className="grid gap-3">
                    {mobileColumns.map((column) => (
                      <div
                        key={column.id}
                        className="grid gap-1 sm:grid-cols-[10rem_minmax(0,1fr)] sm:gap-3"
                      >
                        <dt className="text-xs font-medium uppercase text-muted-foreground">
                          {column.header}
                        </dt>
                        <dd className={`min-w-0 break-words text-sm ${alignClassName(column.align)}`}>
                          {column.cell(row)}
                        </dd>
                      </div>
                    ))}
                  </dl>
                  {renderRowActions ? (
                    <div className="mt-3 flex justify-end">{renderRowActions(row)}</div>
                  ) : null}
                </article>
              </li>
            );
          })}
        </ul>
      )}

      {pagination ? (
        <div className="flex flex-wrap items-center justify-between gap-3">
          <p className="text-sm text-muted-foreground">
            Showing {rows.length} of {pagination.total} records
          </p>
          {onPreviousPage || onNextPage ? (
            <div className="flex items-center gap-2">
              <Button
                type="button"
                variant="outline"
                size="sm"
                className="min-h-11"
                onClick={onPreviousPage}
                disabled={!onPreviousPage || pagination.currentPage <= 1}
              >
                Previous page
              </Button>
              <Button
                type="button"
                variant="outline"
                size="sm"
                className="min-h-11"
                onClick={onNextPage}
                disabled={!onNextPage || pagination.currentPage >= pagination.lastPage}
              >
                Next page
              </Button>
            </div>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}

function DataTableLoading({ label = "Loading rows" }: { label?: string }) {
  return (
    <div className="space-y-4 rounded-md border p-4" aria-label={label} aria-live="polite">
      <div className="flex items-center gap-2 text-sm text-muted-foreground">
        <Spinner className="size-4" />
        <span>{label}</span>
      </div>
      <div className="space-y-2">
        {Array.from({ length: 5 }).map((_, index) => (
          <Skeleton key={index} className="h-10 w-full" />
        ))}
      </div>
    </div>
  );
}

function DataTableError({ title, onRetry }: { title: string; onRetry?: () => void }) {
  return (
    <Alert variant="destructive">
      <AlertTitle>{title}</AlertTitle>
      <AlertDescription>
        {onRetry ? (
          <div className="mt-3 flex">
            <Button type="button" variant="outline" size="sm" className="min-h-11" onClick={onRetry}>
              Retry
            </Button>
          </div>
        ) : null}
      </AlertDescription>
    </Alert>
  );
}

function DataTableEmpty({ title, description }: { title: string; description?: string }) {
  return (
    <Empty className="border-dashed">
      <EmptyHeader>
        <EmptyContent>
          <EmptyTitle>{title}</EmptyTitle>
          {description ? <EmptyDescription>{description}</EmptyDescription> : null}
        </EmptyContent>
      </EmptyHeader>
    </Empty>
  );
}

function alignClassName(align?: "left" | "right" | "center") {
  if (align === "right") return "text-right";
  if (align === "center") return "text-center";
  return "text-left";
}

function nextSort(columnId: string, current?: DataTableSort): DataTableSort {
  if (current?.columnId === columnId && current.direction === "asc") {
    return { columnId, direction: "desc" };
  }

  return { columnId, direction: "asc" };
}
