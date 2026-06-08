"use client";

import { useMemo } from "react";
import { usePathname, useRouter } from "next/navigation";
import { Separator } from "@cognify/ui/components/separator";
import {
  SidebarInset,
  SidebarProvider,
  SidebarTrigger,
} from "@cognify/ui/components/sidebar";
import { TooltipProvider } from "@cognify/ui/components/tooltip";
import { useCurrentUser } from "@/features/identity/hooks/use-current-user";
import { useLogout } from "@/features/identity/hooks/use-logout";
import { AppSidebar } from "./app-sidebar";
import { DefaultBreadcrumbs } from "./breadcrumbs";
import { getBreadcrumbs } from "./navigation";

export function DefaultAppShell({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname() || "/dashboard";
  const { data, isError, isLoading } = useCurrentUser();
  const context = data?.data;
  const logoutMutation = useLogout();
  const breadcrumbs = useMemo(() => getBreadcrumbs(pathname), [pathname]);

  if (isLoading) {
    return (
      <div className="flex min-h-svh items-center justify-center bg-background text-foreground">
        <div role="status" className="text-sm text-muted-foreground">
          Loading workspace...
        </div>
      </div>
    );
  }

  if (isError || !context) {
    return (
      <div className="flex min-h-svh items-center justify-center bg-background text-foreground">
        <div role="alert" className="text-sm text-muted-foreground">
          Unable to load workspace context.
        </div>
      </div>
    );
  }

  return (
    <TooltipProvider>
      <SidebarProvider>
        <a
          href="#main-content"
          className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-foreground focus:px-3 focus:py-2 focus:text-background"
        >
          Skip to main content
        </a>
        <AppSidebar
          context={context}
          pathname={pathname}
          logoutPending={logoutMutation.isPending}
          onLogout={() => {
            logoutMutation.mutate(undefined, {
              onSuccess: () => router.replace("/login"),
            });
          }}
        />
        <SidebarInset id="main-content" tabIndex={-1}>
          <header className="flex h-16 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
            <div className="flex items-center gap-2 px-4">
              <SidebarTrigger className="-ml-1" />
              <Separator
                orientation="vertical"
                className="mr-2 data-vertical:h-4 data-vertical:self-auto"
              />
              <DefaultBreadcrumbs items={breadcrumbs} />
            </div>
          </header>
          <div className="flex flex-1 flex-col gap-4 p-4 pt-0">
            {children}
          </div>
        </SidebarInset>
      </SidebarProvider>
    </TooltipProvider>
  );
}
