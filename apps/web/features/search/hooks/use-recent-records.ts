"use client";

import { useSyncExternalStore } from "react";
import type { SearchResultViewModel } from "../types/search-view-model";

const STORAGE_KEY = "cognify.recentRecords.v1";
const RECENT_RECORDS_EVENT = "cognify:recent-records-changed";
const MAX_RECENT_RECORDS = 8;
const EMPTY_RECENT_RECORDS: SearchResultViewModel[] = [];

let cachedRawValue: string | null = null;
let cachedRecentRecords: SearchResultViewModel[] = EMPTY_RECENT_RECORDS;

export function useRecentRecords(): SearchResultViewModel[] {
  return useSyncExternalStore(subscribe, readRecentRecords, () => EMPTY_RECENT_RECORDS);
}

export function rememberRecentRecord(record: SearchResultViewModel): void {
  if (typeof window === "undefined") {
    return;
  }

  const currentRecords = readRecentRecords();
  const nextRecords = [record, ...currentRecords.filter((item) => item.href !== record.href)].slice(
    0,
    MAX_RECENT_RECORDS,
  );
  const serialized = JSON.stringify(nextRecords);
  try {
    window.sessionStorage.setItem(STORAGE_KEY, serialized);
  } catch (error) {
    console.debug("Unable to persist recent records.", error);
  }
  cachedRawValue = serialized;
  cachedRecentRecords = nextRecords;
  window.dispatchEvent(new Event(RECENT_RECORDS_EVENT));
}

function readRecentRecords(): SearchResultViewModel[] {
  if (typeof window === "undefined") {
    return EMPTY_RECENT_RECORDS;
  }

  try {
    const raw = window.sessionStorage.getItem(STORAGE_KEY);
    if (raw === cachedRawValue) {
      return cachedRecentRecords;
    }

    cachedRawValue = raw;

    if (!raw) {
      cachedRecentRecords = EMPTY_RECENT_RECORDS;
      return cachedRecentRecords;
    }

    const parsed = JSON.parse(raw) as unknown;
    if (!Array.isArray(parsed)) {
      cachedRecentRecords = EMPTY_RECENT_RECORDS;
      return cachedRecentRecords;
    }

    cachedRecentRecords = parsed.filter(isSearchResultViewModel);
    return cachedRecentRecords;
  } catch {
    cachedRawValue = null;
    cachedRecentRecords = EMPTY_RECENT_RECORDS;
    return cachedRecentRecords;
  }
}

function subscribe(callback: () => void): () => void {
  if (typeof window === "undefined") {
    return () => undefined;
  }

  const handleStorage = () => callback();
  window.addEventListener("storage", handleStorage);
  window.addEventListener(RECENT_RECORDS_EVENT, handleStorage);

  return () => {
    window.removeEventListener("storage", handleStorage);
    window.removeEventListener(RECENT_RECORDS_EVENT, handleStorage);
  };
}

function isSearchResultViewModel(value: unknown): value is SearchResultViewModel {
  if (typeof value !== "object" || value === null) {
    return false;
  }

  const candidate = value as Record<string, unknown>;
  return (
    (candidate.type === "requisition" || candidate.type === "project") &&
    typeof candidate.id === "string" &&
    typeof candidate.title === "string" &&
    typeof candidate.href === "string"
  );
}
