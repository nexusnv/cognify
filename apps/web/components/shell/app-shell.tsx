"use client";

import { useMemo, useState } from "react";
import { usePathname } from "next/navigation";
import { useCurrentUser } from "@/features/identity/hooks/use-current-user";
import { useSystemStatus } from "@/features/system-readiness/hooks/use-system-status";
import { getBreadcrumbs, shellNavGroups } from "./shell-route-config";
import { formatTenantRole, formatWorkspaceLabel, getVisibleNavGroups } from "./shell-utils";
import { MobileShellNav } from "./mobile-shell-nav";
import { RightPanelHost } from "./right-panel-host";
import { ShellFooter } from "./shell-footer";
import { ShellHeader } from "./shell-header";
import { ShellNav } from "./shell-nav";

export function AppShell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname() || "/dashboard";
  const { data } = useCurrentUser();
  const context = data?.data;
  const tenantName = formatWorkspaceLabel(context?.activeTenant?.name);
  const tenantId = context?.activeTenant?.id ?? null;
  const userName = context?.user?.name ?? "Account";
  const roleLabel = formatTenantRole(context?.activeRole);
  const permissions = context?.permissions;
  const canViewSystemStatus = Boolean(permissions?.canAccessAdmin);
  const systemStatusQuery = useSystemStatus(tenantId, canViewSystemStatus);
  const [mobileOpen, setMobileOpen] = useState(false);
  const breadcrumbs = useMemo(() => getBreadcrumbs(pathname), [pathname]);
  const groups = useMemo(
    () => (permissions ? getVisibleNavGroups(shellNavGroups, permissions) : []),
    [permissions],
  );

  return (
    <div className="min-h-screen bg-background text-foreground">
      <a
        href="#main-content"
        className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-foreground focus:px-3 focus:py-2 focus:text-background"
      >
        Skip to main content
      </a>
      <aside className="fixed inset-y-0 left-0 hidden w-72 border-r bg-card px-4 py-5 md:block">
        <div className="text-lg font-semibold">Cognify</div>
        <div className="mt-1 truncate text-xs text-muted-foreground">{tenantName}</div>
        <div className="mt-8">
          <ShellNav groups={groups} pathname={pathname} />
        </div>
      </aside>
      <div className="flex min-h-screen flex-col md:pl-72">
        <ShellHeader
          tenantName={tenantName}
          userName={userName}
          roleLabel={roleLabel}
          breadcrumbs={breadcrumbs}
          mobileNav={
            <MobileShellNav
              groups={groups}
              pathname={pathname}
              open={mobileOpen}
              onOpenChange={setMobileOpen}
            />
          }
        />
        <main id="main-content" className="flex-1 px-4 py-6 md:px-6" tabIndex={-1}>
          {children}
        </main>
        <ShellFooter
          tenantName={tenantName}
          canViewSystemStatus={canViewSystemStatus}
          readinessStatus={systemStatusQuery.data?.data.status}
        />
      </div>
      <RightPanelHost />
    </div>
  );
}
