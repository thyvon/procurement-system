import { PettyCashRequestFormPage } from "@/features/payments/cash-requests/petty-cash-request-form-page";

export default async function PettyCashRequestEditRoute({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  return <PettyCashRequestFormPage pettyCashRequestId={id} />;
}
