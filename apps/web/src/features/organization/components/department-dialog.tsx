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
import { organizationDivisionsIndex } from "@/lib/api/division/division";
import {
  organizationDepartmentsStore,
  organizationDepartmentsUpdate,
} from "@/lib/api/department/department";

export type Department = {
  id: string;
  name: string;
  isActive: boolean;
  divisionIds?: string[];
};

type DivisionOption = {
  id: string;
  name: string;
};

type DepartmentForm = {
  name: string;
  isActive: boolean;
  divisionIds: string[];
};

const EMPTY_FORM: DepartmentForm = {
  name: "",
  isActive: true,
  divisionIds: [],
};

function toForm(editing?: Department | null): DepartmentForm {
  if (!editing) return { ...EMPTY_FORM, divisionIds: [] };
  return {
    name: editing.name,
    isActive: editing.isActive,
    divisionIds: [...(editing.divisionIds ?? [])],
  };
}

interface DepartmentDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  editing?: Department | null;
  onSaved?: (id: string) => void;
}

export function DepartmentDialog({
  open,
  onOpenChange,
  editing,
  onSaved,
}: DepartmentDialogProps) {
  const t = useTranslations("organization.departments");
  const qc = useQueryClient();
  const [form, setForm] = useState<DepartmentForm>(() => toForm(editing));

  const divisionsQuery = useQuery({
    queryKey: ["divisions"],
    queryFn: async () =>
      unwrap<DivisionOption[]>(await organizationDivisionsIndex(withAuth())),
  });

  const toggleDivision = (id: string) => {
    setForm((f) => ({
      ...f,
      divisionIds: f.divisionIds.includes(id)
        ? f.divisionIds.filter((d) => d !== id)
        : [...f.divisionIds, id],
    }));
  };

  // UX-only gate (divisions required) — the API 422 remains the source of truth.
  const canSave = form.name.trim().length > 0 && form.divisionIds.length > 0;

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        name: form.name,
        is_active: form.isActive,
        division_ids: form.divisionIds,
      };
      if (editing) {
        unwrap(
          await organizationDepartmentsUpdate(editing.id, payload, withAuth())
        );
        return editing.id;
      }
      const created = unwrap<{ id: string }>(
        await organizationDepartmentsStore(payload, withAuth())
      );
      return created.id;
    },
    onSuccess: (id) => {
      qc.invalidateQueries({ queryKey: ["departments"] });
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
            <span className="w-28 shrink-0 text-left text-sm font-medium leading-none after:ml-1 after:content-[':']">
              {t("divisions")} <RequiredMark />
            </span>
            <div className="flex flex-1 flex-wrap items-center gap-x-4 gap-y-2">
              {(divisionsQuery.data ?? []).map((div) => (
                <label
                  key={div.id}
                  className="flex items-center gap-2 text-sm leading-none"
                >
                  <Checkbox
                    checked={form.divisionIds.includes(div.id)}
                    onCheckedChange={() => toggleDivision(div.id)}
                    aria-label={div.name}
                  />
                  {div.name}
                </label>
              ))}
              {divisionsQuery.isPending && (
                <span className="text-xs text-muted-foreground">
                  {t("loadingDivisions")}
                </span>
              )}
              {!divisionsQuery.isPending &&
                (divisionsQuery.data ?? []).length === 0 && (
                  <span className="text-xs text-muted-foreground">
                    {t("noDivisions")}
                  </span>
                )}
            </div>
          </div>
          <div className="flex items-center gap-3">
            <Label
              htmlFor="department-name"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("name")} <RequiredMark />
            </Label>
            <Input
              id="department-name"
              value={form.name}
              onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
              placeholder={t("namePlaceholder")}
              maxLength={64}
              className="flex-1"
            />
          </div>
          <div className="flex items-center gap-3">
            <Label
              htmlFor="department-active"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("active")}
            </Label>
            <Switch
              id="department-active"
              checked={form.isActive}
              onCheckedChange={(checked) =>
                setForm((f) => ({ ...f, isActive: checked }))
              }
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
