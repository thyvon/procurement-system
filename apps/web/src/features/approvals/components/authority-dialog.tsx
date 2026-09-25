"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { useTranslations } from "next-intl";
import { Combobox as ComboboxNS } from "@base-ui/react/combobox";
import { Button } from "@/components/ui/button";
import {
  Combobox,
  ComboboxContent,
  ComboboxEmpty,
  ComboboxInput,
  ComboboxItem,
  ComboboxList,
} from "@/components/ui/combobox";
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
import { usersUsersIndex } from "@/lib/api/user/user";
import type { StoreTocaEntryRequest } from "@/lib/api/model/storeTocaEntryRequest";
import type { StoreTocaEntryRequestSubjectType } from "@/lib/api/model/storeTocaEntryRequestSubjectType";
import type { UpdateTocaEntryRequest } from "@/lib/api/model/updateTocaEntryRequest";
import type { ApprovalSettingResource } from "@/lib/api/model/approvalSettingResource";

export type TocaEntryRow = {
  id: string;
  userId: number;
  userName: string;
  subjectType: string;
  minAmount: string;
  maxAmount: string | null;
};

type UserOption = {
  id: number;
  name: string;
};

type TocaForm = {
  userId: string;
  subjectType: StoreTocaEntryRequestSubjectType;
  minAmount: string;
  maxAmount: string;
};

const EMPTY_FORM: TocaForm = {
  userId: "",
  subjectType: "evaluation",
  minAmount: "0",
  maxAmount: "",
};

function toForm(editing?: TocaEntryRow | null): TocaForm {
  if (!editing) return { ...EMPTY_FORM };
  return {
    userId: String(editing.userId),
    subjectType: editing.subjectType as StoreTocaEntryRequestSubjectType,
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

  const usersQuery = useQuery({
    queryKey: ["users"],
    queryFn: async () =>
      unwrap<UserOption[]>(await usersUsersIndex(withAuth())),
  });

  const settingsQuery = useQuery({
    queryKey: ["approvalSettings"],
    queryFn: async () =>
      unwrap<ApprovalSettingResource[]>(
        await approvalsSettingsIndex(withAuth())
      ),
  });

  const userItems = ComboboxNS.createItems(
    (usersQuery.data ?? []).map((user) => ({
      value: String(user.id),
      label: user.name,
    })),
    {
      getValue: (user) => user.value,
      getLabel: (user) => user.label,
    }
  );

  const canSave =
    form.userId !== "" &&
    form.minAmount !== "" &&
    Number(form.minAmount) >= 0 &&
    (form.maxAmount === "" || Number(form.maxAmount) >= Number(form.minAmount));

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload: StoreTocaEntryRequest = {
        user_id: Number(form.userId),
        subject_type: form.subjectType,
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
            <Label className="w-28 shrink-0 text-left after:ml-1 after:content-[':']">
              {t("user")} <RequiredMark />
            </Label>
            <Combobox
              items={userItems}
              value={form.userId || null}
              onValueChange={(next) => {
                if (next === null || next === undefined) return;
                setForm((f) => ({ ...f, userId: String(next) }));
              }}
            >
              <ComboboxInput
                className="h-8 min-w-0 flex-1"
                placeholder={t("userPlaceholder")}
              />
              <ComboboxContent>
                <ComboboxEmpty className="text-xs">{t("noMatch")}</ComboboxEmpty>
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
          <div className="flex items-center gap-3">
            <Label className="w-28 shrink-0 text-left after:ml-1 after:content-[':']">
              {t("subject")} <RequiredMark />
            </Label>
            <Select
              value={form.subjectType}
              onValueChange={(value) =>
                setForm((f) => ({
                  ...f,
                  subjectType: value as StoreTocaEntryRequestSubjectType,
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
