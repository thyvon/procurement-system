import { AdvanceLiquidationFormPage } from "@/features/payments/advance-liquidation-form-page";

export default async function AdvanceLiquidationEditRoute({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  return <AdvanceLiquidationFormPage liquidationId={id} />;
}
