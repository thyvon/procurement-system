import { InvoiceDetail } from "@/features/invoices/invoice-detail";

export default async function ProPurchaseInvoiceDetailRoute({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  return <InvoiceDetail branch="pro-purchase-inv" invoiceId={id} />;
}
