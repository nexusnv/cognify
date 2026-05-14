import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { ShellFooter } from "@/components/shell/shell-footer";

describe("ShellFooter readiness indicator", () => {
  it("shows local demo readiness for admin users", () => {
    render(<ShellFooter tenantName="Acme Procurement" canViewSystemStatus readinessStatus="ok" />);

    expect(screen.getByText("Cognify · Local demo · Healthy")).toBeInTheDocument();
    expect(screen.getByText("Workspace: Acme Procurement")).toBeInTheDocument();
  });

  it("shows the degraded readiness label for admin users", () => {
    render(<ShellFooter tenantName="Acme Procurement" canViewSystemStatus readinessStatus="error" />);

    expect(screen.getByText("Cognify · Local demo · Needs attention")).toBeInTheDocument();
  });

  it("shows the warning readiness label for admin users", () => {
    render(<ShellFooter tenantName="Acme Procurement" canViewSystemStatus readinessStatus="warning" />);

    expect(screen.getByText("Cognify · Local demo · Warning")).toBeInTheDocument();
  });

  it("hides detailed readiness for non-admin users", () => {
    render(<ShellFooter tenantName="Acme Procurement" canViewSystemStatus={false} readinessStatus="error" />);

    expect(screen.getByText("Cognify")).toBeInTheDocument();
    expect(screen.queryByText(/Needs attention/)).not.toBeInTheDocument();
  });

  it("keeps the neutral label while admin readiness is still loading", () => {
    render(<ShellFooter tenantName="Acme Procurement" canViewSystemStatus />);

    expect(screen.getByText("Cognify")).toBeInTheDocument();
  });
});
