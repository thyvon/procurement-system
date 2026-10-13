"use client";

import { useRouter } from "next/navigation";
import { InvoiceForm } from "./invoice-form";

interface InvoiceFormPageProps {
  invoiceId?: string;
}

export function InvoiceFormPage({ invoiceId }: InvoiceFormPageProps) {
  const router = useRouter();

  return (
    <div className="min-w-0">
      <InvoiceForm
        invoiceId={invoiceId}
        onBack={() => router.push("/invoices")}
      />
    </div>
  );
}
