"use client";

import { useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
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
import { unwrap, withAuth } from "@/lib/api-client";
import { productsUomsUpdate } from "@/lib/api/uom/uom";
import type { ProductsUomsUpdate200Data } from "@/lib/api/model/productsUomsUpdate200Data";

type ExistingSubUnit = {
  id: string;
  name: string;
  shortName: string;
  conversionFactor: number;
};

export type SubUnitUomRef = {
  id: string;
  name: string;
  shortName: string;
  subUnits?: ExistingSubUnit[];
};

type SubUnitForm = {
  name: string;
  shortName: string;
  conversionFactor: string;
};

const EMPTY_FORM: SubUnitForm = {
  name: "",
  shortName: "",
  conversionFactor: "",
};

interface SubUnitDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  uom: SubUnitUomRef;
  onSaved?: (id: string) => void;
}

export function SubUnitDialog({
  open,
  onOpenChange,
  uom,
  onSaved,
}: SubUnitDialogProps) {
  const t = useTranslations("products.uoms");
  const qc = useQueryClient();
  const [form, setForm] = useState<SubUnitForm>({ ...EMPTY_FORM });

  const canSave =
    form.name.trim().length > 0 &&
    form.shortName.trim().length > 0 &&
    Number(form.conversionFactor) > 0;

  const saveMutation = useMutation({
    mutationFn: async () => {
      const existing = (uom.subUnits ?? []).map((s) => ({
        id: s.id,
        name: s.name,
        short_name: s.shortName,
        conversion_factor: s.conversionFactor,
      }));
      const existingIds = new Set(existing.map((s) => s.id));
      const updated = unwrap<ProductsUomsUpdate200Data>(
        await productsUomsUpdate(
          uom.id,
          {
            sub_units: [
              ...existing,
              {
                name: form.name.trim(),
                short_name: form.shortName.trim(),
                conversion_factor: Number(form.conversionFactor),
              },
            ],
          },
          withAuth()
        )
      );
      const created = (updated.subUnits ?? []).find((s) => !existingIds.has(s.id));
      return created?.id ?? "";
    },
    onSuccess: (id) => {
      qc.invalidateQueries({ queryKey: ["uoms"] });
      toast.success(t("subUnitCreated"));
      onOpenChange(false);
      if (id) onSaved?.(id);
    },
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{t("newSubUnitTitle")}</DialogTitle>
          <DialogDescription>
            {t("newSubUnitDescription", { name: uom.name })}
          </DialogDescription>
        </DialogHeader>
        <div className="space-y-4">
          <div className="flex items-center gap-3">
            <Label
              htmlFor="sub-unit-name"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("subUnitName")} <RequiredMark />
            </Label>
            <Input
              id="sub-unit-name"
              value={form.name}
              onChange={(e) =>
                setForm((f) => ({ ...f, name: e.target.value }))
              }
              placeholder={t("subUnitNamePlaceholder")}
              className="flex-1"
            />
          </div>
          <div className="flex items-center gap-3">
            <Label
              htmlFor="sub-unit-short-name"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("subUnitShortName")} <RequiredMark />
            </Label>
            <Input
              id="sub-unit-short-name"
              value={form.shortName}
              onChange={(e) =>
                setForm((f) => ({ ...f, shortName: e.target.value }))
              }
              placeholder={t("subUnitShortNamePlaceholder")}
              maxLength={16}
              className="flex-1"
            />
          </div>
          <div className="flex items-center gap-3">
            <Label
              htmlFor="sub-unit-factor"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("conversionFactor")} <RequiredMark />
            </Label>
            <Input
              id="sub-unit-factor"
              type="number"
              min="0"
              step="any"
              value={form.conversionFactor}
              onChange={(e) =>
                setForm((f) => ({
                  ...f,
                  conversionFactor: e.target.value,
                }))
              }
              placeholder={t("conversionFactorPlaceholder")}
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
