"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { useTranslations } from "next-intl";
import { ChevronDown, ChevronUp, Plus, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { RequiredMark } from "@/components/required-mark";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { unwrap, withAuth } from "@/lib/api-client";
import {
  approvalsFlowsStore,
  approvalsFlowsUpdate,
} from "@/lib/api/approval-flow/approval-flow";
import { approvalsSettingsIndex } from "@/lib/api/approval-setting/approval-setting";
import type { StoreApprovalFlowRequest } from "@/lib/api/model/storeApprovalFlowRequest";
import type { StoreApprovalFlowRequestSubjectType } from "@/lib/api/model/storeApprovalFlowRequestSubjectType";
import type { UpdateApprovalFlowRequest } from "@/lib/api/model/updateApprovalFlowRequest";
import type { ApprovalSettingResource } from "@/lib/api/model/approvalSettingResource";
import type { ApprovalStepResource } from "@/lib/api/model/approvalStepResource";

export type ApprovalFlowRow = {
  id: string;
  code: string;
  name: string;
  minAmount: string;
  maxAmount: string | null;
  isActive: boolean;
  setting: ApprovalSettingResource;
  steps: ApprovalStepResource[];
};

type StepAction = "approve" | "reject" | "return";

type StepRow = {
  key: string;
  label: string;
  actionMode: "decide" | "record";
  allowedActions: StepAction[];
};

type FlowForm = {
  subjectType: StoreApprovalFlowRequestSubjectType;
  name: string;
  minAmount: string;
  maxAmount: string;
  isActive: boolean;
  steps: StepRow[];
};

const ALL_ACTIONS: StepAction[] = ["approve", "reject", "return"];

const EMPTY_FORM: FlowForm = {
  subjectType: "evaluation",
  name: "",
  minAmount: "0",
  maxAmount: "",
  isActive: true,
  steps: [{ key: "", label: "", actionMode: "decide", allowedActions: [...ALL_ACTIONS] }],
};

function toSteps(editing?: ApprovalFlowRow | null): StepRow[] {
  const rows = [...(editing?.steps ?? [])]
    .sort((a, b) => a.position - b.position)
    .map((step) => ({
      key: step.key,
      label: step.label,
      actionMode: (step.actionMode === "record" ? "record" : "decide") as StepRow["actionMode"],
      allowedActions: (step.allowedActions ?? []) as StepAction[],
    }));
  return rows.length > 0 ? rows : EMPTY_FORM.steps;
}

function toForm(editing?: ApprovalFlowRow | null): FlowForm {
  if (!editing) return { ...EMPTY_FORM, steps: toSteps(null) };
  return {
    subjectType: editing.setting.subjectType as StoreApprovalFlowRequestSubjectType,
    name: editing.name,
    minAmount: editing.minAmount,
    maxAmount: editing.maxAmount ?? "",
    isActive: editing.isActive,
    steps: toSteps(editing),
  };
}

interface FlowDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  editing?: ApprovalFlowRow | null;
  onSaved?: (id: string) => void;
}

export function FlowDialog({
  open,
  onOpenChange,
  editing,
  onSaved,
}: FlowDialogProps) {
  const t = useTranslations("approvals.settings.flows");
  const qc = useQueryClient();
  const [form, setForm] = useState<FlowForm>(() => toForm(editing));

  const settingsQuery = useQuery({
    queryKey: ["approvalSettings"],
    queryFn: async () =>
      unwrap<ApprovalSettingResource[]>(
        await approvalsSettingsIndex(withAuth())
      ),
    enabled: !editing,
  });

  const updateStep = (index: number, patch: Partial<StepRow>) => {
    setForm((f) => ({
      ...f,
      steps: f.steps.map((row, i) => (i === index ? { ...row, ...patch } : row)),
    }));
  };

  const moveStep = (index: number, direction: -1 | 1) => {
    setForm((f) => {
      const next = [...f.steps];
      const target = index + direction;
      if (target < 0 || target >= next.length) return f;
      [next[index], next[target]] = [next[target], next[index]];
      return { ...f, steps: next };
    });
  };

  const toggleAction = (index: number, action: StepAction, checked: boolean) => {
    updateStep(index, {
      allowedActions: checked
        ? [...form.steps[index].allowedActions, action]
        : form.steps[index].allowedActions.filter((a) => a !== action),
    });
  };

  const keys = form.steps.map((row) => row.key.trim());
  const canSave =
    form.name.trim().length > 0 &&
    form.minAmount !== "" &&
    Number(form.minAmount) >= 0 &&
    (form.maxAmount === "" || Number(form.maxAmount) >= Number(form.minAmount)) &&
    form.steps.length > 0 &&
    form.steps.every((row) => row.key.trim().length > 0 && row.label.trim().length > 0) &&
    new Set(keys).size === keys.length &&
    form.steps.every(
      (row) => row.actionMode !== "decide" || row.allowedActions.length > 0
    );

  const saveMutation = useMutation({
    mutationFn: async () => {
      const steps = form.steps.map((row) => ({
        key: row.key.trim(),
        label: row.label.trim(),
        action_mode: row.actionMode,
        allowed_actions: row.actionMode === "decide" ? row.allowedActions : null,
      }));
      if (editing) {
        const payload: UpdateApprovalFlowRequest = {
          name: form.name.trim(),
          min_amount: Number(form.minAmount),
          max_amount: form.maxAmount === "" ? null : Number(form.maxAmount),
          is_active: form.isActive,
          steps,
        };
        unwrap(await approvalsFlowsUpdate(editing.id, payload, withAuth()));
        return editing.id;
      }
      const payload: StoreApprovalFlowRequest = {
        subject_type: form.subjectType,
        name: form.name.trim(),
        min_amount: Number(form.minAmount),
        max_amount: form.maxAmount === "" ? null : Number(form.maxAmount),
        is_active: form.isActive,
        steps,
      };
      const created = unwrap<{ id: string }>(
        await approvalsFlowsStore(payload, withAuth())
      );
      return created.id;
    },
    onSuccess: (id) => {
      qc.invalidateQueries({ queryKey: ["approvalFlows"] });
      qc.invalidateQueries({ queryKey: ["approvalSettings"] });
      toast.success(editing ? t("updated") : t("created"));
      onOpenChange(false);
      onSaved?.(id);
    },
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{editing ? t("editTitle") : t("newTitle")}</DialogTitle>
          <DialogDescription>
            {editing ? t("editDescription") : t("newDescription")}
          </DialogDescription>
        </DialogHeader>
        <div className="space-y-4">
          {editing ? (
            <div className="flex items-center gap-3">
              <Label className="w-28 shrink-0 text-left after:ml-1 after:content-[':']">
                {t("subject")}
              </Label>
              <span className="text-sm text-muted-foreground">
                {editing.setting.name}
              </span>
            </div>
          ) : (
            <div className="flex items-center gap-3">
              <Label className="w-28 shrink-0 text-left after:ml-1 after:content-[':']">
                {t("subject")} <RequiredMark />
              </Label>
              <Select
                value={form.subjectType}
                onValueChange={(value) =>
                  setForm((f) => ({
                    ...f,
                    subjectType: value as StoreApprovalFlowRequestSubjectType,
                  }))
                }
              >
                <SelectTrigger className="h-8 flex-1">
                  <SelectValue placeholder={t("subjectPlaceholder")} />
                </SelectTrigger>
                <SelectContent>
                  {(settingsQuery.data ?? []).map((setting) => (
                    <SelectItem
                      key={String(setting.id)}
                      value={setting.subjectType}
                    >
                      {setting.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}
          <div className="flex items-center gap-3">
            <Label
              htmlFor="flow-name"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("name")} <RequiredMark />
            </Label>
            <Input
              id="flow-name"
              value={form.name}
              onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
              placeholder={t("namePlaceholder")}
              className="flex-1"
            />
          </div>
          <div className="flex items-center gap-3">
            <Label
              htmlFor="flow-min"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("minAmount")} <RequiredMark />
            </Label>
            <Input
              id="flow-min"
              type="number"
              min={0}
              value={form.minAmount}
              onChange={(e) =>
                setForm((f) => ({ ...f, minAmount: e.target.value }))
              }
              className="flex-1"
            />
            <Label
              htmlFor="flow-max"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("maxAmount")}
            </Label>
            <Input
              id="flow-max"
              type="number"
              min={0}
              value={form.maxAmount}
              onChange={(e) =>
                setForm((f) => ({ ...f, maxAmount: e.target.value }))
              }
              placeholder={t("maxAmountPlaceholder")}
              className="flex-1"
            />
          </div>
          <div className="flex items-center gap-3">
            <Label
              htmlFor="flow-active"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("active")}
            </Label>
            <Switch
              id="flow-active"
              checked={form.isActive}
              onCheckedChange={(checked) =>
                setForm((f) => ({ ...f, isActive: checked }))
              }
            />
          </div>

          <div className="space-y-2 rounded-md border p-3">
            <div className="flex items-center justify-between gap-2">
              <div>
                <Label className="text-sm font-medium">
                  {t("steps")} <RequiredMark />
                </Label>
                <p className="text-xs text-muted-foreground">
                  {t("stepsHint")}
                </p>
              </div>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() =>
                  setForm((f) => ({
                    ...f,
                    steps: [
                      ...f.steps,
                      {
                        key: "",
                        label: "",
                        actionMode: "decide",
                        allowedActions: [...ALL_ACTIONS],
                      },
                    ],
                  }))
                }
              >
                <Plus className="mr-1 size-3" />
                {t("addStep")}
              </Button>
            </div>
            <div className="space-y-2">
              {form.steps.map((row, index) => (
                <div
                  key={row.key || `step-${index}`}
                  className="space-y-2 rounded-md border p-2"
                >
                  <div className="grid grid-cols-[1.5rem_7rem_9rem_1fr_auto] items-center gap-2">
                    <span className="text-center font-mono text-xs text-muted-foreground">
                      {index + 1}
                    </span>
                    <Select
                      value={row.actionMode}
                      onValueChange={(value) =>
                        updateStep(index, {
                          actionMode: value as StepRow["actionMode"],
                        })
                      }
                    >
                      <SelectTrigger className="h-8">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="decide">{t("modeDecide")}</SelectItem>
                        <SelectItem value="record">{t("modeRecord")}</SelectItem>
                      </SelectContent>
                    </Select>
                    <Input
                      value={row.key}
                      onChange={(e) =>
                        updateStep(index, { key: e.target.value })
                      }
                      placeholder={t("stepKeyPlaceholder")}
                      className="h-8 font-mono"
                      aria-label={t("stepKey")}
                    />
                    <Input
                      value={row.label}
                      onChange={(e) =>
                        updateStep(index, { label: e.target.value })
                      }
                      placeholder={t("stepLabelPlaceholder")}
                      className="h-8"
                      aria-label={t("stepLabel")}
                    />
                    <div className="flex items-center">
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        disabled={index === 0}
                        aria-label={t("moveUp")}
                        onClick={() => moveStep(index, -1)}
                      >
                        <ChevronUp className="size-4" />
                      </Button>
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        disabled={index === form.steps.length - 1}
                        aria-label={t("moveDown")}
                        onClick={() => moveStep(index, 1)}
                      >
                        <ChevronDown className="size-4" />
                      </Button>
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8 text-destructive disabled:opacity-40"
                        disabled={form.steps.length <= 1}
                        aria-label={t("removeStep")}
                        onClick={() =>
                          setForm((f) => ({
                            ...f,
                            steps: f.steps.filter((_, i) => i !== index),
                          }))
                        }
                      >
                        <Trash2 className="size-4" />
                      </Button>
                    </div>
                  </div>
                  {row.actionMode === "decide" && (
                    <div className="flex flex-wrap items-center gap-4 pl-9">
                      {ALL_ACTIONS.map((action) => (
                        <label
                          key={action}
                          className="flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                          <Checkbox
                            checked={row.allowedActions.includes(action)}
                            onCheckedChange={(checked) =>
                              toggleAction(index, action, checked === true)
                            }
                          />
                          {action === "approve"
                            ? t("actionApprove")
                            : action === "reject"
                              ? t("actionReject")
                              : t("actionReturn")}
                        </label>
                      ))}
                    </div>
                  )}
                </div>
              ))}
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button
            variant="outline"
            onClick={() => onOpenChange(false)}
            disabled={saveMutation.isPending}
          >
            {t("cancel")}
          </Button>
          <Button
            onClick={() => saveMutation.mutate()}
            disabled={saveMutation.isPending || !canSave}
          >
            {saveMutation.isPending ? t("saving") : t("save")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
