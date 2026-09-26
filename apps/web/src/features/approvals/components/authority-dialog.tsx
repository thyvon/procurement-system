"use client";

import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/button";
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
import { unwrap, withAuth } from "@/lib/api-client";
import {
  approvalsTocaEntriesStore,
  approvalsTocaEntriesUpdate,
} from "@/lib/api/toca-entry/toca-entry";
import { approvalsSettingsIndex } from "@/lib/api/approval-setting/approval-setting";
import { approvalsFlowsIndex } from "@/lib/api/approval-flow/approval-flow";
import type { StoreTocaEntryRequest } from "@/lib/api/model/storeTocaEntryRequest";
import type { StoreTocaEntryRequestSubjectType } from "@/lib/api/model/storeTocaEntryRequestSubjectType";
import type { UpdateTocaEntryRequest } from "@/lib/api/model/updateTocaEntryRequest";
import type { ApprovalSettingResource } from "@/lib/api/model/approvalSettingResource";
import type { ApprovalFlowRow } from "./flow-dialog";
import { decideStepLabels } from "../approval-types";

export type TocaEntryRow = {
  id: string;
  name: string;
  subjectType: string;
  stepKey: string | null;
  minAmount: string;
  maxAmount: string | null;
  users: { id: number; name: string }[];
};

type TocaForm = {
  name: string;
  subjectType: StoreTocaEntryRequestSubjectType;
  stepKey: string;
  minAmount: string;
  maxAmount: string;
};

const ANY_STEP = "__any__";

const EMPTY_FORM: TocaForm = {
  name: "",
  subjectType: "evaluation",
  stepKey: "",
  minAmount: "0",
  maxAmount: "",
};

function toForm(editing?: TocaEntryRow | null): TocaForm {
  if (!editing) return { ...EMPTY_FORM };
  return {
    name: editing.name,
    subjectType: editing.subjectType as StoreTocaEntryRequestSubjectType,
    stepKey: editing.stepKey ?? "",
    minAmount: editing.minAmount,
    maxAmount: editing.maxAmount ?? "",
  };
}

interface AuthorityDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  editing?: TocaEntryRow | null;
  onSaved?: (id: string) => void;
}

export function AuthorityDialog({
  open,
  onOpenChange,
  editing,
  onSaved,
}: AuthorityDialogProps) {
  const t = useTranslations("approvals.settings.authority");
  const qc = useQueryClient();
  const [form, setForm] = useState<TocaForm>(() => toForm(editing));

  const settingsQuery = useQuery({
    queryKey: ["approvalSettings"],
    queryFn: async () =>
      unwrap<ApprovalSettingResource[]>(
        await approvalsSettingsIndex(withAuth())
      ),
  });

  const flowsQuery = useQuery({
    queryKey: ["approvalFlows"],
    queryFn: async () =>
      unwrap<ApprovalFlowRow[]>(await approvalsFlowsIndex(withAuth())),
  });

  const stepSelectItems = useMemo(() => {
    const labels = decideStepLabels(flowsQuery.data, form.subjectType);
    const items = [{ value: ANY_STEP, label: t("anyStep") }];
    for (const [value, label] of labels) {
      items.push({ value, label });
    }
    if (form.stepKey !== "" && !labels.has(form.stepKey)) {
      items.push({ value: form.stepKey, label: form.stepKey });
    }
    return items;
  }, [flowsQuery.data, form.subjectType, form.stepKey, t]);

  const canSave =
    form.name.trim() !== "" &&
    form.minAmount !== "" &&
    Number(form.minAmount) >= 0 &&
    (form.maxAmount === "" || Number(form.maxAmount) >= Number(form.minAmount));

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload: StoreTocaEntryRequest = {
        name: form.name.trim(),
        subject_type: form.subjectType,
        step_key: form.stepKey === "" ? null : form.stepKey,
        min_amount: Number(form.minAmount),
        max_amount: form.maxAmount === "" ? null : Number(form.maxAmount),
      };
      if (editing) {
        const update: UpdateTocaEntryRequest = payload;
        unwrap(await approvalsTocaEntriesUpdate(editing.id, update, withAuth()));
        return editing.id;
      }
      const created = unwrap<{ id: string }>(
        await approvalsTocaEntriesStore(payload, withAuth())
      );
      return created.id;
    },
    onSuccess: (id) => {
      qc.invalidateQueries({ queryKey: ["tocaEntries"] });
      toast.success(editing ? t("updated") : t("created"));
      onOpenChange(false);
      onSaved?.(id);
    },
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{editing ? t("editTitle") : t("newTitle")}</DialogTitle>
          <DialogDescription>
            {editing ? t("editDescription") : t("newDescription")}
          </DialogDescription>
        </DialogHeader>
        <div className="space-y-4">
          <div className="flex items-center gap-3">
            <Label
              htmlFor="toca-name"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("name")} <RequiredMark />
            </Label>
            <Input
              id="toca-name"
              value={form.name}
              onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
              className="flex-1"
            />
          </div>
          <div className="flex items-center gap-3">
            <Label className="w-28 shrink-0 text-left after:ml-1 after:content-[':']">
              {t("subject")} <RequiredMark />
            </Label>
            <Select
              value={form.subjectType}
              items={(settingsQuery.data ?? []).map((setting) => ({
                value: setting.subjectType,
                label: setting.name,
              }))}
              onValueChange={(value) =>
                setForm((f) => ({
                  ...f,
                  subjectType: value as StoreTocaEntryRequestSubjectType,
                  stepKey: "",
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
          <div className="flex items-center gap-3">
            <Label className="w-28 shrink-0 text-left after:ml-1 after:content-[':']">
              {t("step")}
            </Label>
            <Select
              value={form.stepKey === "" ? ANY_STEP : form.stepKey}
              items={stepSelectItems}
              onValueChange={(value) =>
                setForm((f) => ({
                  ...f,
                  stepKey: value === ANY_STEP ? "" : String(value ?? ""),
                }))
              }
            >
              <SelectTrigger className="h-8 flex-1">
                <SelectValue placeholder={t("anyStep")} />
              </SelectTrigger>
              <SelectContent>
                {stepSelectItems.map((option) => (
                  <SelectItem key={option.value} value={option.value}>
                    {option.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="flex items-center gap-3">
            <Label
              htmlFor="toca-min"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("minAmount")} <RequiredMark />
            </Label>
            <Input
              id="toca-min"
              type="number"
              min={0}
              value={form.minAmount}
              onChange={(e) =>
                setForm((f) => ({ ...f, minAmount: e.target.value }))
              }
              className="flex-1"
            />
          </div>
          <div className="flex items-center gap-3">
            <Label
              htmlFor="toca-max"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("maxAmount")}
            </Label>
            <Input
              id="toca-max"
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
