import { AdvanceLiquidationDetail } from "@/features/payments/advance-liquidations/advance-liquidation-detail";

export default async function AdvanceLiquidationDetailRoute({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  return <AdvanceLiquidationDetail liquidationId={id} />;
}
