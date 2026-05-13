export function DataTableLoading({ label = "Loading rows" }: { label?: string }) {
  return (
    <div className="space-y-2 rounded-md border p-3" aria-label={label} aria-live="polite">
      {Array.from({ length: 5 }).map((_, index) => (
        <div key={index} className="h-12 rounded-md bg-card" />
      ))}
    </div>
  );
}

export function DataTableError({ title, onRetry }: { title: string; onRetry?: () => void }) {
  return (
    <div role="alert" className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">
      <p className="font-medium">{title}</p>
      {onRetry ? (
        <button
          type="button"
          className="mt-3 min-h-11 rounded-md border bg-white px-3"
          onClick={onRetry}
        >
          Retry
        </button>
      ) : null}
    </div>
  );
}

export function DataTableEmpty({ title, description }: { title: string; description?: string }) {
  return (
    <div className="rounded-md border p-6">
      <h2 className="text-base font-semibold">{title}</h2>
      {description ? <p className="mt-1 text-sm text-muted-foreground">{description}</p> : null}
    </div>
  );
}
