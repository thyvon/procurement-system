import { InvoiceFormPage } from "@/features/invoices/invoice-form-page";

export default async function ProPurchaseInvoiceEditRoute({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  return <InvoiceFormPage branch="pro-purchase-inv" invoiceId={id} />;
}
