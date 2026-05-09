import { CommandPaletteHost } from "./command-palette-host";
import { RightPanelHost } from "./right-panel-host";

export function DashboardShell({ children }: { children: React.ReactNode }) {
  return (
    <div className="min-h-screen bg-background text-foreground">
      <aside className="fixed inset-y-0 left-0 hidden w-64 border-r bg-card px-4 py-5 md:block">
        <div className="text-lg font-semibold">Cognify</div>
        <nav className="mt-8 flex flex-col gap-2 text-sm text-muted-foreground">
          <span>Dashboard</span>
          <span>Requisitions</span>
          <span>Vendors</span>
          <span>Approvals</span>
          <span>Reporting</span>
        </nav>
      </aside>
      <div className="md:pl-64">
        <header className="sticky top-0 z-10 flex h-16 items-center justify-between border-b bg-background/95 px-6 backdrop-blur">
          <div className="text-sm text-muted-foreground">Dashboard</div>
          <div className="flex items-center gap-3 text-sm">
            <CommandPaletteHost />
            <span>System nominal</span>
          </div>
        </header>
        <main id="main-content" className="px-6 py-6">
          {children}
        </main>
        <footer className="border-t px-6 py-4 text-xs text-muted-foreground">
          Cognify · local scaffold · support pending
        </footer>
      </div>
      <RightPanelHost />
    </div>
  );
}
