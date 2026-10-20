import { CashRequestDetail } from "@/features/payments/cash-request-detail";

export default async function CashRequestDetailRoute({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  return <CashRequestDetail cashRequestId={id} />;
}
