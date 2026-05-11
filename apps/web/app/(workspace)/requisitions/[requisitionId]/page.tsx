export default async function RequisitionWorkspacePage({
  params,
}: {
  params: Promise<{ requisitionId: string }>;
}) {
  const { requisitionId } = await params;

  return (
    <section className="flex flex-col gap-4">
      <h1 className="text-2xl font-semibold">Requisition {requisitionId}</h1>
      <p className="text-sm text-muted-foreground">
        Workspace route scaffold for requisition lifecycle implementation.
      </p>
    </section>
  );
}
