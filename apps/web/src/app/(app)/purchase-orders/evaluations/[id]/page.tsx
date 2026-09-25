import { EvaluationFormPage } from "@/features/purchase-orders/evaluation-form-page";

export default async function EvaluationRoute({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  return <EvaluationFormPage evaluationId={id} />;
}
