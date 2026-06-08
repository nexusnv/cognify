"use client";

import { useEffect, useMemo, useState } from "react";
import { usePathname, useRouter } from "next/navigation";
import { Button } from "@cognify/ui/components/button";
import { Sidebar, SidebarContent, SidebarHeader, SidebarProvider } from "@cognify/ui/components/sidebar";
import { TooltipProvider } from "@cognify/ui/components/tooltip";
import { useCurrentUser } from "@/features/identity/hooks/use-current-user";
import { useLogout } from "@/features/identity/hooks/use-logout";
import { useSystemStatus } from "@/features/system-readiness/hooks/use-system-status";
import {
  getBreadcrumbs,
  getShellRouteContext,
  primaryShellNavItems,
} from "./shell-route-config";
import {
  formatTenantRole,
  formatWorkspaceLabel,
  getVisiblePrimaryNavItems,
} from "./shell-utils";
import { RightPanelHost } from "./right-panel-host";
import { ShellFooter } from "./shell-footer";
import { ShellHeader } from "./shell-header";
import { PrimaryShellNav, SecondaryShellNav } from "./shell-nav";

function isEditableShortcutTarget(target: EventTarget | null) {
  if (!(target instanceof Element)) return false;

  return Boolean(
    target.closest("input, textarea, select, [contenteditable='true'], [contenteditable='']"),
  );
}

export function AppShell({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname() || "/dashboard";
  const { data } = useCurrentUser();
  const logoutMutation = useLogout();
  const context = data?.data;
  const tenantName = formatWorkspaceLabel(context?.activeTenant?.name);
  const tenantId = context?.activeTenant?.id ?? null;
  const userName = context?.user?.name ?? "Account";
  const roleLabel = formatTenantRole(context?.activeRole);
  const permissions = context?.permissions;
  const canViewSystemStatus = Boolean(permissions?.canAccessAdmin);
  const systemStatusQuery = useSystemStatus(tenantId, canViewSystemStatus);
  const [primaryOpenState, setPrimaryOpenState] = useState(true);
  const [secondaryOpen, setSecondaryOpen] = useState(true);
  const breadcrumbs = useMemo(() => getBreadcrumbs(pathname), [pathname]);
  const routeContext = useMemo(
    () => getShellRouteContext(pathname, permissions),
    [pathname, permissions],
  );
  const primaryItems = useMemo(
    () => (permissions ? getVisiblePrimaryNavItems(primaryShellNavItems, permissions) : []),
    [permissions],
  );
  const primaryOpen = routeContext.hasSecondarySidebar ? false : primaryOpenState;

  useEffect(() => {
    if (!routeContext.hasSecondarySidebar) return;

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key.toLowerCase() !== "b" || (!event.metaKey && !event.ctrlKey)) {
        return;
      }

      if (isEditableShortcutTarget(event.target)) {
        return;
      }

      event.preventDefault();
      setSecondaryOpen((open) => !open);
    };

    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, [routeContext.hasSecondarySidebar]);

  const headerContent = (
    <>
      <ShellHeader
        tenantName={tenantName}
        userName={userName}
        roleLabel={roleLabel}
        breadcrumbs={breadcrumbs}
        sidebarToggle={
          routeContext.hasSecondarySidebar ? (
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => setSecondaryOpen((open) => !open)}
            >
              {secondaryOpen ? "Collapse secondary sidebar" : "Expand secondary sidebar"}
            </Button>
          ) : (
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => setPrimaryOpenState((open) => !open)}
            >
              {primaryOpen ? "Collapse primary sidebar" : "Expand primary sidebar"}
            </Button>
          )
        }
        logoutPending={logoutMutation.isPending}
        onLogout={() => {
          logoutMutation.mutate(undefined, {
            onSuccess: () => router.replace("/login"),
          });
        }}
      />
      <main id="main-content" className="flex-1 px-4 py-6 md:px-6" tabIndex={-1}>
        {children}
      </main>
      <ShellFooter
        tenantName={tenantName}
        canViewSystemStatus={canViewSystemStatus}
        readinessStatus={systemStatusQuery.data?.data.status}
      />
    </>
  );

  return (
    <TooltipProvider>
      <div
        data-testid="desktop-app-shell"
        data-primary-state={primaryOpen ? "expanded" : "collapsed"}
        data-secondary-present={routeContext.hasSecondarySidebar ? "true" : "false"}
        data-secondary-state={
          routeContext.hasSecondarySidebar ? (secondaryOpen ? "expanded" : "collapsed") : undefined
        }
        className="min-h-screen bg-background text-foreground"
      >
        <SidebarProvider
          open={primaryOpen}
          onOpenChange={(open) => {
            if (routeContext.hasSecondarySidebar) return;
            setPrimaryOpenState(open);
          }}
        >
          <a
            href="#main-content"
            className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-foreground focus:px-3 focus:py-2 focus:text-background"
          >
            Skip to main content
          </a>
          <Sidebar collapsible="icon" className="border-r bg-card">
            <SidebarHeader className="px-4 py-5">
              <div className="text-lg font-semibold">Cognify</div>
              <div className="truncate text-xs text-muted-foreground">{tenantName}</div>
            </SidebarHeader>
            <SidebarContent className="px-3 py-3">
              <PrimaryShellNav
                items={primaryItems}
                activeArea={routeContext.primaryArea}
                pathname={pathname}
              />
            </SidebarContent>
          </Sidebar>
          {routeContext.hasSecondarySidebar ? (
            <>
              <div
                className={[
                  "hidden min-h-screen shrink-0 overflow-hidden bg-card/60 text-sidebar-foreground transition-[width,border-color] duration-200 ease-linear md:flex md:flex-col",
                  secondaryOpen ? "w-64 border-r" : "w-0 border-r-0",
                ].join(" ")}
                data-secondary-sidebar
                data-state={secondaryOpen ? "expanded" : "collapsed"}
              >
                {secondaryOpen ? (
                  <>
                    <SidebarHeader className="px-4 py-5">
                      <div className="text-sm font-semibold">Workspace</div>
                      <div className="truncate text-xs text-muted-foreground">Procurement</div>
                    </SidebarHeader>
                    <SidebarContent className="px-3 py-3">
                      <SecondaryShellNav groups={routeContext.secondaryGroups} pathname={pathname} />
                    </SidebarContent>
                  </>
                ) : null}
              </div>
              <div className="flex min-h-screen flex-1 flex-col">{headerContent}</div>
            </>
          ) : (
            <div className="flex min-h-screen flex-1 flex-col">{headerContent}</div>
          )}
          <RightPanelHost />
        </SidebarProvider>
      </div>
    </TooltipProvider>
  );
}
