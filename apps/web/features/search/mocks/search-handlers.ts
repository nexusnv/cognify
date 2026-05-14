import { http, HttpResponse } from "msw";
import { searchResultFixtures } from "./search-fixtures";
import type { SearchResultViewModel } from "../types/search-view-model";

const MAX_LIMIT = 25;

export const searchHandlers = [
  http.get("/api/search", ({ request }) => {
    const url = new URL(request.url);
    const query = (url.searchParams.get("query") ?? "").trim();
    const types = parseTypes(url.searchParams.get("types"));
    const limit = parseLimit(url.searchParams.get("limit"));

    if (query.length < 2 || !types.every((type) => type === "requisition") || limit === null) {
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
      .filter((result) => matchesResult(result, normalizedQuery))
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

function parseTypes(rawTypes: string | null): string[] {
  if (!rawTypes) {
    return ["requisition"];
  }

  return rawTypes
    .split(",")
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

function matchesResult(result: SearchResultViewModel, query: string): boolean {
  return [result.title, result.subtitle ?? "", result.status ?? ""].some((value) =>
    value.toLowerCase().includes(query),
  );
}

