"use client";

import Link from "next/link";
import { CommandPaletteHost } from "./command-palette-host";
import { RightPanelHost } from "./right-panel-host";
import { useCurrentUser } from "@/features/identity/hooks/use-current-user";

export function WorkspaceShell({ children }: { children: React.ReactNode }) {
  const { data } = useCurrentUser();
  const context = data?.data;
  const activeTenant = context?.activeTenant;

  return (
    <div className="min-h-screen bg-background text-foreground">
      <aside className="fixed inset-y-0 left-0 hidden w-16 border-r bg-card md:block" />
      <aside className="fixed inset-y-0 left-16 hidden w-72 border-r bg-background px-4 py-5 md:block">
        <div className="text-sm font-medium">Workspace</div>
        <nav className="mt-6 flex flex-col gap-2 text-sm text-muted-foreground">
          <Link className="rounded-md px-2 py-2 text-foreground" href="/requisitions">
            Requisitions
          </Link>
          <span className="px-2 py-2">Vendors</span>
          <span className="px-2 py-2">Quotations</span>
          <span className="px-2 py-2">Comparison</span>
          <span className="px-2 py-2">Approvals</span>
          <span className="px-2 py-2">Evidence</span>
        </nav>
      </aside>
      <div className="md:pl-[22rem]">
        <header className="sticky top-0 z-10 flex h-16 items-center justify-between border-b bg-background/95 px-6 backdrop-blur">
          <div className="flex items-center gap-4">
            <div className="text-sm text-muted-foreground">
              {activeTenant ? activeTenant.name : "Operational workspace"}
            </div>
            {context?.user && (
              <Link
                href="/account"
                className="text-sm text-muted-foreground underline underline-offset-2 hover:text-foreground"
              >
                {context.user.name}
              </Link>
            )}
          </div>
          <CommandPaletteHost />
        </header>
        <main id="main-content" className="px-6 py-6">
          {children}
        </main>
      </div>
      <RightPanelHost />
    </div>
  );
}
