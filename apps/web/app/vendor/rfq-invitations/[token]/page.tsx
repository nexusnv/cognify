import { VendorRfqInvitationPage } from "@/features/vendor-portal/workflows/vendor-rfq-invitation-page";

export default async function Page({ params }: { params: Promise<{ token: string }> }) {
  const { token } = await params;

  return <VendorRfqInvitationPage token={token} />;
}
