import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import Home from "./page";

describe("home page", () => {
  it("routes first-time users to sign in before opening the workspace", () => {
    render(<Home />);

    for (const link of screen.getAllByRole("link", { name: "Open workspace" })) {
      expect(link).toHaveAttribute("href", "/login?next=%2Fdashboard");
    }

    expect(screen.getAllByRole("link", { name: "Sign in" })[0]).toHaveAttribute(
      "href",
      "/login?next=%2Fdashboard",
    );
  });
});
