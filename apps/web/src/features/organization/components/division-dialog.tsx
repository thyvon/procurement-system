"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { useTranslations } from "next-intl";
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
import { Switch } from "@/components/ui/switch";
import { unwrap, withAuth } from "@/lib/api-client";
import {
  organizationDepartmentsIndex,
} from "@/lib/api/department/department";
import {
  organizationDivisionsStore,
  organizationDivisionsUpdate,
} from "@/lib/api/division/division";

export type Division = {
  id: string;
  name: string;
  isActive: boolean;
  departmentIds?: string[];
};

type DepartmentOption = {
  id: string;
  name: string;
};

type DivisionForm = {
  name: string;
  isActive: boolean;
  departmentIds: string[];
};

const EMPTY_FORM: DivisionForm = {
  name: "",
  isActive: true,
  departmentIds: [],
};

function toForm(editing?: Division | null): DivisionForm {
  if (!editing) return { ...EMPTY_FORM, departmentIds: [] };
  return {
    name: editing.name,
    isActive: editing.isActive,
    departmentIds: [...(editing.departmentIds ?? [])],
  };
}

interface DivisionDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  editing?: Division | null;
  onSaved?: (id: string) => void;
}

export function DivisionDialog({
  open,
  onOpenChange,
  editing,
  onSaved,
}: DivisionDialogProps) {
  const t = useTranslations("organization.divisions");
  const qc = useQueryClient();
  const [form, setForm] = useState<DivisionForm>(() => toForm(editing));

  const departmentsQuery = useQuery({
    queryKey: ["departments"],
    queryFn: async () =>
      unwrap<DepartmentOption[]>(
        await organizationDepartmentsIndex(withAuth())
      ),
  });

  const canSave = form.name.trim().length > 0;

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        name: form.name,
        is_active: form.isActive,
      };
      if (editing) {
        unwrap(
          await organizationDivisionsUpdate(editing.id, payload, withAuth())
        );
        return editing.id;
      }
      const created = unwrap<{ id: string }>(
        await organizationDivisionsStore(payload, withAuth())
      );
      return created.id;
    },
    onSuccess: (id) => {
      qc.invalidateQueries({ queryKey: ["divisions"] });
      toast.success(editing ? t("updated") : t("created"));
      onOpenChange(false);
      onSaved?.(id);
    },
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{editing ? t("editTitle") : t("newTitle")}</DialogTitle>
          <DialogDescription>
            {editing ? t("editDescription") : t("newDescription")}
          </DialogDescription>
        </DialogHeader>
        <div className="space-y-4">
          <div className="flex items-center gap-3">
            <Label
              htmlFor="division-name"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("name")} <RequiredMark />
            </Label>
            <Input
              id="division-name"
              value={form.name}
              onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
              placeholder={t("namePlaceholder")}
              maxLength={64}
              className="flex-1"
            />
          </div>
          <div className="flex items-center gap-3">
            <Label
              htmlFor="division-active"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("active")}
            </Label>
            <Switch
              id="division-active"
              checked={form.isActive}
              onCheckedChange={(checked) =>
                setForm((f) => ({ ...f, isActive: checked }))
              }
            />
          </div>
          <div className="flex items-center gap-3">
            <span className="w-28 shrink-0 text-left text-sm font-medium leading-none after:ml-1 after:content-[':']">
              {t("departments")}
            </span>
            <div className="flex flex-1 flex-wrap items-center gap-x-4 gap-y-2">
              {(departmentsQuery.data ?? []).map((dept) => (
                <label
                  key={dept.id}
                  className="flex items-center gap-2 text-sm leading-none text-muted-foreground"
                >
                  <Checkbox
                    checked={form.departmentIds.includes(dept.id)}
                    disabled
                    aria-label={dept.name}
                  />
                  {dept.name}
                </label>
              ))}
              {departmentsQuery.isPending && (
                <span className="text-xs text-muted-foreground">
                  {t("loadingDepartments")}
                </span>
              )}
              {!departmentsQuery.isPending &&
                (departmentsQuery.data ?? []).length === 0 && (
                  <span className="text-xs text-muted-foreground">
                    {t("noDepartments")}
                  </span>
                )}
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
