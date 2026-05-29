import { cloneElement } from "react";
import type { ReactElement } from "react";
import { Field, FieldDescription, FieldError, FieldLabel } from "@cognify/ui";

type FieldControlProps = React.InputHTMLAttributes<HTMLInputElement> &
  React.TextareaHTMLAttributes<HTMLTextAreaElement> &
  React.SelectHTMLAttributes<HTMLSelectElement>;

export function FormField({
  htmlFor,
  label,
  description,
  error,
  required = false,
  children,
}: {
  htmlFor: string;
  label: string;
  description?: string;
  error?: string;
  required?: boolean;
  children: ReactElement<FieldControlProps>;
}) {
  const descriptionId = description ? `${htmlFor}-description` : undefined;
  const errorId = error ? `${htmlFor}-error` : undefined;
  const describedBy =
    [children.props["aria-describedby"], descriptionId, errorId].filter(Boolean).join(" ") ||
    undefined;

  return (
    <Field className="space-y-1.5">
      <div className="flex items-center gap-2">
        <FieldLabel htmlFor={htmlFor} className="text-sm font-medium">
          {label}
        </FieldLabel>
        {required ? <span className="text-xs text-muted-foreground">Required</span> : null}
      </div>
      {description ? (
        <FieldDescription id={descriptionId} className="text-sm text-muted-foreground">
          {description}
        </FieldDescription>
      ) : null}
      {cloneElement(children, {
        id: children.props.id ?? htmlFor,
        "aria-describedby": describedBy,
        "aria-invalid": Boolean(error) || children.props["aria-invalid"],
        "aria-required": required || children.props["aria-required"],
        required: required || children.props.required,
      })}
      {error ? (
        <FieldError id={errorId} className="text-sm text-red-700">
          {error}
        </FieldError>
      ) : null}
    </Field>
  );
}
