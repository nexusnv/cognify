"use client";

import { useState } from "react";
import type { DataTableSort } from "./data-table-types";

export function useDataTableState({ initialSort }: { initialSort?: DataTableSort } = {}) {
  const [sort, setSort] = useState<DataTableSort | undefined>(initialSort);

  return {
    sort,
    setSort,
  };
}
