import { Alert, AlertTitle, Button, Card, CardContent, Skeleton } from "@cognify/ui";
import { EmptyState } from "@/components/ui/empty-state";

export function DataTableLoading({ label = "Loading rows" }: { label?: string }) {
  return (
    <Card aria-label={label} aria-live="polite">
      <CardContent className="space-y-3 p-6">
        {Array.from({ length: 5 }).map((_, index) => (
          <Skeleton key={index} className="h-12 w-full" />
        ))}
      </CardContent>
    </Card>
  );
}

export function DataTableError({ title, onRetry }: { title: string; onRetry?: () => void }) {
  return (
    <Alert variant="destructive">
      <AlertTitle>{title}</AlertTitle>
      {onRetry ? (
        <div className="mt-3">
          <Button type="button" variant="outline" onClick={onRetry}>
            Retry
          </Button>
        </div>
      ) : null}
    </Alert>
  );
}

export function DataTableEmpty({ title, description }: { title: string; description?: string }) {
  return <EmptyState title={title} description={description} />;
}
