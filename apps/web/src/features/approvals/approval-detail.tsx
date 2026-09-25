"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { ArrowLeft, Check, RotateCcw, X } from "lucide-react";
import { approvalsRequestsShow } from "@/lib/api/approval-request/approval-request";
import { unwrap, withAuth } from "@/lib/api-client";
import { useMe } from "@/hooks/use-me";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  parseApprovalRequest,
  type ApprovalAction,
  type ApprovalRequestView,
} from "./approval-types";
import { ApprovalStatusBadge, ApprovalTimeline } from "./components/approval-timeline";
import { ApprovalActionDialog } from "./components/approval-action-dialog";

function Row({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex items-start justify-between gap-3 text-sm">
      <span className="text-muted-foreground">{label}</span>
      <span className="text-right font-medium">{value ?? "—"}</span>
    </div>
  );
}

const money = new Intl.NumberFormat("en-US", {
  style: "currency",
  currency: "USD",
  minimumFractionDigits: 2,
});

export function ApprovalDetail({ requestId }: { requestId: string }) {
  const t = useTranslations("approvals.detail");
  const ts = useTranslations("approvals.subjects");
  const router = useRouter();
  const me = useMe();
  const [action, setAction] = useState<ApprovalAction | null>(null);

  const query = useQuery({
    queryKey: ["approvals", "requests", requestId],
    queryFn: async (): Promise<ApprovalRequestView> =>
      parseApprovalRequest(
        unwrap(await approvalsRequestsShow(requestId, withAuth()))
      ),
  });

  if (query.isPending) {
    return null;
  }

  if (query.isError || !query.data) {
    return (
      <div className="space-y-4">
        <p className="text-sm text-muted-foreground">{t("notFound")}</p>
        <Button variant="outline" onClick={() => router.push("/approvals")}>
          <ArrowLeft />
          {t("back")}
        </Button>
      </div>
    );
  }

  const request = query.data;
  const currentStep = request.currentStep;
  const isAssignee =
    request.status === "pending" &&
    currentStep?.assigneeId != null &&
    me.data?.id === currentStep.assigneeId;
  const allowedActions =
    request.steps.find((step) => step.position === currentStep?.position)
      ?.allowedActions ?? [];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Button
            variant="outline"
            size="icon"
            onClick={() => router.push("/approvals")}
            aria-label={t("back")}
          >
            <ArrowLeft />
          </Button>
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-xl font-semibold tracking-tight">
                {request.documentCode ?? t("document")}
              </h1>
              <ApprovalStatusBadge status={request.status} />
            </div>
            <p className="text-sm text-muted-foreground">
              {request.subjectType === "evaluation"
                ? ts("evaluation")
                : request.subjectType}
            </p>
          </div>
        </div>

        {isAssignee ? (
          <div className="flex flex-wrap items-center gap-2">
            {allowedActions.includes("approve") ? (
              <Button onClick={() => setAction("approve")}>
                <Check />
                {t("approve")}
              </Button>
            ) : null}
            {allowedActions.includes("return") ? (
              <Button variant="outline" onClick={() => setAction("return")}>
                <RotateCcw />
                {t("return")}
              </Button>
            ) : null}
            {allowedActions.includes("reject") ? (
              <Button variant="destructive" onClick={() => setAction("reject")}>
                <X />
                {t("reject")}
              </Button>
            ) : null}
          </div>
        ) : request.status === "pending" ? (
          <p className="text-sm text-muted-foreground">{t("notAssignee")}</p>
        ) : null}
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>{t("status")}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <Row label={t("document")} value={request.documentCode} />
            <Row
              label={t("amount")}
              value={
                <span className="tabular-nums">
                  {money.format(Number(request.amountSnapshot))}
                </span>
              }
            />
            <Row label={t("flow")} value={request.flow?.name} />
            <Row label={t("submittedBy")} value={request.submittedBy} />
            <Row label={t("submittedAt")} value={request.submittedAt} />
            <Row label={t("decidedAt")} value={request.decidedAt} />
            <Row
              label={t("currentStep")}
              value={currentStep ? currentStep.label : "—"}
            />
            <Row
              label={t("assignedTo")}
              value={currentStep?.assigneeName ?? "—"}
            />
          </CardContent>
        </Card>

        <ApprovalTimeline request={request} />
      </div>

      {action ? (
        <ApprovalActionDialog
          key={action}
          request={request}
          action={action}
          open
          onOpenChange={(open) => {
            if (!open) setAction(null);
          }}
        />
      ) : null}
    </div>
  );
}
