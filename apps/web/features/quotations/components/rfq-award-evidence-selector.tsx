import type {
  RfqAwardRecommendationEvidenceReference,
  RfqAwardRecommendationEvidenceReferenceInput,
} from "@cognify/api-client/schemas";
import { Badge, Card, CardContent, CardHeader, CardTitle, Checkbox } from "@cognify/ui";

type Props = {
  references: RfqAwardRecommendationEvidenceReference[];
  selected: RfqAwardRecommendationEvidenceReferenceInput[];
  readOnly?: boolean;
  onChange: (selected: RfqAwardRecommendationEvidenceReferenceInput[]) => void;
};

export function RfqAwardEvidenceSelector({ references, selected, readOnly = false, onChange }: Props) {
  const selectedKeys = new Set(selected.map((item) => `${item.type}:${item.id}`));

  return (
    <Card aria-label="Evidence selector">
      <CardHeader>
        <CardTitle className="text-base">Supporting evidence</CardTitle>
      </CardHeader>
      <CardContent>
        <ul className="mt-3 space-y-2">
        {references.map((reference) => {
          const key = `${reference.type}:${reference.id}`;
          const checked = selectedKeys.has(key);
          return (
            <li key={key}>
              <label className="flex items-center gap-2 text-sm">
                <Checkbox
                  checked={checked}
                  disabled={readOnly}
                  onCheckedChange={(checkedState) => {
                    if (checkedState) {
                      onChange([...selected, { type: reference.type, id: reference.id, label: reference.label }]);
                      return;
                    }
                    onChange(selected.filter((item) => `${item.type}:${item.id}` !== key));
                  }}
                />
                <span>{reference.label}</span>
              </label>
              <Badge variant="outline" className="ml-6">{reference.type}</Badge>
            </li>
          );
        })}
        </ul>
      </CardContent>
    </Card>
  );
}
