"use client";

import { useEffect, useState, type ReactNode } from "react";
import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { toast } from "sonner";
import { Loader2, Plus, Send, X } from "lucide-react";
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
import { Skeleton } from "@/components/ui/skeleton";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { RequiredMark } from "@/components/required-mark";
import { useMe } from "@/hooks/use-me";
import { approvalSubject } from "../approval-subjects";
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
  /** Reports the current picks per decide step so Save can persist a draft. */
  onAssigneesChange?: (assignees: Record<number, string[]>) => void;
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
  const [assignees, setAssignees] = useState<Record<number, string[]>>({});
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
          Object.entries(saved).map(([position, ids]) => [
            Number(position),
            (Array.isArray(ids) ? ids : [ids]).map(String),
          ])
        )
      );
    }
  }

  // Report only picked users — a freshly added, still empty row ("") must
  // never reach the draft payload (assignees.*.* is integer min:1).
  useEffect(() => {
    const cleaned = Object.fromEntries(
      Object.entries(assignees)
        .map(
          ([position, ids]) =>
            [Number(position), ids.filter((id) => id !== "")] as [
              number,
              string[],
            ]
        )
        .filter(([, ids]) => ids.length > 0)
    ) as Record<number, string[]>;
    onAssigneesChange?.(cleaned);
  }, [assignees, onAssigneesChange]);

  // One table row per co-signer: explicit picks win (a "" marks a freshly
  // added, still empty row); an untouched step keeps the single-candidate
  // implicit pick. Stale draft picks (authority/band changed) fall away.
  const rowIds = (step: ApprovalPreviewStep): string[] => {
    const candidates = (step.candidates ?? []).map((candidate) =>
      String(candidate.id)
    );
    const kept = (assignees[step.position] ?? []).filter(
      (id) => id === "" || candidates.includes(id)
    );
    if (kept.length > 0) return kept;
    return [candidates.length === 1 ? candidates[0] : ""];
  };

  const resolveAssignees = (step: ApprovalPreviewStep): string[] =>
    rowIds(step).filter((id) => id !== "");

  const updateRow = (
    step: ApprovalPreviewStep,
    rowIndex: number,
    id: string
  ) => {
    const rows = [...rowIds(step)];
    rows[rowIndex] = id;
    setAssignees((previous) => ({ ...previous, [step.position]: rows }));
  };

  const addRow = (step: ApprovalPreviewStep) => {
    setAssignees((previous) => ({
      ...previous,
      [step.position]: [...rowIds(step), ""],
    }));
  };

  const removeRow = (step: ApprovalPreviewStep, rowIndex: number) => {
    setAssignees((previous) => ({
      ...previous,
      [step.position]: rowIds(step).filter((_, index) => index !== rowIndex),
    }));
  };

  const decideSteps = (preview?.steps ?? []).filter(
    (step) => step.actionMode === "decide"
  );

  const missingSteps = decideSteps.filter(
    (step) => resolveAssignees(step).length === 0
  );

  const missingAssignee = missingSteps.length > 0;

  // The subject's own query key and detail route — never hardcode one type.
  const subjectConfig = approvalSubject(subjectType);

  const submitMutation = useMutation({
    mutationFn: async (vars: { subjectId: string; created: boolean }) => {
      const payload = {
        subject_type: subjectType,
        subject_id: vars.subjectId,
        assignees: Object.fromEntries(
          decideSteps.map((step) => [
            step.position,
            resolveAssignees(step).map(Number),
          ])
        ),
      } as unknown as StoreApprovalRequest;

      return unwrap(await approvalsRequestsStore(payload, withAuth()));
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["approvals"] });
      if (subjectConfig) {
        qc.invalidateQueries({ queryKey: subjectConfig.documentQueryKey });
      }
      setAssignees({});
      toast.success(t("submitted"));
    },
    onSettled: (_data, _error, vars) => {
      if (vars.created) {
        // The document now exists even if the round failed to open — land on
        // its own detail page (approvals tray for unknown subjects).
        router.push(
          subjectConfig ? subjectConfig.detailPath(vars.subjectId) : "/approvals"
        );
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
          <div className="space-y-3">
            <div className="flex flex-wrap gap-x-6 gap-y-1">
              <Skeleton className="h-4 w-40" />
              <Skeleton className="h-4 w-28" />
            </div>
            <div className="space-y-2">
              <Skeleton className="h-8 w-full" />
              <Skeleton className="h-8 w-full" />
              <Skeleton className="h-8 w-5/6" />
            </div>
          </div>
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

            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="h-8 w-12">{t("stepNumber")}</TableHead>
                  <TableHead className="h-8">{t("stepName")}</TableHead>
                  <TableHead className="h-8 w-72">{t("assignee")}</TableHead>
                  <TableHead className="h-8 w-44">
                    {t("assigneePosition")}
                  </TableHead>
                  <TableHead className="hidden h-8 w-56 md:table-cell">
                    {t("additionalMessage")}
                  </TableHead>
                  <TableHead className="h-8 w-32">
                    {t("addCoApproval")}
                  </TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {(preview.steps ?? []).map((step, index) => {
                  const isRecord = step.actionMode === "record";
                  const candidates = isRecord ? [] : (step.candidates ?? []);
                  const rows: (string | null)[] = isRecord
                    ? [null]
                    : rowIds(step);

                  return rows.map((rowId, rowIndex) => {
                    const others = rows.filter((_, i) => i !== rowIndex);
                    const options = ComboboxNS.createItems(
                      candidates
                        .filter(
                          (candidate) => !others.includes(String(candidate.id))
                        )
                        .map((candidate) => ({
                          value: String(candidate.id),
                          label: candidate.name,
                        })),
                      {
                        getValue: (candidate) => candidate.value,
                        getLabel: (candidate) => candidate.label,
                      }
                    );
                    const picked = candidates.find(
                      (candidate) => String(candidate.id) === rowId
                    );
                    const positionText = isRecord
                      ? ((me.data ?? null)?.position ?? null)
                      : (picked?.position ?? null);
                    const addDisabled =
                      candidates.length === 0 ||
                      candidates.every((candidate) =>
                        rows.includes(String(candidate.id))
                      );

                    return (
                      <TableRow key={`${step.position}:${rowIndex}`}>
                        <TableCell className="py-1 tabular-nums">
                          {rowIndex === 0 ? index + 1 : ""}
                        </TableCell>
                        <TableCell className="whitespace-normal py-1">
                          {rowIndex === 0 ? (
                            <>
                              <Label className="text-sm font-medium">
                                {step.label}
                                {isRecord ? null : (
                                  <>
                                    {" "}
                                    <RequiredMark />
                                  </>
                                )}
                              </Label>
                              {isRecord ? (
                                <Badge
                                  variant="secondary"
                                  className="ml-2 px-1.5 text-[10px] font-normal"
                                >
                                  {t("auto")}
                                </Badge>
                              ) : null}
                            </>
                          ) : (
                            <Label className="text-sm font-medium text-muted-foreground">
                              {t("coStepName", { name: step.label })}
                            </Label>
                          )}
                        </TableCell>
                        <TableCell className="whitespace-normal py-1">
                          {isRecord ? (
                            <span className="text-sm text-muted-foreground">
                              {(me.data ?? null)?.name ?? t("autoRecorded")}
                            </span>
                          ) : (
                            <Combobox
                              items={options}
                              value={rowId || null}
                              onValueChange={(next) => {
                                if (next === null || next === undefined)
                                  return;
                                updateRow(step, rowIndex, String(next));
                              }}
                            >
                              <ComboboxInput
                                className="h-8 min-w-0 w-full"
                                placeholder={
                                  candidates.length === 0
                                    ? t("noCandidates")
                                    : t("selectAssignee")
                                }
                                aria-label={t("selectAssignee")}
                              />
                              <ComboboxContent>
                                <ComboboxEmpty className="text-xs">
                                  {candidates.length === 0
                                    ? t("noCandidates")
                                    : t("noMatch")}
                                </ComboboxEmpty>
                                <ComboboxList>
                                  {(option) => (
                                    <ComboboxItem
                                      key={option.value}
                                      value={option.value}
                                      className="text-xs"
                                    >
                                      {option.label}
                                    </ComboboxItem>
                                  )}
                                </ComboboxList>
                              </ComboboxContent>
                            </Combobox>
                          )}
                        </TableCell>
                        <TableCell className="whitespace-normal py-1">
                          {positionText ? (
                            positionText
                          ) : (
                            <span className="text-muted-foreground">—</span>
                          )}
                        </TableCell>
                        <TableCell className="hidden py-1 text-muted-foreground md:table-cell">
                          —
                        </TableCell>
                        <TableCell className="py-1">
                          {isRecord ? null : rowIndex === 0 ? (
                            <Button
                              type="button"
                              variant="ghost"
                              size="icon"
                              className="size-7"
                              aria-label={t("addCoApproval")}
                              title={
                                addDisabled
                                  ? t("allSelected")
                                  : t("addCoApproval")
                              }
                              disabled={addDisabled}
                              onClick={() => addRow(step)}
                            >
                              <Plus className="size-4" />
                            </Button>
                          ) : (
                            <Button
                              type="button"
                              variant="ghost"
                              size="icon"
                              className="size-7 text-destructive disabled:opacity-40"
                              aria-label={t("removeAssignee", {
                                name: picked?.name ?? t("assignee"),
                              })}
                              onClick={() => removeRow(step, rowIndex)}
                            >
                              <X className="size-4" />
                            </Button>
                          )}
                        </TableCell>
                      </TableRow>
                    );
                  });
                })}
              </TableBody>
            </Table>
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
