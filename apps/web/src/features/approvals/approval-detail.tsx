"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { ArrowLeft, Ban, Check, Pencil, Printer, RotateCcw, X } from "lucide-react";
import {
  approvalsRequestsCancel,
  approvalsRequestsShow,
} from "@/lib/api/approval-request/approval-request";
import { unwrap, withAuth } from "@/lib/api-client";
import { useMe } from "@/hooks/use-me";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  fetchApprovalRounds,
  parseApprovalRequest,
  type ApprovalAction,
  type ApprovalRequestView,
} from "./approval-types";
import { ApprovalStatusBadge, ApprovalTimeline } from "./components/approval-timeline";
import { ApprovalActionDialog } from "./components/approval-action-dialog";
import { SubjectDetailSlot } from "./subject-detail-slot";

export function ApprovalDetail({ requestId }: { requestId: string }) {
  const t = useTranslations("approvals.detail");
  const ts = useTranslations("approvals.subjects");
  const ta = useTranslations("approvals.form");
  const router = useRouter();
  const me = useMe();
  const qc = useQueryClient();
  const [action, setAction] = useState<ApprovalAction | null>(null);
  const [cancelOpen, setCancelOpen] = useState(false);

  const query = useQuery({
    queryKey: ["approvals", "requests", requestId],
    queryFn: async (): Promise<ApprovalRequestView> =>
      parseApprovalRequest(
        unwrap(await approvalsRequestsShow(requestId, withAuth()))
      ),
  });

  // Same key/queryFn as evaluation-detail & evaluation-form: every round
  // for this document, so the timeline reads like a full audit trail.
  const subjectType = query.data?.subjectType ?? "";
  const subjectId = query.data?.subjectId ?? "";
  const roundsQuery = useQuery({
    queryKey: ["approvals", "requests", "subject", subjectType, subjectId],
    enabled: subjectId !== "",
    queryFn: () => fetchApprovalRounds(subjectType, subjectId),
  });

  // Withdrawn by the submitter while a round is still pending — the API
  // decides whether that is allowed (pending + owner), we only surface it.
  const cancelMutation = useMutation({
    mutationFn: async () =>
      unwrap(await approvalsRequestsCancel(requestId, withAuth())),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["approvals"] });
      qc.invalidateQueries({ queryKey: ["evaluations"] });
      setCancelOpen(false);
      toast.success(t("cancelled"));
    },
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
  const isOwner =
    request.createdById != null &&
    me.data != null &&
    request.createdById === me.data.id;
  // Only the submitter may withdraw a live round; a returned document is
  // back in draft on its subject, so the fix happens in the subject's form.
  const canCancel = request.status === "pending" && isOwner;
  const canResubmit =
    request.status === "returned" &&
    isOwner &&
    request.subjectType === "evaluation";

  const handlePrint = () => {
    const originalTitle = document.title;
    document.title = `${request.documentCode ?? t("document")}-approval`;
    try {
      window.print();
    } finally {
      document.title = originalTitle;
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
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

        <div className="flex flex-wrap items-center gap-2">
          <Button
            variant="outline"
            onClick={handlePrint}
            disabled={roundsQuery.isPending}
          >
            <Printer />
            {t("print")}
          </Button>
          {canResubmit ? (
            <Button
              onClick={() =>
                router.push(
                  `/purchase-orders/evaluations/${request.subjectId}/edit`
                )
              }
            >
              <Pencil />
              {t("editResubmit")}
            </Button>
          ) : null}
          {canCancel ? (
            <Button variant="outline" onClick={() => setCancelOpen(true)}>
              <Ban />
              {t("cancelRequest")}
            </Button>
          ) : null}
          {isAssignee ? (
            <>
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
                <Button
                  variant="destructive"
                  onClick={() => setAction("reject")}
                >
                  <X />
                  {t("reject")}
                </Button>
              ) : null}
            </>
          ) : request.status === "pending" ? (
            <p className="text-sm text-muted-foreground">{t("notAssignee")}</p>
          ) : null}
        </div>
      </div>

      {request.status === "pending" ? (
        <div className="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm print:hidden">
          {ta("inReview")}
        </div>
      ) : null}

      <SubjectDetailSlot
        subjectType={request.subjectType}
        subjectId={request.subjectId}
      />

      <div className="print:hidden">
        <ApprovalTimeline request={request} rounds={roundsQuery.data} />
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

      <Dialog open={cancelOpen} onOpenChange={setCancelOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>{t("cancelRequestTitle")}</DialogTitle>
            <DialogDescription>{t("cancelRequestHint")}</DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setCancelOpen(false)}
              disabled={cancelMutation.isPending}
            >
              {t("cancel")}
            </Button>
            <Button
              variant="destructive"
              onClick={() => cancelMutation.mutate()}
              disabled={cancelMutation.isPending}
            >
              {cancelMutation.isPending ? t("saving") : t("confirm")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
