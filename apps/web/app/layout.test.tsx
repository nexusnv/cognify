import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, it, vi } from "vitest";
import RootLayout from "./layout";

vi.mock("next/font/google", () => ({
  IBM_Plex_Sans: () => ({ variable: "font-sans-variable" }),
}));

vi.mock("@/components/providers/app-providers", () => ({
  AppProviders: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

describe("RootLayout", () => {
  it("renders theme-neutral server markup for next-themes", () => {
    const markup = renderToStaticMarkup(
      <RootLayout>
        <main>Root content</main>
      </RootLayout>,
    );

    expect(markup).toContain('<html lang="en"');
    expect(markup).toContain("font-sans-variable");
    expect(markup).not.toContain(" dark ");
    expect(markup).not.toContain('class="dark');
  });
});
