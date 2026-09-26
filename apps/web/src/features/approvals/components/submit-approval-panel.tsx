"use client";

import { useEffect, useState, type ReactNode } from "react";
import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { toast } from "sonner";
import { Loader2, Send } from "lucide-react";
import { Combobox as ComboboxNS } from "@base-ui/react/combobox";
import {
  approvalsPreview,
  approvalsRequestsStore,
} from "@/lib/api/approval-request/approval-request";
import type { StoreApprovalRequest } from "@/lib/api/model/storeApprovalRequest";
import { unwrap, withAuth } from "@/lib/api-client";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
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
import type { ApprovalPreview, ApprovalPreviewStep } from "../approval-types";

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
  /** Reports the current pick per decide step so Save can persist a draft. */
  onAssigneesChange?: (assignees: Record<number, string>) => void;
  actions?: ReactNode;
};

export function SubmitApprovalPanel({
  subjectType,
  subjectId = null,
  amount = null,
  dirty = false,
  disabled = false,
  prepareDocument,
  onAssigneesChange,
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

  // Restore the selection stored by "Save" — same render-phase hydration
  // pattern the evaluation form uses, and only while nothing is picked yet,
  // so a preview refetch never clobbers the user's current choices.
  const [seededPreview, setSeededPreview] = useState<ApprovalPreview | null>(
    null
  );
  if (preview && seededPreview !== preview) {
    const saved = preview.assignees;
    setSeededPreview(preview);
    if (saved && Object.keys(assignees).length === 0) {
      setAssignees(
        Object.fromEntries(
          Object.entries(saved).map(([position, id]) => [
            Number(position),
            String(id),
          ])
        )
      );
    }
  }

  useEffect(() => {
    onAssigneesChange?.(assignees);
  }, [assignees, onAssigneesChange]);

  // Explicit pick wins when it is still a valid candidate; a stale draft pick
  // (authority/band changed) falls through so the user re-selects.
  const resolveAssignee = (step: ApprovalPreviewStep): string | null => {
    const candidates = step.candidates ?? [];
    const explicit = assignees[step.position];
    if (
      explicit !== undefined &&
      candidates.some((candidate) => String(candidate.id) === explicit)
    ) {
      return explicit;
    }
    return candidates.length === 1 ? String(candidates[0].id) : null;
  };

  const decideSteps = (preview?.steps ?? []).filter(
    (step) => step.actionMode === "decide"
  );

  const missingSteps = decideSteps.filter(
    (step) => resolveAssignee(step) === null
  );

  const missingAssignee = missingSteps.length > 0;

  const submitMutation = useMutation({
    mutationFn: async (vars: { subjectId: string; created: boolean }) => {
      const payload = {
        subject_type: subjectType,
        subject_id: vars.subjectId,
        assignees: Object.fromEntries(
          decideSteps.map((step) => [
            step.position,
            Number(resolveAssignee(step)),
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
        <CardDescription className="text-xs">{t("description")}</CardDescription>
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
              {preview.documentCode ? (
                <span className="text-muted-foreground">
                  {t("document")}
                  <span className="ml-1.5 font-medium text-foreground">
                    {preview.documentCode}
                  </span>
                </span>
              ) : null}
            </div>

            <ol>
              {(preview.steps ?? []).map((step, index, all) => {
                const candidates =
                  step.actionMode === "decide" ? step.candidates ?? [] : [];
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
                  <li
                    key={step.position}
                    className="relative flex gap-3 pb-4 last:pb-0"
                  >
                    {index < all.length - 1 ? (
                      <span
                        aria-hidden
                        className="absolute left-[11px] top-6 h-[calc(100%_-_1.5rem)] w-px bg-border"
                      />
                    ) : null}
                    <span className="relative z-10 flex size-6 shrink-0 items-center justify-center rounded-full border bg-background text-xs font-medium tabular-nums">
                      {index + 1}
                    </span>
                    <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                      <div className="flex items-center gap-2">
                        <Label className="text-sm font-medium">
                          {step.label}
                          {step.actionMode === "decide" ? (
                            <>
                              {" "}
                              <RequiredMark />
                            </>
                          ) : null}
                        </Label>
                        {step.actionMode === "record" ? (
                          <Badge
                            variant="secondary"
                            className="px-1.5 text-[10px] font-normal"
                          >
                            {t("auto")}
                          </Badge>
                        ) : null}
                      </div>
                      {step.actionMode === "record" ? (
                        <p className="truncate text-sm text-muted-foreground">
                          {(me.data ?? null)?.name ?? t("autoRecorded")}
                        </p>
                      ) : (
                        <div className="space-y-1">
                          <Combobox
                            items={items}
                            value={resolveAssignee(step)}
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
                          {candidates.length === 0 ? (
                            <p className="text-xs text-destructive">
                              {t("noCandidates")}
                            </p>
                          ) : null}
                        </div>
                      )}
                    </div>
                  </li>
                );
              })}
            </ol>
          </div>
        )}
      </CardContent>

      <CardFooter className="gap-2">
        {dirty ? (
          <p className="text-xs text-muted-foreground">{t("saveFirst")}</p>
        ) : missingSteps.length > 0 && preview ? (
          <p className="text-xs text-destructive">
            {t("selectFor", {
              steps: missingSteps.map((step) => step.label).join(", "),
            })}
          </p>
        ) : null}
        <div className="ml-auto flex flex-wrap items-center gap-2">
          <Button
            variant="outline"
            onClick={() => void handleSubmit()}
            disabled={busy || decideSteps.length === 0 || missingAssignee}
          >
            {preparing || submitMutation.isPending ? (
              <Loader2 className="animate-spin" />
            ) : (
              <Send />
            )}
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
