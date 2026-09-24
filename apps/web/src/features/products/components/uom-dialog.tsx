"use client";

import { useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { useTranslations } from "next-intl";
import { Plus, X } from "lucide-react";
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
import { Switch } from "@/components/ui/switch";
import { unwrap, withAuth } from "@/lib/api-client";
import { productsUomsStore, productsUomsUpdate } from "@/lib/api/uom/uom";

type SubUnit = {
  id: string;
  name: string;
  shortName: string;
  conversionFactor: number;
};

export type Uom = {
  id: string;
  code: string;
  name: string;
  shortName: string;
  isActive: boolean;
  subUnits?: SubUnit[];
};

type SubUnitFormRow = {
  id?: string;
  name: string;
  shortName: string;
  conversionFactor: string;
};

type UomForm = {
  name: string;
  shortName: string;
  isActive: boolean;
  subUnits: SubUnitFormRow[];
};

const EMPTY_SUB_UNIT: SubUnitFormRow = {
  name: "",
  shortName: "",
  conversionFactor: "",
};

const EMPTY_FORM: UomForm = {
  name: "",
  shortName: "",
  isActive: true,
  subUnits: [],
};

function toForm(editing?: Uom | null): UomForm {
  if (!editing) return { ...EMPTY_FORM, subUnits: [] };
  return {
    name: editing.name,
    shortName: editing.shortName,
    isActive: editing.isActive,
    subUnits: (editing.subUnits ?? []).map((s) => ({
      id: s.id,
      name: s.name,
      shortName: s.shortName,
      conversionFactor: String(s.conversionFactor),
    })),
  };
}

function isSubUnitRowComplete(row: SubUnitFormRow): boolean {
  return (
    row.name.trim().length > 0 &&
    row.shortName.trim().length > 0 &&
    Number(row.conversionFactor) > 0
  );
}

function isSubUnitRowPartial(row: SubUnitFormRow): boolean {
  const anyFilled =
    row.name.trim().length > 0 ||
    row.shortName.trim().length > 0 ||
    row.conversionFactor.trim().length > 0;
  return anyFilled && !isSubUnitRowComplete(row);
}

interface UomDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  editing?: Uom | null;
  onSaved?: (id: string) => void;
}

export function UomDialog({
  open,
  onOpenChange,
  editing,
  onSaved,
}: UomDialogProps) {
  const t = useTranslations("products.uoms");
  const qc = useQueryClient();
  const [form, setForm] = useState<UomForm>(() => toForm(editing));

  const hasInvalidSubUnit = form.subUnits.some(isSubUnitRowPartial);
  const canSave = form.name.trim().length > 0 && !hasInvalidSubUnit;

  const updateSubUnitRow = (index: number, patch: Partial<SubUnitFormRow>) => {
    setForm((f) => ({
      ...f,
      subUnits: f.subUnits.map((row, i) =>
        i === index ? { ...row, ...patch } : row
      ),
    }));
  };

  const saveMutation = useMutation({
    mutationFn: async () => {
      const shortName = form.shortName.trim();
      const subUnits = form.subUnits.filter(isSubUnitRowComplete).map((row) => ({
        ...(row.id ? { id: row.id } : {}),
        name: row.name.trim(),
        short_name: row.shortName.trim(),
        conversion_factor: Number(row.conversionFactor),
      }));
      const payload = {
        name: form.name,
        ...(shortName ? { short_name: shortName } : {}),
        is_active: form.isActive,
        sub_units: subUnits,
      };
      if (editing) {
        unwrap(await productsUomsUpdate(editing.id, payload, withAuth()));
        return editing.id;
      }
      const created = unwrap<{ id: string }>(
        await productsUomsStore(payload, withAuth())
      );
      return created.id;
    },
    onSuccess: (id) => {
      qc.invalidateQueries({ queryKey: ["uoms"] });
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
              htmlFor="uom-name"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("name")} <RequiredMark />
            </Label>
            <Input
              id="uom-name"
              value={form.name}
              onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
              placeholder={t("namePlaceholder")}
              className="flex-1"
            />
          </div>
          <div className="flex items-center gap-3">
            <Label
              htmlFor="uom-short-name"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("shortName")}
            </Label>
            <Input
              id="uom-short-name"
              value={form.shortName}
              onChange={(e) =>
                setForm((f) => ({ ...f, shortName: e.target.value }))
              }
              placeholder={t("shortNamePlaceholder")}
              maxLength={16}
              className="flex-1"
            />
          </div>
          <div className="flex items-center gap-3">
            <Label
              htmlFor="uom-active"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("active")}
            </Label>
            <Switch
              id="uom-active"
              checked={form.isActive}
              onCheckedChange={(checked) =>
                setForm((f) => ({ ...f, isActive: checked }))
              }
            />
          </div>

          <div className="space-y-2">
            <div className="flex items-center justify-between">
              <Label>{t("subUnits")}</Label>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() =>
                  setForm((f) => ({
                    ...f,
                    subUnits: [...f.subUnits, { ...EMPTY_SUB_UNIT }],
                  }))
                }
              >
                <Plus className="mr-1 size-3" />
                {t("addSubUnit")}
              </Button>
            </div>
            {form.subUnits.length === 0 && (
              <p className="text-xs text-muted-foreground">
                {t("subUnitsEmpty")}
              </p>
            )}
            {form.subUnits.map((row, index) => (
              <div
                key={row.id ?? `new-${index}`}
                className="space-y-2 rounded-md border p-3"
              >
                <div className="flex items-start gap-2">
                  <div className="flex-1 space-y-1">
                    <Label htmlFor={`sub-unit-name-${index}`} className="text-xs">
                      {t("subUnitName")} <RequiredMark />
                    </Label>
                    <Input
                      id={`sub-unit-name-${index}`}
                      value={row.name}
                      onChange={(e) =>
                        updateSubUnitRow(index, { name: e.target.value })
                      }
                      placeholder={t("subUnitNamePlaceholder")}
                      className="h-8"
                    />
                  </div>
                  <div className="w-28 space-y-1">
                    <Label
                      htmlFor={`sub-unit-short-${index}`}
                      className="text-xs"
                    >
                      {t("subUnitShortName")} <RequiredMark />
                    </Label>
                    <Input
                      id={`sub-unit-short-${index}`}
                      value={row.shortName}
                      onChange={(e) =>
                        updateSubUnitRow(index, { shortName: e.target.value })
                      }
                      placeholder={t("subUnitShortNamePlaceholder")}
                      maxLength={16}
                      className="h-8"
                    />
                  </div>
                  <div className="w-24 space-y-1">
                    <Label
                      htmlFor={`sub-unit-factor-${index}`}
                      className="text-xs"
                    >
                      {t("conversionFactor")} <RequiredMark />
                    </Label>
                    <Input
                      id={`sub-unit-factor-${index}`}
                      type="number"
                      min="0"
                      step="any"
                      value={row.conversionFactor}
                      onChange={(e) =>
                        updateSubUnitRow(index, {
                          conversionFactor: e.target.value,
                        })
                      }
                      placeholder={t("conversionFactorPlaceholder")}
                      className="h-8"
                    />
                  </div>
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="mt-5 size-8 shrink-0 text-muted-foreground hover:text-destructive"
                    aria-label={t("removeSubUnit")}
                    onClick={() =>
                      setForm((f) => ({
                        ...f,
                        subUnits: f.subUnits.filter((_, i) => i !== index),
                      }))
                    }
                  >
                    <X className="size-4" />
                  </Button>
                </div>
                {isSubUnitRowPartial(row) && (
                  <p className="text-xs text-destructive">
                    {t("subUnitIncomplete")}
                  </p>
                )}
              </div>
            ))}
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
