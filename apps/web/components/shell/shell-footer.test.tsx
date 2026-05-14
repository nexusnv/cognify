import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { ShellFooter } from "./shell-footer";

describe("shell footer", () => {
  it("renders a meaningful workspace fallback for empty tenant labels", () => {
    render(<ShellFooter tenantName="" />);

    expect(screen.getByRole("contentinfo")).toHaveTextContent("Workspace: Operational workspace");
  });
});
