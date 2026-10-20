import { CashClearanceDetail } from "@/features/payments/cash-clearance-detail";

export default async function CashClearanceDetailRoute({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  return <CashClearanceDetail clearanceId={id} />;
}
