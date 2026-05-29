import { Card, CardContent, Skeleton, Spinner } from "@cognify/ui";

type LoadingStateProps = {
  label?: string;
  rows?: number;
  spinner?: boolean;
};

export function LoadingState({ label = "Loading", rows = 4, spinner = false }: LoadingStateProps) {
  return (
    <Card aria-label={label} aria-live="polite">
      <CardContent className="space-y-3">
        {spinner ? (
          <div className="flex min-h-24 items-center justify-center">
            <Spinner aria-hidden="true" />
            <span className="sr-only">{label}</span>
          </div>
        ) : (
          Array.from({ length: rows }).map((_, index) => <Skeleton key={index} className="h-12 w-full" />)
        )}
      </CardContent>
    </Card>
  );
}
