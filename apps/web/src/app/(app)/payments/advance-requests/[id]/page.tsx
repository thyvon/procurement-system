import { AdvanceRequestDetail } from "@/features/payments/cash-requests/advance-request-detail";

export default async function AdvanceRequestDetailRoute({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  return <AdvanceRequestDetail advanceRequestId={id} />;
}
