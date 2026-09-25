"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import {
  approvalsPreview,
  approvalsRequestsStore,
} from "@/lib/api/approval-request/approval-request";
import type { StoreApprovalRequest } from "@/lib/api/model/storeApprovalRequest";
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { RequiredMark } from "@/components/required-mark";
import type { ApprovalPreview } from "../approval-types";

const money = new Intl.NumberFormat("en-US", {
  style: "currency",
  currency: "USD",
  minimumFractionDigits: 2,
});

type Props = {
  subjectType: string;
  subjectId: string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

export function SubmitApprovalDialog({
  subjectType,
  subjectId,
  open,
  onOpenChange,
}: Props) {
  const t = useTranslations("approvals.submit");
  const qc = useQueryClient();
  const [assignees, setAssignees] = useState<Record<number, string>>({});
  const [wasOpen, setWasOpen] = useState(open);

  if (wasOpen !== open) {
    setWasOpen(open);
    if (open) setAssignees({});
  }

  const previewQuery = useQuery({
    queryKey: ["approvals", "preview", subjectType, subjectId],
    queryFn: async (): Promise<ApprovalPreview> =>
      unwrap(
        await approvalsPreview(
          { subject_type: subjectType, subject_id: subjectId },
          withAuth()
        )
      ) as ApprovalPreview,
    enabled: open,
  });

  const preview = previewQuery.data;

  const decideSteps = (preview?.steps ?? []).filter(
    (step) => step.actionMode === "decide"
  );

  const missingAssignee = decideSteps.some(
    (step) => !assignees[step.position]
  );

  const submitMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        subject_type: subjectType,
        subject_id: subjectId,
        assignees: Object.fromEntries(
          decideSteps.map((step) => [
            step.position,
            Number(assignees[step.position]),
          ])
        ),
      } as unknown as StoreApprovalRequest;

      return unwrap(await approvalsRequestsStore(payload, withAuth()));
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["approvals"] });
      qc.invalidateQueries({ queryKey: ["evaluations"] });
      onOpenChange(false);
      toast.success(t("submitted"));
    },
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{t("title")}</DialogTitle>
          <DialogDescription>{t("description")}</DialogDescription>
        </DialogHeader>

        {previewQuery.isPending ? (
          <p className="text-sm text-muted-foreground">{t("submitting")}</p>
        ) : previewQuery.isError || !preview ? (
          <p className="text-sm text-destructive">
            {(previewQuery.error as Error | null)?.message ?? t("loadFailed")}
          </p>
        ) : (
          <div className="space-y-4">
            <div className="rounded-lg border border-border p-3 text-sm">
              <div className="flex items-center justify-between gap-3">
                <span className="text-muted-foreground">{t("flow")}</span>
                <span className="font-medium">{preview.flow.name}</span>
              </div>
              <div className="mt-1 flex items-center justify-between gap-3">
                <span className="text-muted-foreground">{t("amount")}</span>
                <span className="font-medium tabular-nums">
                  {money.format(Number(preview.amount))}
                </span>
              </div>
            </div>

            <div className="space-y-3">
              {preview.steps.map((step) => {
                if (step.actionMode === "record") {
                  return (
                    <div
                      key={step.position}
                      className="flex items-center justify-between gap-3 text-sm"
                    >
                      <span>{step.label}</span>
                      <span className="text-xs text-muted-foreground">
                        {t("autoRecorded")}
                      </span>
                    </div>
                  );
                }

                const candidates = step.candidates ?? [];

                return (
                  <div
                    key={step.position}
                    className="flex flex-wrap items-center justify-between gap-3"
                  >
                    <Label className="text-left text-xs after:ml-1 after:content-[':']">
                      {step.label} <RequiredMark />
                    </Label>
                    <Select
                      value={assignees[step.position] ?? null}
                      onValueChange={(value) =>
                        setAssignees((previous) => ({
                          ...previous,
                          [step.position]: value ?? "",
                        }))
                      }
                      items={candidates.map((candidate) => ({
                        value: String(candidate.id),
                        label: candidate.name,
                      }))}
                    >
                      <SelectTrigger className="min-w-0 flex-1">
                        <SelectValue
                          className="min-w-0"
                          placeholder={
                            candidates.length === 0
                              ? t("noCandidates")
                              : t("selectAssignee")
                          }
                        />
                      </SelectTrigger>
                      <SelectContent>
                        {candidates.map((candidate) => (
                          <SelectItem
                            key={candidate.id}
                            value={String(candidate.id)}
                          >
                            {candidate.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                );
              })}
            </div>
          </div>
        )}

        <DialogFooter>
          <Button
            variant="outline"
            onClick={() => onOpenChange(false)}
            disabled={submitMutation.isPending}
          >
            {t("cancel")}
          </Button>
          <Button
            onClick={() => submitMutation.mutate()}
            disabled={
              submitMutation.isPending ||
              !preview ||
              decideSteps.length === 0 ||
              missingAssignee
            }
          >
            {submitMutation.isPending ? t("submitting") : t("submit")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
