import { Button } from "@cognify/ui";
import type { QuotationScoringTemplate } from "@cognify/api-client/schemas";

export function RfqScorecardTemplatePicker({
  templates,
  isPending = false,
  onApply,
}: {
  templates: QuotationScoringTemplate[];
  isPending?: boolean;
  onApply: (templateId: string) => void;
}) {
  const activeTemplates = templates.filter((template) => template.active);

  return (
    <section className="space-y-3" aria-label="Template picker">
      <h2 className="text-lg font-semibold">Apply scoring template</h2>
      <div className="grid gap-3 md:grid-cols-2">
        {activeTemplates.map((template) => (
          <article key={template.id} className="rounded-md border p-4">
            <div className="flex items-start justify-between gap-3">
              <div>
                <h3 className="font-medium">{template.name}</h3>
                <p className="mt-1 text-sm text-muted-foreground">{template.description}</p>
              </div>
              <Button size="sm" disabled={isPending} onClick={() => onApply(template.id)}>
                Apply
              </Button>
            </div>
            <dl className="mt-3 grid grid-cols-2 gap-2 text-sm">
              <div>
                <dt className="text-muted-foreground">Criteria</dt>
                <dd>{template.criteria.length}</dd>
              </div>
              <div>
                <dt className="text-muted-foreground">Total weight</dt>
                <dd>{template.criteria.reduce((sum, criterion) => sum + Number(criterion.weight), 0).toFixed(2)}</dd>
              </div>
            </dl>
          </article>
        ))}
      </div>
    </section>
  );
}
