"use client";

import { useRouter } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { ArrowLeft, Pencil, Printer } from "lucide-react";
import { unwrap, unwrapWithMeta, withAuth } from "@/lib/api-client";
import { purchaseOrdersEvaluationsShow } from "@/lib/api/evaluation/evaluation";
import { approvalsRequestsIndex } from "@/lib/api/approval-request/approval-request";
import type { EvaluationResource } from "@/lib/api/model/evaluationResource";
import { Button } from "@/components/ui/button";
import { DataTableSkeleton } from "@/components/ui/data-table-skeleton";
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import {
  EVALUATION_SUBJECT,
  parseApprovalRequest,
  type ApprovalRequestView,
} from "@/features/approvals/approval-types";
import { ApprovalTimeline } from "@/features/approvals/components/approval-timeline";
import { EvaluationMatrixReadOnly } from "./components/evaluation-matrix-readonly";
import { EvaluationPrint } from "./components/evaluation-print";
import { EvaluationStatusBadge } from "./components/evaluation-status-badge";
import { fromEvaluation } from "./components/evaluation-matrix";

interface EvaluationDetailProps {
  evaluationId: string;
}

export function EvaluationDetail({ evaluationId }: EvaluationDetailProps) {
  const router = useRouter();
  const tf = useTranslations("purchaseOrders.form");
  const tt = useTranslations("purchaseOrders.table");
  const td = useTranslations("purchaseOrders.detail");
  const ta = useTranslations("approvals.form");

  const showQuery = useQuery({
    queryKey: ["evaluations", "detail", evaluationId],
    queryFn: async (): Promise<EvaluationResource> =>
      unwrap(await purchaseOrdersEvaluationsShow(evaluationId, withAuth())),
  });

  const approvalQuery = useQuery({
    queryKey: [
      "approvals",
      "requests",
      "subject",
      EVALUATION_SUBJECT,
      evaluationId,
    ],
    queryFn: async (): Promise<ApprovalRequestView | null> => {
      const envelope = unwrapWithMeta<unknown>(
        await approvalsRequestsIndex(
          { subject_type: EVALUATION_SUBJECT, subject_id: evaluationId },
          withAuth()
        )
      );
      const rows = (envelope.data ?? []) as unknown[];
      return rows.length > 0 ? parseApprovalRequest(rows[0]) : null;
    },
  });

  if (showQuery.isPending) {
    return <DataTableSkeleton columns={6} actions={2} />;
  }

  if (showQuery.isError || !showQuery.data) {
    return (
      <div className="mt-1 min-w-0 space-y-4">
        <Button variant="outline" onClick={() => router.push("/purchase-orders/evaluations")}>
          {tf("back")}
        </Button>
        <p className="text-sm text-destructive">{tf("loadFailed")}</p>
      </div>
    );
  }

  const evaluation = showQuery.data;
  const value = fromEvaluation(evaluation);
  const approvalRequest = approvalQuery.data ?? null;
  const status = evaluation.status;

  const handlePrint = () => {
    const originalTitle = document.title;
    document.title = `${evaluation.code}-price-evaluation`;
    try {
      window.print();
    } finally {
      document.title = originalTitle;
    }
  };

  return (
    <>
      <div className="mt-1 min-w-0 space-y-6 print:hidden">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-3">
            <Button
              variant="outline"
              size="icon"
              onClick={() => router.push("/purchase-orders/evaluations")}
              aria-label={tf("back")}
            >
              <ArrowLeft />
            </Button>
            <div>
              <div className="flex items-center gap-2">
                <h1 className="text-xl font-semibold tracking-tight">
                  {evaluation.code}
                </h1>
                <EvaluationStatusBadge status={status} />
              </div>
              <p className="text-sm text-muted-foreground">{td("subtitle")}</p>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <Button
            variant="outline"
            onClick={handlePrint}
            disabled={approvalQuery.isPending}
          >
              <Printer />
              {td("print")}
            </Button>
            <Button
              onClick={() =>
                router.push(`/purchase-orders/evaluations/${evaluation.id}/edit`)
              }
            >
              <Pencil />
              {tt("edit")}
            </Button>
          </div>
        </div>

        {status === "in_review" ? (
          <div className="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm">
            {ta("inReview")}
          </div>
        ) : status === "rejected" ? (
          <div className="rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm">
            {ta("rejectedState")}
          </div>
        ) : status === "returned" ? (
          <div className="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm">
            {ta("returnedState")}
          </div>
        ) : null}

        <Card size="sm" className="min-w-0 text-xs">
          <CardHeader>
            <CardTitle className="text-xs">{tf("title")}</CardTitle>
          </CardHeader>
          <CardContent className="min-w-0 space-y-3">
            <EvaluationMatrixReadOnly
              value={value}
              currency={evaluation.currency}
            />

            <div className="space-y-3 border-t border-border pt-3">
              <div className="flex flex-wrap items-center gap-x-6 gap-y-2">
                <div className="flex items-center gap-1">
                  <Label className="text-left text-xs after:ml-1 after:content-[':']">
                    {tf("currency")}
                  </Label>
                  <p className="text-xs tabular-nums">{evaluation.currency}</p>
                </div>
                <div className="flex items-center gap-1">
                  <Label className="text-left text-xs after:ml-1 after:content-[':']">
                    {tf("exchangeRate")}
                  </Label>
                  <p className="text-xs tabular-nums">
                    {evaluation.exchangeRate.toLocaleString("en-US")}
                    {evaluation.currency === "KHR" ? (
                      <span className="text-muted-foreground">
                        {" "}
                        {tf("exchangeRateHint")}
                      </span>
                    ) : null}
                  </p>
                </div>
              </div>

              <div className="space-y-2">
                <Label className="text-left text-xs after:ml-1 after:content-[':']">
                  {tf("basis")}
                </Label>
                <p className="whitespace-pre-wrap text-xs">
                  {evaluation.recommendationBasis || (
                    <span className="text-muted-foreground">—</span>
                  )}
                </p>
              </div>
            </div>
          </CardContent>
        </Card>

        {approvalRequest ? (
          <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <h2 className="text-sm font-medium">{ta("reviewTitle")}</h2>
              <Button
                variant="outline"
                onClick={() => router.push(`/approvals/${approvalRequest.id}`)}
              >
                {ta("viewRequest")}
              </Button>
            </div>

            <ApprovalTimeline request={approvalRequest} />
          </div>
        ) : null}
      </div>

      <EvaluationPrint
        evaluation={evaluation}
        value={value}
        approvalRequest={approvalRequest}
      />
    </>
  );
}
