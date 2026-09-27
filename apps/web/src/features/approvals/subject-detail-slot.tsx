import type { ComponentType } from "react";
import { useTranslations } from "next-intl";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { EvaluationDetail } from "@/features/purchase-orders/evaluation-detail";

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

/** One entry per ApprovalSubjectRegistry type: subjectType → its detail view. */
const SUBJECT_DETAILS: Record<string, ComponentType<SubjectDetailProps>> = {
  evaluation: EvaluationSubjectDetail,
};

export function SubjectDetailSlot({
  subjectType,
  subjectId,
}: {
  subjectType: string;
  subjectId: string;
}) {
  const t = useTranslations("approvals.detail");
  const Detail = subjectId ? SUBJECT_DETAILS[subjectType] : undefined;

  if (!Detail) {
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

  return <Detail subjectId={subjectId} embedded />;
}
