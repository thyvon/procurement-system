import { PettyCashRequestDetail } from "@/features/payments/petty-cash-request-detail";

export default async function PettyCashRequestDetailRoute({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  return <PettyCashRequestDetail pettyCashRequestId={id} />;
}
