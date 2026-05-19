"use client";

import { useState } from "react";
import { Button } from "@cognify/ui";
import { useSourcingIntakeReviews } from "../hooks/use-sourcing-intake-reviews";
import { SourcingIntakeTable } from "../tables/sourcing-intake-table";

const presets = [
  { value: "unassigned", label: "Unassigned" },
  { value: "mine", label: "Mine" },
  { value: "needs_clarification", label: "Needs clarification" },
  { value: "ready_for_rfq", label: "Ready for RFQ" },
  { value: "closed", label: "Closed" },
];

export function SourcingIntakeListPage() {
  const [preset, setPreset] = useState("unassigned");
  const query = useSourcingIntakeReviews({ preset });
  const reviews = query.data?.data ?? [];
  const state = query.isLoading
    ? "loading"
    : query.isError
      ? "error"
      : reviews.length === 0
        ? "empty"
        : "idle";

  return (
    <section className="space-y-5">
      <div className="flex flex-col gap-3 border-b pb-4 md:flex-row md:items-start md:justify-between">
        <div>
          <h1 className="text-2xl font-semibold">Sourcing intake</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Review requisitions before RFQ creation or sourcing closeout.
          </p>
        </div>
        <div className="flex flex-wrap gap-2" aria-label="Intake presets">
          {presets.map((item) => (
            <Button
              key={item.value}
              type="button"
              variant={preset === item.value ? "default" : "outline"}
              onClick={() => setPreset(item.value)}
            >
              {item.label}
            </Button>
          ))}
        </div>
      </div>

      <SourcingIntakeTable reviews={reviews} state={state} onRetry={() => query.refetch()} />
    </section>
  );
}
