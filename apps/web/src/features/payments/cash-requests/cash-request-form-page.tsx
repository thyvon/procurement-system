"use client";

import { useRouter } from "next/navigation";
import { CashRequestForm } from "./cash-request-form";

interface CashRequestFormPageProps {
  cashRequestId?: string;
}

export function CashRequestFormPage({
  cashRequestId,
}: CashRequestFormPageProps) {
  const router = useRouter();

  return (
    <div className="min-w-0">
      <CashRequestForm
        cashRequestId={cashRequestId}
        onBack={() => router.push("/payments/cash-requests")}
      />
    </div>
  );
}
