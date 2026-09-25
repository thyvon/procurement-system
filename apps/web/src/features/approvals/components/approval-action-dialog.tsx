"use client";

import { useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { approvalsRequestsActions } from "@/lib/api/approval-request/approval-request";
import type { StoreApprovalActionRequest } from "@/lib/api/model/storeApprovalActionRequest";
import { unwrap, withAuth } from "@/lib/api-client";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { RequiredMark } from "@/components/required-mark";
import type { ApprovalAction, ApprovalRequestView } from "../approval-types";

type Props = {
  request: ApprovalRequestView;
  action: ApprovalAction;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

export function ApprovalActionDialog({
  request,
  action,
  open,
  onOpenChange,
}: Props) {
  const t = useTranslations("approvals.detail");
  const qc = useQueryClient();
  const [comment, setComment] = useState("");

  const commentRequired = action !== "approve";
  const stepLabel = request.currentStep?.label ?? "";

  const actionMutation = useMutation({
    mutationFn: async () => {
      const payload: StoreApprovalActionRequest = {
        action,
        comment: comment.trim() ? comment.trim() : null,
      };
      return unwrap(await approvalsRequestsActions(request.id, payload, withAuth()));
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["approvals"] });
      qc.invalidateQueries({ queryKey: ["evaluations"] });
      setComment("");
      onOpenChange(false);
      toast.success(t("decided"));
    },
  });

  const canSubmit = !commentRequired || comment.trim().length > 0;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>
            {t("dialogTitle", {
              action: t(action === "approve" ? "approve" : action === "reject" ? "reject" : "return"),
              step: stepLabel,
            })}
          </DialogTitle>
          <DialogDescription>{t("commentHint")}</DialogDescription>
        </DialogHeader>

        <div className="space-y-2">
          <Label
            htmlFor="approval-comment"
            className="text-left text-xs after:ml-1 after:content-[':']"
          >
            {t("comment")} {commentRequired ? <RequiredMark /> : null}
          </Label>
          <Textarea
            id="approval-comment"
            value={comment}
            onChange={(e) => setComment(e.target.value)}
            rows={3}
            placeholder={t("commentPlaceholder")}
          />
        </div>

        <DialogFooter>
          <Button
            variant="outline"
            onClick={() => onOpenChange(false)}
            disabled={actionMutation.isPending}
          >
            {t("cancel")}
          </Button>
          <Button
            onClick={() => actionMutation.mutate()}
            disabled={actionMutation.isPending || !canSubmit}
            variant={action === "reject" ? "destructive" : "default"}
          >
            {actionMutation.isPending ? t("saving") : t("confirm")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
