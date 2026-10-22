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
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
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
  uid: string;
  key: string;
  label: string;
  labelKh: string;
  actionMode: "decide" | "record";
  allowedActions: StepAction[];
  showOnPrint: boolean;
};

type FlowForm = {
  /** Empty until a new row adopts the first configured setting. */
  subjectType: StoreApprovalFlowRequestSubjectType | "";
  name: string;
  minAmount: string;
  maxAmount: string;
  isActive: boolean;
  steps: StepRow[];
};

const ALL_ACTIONS: StepAction[] = ["approve", "reject", "return"];

function newRow(): StepRow {
  return {
    uid: `row-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
    key: "",
    label: "",
    labelKh: "",
    actionMode: "decide",
    allowedActions: [...ALL_ACTIONS],
    showOnPrint: true,
  };
}

const EMPTY_FORM: FlowForm = {
  subjectType: "",
  name: "",
  minAmount: "0",
  maxAmount: "",
  isActive: true,
  steps: [],
};

function toSteps(editing?: ApprovalFlowRow | null): StepRow[] {
  const rows = [...(editing?.steps ?? [])]
    .sort((a, b) => a.position - b.position)
    .map((step) => ({
      ...newRow(),
      key: step.key,
      label: step.label,
      labelKh: step.labelKh ?? "",
      actionMode: (step.actionMode === "record" ? "record" : "decide") as StepRow["actionMode"],
      allowedActions: (step.allowedActions ?? []) as StepAction[],
      showOnPrint: step.showOnPrint ?? true,
    }));
  return rows.length > 0 ? rows : [newRow()];
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

  // New rows adopt the first configured subject — derived from the settings,
  // never a hardcoded default.
  const subjectType =
    form.subjectType || settingsQuery.data?.[0]?.subjectType || "";

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
    subjectType !== "" &&
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
        label_kh: row.labelKh.trim() === "" ? null : row.labelKh.trim(),
        action_mode: row.actionMode,
        allowed_actions: row.actionMode === "decide" ? row.allowedActions : null,
        show_on_print: row.showOnPrint,
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
        subject_type: subjectType as StoreApprovalFlowRequestSubjectType,
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
      <DialogContent className="sm:max-w-6xl">
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
                value={subjectType}
                items={(settingsQuery.data ?? []).map((setting) => ({
                  value: setting.subjectType,
                  label: setting.name,
                }))}
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
          <div className="flex flex-wrap items-center gap-3">
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
              className="min-w-48 flex-1"
            />
            <Label
              htmlFor="flow-min"
              className="shrink-0 text-left after:ml-1 after:content-[':']"
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
              className="w-36"
            />
            <Label
              htmlFor="flow-max"
              className="shrink-0 text-left after:ml-1 after:content-[':']"
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
              className="w-36"
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
                    steps: [...f.steps, newRow()],
                  }))
                }
              >
                <Plus className="mr-1 size-3" />
                {t("addStep")}
              </Button>
            </div>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="h-8 w-8 text-center">#</TableHead>
                  <TableHead className="h-8 w-28">{t("mode")}</TableHead>
                  <TableHead className="h-8 w-24">{t("stepKey")}</TableHead>
                  <TableHead className="h-8">{t("stepLabel")}</TableHead>
                  <TableHead className="h-8">{t("stepLabelKh")}</TableHead>
                  <TableHead className="h-8 w-44">
                    {t("allowedActions")}
                  </TableHead>
                  <TableHead className="h-8 w-14">{t("print")}</TableHead>
                  <TableHead className="h-8 w-24" />
                </TableRow>
              </TableHeader>
              <TableBody>
                {form.steps.map((row, index) => (
                  <TableRow key={row.uid}>
                    <TableCell className="text-center font-mono text-xs tabular-nums text-muted-foreground">
                      {index + 1}
                    </TableCell>
                    <TableCell>
                      <Select
                        value={row.actionMode}
                        items={[
                          { value: "decide", label: t("modeDecide") },
                          { value: "record", label: t("modeRecord") },
                        ]}
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
                          <SelectItem value="decide">
                            {t("modeDecide")}
                          </SelectItem>
                          <SelectItem value="record">
                            {t("modeRecord")}
                          </SelectItem>
                        </SelectContent>
                      </Select>
                    </TableCell>
                    <TableCell>
                      <Input
                        value={row.key}
                        onChange={(e) =>
                          updateStep(index, { key: e.target.value })
                        }
                        placeholder={t("stepKeyPlaceholder")}
                        className="h-8 w-24 font-mono"
                        aria-label={t("stepKey")}
                      />
                    </TableCell>
                    <TableCell>
                      <Input
                        value={row.label}
                        onChange={(e) =>
                          updateStep(index, { label: e.target.value })
                        }
                        placeholder={t("stepLabelPlaceholder")}
                        className="h-8 w-full"
                        aria-label={t("stepLabel")}
                      />
                    </TableCell>
                    <TableCell>
                      <Input
                        value={row.labelKh}
                        onChange={(e) =>
                          updateStep(index, { labelKh: e.target.value })
                        }
                        placeholder={t("stepLabelKhPlaceholder")}
                        className="h-8 w-full"
                        aria-label={t("stepLabelKh")}
                      />
                    </TableCell>
                    <TableCell className="whitespace-normal">
                      {row.actionMode === "decide" ? (
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
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
                      ) : (
                        <span className="text-muted-foreground">—</span>
                      )}
                    </TableCell>
                    <TableCell>
                      <Checkbox
                        checked={row.showOnPrint}
                        onCheckedChange={(checked) =>
                          updateStep(index, { showOnPrint: checked === true })
                        }
                        aria-label={t("showOnPrint")}
                        title={t("showOnPrintHint")}
                      />
                    </TableCell>
                    <TableCell>
                      <div className="flex items-center justify-end">
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
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
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
