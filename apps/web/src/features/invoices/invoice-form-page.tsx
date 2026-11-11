"use client";

import { useRouter } from "next/navigation";
import { InvoiceForm } from "./invoice-form";
import type { InvoiceBranch } from "./invoice-branches";

interface InvoiceFormPageProps {
  branch: InvoiceBranch;
  invoiceId?: string;
}

export function InvoiceFormPage({ branch, invoiceId }: InvoiceFormPageProps) {
  const router = useRouter();

  return (
    <div className="min-w-0">
      <InvoiceForm
        branch={branch}
        invoiceId={invoiceId}
        onBack={() => router.push(`/invoices/${branch}`)}
      />
    </div>
  );
}
