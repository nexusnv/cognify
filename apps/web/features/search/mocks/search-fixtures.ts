import type { SearchResultViewModel } from "../types/search-view-model";

export const searchResultFixtures: SearchResultViewModel[] = [
  {
    type: "requisition",
    id: "req-1",
    title: "Office fit-out procurement",
    subtitle: "REQ-2026-000001",
    status: "submitted",
    href: "/requisitions/req-1",
    updatedAt: "2026-05-14T10:30:00.000Z",
  },
  {
    type: "requisition",
    id: "req-2",
    title: "Warehouse packing supplies",
    subtitle: "REQ-2026-000002",
    status: "submitted",
    href: "/requisitions/req-2",
    updatedAt: "2026-05-09T06:45:00.000Z",
  },
];

