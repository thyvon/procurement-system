"use client";

import { useState, type ReactNode } from "react";
import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { toast } from "sonner";
import { Send } from "lucide-react";
import { Combobox as ComboboxNS } from "@base-ui/react/combobox";
import {
  approvalsPreview,
  approvalsRequestsStore,
} from "@/lib/api/approval-request/approval-request";
import type { StoreApprovalRequest } from "@/lib/api/model/storeApprovalRequest";
import { unwrap, withAuth } from "@/lib/api-client";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Combobox,
  ComboboxContent,
  ComboboxEmpty,
  ComboboxInput,
  ComboboxItem,
  ComboboxList,
} from "@/components/ui/combobox";
import { Label } from "@/components/ui/label";
import { RequiredMark } from "@/components/required-mark";
import { useMe } from "@/hooks/use-me";
import type { ApprovalPreview } from "../approval-types";

const money = new Intl.NumberFormat("en-US", {
  style: "currency",
  currency: "USD",
  minimumFractionDigits: 2,
});

type Props = {
  subjectType: string;
  /** Existing document id; null/undefined = not created yet (draft preview). */
  subjectId?: string | null;
  /** Draft amount the document would carry once saved; null = last saved value. */
  amount?: number | null;
  /** Unsaved form changes: the approval always runs on the saved record. */
  dirty?: boolean;
  disabled?: boolean;
  /** Create mode: saves the document and returns its id so submit can chain. */
  prepareDocument?: () => Promise<string | null>;
  actions?: ReactNode;
};

export function SubmitApprovalPanel({
  subjectType,
  subjectId = null,
  amount = null,
  dirty = false,
  disabled = false,
  prepareDocument,
  actions,
}: Props) {
  const t = useTranslations("approvals.submit");
  const qc = useQueryClient();
  const router = useRouter();
  const me = useMe();
  const [assignees, setAssignees] = useState<Record<number, string>>({});
  const [preparing, setPreparing] = useState(false);

  const previewQuery = useQuery({
    queryKey: ["approvals", "preview", subjectType, subjectId, amount],
    queryFn: async (): Promise<ApprovalPreview> =>
      unwrap(
        await approvalsPreview(
          {
            subject_type: subjectType,
            ...(subjectId === null ? {} : { subject_id: subjectId }),
            ...(amount === null ? {} : { amount }),
          },
          withAuth()
        )
      ) as ApprovalPreview,
    placeholderData: keepPreviousData,
    enabled: subjectId !== null || amount !== null,
  });

  const preview = previewQuery.data;

  const decideSteps = (preview?.steps ?? []).filter(
    (step) => step.actionMode === "decide"
  );

  const missingAssignee = decideSteps.some(
    (step) => !assignees[step.position]
  );

  const submitMutation = useMutation({
    mutationFn: async (vars: { subjectId: string; created: boolean }) => {
      const payload = {
        subject_type: subjectType,
        subject_id: vars.subjectId,
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
      setAssignees({});
      toast.success(t("submitted"));
    },
    onSettled: (_data, _error, vars) => {
      if (vars.created) {
        router.push(`/purchase-orders/evaluations/${vars.subjectId}`);
      }
    },
  });

  const handleSubmit = async () => {
    if (subjectId !== null) {
      submitMutation.mutate({ subjectId, created: false });
      return;
    }

    setPreparing(true);
    try {
      const createdId = await prepareDocument?.();
      if (!createdId) return;
      submitMutation.mutate({ subjectId: createdId, created: true });
    } catch {
      // Save failed — the global mutation error toast already explained why.
    } finally {
      setPreparing(false);
    }
  };

  const busy =
    preparing || submitMutation.isPending || disabled || dirty || !preview;

  return (
    <Card size="sm" className="min-w-0">
      <CardHeader>
        <CardTitle className="text-xs">{t("title")}</CardTitle>
      </CardHeader>
      <CardContent className="min-w-0 space-y-3">
          {previewQuery.isPending ? (
            <p className="text-sm text-muted-foreground">{t("loading")}</p>
          ) : previewQuery.isError || !preview ? (
            <p className="text-sm text-destructive">
              {(previewQuery.error as Error | null)?.message ?? t("loadFailed")}
            </p>
          ) : (
          <div className="space-y-3">
            <div className="flex flex-wrap gap-x-6 gap-y-1 text-sm">
              <span className="text-muted-foreground">
                {t("flow")}
                <span className="ml-1.5 font-medium text-foreground">
                  {preview.flow.name}
                </span>
              </span>
              <span className="text-muted-foreground">
                {t("amount")}
                <span className="ml-1.5 font-medium tabular-nums text-foreground">
                  {money.format(Number(preview.amount))}
                </span>
              </span>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
              {(preview.steps ?? []).map((step) => {
                if (step.actionMode === "record") {
                  const recorder = me.data ?? null;
                  const recordItems = ComboboxNS.createItems(
                    recorder
                      ? [{ value: String(recorder.id), label: recorder.name }]
                      : [],
                    {
                      getValue: (record) => record.value,
                      getLabel: (record) => record.label,
                    }
                  );

                  return (
                    <div key={step.position} className="space-y-1">
                      <Label className="text-left text-xs after:ml-1 after:content-[':']">
                        {step.label}
                      </Label>
                      <Combobox
                        items={recordItems}
                        value={recorder ? String(recorder.id) : null}
                      >
                        <ComboboxInput
                          disabled
                          className="min-w-0 w-full"
                          placeholder={t("autoRecorded")}
                        />
                        <ComboboxContent>
                          <ComboboxList>
                            {(record) => (
                              <ComboboxItem
                                key={record.value}
                                value={record.value}
                                className="text-xs"
                              >
                                {record.label}
                              </ComboboxItem>
                            )}
                          </ComboboxList>
                        </ComboboxContent>
                      </Combobox>
                    </div>
                  );
                }

                const candidates = step.candidates ?? [];

                const items = ComboboxNS.createItems(
                  candidates.map((candidate) => ({
                    value: String(candidate.id),
                    label: candidate.name,
                  })),
                  {
                    getValue: (candidate) => candidate.value,
                    getLabel: (candidate) => candidate.label,
                  }
                );

                return (
                  <div key={step.position} className="space-y-1">
                    <Label className="text-left text-xs after:ml-1 after:content-[':']">
                      {step.label} <RequiredMark />
                    </Label>
                    <Combobox
                      items={items}
                      value={assignees[step.position] ?? null}
                      onValueChange={(next) => {
                        if (next === null || next === undefined) return;
                        setAssignees((previous) => ({
                          ...previous,
                          [step.position]: String(next),
                        }));
                      }}
                    >
                      <ComboboxInput
                        className="min-w-0 w-full"
                        placeholder={
                          candidates.length === 0
                            ? t("noCandidates")
                            : t("selectAssignee")
                        }
                      />
                      <ComboboxContent>
                        <ComboboxEmpty className="text-xs">
                          {candidates.length === 0
                            ? t("noCandidates")
                            : t("noMatch")}
                        </ComboboxEmpty>
                        <ComboboxList>
                          {(item) => (
                            <ComboboxItem
                              key={item.value}
                              value={item.value}
                              className="text-xs"
                            >
                              {item.label}
                            </ComboboxItem>
                          )}
                        </ComboboxList>
                      </ComboboxContent>
                    </Combobox>
                  </div>
                );
              })}
            </div>
          </div>
        )}
      </CardContent>

      <CardFooter className="gap-2">
        {dirty ? (
          <p className="text-xs text-muted-foreground">{t("saveFirst")}</p>
        ) : null}
        <div className="ml-auto flex flex-wrap items-center gap-2">
          <Button
            variant="outline"
            onClick={() => void handleSubmit()}
            disabled={busy || decideSteps.length === 0 || missingAssignee}
          >
            <Send />
            <span>
              {preparing || submitMutation.isPending
                ? t("submitting")
                : subjectId === null
                  ? t("saveAndSubmit")
                  : t("submit")}
            </span>
          </Button>
          {actions}
        </div>
      </CardFooter>
    </Card>
  );
}
