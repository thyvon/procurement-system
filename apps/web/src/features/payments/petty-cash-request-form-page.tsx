"use client";

import { useRouter } from "next/navigation";
import { PettyCashRequestForm } from "./petty-cash-request-form";

interface PettyCashRequestFormPageProps {
  pettyCashRequestId?: string;
}

export function PettyCashRequestFormPage({
  pettyCashRequestId,
}: PettyCashRequestFormPageProps) {
  const router = useRouter();

  return (
    <div className="min-w-0">
      <PettyCashRequestForm
        pettyCashRequestId={pettyCashRequestId}
        onBack={() => router.push("/payments/petty-cash-requests")}
      />
    </div>
  );
}
