"use client";

import * as Dialog from "@radix-ui/react-dialog";
import { Command } from "cmdk";
import {
  Building2,
  CheckCircle2,
  FileText,
  FolderKanban,
  ReceiptText,
  Search,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";
import { useMemo, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { CommandPaletteItem } from "./command-palette-item";
import { useCurrentUser } from "@/features/identity/hooks/use-current-user";
import { getSearchCommands } from "../search-commands";
import { useGlobalSearch } from "../hooks/use-global-search";
import { useRecentRecords } from "../hooks/use-recent-records";
import type { SearchResultViewModel } from "../types/search-view-model";

export function CommandPalette({
  open,
  onOpenChange,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  }) {
  const router = useRouter();
  const currentUserQuery = useCurrentUser();
  const permissions = currentUserQuery.data?.data.permissions ?? null;
  const tenantId = currentUserQuery.data?.data.activeTenant?.id ?? null;
  const commands = useMemo(() => {
    const uniqueCommands = new Map(
      getSearchCommands(permissions).map((command) => [command.id, command] as const),
    );

    return [...uniqueCommands.values()];
  }, [permissions]);
  const recentRecords = useRecentRecords();
  const [query, setQuery] = useState("");
  const inputRef = useRef<HTMLInputElement | null>(null);
  const normalizedQuery = query.trim().toLowerCase();
  const searchState = useGlobalSearch(query, tenantId);
  const showRemoteSearch = searchState.query.length >= 2;

  const localCommands = commands.filter((command) => command.enabled);
  const visibleCommands =
    normalizedQuery.length === 0
      ? localCommands
      : localCommands.filter((command) =>
          matchesQuery(normalizedQuery, [
            command.label,
            command.description,
            command.keywords.join(" "),
          ]),
        );
  const visibleRecentRecords =
    normalizedQuery.length === 0
      ? recentRecords
      : recentRecords.filter((record) =>
          matchesQuery(normalizedQuery, [record.title, record.subtitle, record.status]),
        );
  const visibleRemoteResults = searchState.results;
  const hasVisibleItems =
    visibleCommands.length > 0 ||
    visibleRecentRecords.length > 0 ||
    visibleRemoteResults.length > 0;

  function handleNavigate(href: string) {
    router.push(href);
    onOpenChange(false);
  }

  return (
    <Dialog.Root
      open={open}
      onOpenChange={(nextOpen) => {
        onOpenChange(nextOpen);
      }}
    >
      <Dialog.Portal>
        <Dialog.Overlay className="fixed inset-0 z-50 bg-black/40 backdrop-blur-[2px]" />
        <Dialog.Content
          className="fixed left-1/2 top-24 z-50 w-[min(92vw,48rem)] -translate-x-1/2 overflow-hidden rounded-md border bg-background shadow-2xl outline-none"
          onOpenAutoFocus={(event) => {
            event.preventDefault();
            window.requestAnimationFrame(() => {
              inputRef.current?.focus();
            });
          }}
        >
          <Dialog.Title className="sr-only">Command menu</Dialog.Title>
          <Dialog.Description className="sr-only">
            Search commands, recent records, and requisitions.
          </Dialog.Description>
          <Command className="w-full" shouldFilter={false}>
            <div className="flex items-center gap-3 border-b px-3">
              <Search className="h-4 w-4 shrink-0 text-muted-foreground" aria-hidden="true" />
              <Command.Input
                ref={inputRef}
                autoFocus
                value={query}
                onValueChange={setQuery}
                placeholder="Search or jump to..."
                className="h-12 w-full bg-transparent text-sm outline-none placeholder:text-muted-foreground"
              />
              <button
                type="button"
                className="inline-flex h-9 items-center rounded-md border px-2 text-xs text-muted-foreground hover:text-foreground"
                onClick={() => onOpenChange(false)}
              >
                Esc
              </button>
            </div>
            <Command.List className="max-h-[min(70vh,34rem)] overflow-y-auto p-2">
              {searchState.errorMessage ? (
                <div role="alert" className="mx-1 mb-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900">
                  {searchState.errorMessage}
                </div>
              ) : null}

              {visibleCommands.length > 0 ? (
                <Command.Group heading="Commands" className="pb-2">
                  {visibleCommands.map((command) => (
                    <CommandPaletteItem
                      key={command.id}
                      value={command.label}
                      keywords={command.keywords}
                      icon={command.icon}
                      label={command.label}
                      description={command.description}
                      onSelect={() => handleNavigate(command.href)}
                    />
                  ))}
                </Command.Group>
              ) : null}

              {visibleRecentRecords.length > 0 && !showRemoteSearch ? (
                <Command.Group heading="Recent records" className="pb-2">
                  {visibleRecentRecords.map((record) => (
                    <CommandPaletteItem
                      key={`recent:${record.id}`}
                      value={`${record.title} ${record.subtitle ?? ""} ${record.status ?? ""}`}
                      keywords={[record.title, record.subtitle ?? "", record.status ?? ""]}
                      label={record.title}
                      description={record.subtitle ?? undefined}
                      trailing={record.status ?? undefined}
                      onSelect={() => handleNavigate(record.href)}
                    />
                  ))}
                </Command.Group>
              ) : null}

              {showRemoteSearch && searchState.isLoading ? (
                <div
                  role="status"
                  aria-label="Searching requisitions"
                  className="px-3 py-2 text-sm text-muted-foreground"
                >
                  Searching requisitions...
                </div>
              ) : null}

              {showRemoteSearch && !searchState.errorMessage && visibleRemoteResults.length > 0 ? (
                <Command.Group heading="Search results" className="pb-2">
                  {visibleRemoteResults.map((result) => (
                    <CommandPaletteItem
                      key={`result:${result.type}:${result.id}`}
                      value={`${result.title} ${result.subtitle ?? ""} ${result.status ?? ""}`}
                      keywords={[result.title, result.subtitle ?? "", result.status ?? ""]}
                      icon={resultIcons[result.type]}
                      label={result.title}
                      description={result.subtitle ?? undefined}
                      trailing={result.status ?? undefined}
                      onSelect={() => handleNavigate(result.href)}
                    />
                  ))}
                </Command.Group>
              ) : null}

              {!hasVisibleItems && !searchState.isLoading && !searchState.errorMessage ? (
                <Command.Empty className="px-3 py-6 text-sm text-muted-foreground">
                  No matching commands or requisitions.
                </Command.Empty>
              ) : null}
            </Command.List>
          </Command>
        </Dialog.Content>
      </Dialog.Portal>
    </Dialog.Root>
  );
}

function matchesQuery(query: string, values: Array<string | null | undefined>): boolean {
  return values.some((value) => (value ?? "").toLowerCase().includes(query));
}

const resultIcons: Record<SearchResultViewModel["type"], LucideIcon> = {
  requisition: FileText,
  vendor: Building2,
  project: FolderKanban,
  rfq: ReceiptText,
  quotation: ReceiptText,
  award: CheckCircle2,
};
