"use client";

import { useRouter } from "next/navigation";
import { EvaluationForm } from "./evaluation-form";

interface EvaluationFormPageProps {
  evaluationId?: string;
}

export function EvaluationFormPage({ evaluationId }: EvaluationFormPageProps) {
  const router = useRouter();

  return (
    <div className="min-w-0">
      <EvaluationForm
        evaluationId={evaluationId}
        onBack={() => router.push("/purchase-orders/evaluations")}
      />
    </div>
  );
}
