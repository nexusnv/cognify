import { Alert, AlertDescription, AlertTitle } from "@cognify/ui";

export type FormSummaryError = {
  field?: string;
  fieldId?: string;
  message: string;
};

export function FormErrorSummary({
  title,
  errors,
}: {
  title: string;
  errors: FormSummaryError[];
}) {
  if (errors.length === 0) return null;

  return (
    <Alert variant="destructive">
      <AlertTitle>{title}</AlertTitle>
      <AlertDescription>
        <ul className="ml-4 list-disc space-y-1">
          {errors.map((error, index) => (
            <li key={`${error.field ?? "form"}-${index}`}>
              {error.fieldId ? (
                <a className="underline underline-offset-4" href={`#${error.fieldId}`}>
                  {error.message}
                </a>
              ) : (
                error.message
              )}
            </li>
          ))}
        </ul>
      </AlertDescription>
    </Alert>
  );
}
