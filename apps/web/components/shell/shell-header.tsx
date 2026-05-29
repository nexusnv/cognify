import Link from "next/link";
import { LogOut } from "lucide-react";
import { Button, Separator } from "@cognify/ui";
import { CommandPaletteHost } from "./command-palette-host";
import { Breadcrumbs } from "./breadcrumbs";
import { NotificationHost } from "./notification-host";
import type { BreadcrumbItem } from "./shell-types";

export interface ShellHeaderProps {
  tenantName: string;
  userName: string;
  roleLabel: string;
  breadcrumbs: BreadcrumbItem[];
  mobileNav: React.ReactNode;
  logoutPending?: boolean;
  onLogout?: () => void;
}

export function ShellHeader({
  tenantName,
  userName,
  roleLabel,
  breadcrumbs,
  mobileNav,
  logoutPending = false,
  onLogout,
}: ShellHeaderProps) {
  const displayedTenantName = tenantName.trim() || "Operational workspace";
  const displayedRoleLabel = roleLabel.trim() || "Member";
  const displayedUserName = userName.trim() || "Account";

  return (
    <header className="sticky top-0 z-30 border-b bg-background/95 backdrop-blur">
      <div className="flex min-h-16 items-center gap-3 px-4 md:px-6">
        {mobileNav}
        <div className="min-w-0 flex-1">
          <div className="flex min-w-0 items-center gap-2 text-sm">
            <span className="truncate font-medium">{displayedTenantName}</span>
            <span className="text-muted-foreground" aria-hidden="true">
              /
            </span>
            <span className="shrink-0 text-muted-foreground">{displayedRoleLabel}</span>
          </div>
          <div className="mt-1">
            <Breadcrumbs items={breadcrumbs} />
          </div>
        </div>
        <div className="flex shrink-0 items-center gap-2">
          <CommandPaletteHost />
          <NotificationHost />
          <Separator orientation="vertical" className="hidden h-6 sm:block" />
          <Button
            asChild
            variant="outline"
            className="hidden min-h-10 max-w-44 truncate text-muted-foreground sm:inline-flex"
          >
            <Link href="/account">{displayedUserName}</Link>
          </Button>
          {onLogout && (
            <Button
              type="button"
              aria-label="Sign out"
              variant="outline"
              className="min-h-10 text-muted-foreground"
              onClick={onLogout}
              disabled={logoutPending}
            >
              <LogOut className="h-4 w-4" aria-hidden="true" />
              <span className="hidden sm:inline">{logoutPending ? "Signing out" : "Sign out"}</span>
              <span className="sm:hidden">Sign out</span>
            </Button>
          )}
        </div>
      </div>
    </header>
  );
}
