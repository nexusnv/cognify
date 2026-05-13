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
    <div role="alert" className="rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-900">
      <p className="font-medium">{title}</p>
      <ul className="mt-2 list-disc space-y-1 pl-5">
        {errors.map((error, index) => (
          <li key={`${error.field ?? "form"}-${index}`}>
            {error.fieldId ? (
              <a className="underline" href={`#${error.fieldId}`}>
                {error.message}
              </a>
            ) : (
              error.message
            )}
          </li>
        ))}
      </ul>
    </div>
  );
}
