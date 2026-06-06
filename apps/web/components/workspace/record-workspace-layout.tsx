import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { Button, Card, CardContent, CardHeader, ScrollArea } from "@cognify/ui";
import type { ReactNode } from "react";
import { SurfaceSection } from "../ui/surface-section";

export type RecordWorkspaceMetadataItem = {
  id: string;
  label: string;
  value: ReactNode;
};

export type RecordWorkspaceSection = {
  id: string;
  label: string;
};

export function RecordWorkspaceLayout({
  backHref,
  backLabel,
  eyebrow,
  title,
  status,
  metadata,
  sections,
  primaryActions,
  secondaryActions,
  sidebar,
  children,
}: {
  backHref: string;
  backLabel: string;
  eyebrow?: ReactNode;
  title: string;
  status?: ReactNode;
  metadata: RecordWorkspaceMetadataItem[];
  sections: RecordWorkspaceSection[];
  primaryActions?: ReactNode;
  secondaryActions?: ReactNode;
  sidebar?: ReactNode;
  children: ReactNode;
}) {
  return (
    <section className="space-y-5">
      <Button asChild variant="outline" className="inline-flex min-h-11 w-fit gap-2">
        <Link href={backHref}>
          <ArrowLeft className="h-4 w-4" aria-hidden="true" />
          {backLabel}
        </Link>
      </Button>

      <Card>
        <CardHeader className="gap-4 pb-4">
          <div className="flex flex-wrap items-center gap-3">
            {eyebrow ? <div className="font-mono text-xs text-muted-foreground">{eyebrow}</div> : null}
            {status}
          </div>
          <h1 className="text-2xl font-semibold">{title}</h1>
        </CardHeader>
        <CardContent className="space-y-4">
          <dl
            className="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3"
            role="group"
            aria-label="Record metadata"
          >
            {metadata.map((item) => (
              <div key={item.id} className="min-w-0">
                <dt className="text-muted-foreground">{item.label}</dt>
                <dd
                  className="truncate font-medium"
                  title={typeof item.value === "string" ? item.value : undefined}
                >
                  {item.value}
                </dd>
              </div>
            ))}
          </dl>

          {(primaryActions || secondaryActions) && (
            <div className="flex flex-wrap gap-2">
              {primaryActions}
              {secondaryActions}
            </div>
          )}
        </CardContent>
      </Card>

      {sections.length > 0 ? (
        <nav aria-label="Record sections">
          <ScrollArea className="w-full">
            <div className="flex min-w-max gap-1 pb-1">
              {sections.map((section) => (
                <Button
                  key={section.id}
                  asChild
                  variant="ghost"
                  size="sm"
                  className="min-h-10 justify-start rounded-md px-3"
                >
                  <Link href={`#${section.id}`}>{section.label}</Link>
                </Button>
              ))}
            </div>
          </ScrollArea>
        </nav>
      ) : null}

      <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div className="min-w-0 space-y-5">{children}</div>
        {sidebar ? (
          <aside aria-label="Record sidebar" className="space-y-5">
            <SurfaceSection className="h-fit">{sidebar}</SurfaceSection>
          </aside>
        ) : null}
      </div>
    </section>
  );
}
