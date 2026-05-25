import type {
  RfqAwardRecommendationEvidenceReference,
  RfqAwardRecommendationEvidenceReferenceInput,
} from "@cognify/api-client/schemas";

type Props = {
  references: RfqAwardRecommendationEvidenceReference[];
  selected: RfqAwardRecommendationEvidenceReferenceInput[];
  readOnly?: boolean;
  onChange: (selected: RfqAwardRecommendationEvidenceReferenceInput[]) => void;
};

export function RfqAwardEvidenceSelector({ references, selected, readOnly = false, onChange }: Props) {
  const selectedKeys = new Set(selected.map((item) => `${item.type}:${item.id}`));

  return (
    <section className="rounded-md border p-4" aria-label="Evidence selector">
      <h2 className="text-base font-semibold">Supporting evidence</h2>
      <ul className="mt-3 space-y-2">
        {references.map((reference) => {
          const key = `${reference.type}:${reference.id}`;
          const checked = selectedKeys.has(key);
          return (
            <li key={key}>
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={checked}
                  disabled={readOnly}
                  onChange={(event) => {
                    if (event.target.checked) {
                      onChange([...selected, { type: reference.type, id: reference.id, label: reference.label }]);
                      return;
                    }
                    onChange(selected.filter((item) => `${item.type}:${item.id}` !== key));
                  }}
                />
                <span>{reference.label}</span>
              </label>
            </li>
          );
        })}
      </ul>
    </section>
  );
}
