"use client";

import { useState } from "react";
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
import { Switch } from "@/components/ui/switch";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { unwrap, withAuth } from "@/lib/api-client";
import {
  productsCategoriesIndex,
  productsCategoriesStore,
  productsCategoriesUpdate,
} from "@/lib/api/product-category/product-category";

export type Category = {
  id: string;
  parentId: string | null;
  code: string;
  shortCode: string | null;
  name: string;
  nameKm: string | null;
  sortOrder: number;
  isActive: boolean;
};

type CategoryForm = {
  name: string;
  shortCode: string;
  nameKm: string;
  sortOrder: number;
  isActive: boolean;
  parentId: string | null;
};

const EMPTY_FORM: CategoryForm = {
  name: "",
  shortCode: "",
  nameKm: "",
  sortOrder: 0,
  isActive: true,
  parentId: null,
};

function toForm(editing?: Category | null, parentId?: string | null): CategoryForm {
  if (!editing) return { ...EMPTY_FORM, parentId: parentId ?? null };
  return {
    name: editing.name,
    shortCode: editing.shortCode ?? "",
    nameKm: editing.nameKm ?? "",
    sortOrder: editing.sortOrder,
    isActive: editing.isActive,
    parentId: editing.parentId,
  };
}

interface CategoryDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  editing?: Category | null;
  parentId?: string | null;
  onSaved?: (id: string) => void;
}

export function CategoryDialog({
  open,
  onOpenChange,
  editing,
  parentId,
  onSaved,
}: CategoryDialogProps) {
  const t = useTranslations("products.categories");
  const qc = useQueryClient();
  const lockParent = !editing && parentId != null;
  const [form, setForm] = useState<CategoryForm>(() => toForm(editing, parentId));

  const categoriesQuery = useQuery({
    queryKey: ["categories"],
    queryFn: async () =>
      unwrap<Category[]>(await productsCategoriesIndex(withAuth())),
  });

  // While editing, hide the category itself and its descendants as parent
  // options — re-parenting onto a child would form a cycle.
  const excludedParents = (() => {
    if (!editing) return new Set<string>();
    const childrenOf = new Map<string | null, string[]>();
    for (const cat of categoriesQuery.data ?? []) {
      childrenOf.set(cat.parentId, [
        ...(childrenOf.get(cat.parentId) ?? []),
        cat.id,
      ]);
    }
    const excluded = new Set([editing.id]);
    const stack = [editing.id];
    while (stack.length > 0) {
      for (const childId of childrenOf.get(stack.pop()!) ?? []) {
        if (!excluded.has(childId)) {
          excluded.add(childId);
          stack.push(childId);
        }
      }
    }
    return excluded;
  })();

  const parentOptions = (categoriesQuery.data ?? []).filter(
    (c) => !excludedParents.has(c.id)
  );

  const saveMutation = useMutation({
    mutationFn: async () => {
      const shortCode = form.shortCode.trim();
      const payload = {
        name: form.name,
        parent_id: form.parentId,
        name_km: form.nameKm || undefined,
        sort_order: form.sortOrder,
        is_active: form.isActive,
        ...(shortCode ? { short_code: shortCode } : {}),
      };
      if (editing) {
        unwrap(
          await productsCategoriesUpdate(editing.id, payload, withAuth())
        );
        return editing.id;
      }
      const created = unwrap<{ id: string }>(
        await productsCategoriesStore(payload, withAuth())
      );
      return created.id;
    },
    onSuccess: (id) => {
      qc.invalidateQueries({ queryKey: ["categories"] });
      toast.success(editing ? t("updated") : t("created"));
      onOpenChange(false);
      onSaved?.(id);
    },
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>
            {editing
              ? t("editTitle")
              : lockParent
                ? t("newSubTitle")
                : t("newTitle")}
          </DialogTitle>
          <DialogDescription>
            {editing
              ? t("editDescription")
              : lockParent
                ? t("newSubDescription")
                : t("newDescription")}
          </DialogDescription>
        </DialogHeader>
        <div className="space-y-4">
          <div className="flex items-center gap-3">
            <Label
              htmlFor="cat-name"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("name")} <RequiredMark />
            </Label>
            <Input
              id="cat-name"
              value={form.name}
              onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
              placeholder={t("namePlaceholder")}
              className="flex-1"
            />
          </div>
          <div className="flex items-center gap-3">
            <Label
              htmlFor="cat-name-km"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("nameKm")}
            </Label>
            <Input
              id="cat-name-km"
              value={form.nameKm}
              onChange={(e) =>
                setForm((f) => ({ ...f, nameKm: e.target.value }))
              }
              placeholder={t("nameKmPlaceholder")}
              className="flex-1"
            />
          </div>
          {!lockParent && (
            <div className="flex items-center gap-3">
              <Label className="w-28 shrink-0 text-left after:ml-1 after:content-[':']">
                {t("parent")}
              </Label>
              <Select
                value={form.parentId}
                onValueChange={(v) =>
                  setForm((f) => ({ ...f, parentId: v ?? null }))
                }
                items={[
                  { value: null, label: t("rootOption") },
                  ...parentOptions.map((c) => ({
                    value: c.id,
                    label: `${c.code} — ${c.name}`,
                  })),
                ]}
              >
                <SelectTrigger className="min-w-0 flex-1">
                  <SelectValue
                    className="min-w-0"
                    placeholder={t("rootOption")}
                  />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={null}>{t("rootOption")}</SelectItem>
                  {parentOptions.map((c) => (
                    <SelectItem key={c.id} value={c.id}>
                      {c.code} — {c.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}
          <div className="flex items-center gap-3">
            <Label
              htmlFor="cat-short-code"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("shortCode")}
            </Label>
            <Input
              id="cat-short-code"
              value={form.shortCode}
              onChange={(e) =>
                setForm((f) => ({ ...f, shortCode: e.target.value.toUpperCase() }))
              }
              placeholder={t("shortCodePlaceholder")}
              className="flex-1 font-mono"
            />
          </div>
          <div className="flex items-center gap-3">
            <Label
              htmlFor="cat-sort"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("sortOrder")}
            </Label>
            <Input
              id="cat-sort"
              type="number"
              min={0}
              value={form.sortOrder}
              onChange={(e) =>
                setForm((f) => ({ ...f, sortOrder: Number(e.target.value) }))
              }
              className="flex-1"
            />
          </div>
          <div className="flex items-center gap-3">
            <Label
              htmlFor="cat-active"
              className="w-28 shrink-0 text-left after:ml-1 after:content-[':']"
            >
              {t("active")}
            </Label>
            <Switch
              id="cat-active"
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
            disabled={saveMutation.isPending || !form.name.trim()}
          >
            {saveMutation.isPending ? t("saving") : t("save")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
