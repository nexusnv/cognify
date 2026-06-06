// shadcn-factory-exception: Workflow detail records need shared metadata, section navigation, action, and sidebar structure beyond shadcn Card primitives; primitives=Button,Card; routes=requisitions,projects,sourcing,quotations

import Link from "next/link";
import { ArrowLeft } from "lucide-react";

export type WorkflowRecordMetadataItem = {
  id: string;
  label: string;
  value: React.ReactNode;
};

export type WorkflowRecordSection = {
  id: string;
  label: string;
};

export function WorkflowStateLayout({
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
  eyebrow?: React.ReactNode;
  title: string;
  status?: React.ReactNode;
  metadata: WorkflowRecordMetadataItem[];
  sections: WorkflowRecordSection[];
  primaryActions?: React.ReactNode;
  secondaryActions?: React.ReactNode;
  sidebar?: React.ReactNode;
  children: React.ReactNode;
}) {
  return (
    <section className="space-y-5">
      <Link
        href={backHref}
        className="inline-flex min-h-11 items-center gap-2 rounded-md border px-3 text-sm font-medium"
      >
        <ArrowLeft className="h-4 w-4" aria-hidden="true" />
        {backLabel}
      </Link>

      <header className="grid gap-4 border-b pb-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-3">
            {eyebrow ? (
              <div className="font-mono text-xs text-muted-foreground">{eyebrow}</div>
            ) : null}
            {status}
          </div>
          <h1 className="mt-3 text-2xl font-semibold">{title}</h1>
          <dl
            className="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3"
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
        </div>

        {(primaryActions || secondaryActions) && (
          <div className="flex flex-col gap-2 rounded-md border p-3">
            {primaryActions}
            {secondaryActions}
          </div>
        )}
      </header>

      {sections.length > 0 ? (
        <nav aria-label="Record sections" className="overflow-x-auto border-b">
          <div className="flex min-w-max gap-1">
            {sections.map((section) => (
              <a
                key={section.id}
                href={`#${section.id}`}
                className="min-h-10 rounded-t-md px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground"
              >
                {section.label}
              </a>
            ))}
          </div>
        </nav>
      ) : null}

      <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div className="min-w-0 space-y-5">{children}</div>
        {sidebar ? (
          <aside aria-label="Record sidebar" className="space-y-5">
            {sidebar}
          </aside>
        ) : null}
      </div>
    </section>
  );
}
