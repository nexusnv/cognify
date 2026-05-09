import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { AppProviders } from "./app-providers";

describe("AppProviders", () => {
  it("renders children inside global providers", () => {
    render(
      <AppProviders>
        <main>Provider smoke test</main>
      </AppProviders>,
    );

    expect(screen.getByText("Provider smoke test")).toBeInTheDocument();
  });
});
