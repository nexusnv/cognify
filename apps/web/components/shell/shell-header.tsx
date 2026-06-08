import Link from "next/link";
import { LogOut, UserCircle } from "lucide-react";
import { Button } from "@cognify/ui/components/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@cognify/ui/components/dropdown-menu";
import { CommandPaletteHost } from "./command-palette-host";
import { Breadcrumbs } from "./breadcrumbs";
import { NotificationHost } from "./notification-host";
import { ThemeToggle } from "./theme-toggle";
import type { BreadcrumbItem } from "./shell-types";

export interface ShellHeaderProps {
  tenantName: string;
  userName: string;
  roleLabel: string;
  breadcrumbs: BreadcrumbItem[];
  sidebarToggle: React.ReactNode;
  logoutPending?: boolean;
  onLogout?: () => void;
}

export function ShellHeader({
  tenantName,
  userName,
  roleLabel,
  breadcrumbs,
  sidebarToggle,
  logoutPending = false,
  onLogout,
}: ShellHeaderProps) {
  const displayedTenantName = tenantName.trim() || "Operational workspace";
  const displayedRoleLabel = roleLabel.trim() || "Member";
  const displayedUserName = userName.trim() || "Account";

  return (
    <header className="sticky top-0 z-30 border-b bg-background/95 backdrop-blur">
      <div className="flex min-h-16 items-center gap-3 px-4 md:px-6">
        {sidebarToggle}
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
          <ThemeToggle />
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button
                type="button"
                variant="outline"
                size="lg"
                aria-label="Account menu"
                className="max-w-44"
              >
                <UserCircle className="h-4 w-4" aria-hidden="true" />
                <span className="hidden truncate sm:inline">{displayedUserName}</span>
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
              <DropdownMenuLabel>
                <span className="block truncate text-foreground">{displayedUserName}</span>
                <span className="block truncate">{displayedRoleLabel}</span>
              </DropdownMenuLabel>
              <DropdownMenuSeparator />
              <DropdownMenuItem asChild>
                <Link href="/account">Account settings</Link>
              </DropdownMenuItem>
              {onLogout && (
                <>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem
                    disabled={logoutPending}
                    onSelect={(event) => {
                      event.preventDefault();
                      onLogout();
                    }}
                  >
                    <LogOut className="h-4 w-4" aria-hidden="true" />
                    {logoutPending ? "Signing out" : "Sign out"}
                  </DropdownMenuItem>
                </>
              )}
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </header>
  );
}
