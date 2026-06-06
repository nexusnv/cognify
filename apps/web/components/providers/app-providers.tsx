"use client";

import { Toaster } from "@cognify/ui/components/sonner";
import { TooltipProvider } from "@cognify/ui/components/tooltip";
import type { ReactNode } from "react";
import { RightPanelProvider } from "@/components/right-panel/right-panel-provider";
import { AccessibilityProvider } from "./accessibility-provider";
import { AnalyticsProvider } from "./analytics-provider";
import { ErrorReportingProvider } from "./error-reporting-provider";
import { QueryProvider } from "./query-provider";
import { ThemeProvider } from "./theme-provider";

export function AppProviders({ children }: { children: ReactNode }) {
  return (
    <ErrorReportingProvider>
      <ThemeProvider>
        <AccessibilityProvider>
          <AnalyticsProvider>
            <QueryProvider>
              <TooltipProvider>
                <RightPanelProvider>{children}</RightPanelProvider>
              </TooltipProvider>
            </QueryProvider>
            <Toaster richColors />
          </AnalyticsProvider>
        </AccessibilityProvider>
      </ThemeProvider>
    </ErrorReportingProvider>
  );
}
