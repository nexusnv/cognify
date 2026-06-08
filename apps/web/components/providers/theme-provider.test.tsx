import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { ThemeProvider } from "./theme-provider";

const nextThemeProviderProps = vi.hoisted(() => ({
  current: null as null | Record<string, unknown>,
}));

vi.mock("next-themes", () => ({
  ThemeProvider: ({ children, ...props }: React.PropsWithChildren<Record<string, unknown>>) => {
    nextThemeProviderProps.current = props;
    return <>{children}</>;
  },
}));

describe("ThemeProvider", () => {
  it("defaults to light mode until theming is finalized", () => {
    render(
      <ThemeProvider>
        <div>Theme provider content</div>
      </ThemeProvider>,
    );

    expect(screen.getByText("Theme provider content")).toBeInTheDocument();
    expect(nextThemeProviderProps.current).toEqual(
      expect.objectContaining({
        attribute: "class",
        defaultTheme: "light",
      }),
    );
  });
});
