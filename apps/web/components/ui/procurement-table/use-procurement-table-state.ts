"use client";

// shadcn-factory-exception: TanStack Table state and procurement table density require shared glue beyond shadcn Table primitives; primitives=Table,Button,DropdownMenu,Checkbox,Alert,Empty,Skeleton,Spinner; routes=requisitions,projects,approvals,quotations,sourcing

import { useState } from "react";
import type { DataTableSort } from "./data-table-types";

export function useDataTableState({ initialSort }: { initialSort?: DataTableSort } = {}) {
  const [sort, setSort] = useState<DataTableSort | undefined>(initialSort);

  return {
    sort,
    setSort,
  };
}

export const useProcurementTableState = useDataTableState;
