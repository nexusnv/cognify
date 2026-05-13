import { render, screen } from "@testing-library/react";
import { z } from "zod";
import { describe, expect, it } from "vitest";
import { FormErrorSummary } from "./form-error-summary";
import { FormField } from "./form-field";
import {
  flattenZodFieldErrors,
  focusFirstInvalidField,
  normalizeValidationErrors,
} from "./validation-errors";

describe("FormField", () => {
  it("wires labels, descriptions, and errors to the control", () => {
    render(
      <FormField
        htmlFor="title"
        label="Title"
        description="Use a short procurement title."
        error="Title is required."
        required
      >
        <input id="title" aria-invalid="true" />
      </FormField>,
    );

    expect(screen.getByLabelText(/Title/)).toHaveAccessibleDescription(
      "Use a short procurement title. Title is required.",
    );
    expect(screen.getByLabelText(/Title/)).toHaveAttribute("aria-required", "true");
    expect(screen.getByText("Required")).toBeInTheDocument();
  });
});

describe("FormErrorSummary", () => {
  it("renders linked field errors", () => {
    render(
      <FormErrorSummary
        title="Complete the highlighted fields before submitting."
        errors={[
          { field: "title", fieldId: "title", message: "Title is required." },
          { field: "neededByDate", fieldId: "needed-by", message: "Needed-by date is required." },
        ]}
      />,
    );

    expect(screen.getByRole("alert")).toHaveTextContent(
      "Complete the highlighted fields before submitting.",
    );
    expect(screen.getByRole("link", { name: "Title is required." })).toHaveAttribute(
      "href",
      "#title",
    );
  });
});

describe("validation error helpers", () => {
  it("flattens zod field errors", () => {
    const schema = z.object({
      title: z.string().min(1, "Title is required."),
    });
    const result = schema.safeParse({ title: "" });

    expect(result.success).toBe(false);
    if (!result.success) {
      expect(flattenZodFieldErrors(result.error.flatten().fieldErrors)).toEqual([
        { field: "title", message: "Title is required." },
      ]);
    }
  });

  it("normalizes API validation error shapes", () => {
    const error = {
      details: {
        fields: {
          title: ["Title is required."],
          neededByDate: ["Needed-by date is required."],
        },
      },
    };

    expect(normalizeValidationErrors(error)).toEqual([
      { field: "title", message: "Title is required." },
      { field: "neededByDate", message: "Needed-by date is required." },
    ]);
  });

  it("focuses the first invalid field", () => {
    render(
      <form>
        <input id="first" aria-invalid="true" />
        <input id="second" aria-invalid="true" />
      </form>,
    );

    focusFirstInvalidField(document);

    expect(screen.getAllByRole("textbox")[0]).toHaveFocus();
  });
});
