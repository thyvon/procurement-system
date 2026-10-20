import type { ComponentType } from "react";
import { useTranslations } from "next-intl";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { EvaluationDetail } from "@/features/purchase-orders/evaluation-detail";
import { AdvanceRequestDetail } from "@/features/payments/cash-requests/advance-request-detail";
import { CashRequestDetail } from "@/features/payments/cash-requests/cash-request-detail";
import { PettyCashRequestDetail } from "@/features/payments/cash-requests/petty-cash-request-detail";
import { CashClearanceDetail } from "@/features/payments/cash-clearances/cash-clearance-detail";
import { AdvanceLiquidationDetail } from "@/features/payments/advance-liquidations/advance-liquidation-detail";

type SubjectDetailProps = {
  subjectId: string;
  embedded: boolean;
};

function EvaluationSubjectDetail({
  subjectId,
  embedded,
}: SubjectDetailProps) {
  return <EvaluationDetail evaluationId={subjectId} embedded={embedded} />;
}

function CashRequestSubjectDetail({
  subjectId,
  embedded,
}: SubjectDetailProps) {
  return <CashRequestDetail cashRequestId={subjectId} embedded={embedded} />;
}

function PettyCashRequestSubjectDetail({
  subjectId,
  embedded,
}: SubjectDetailProps) {
  return (
    <PettyCashRequestDetail pettyCashRequestId={subjectId} embedded={embedded} />
  );
}

function AdvanceRequestSubjectDetail({
  subjectId,
  embedded,
}: SubjectDetailProps) {
  return (
    <AdvanceRequestDetail advanceRequestId={subjectId} embedded={embedded} />
  );
}

function CashClearanceSubjectDetail({
  subjectId,
  embedded,
}: SubjectDetailProps) {
  return <CashClearanceDetail clearanceId={subjectId} embedded={embedded} />;
}

function AdvanceLiquidationSubjectDetail({
  subjectId,
  embedded,
}: SubjectDetailProps) {
  return (
    <AdvanceLiquidationDetail liquidationId={subjectId} embedded={embedded} />
  );
}

function DocumentPlaceholder() {
  const t = useTranslations("approvals.detail");

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t("document")}</CardTitle>
      </CardHeader>
      <CardContent className="text-sm text-muted-foreground">
        {t("noDocumentPreview")}
      </CardContent>
    </Card>
  );
}

/** One entry per ApprovalSubjectRegistry type: subjectType → its detail view. */
const SUBJECT_DETAILS: Record<string, ComponentType<SubjectDetailProps>> = {
  evaluation: EvaluationSubjectDetail,
  petty_cash_request: PettyCashRequestSubjectDetail,
  advance_request: AdvanceRequestSubjectDetail,
  cash_request: CashRequestSubjectDetail,
  cash_clearance: CashClearanceSubjectDetail,
  advance_liquidation: AdvanceLiquidationSubjectDetail,
};

export function SubjectDetailSlot({
  subjectType,
  subjectId,
}: {
  subjectType: string;
  subjectId: string;
}) {
  const Detail = subjectId ? SUBJECT_DETAILS[subjectType] : undefined;

  if (!Detail) {
    return <DocumentPlaceholder />;
  }

  return <Detail subjectId={subjectId} embedded />;
}
