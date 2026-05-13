"use client";

import { Toaster } from "sonner";
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
              <RightPanelProvider>{children}</RightPanelProvider>
            </QueryProvider>
            <Toaster richColors />
          </AnalyticsProvider>
        </AccessibilityProvider>
      </ThemeProvider>
    </ErrorReportingProvider>
  );
}
