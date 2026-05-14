import type { LucideIcon } from "lucide-react";
import type { SearchMeta, SearchResponse, SearchResult } from "@cognify/api-client";

export type SearchResultViewModel = SearchResult;
export type { SearchMeta, SearchResponse };

export type SearchCommandViewModel = {
  id: string;
  group: string;
  label: string;
  description: string;
  href: string;
  keywords: string[];
  icon: LucideIcon;
  enabled: boolean;
};
