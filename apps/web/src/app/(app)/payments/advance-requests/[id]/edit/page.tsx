import { AdvanceRequestFormPage } from "@/features/payments/cash-requests/advance-request-form-page";

export default async function AdvanceRequestEditRoute({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  return <AdvanceRequestFormPage advanceRequestId={id} />;
}
