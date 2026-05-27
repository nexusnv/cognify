import { ListGlobalSearchTypesItem } from "@cognify/api-client/schemas";
import type { ListGlobalSearchParams, SearchResult } from "@cognify/api-client/schemas";

export const GLOBAL_SEARCH_TYPES: NonNullable<ListGlobalSearchParams["types"]> = [
  ListGlobalSearchTypesItem.requisition,
  ListGlobalSearchTypesItem.vendor,
  ListGlobalSearchTypesItem.project,
  ListGlobalSearchTypesItem.rfq,
  ListGlobalSearchTypesItem.quotation,
  ListGlobalSearchTypesItem.award,
  ListGlobalSearchTypesItem.po_handoff,
];

export function matchesSearchResultQuery(result: SearchResult, query: string): boolean {
  return [result.title, result.subtitle ?? "", result.status ?? ""].some((value) =>
    value.toLowerCase().includes(query),
  );
}
