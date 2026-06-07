"use client";

import { getApiErrorCode, getApiErrorMessage } from "@cognify/api-client";
import { Alert, AlertDescription, Card, CardContent, CardHeader } from "@cognify/ui";
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

  return <VendorRfqPackage invitation={invitationQuery.data} token={token} />;
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
  if (role === "alert") {
    return (
      <main className="flex min-h-screen items-center justify-center bg-muted/30 px-4 py-10">
        <Alert variant="destructive" className="w-full max-w-xl px-6 py-5 text-center">
          <AlertDescription className="space-y-3">
            <p className="text-sm font-medium text-muted-foreground">Cognify vendor portal</p>
            <h1 className="text-2xl font-semibold text-foreground">{title}</h1>
            <p className="text-sm text-muted-foreground">{message}</p>
          </AlertDescription>
        </Alert>
      </main>
    );
  }

  return (
    <main className="flex min-h-screen items-center justify-center bg-muted/30 px-4 py-10">
      <Card className="w-full max-w-xl py-0 text-center">
        <CardHeader className="border-b bg-muted/30">
          <p className="text-sm font-medium text-muted-foreground">Cognify vendor portal</p>
          <h1 className="text-2xl font-semibold">{title}</h1>
        </CardHeader>
        <CardContent className="py-4">
          <p className="text-sm text-muted-foreground">{message}</p>
        </CardContent>
      </Card>
    </main>
  );
}
