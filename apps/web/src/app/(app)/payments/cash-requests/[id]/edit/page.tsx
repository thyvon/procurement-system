import { CashRequestFormPage } from "@/features/payments/cash-request-form-page";

export default async function CashRequestEditRoute({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  return <CashRequestFormPage cashRequestId={id} />;
}
