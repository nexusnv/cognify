// shadcn-factory-exception: TanStack Table state and procurement table density require shared glue beyond shadcn Table primitives; primitives=Table,Button,DropdownMenu,Checkbox,Alert,Empty,Skeleton,Spinner; routes=requisitions,projects,approvals,quotations,sourcing

export { DataTable } from "./procurement-data-table";
export type {
  DataTableColumn,
  DataTablePagination,
  DataTableSort,
  DataTableSortDirection,
  DataTableState,
} from "./data-table-types";
export { useDataTableState, useProcurementTableState } from "./use-procurement-table-state";
