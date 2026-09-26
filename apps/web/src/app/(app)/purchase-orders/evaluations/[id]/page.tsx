import { EvaluationDetail } from "@/features/purchase-orders/evaluation-detail";

export default async function EvaluationDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  return <EvaluationDetail evaluationId={id} />;
}
