import type { ReactNode } from "react";

export type DataTableSortDirection = "asc" | "desc";

export type DataTableSort = {
  columnId: string;
  direction: DataTableSortDirection;
};

export type DataTableColumn<TRow> = {
  id: string;
  header: string;
  cell: (row: TRow) => ReactNode;
  sortable?: boolean;
  align?: "left" | "right" | "center";
  widthClassName?: string;
  hideOnMobile?: boolean;
};

export type DataTableState = "idle" | "loading" | "error" | "empty";

export type DataTablePagination = {
  currentPage: number;
  perPage: number;
  total: number;
  lastPage: number;
};
