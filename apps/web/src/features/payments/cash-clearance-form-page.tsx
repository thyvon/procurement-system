"use client";

import { useRouter } from "next/navigation";
import { CashClearanceForm } from "./cash-clearance-form";

interface CashClearanceFormPageProps {
  clearanceId?: string;
}

export function CashClearanceFormPage({
  clearanceId,
}: CashClearanceFormPageProps) {
  const router = useRouter();

  return (
    <div className="min-w-0">
      <CashClearanceForm
        clearanceId={clearanceId}
        onBack={() => router.push("/payments/cash-clearances")}
      />
    </div>
  );
}
