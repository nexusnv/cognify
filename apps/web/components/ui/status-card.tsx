import { type ComponentType, type ReactNode } from "react";
import { Badge, Card, CardContent } from "@cognify/ui";

type StatusCardProps = {
  label: string;
  value: ReactNode;
  description?: string;
  icon?: ComponentType<{ className?: string; "aria-hidden"?: boolean }>;
  badge?: ReactNode;
  className?: string;
};

export function StatusCard({ label, value, description, icon: Icon, badge, className }: StatusCardProps) {
  return (
    <Card className={className}>
      <CardContent className="space-y-3">
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0 space-y-1">
            <p className="text-sm font-medium text-muted-foreground">{label}</p>
            <div className="text-2xl font-semibold tracking-normal">{value}</div>
          </div>
          {Icon ? (
            <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
              <Icon className="size-4" aria-hidden />
            </div>
          ) : null}
        </div>
        {description || badge ? (
          <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
            {description ? <span>{description}</span> : null}
            {badge ? <Badge variant="secondary">{badge}</Badge> : null}
          </div>
        ) : null}
      </CardContent>
    </Card>
  );
}
