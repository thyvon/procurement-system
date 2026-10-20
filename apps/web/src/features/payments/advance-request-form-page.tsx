"use client";

import { useRouter } from "next/navigation";
import { AdvanceRequestForm } from "./advance-request-form";

interface AdvanceRequestFormPageProps {
  advanceRequestId?: string;
}

export function AdvanceRequestFormPage({
  advanceRequestId,
}: AdvanceRequestFormPageProps) {
  const router = useRouter();

  return (
    <div className="min-w-0">
      <AdvanceRequestForm
        advanceRequestId={advanceRequestId}
        onBack={() => router.push("/payments/advance-requests")}
      />
    </div>
  );
}
