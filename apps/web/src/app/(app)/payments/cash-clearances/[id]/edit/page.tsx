import { CashClearanceFormPage } from "@/features/payments/cash-clearances/cash-clearance-form-page";

export default async function CashClearanceEditRoute({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  return <CashClearanceFormPage clearanceId={id} />;
}
