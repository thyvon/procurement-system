import { ApprovalDetail } from "@/features/approvals/approval-detail";

export default async function ApprovalDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;
  return <ApprovalDetail requestId={id} />;
}
