"use client";

import { Toaster } from "sonner";
import { AccessibilityProvider } from "./accessibility-provider";
import { AnalyticsProvider } from "./analytics-provider";
import { ErrorReportingProvider } from "./error-reporting-provider";
import { QueryProvider } from "./query-provider";
import { ThemeProvider } from "./theme-provider";

export function AppProviders({ children }: { children: React.ReactNode }) {
  return (
    <ErrorReportingProvider>
      <ThemeProvider>
        <AccessibilityProvider>
          <AnalyticsProvider>
            <QueryProvider>{children}</QueryProvider>
            <Toaster richColors />
          </AnalyticsProvider>
        </AccessibilityProvider>
      </ThemeProvider>
    </ErrorReportingProvider>
  );
}
