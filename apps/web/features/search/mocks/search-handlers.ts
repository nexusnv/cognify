import { http, HttpResponse } from "msw";
import type { ListGlobalSearchTypesItem } from "@cognify/api-client/schemas";
import { searchResultFixtures } from "./search-fixtures";
import { GLOBAL_SEARCH_TYPES, matchesSearchResultQuery } from "../search-contract";

const MAX_LIMIT = 25;
const ALLOWED_TYPES = new Set(GLOBAL_SEARCH_TYPES);

export const searchHandlers = [
  http.get("/api/search", ({ request }) => {
    const url = new URL(request.url);
    const query = (url.searchParams.get("query") ?? "").trim();
    const types = parseTypes(url.searchParams);
    const limit = parseLimit(url.searchParams.get("limit"));

    if (
      query.length < 2 ||
      !types.every((type) => ALLOWED_TYPES.has(type as ListGlobalSearchTypesItem)) ||
      limit === null
    ) {
      return HttpResponse.json(
        {
          error: {
            code: "validation_failed",
            message: "The search query is invalid.",
            details: {},
            requestId: null,
          },
        },
        { status: 422 },
      );
    }

    const normalizedQuery = query.toLowerCase();
    const data = searchResultFixtures
      .filter((result) => types.includes(result.type))
      .filter((result) => matchesSearchResultQuery(result, normalizedQuery))
      .slice(0, limit);

    return HttpResponse.json({
      data,
      meta: {
        query,
        limit,
        returned: data.length,
      },
    });
  }),
];

function parseTypes(searchParams: URLSearchParams): string[] {
  const repeatedTypes = searchParams.getAll("types");
  const singleType = searchParams.get("types");
  const rawTypes = repeatedTypes.length > 0 ? repeatedTypes : singleType ? [singleType] : [];

  if (rawTypes.length === 0) {
    return ["requisition"];
  }

  return rawTypes
    .flatMap((value) => value.split(","))
    .map((type) => type.trim())
    .filter(Boolean);
}

function parseLimit(rawLimit: string | null): number | null {
  if (rawLimit === null || rawLimit === "") {
    return 10;
  }

  const limit = Number(rawLimit);
  if (!Number.isInteger(limit) || limit < 1 || limit > MAX_LIMIT) {
    return null;
  }

  return limit;
}
