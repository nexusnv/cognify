"use client";

import { getApiErrorCode, getApiErrorMessage } from "@cognify/api-client";
import { VendorRfqPackage } from "../components/vendor-rfq-package";
import { useVendorRfqInvitation } from "../hooks/use-vendor-rfq-invitation";

export function VendorRfqInvitationPage({ token }: { token: string }) {
  const invitationQuery = useVendorRfqInvitation(token);

  if (invitationQuery.isLoading) {
    return <StatusPanel title="Loading RFQ package" message="Preparing your invitation details." />;
  }

  if (invitationQuery.isError || !invitationQuery.data) {
    const code = getApiErrorCode(invitationQuery.error);
    const title = code === "not_found" ? "Invitation link not found" : "Invitation link unavailable";
    const message =
      code === "not_found"
        ? "This link is invalid or has already been replaced. Contact the buyer for a new invitation link."
        : code === "conflict"
          ? "This invitation is expired, cancelled, or no longer available. Contact the buyer if you believe this is incorrect."
          : getApiErrorMessage(invitationQuery.error);

    return <StatusPanel role="alert" title={title} message={message} />;
  }

  return <VendorRfqPackage invitation={invitationQuery.data} />;
}

function StatusPanel({
  title,
  message,
  role,
}: {
  title: string;
  message: string;
  role?: "alert";
}) {
  return (
    <main className="flex min-h-screen items-center justify-center bg-muted/30 px-4 py-10">
      <section role={role} className="w-full max-w-xl rounded-lg border bg-background p-6 text-center shadow-sm">
        <p className="text-sm font-medium text-muted-foreground">Cognify vendor portal</p>
        <h1 className="mt-2 text-2xl font-semibold">{title}</h1>
        <p className="mt-3 text-sm text-muted-foreground">{message}</p>
      </section>
    </main>
  );
}
