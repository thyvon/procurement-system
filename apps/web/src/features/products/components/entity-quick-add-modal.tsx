"use client";

import { useState } from "react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { unwrap, withAuth } from "../api";
import {
  productsBrandsStore,
} from "@/lib/api/brand/brand";
import {
  productsCategoriesStore,
} from "@/lib/api/product-category/product-category";
import {
  productsGroupsStore,
} from "@/lib/api/product-group/product-group";
import { productsUomsStore } from "@/lib/api/uom/uom";

type EntityType = "category" | "brand" | "group" | "uom";

interface EntityQuickAddModalProps {
  entity: EntityType;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSaved: (newId?: string) => void;
  categories?: Array<{ id: string; name: string; code: string }>;
}

const TITLES: Record<EntityType, { new: string; edit: string }> = {
  category: { new: "Add Category", edit: "Edit Category" },
  brand: { new: "Add Brand", edit: "Edit Brand" },
  group: { new: "Add Product Group", edit: "Edit Product Group" },
  uom: { new: "Add Unit of Measure", edit: "Edit Unit of Measure" },
};

const UOM_TYPES = ["unit", "weight", "volume", "length", "time", "packaging", "other"];

export function EntityQuickAddModal({
  entity,
  open,
  onOpenChange,
  onSaved,
  categories = [],
}: EntityQuickAddModalProps) {
  const [code, setCode] = useState("");
  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [categoryId, setCategoryId] = useState("");
  const [uomType, setUomType] = useState("unit");
  const [saving, setSaving] = useState(false);

  const handleSave = async () => {
    if (!code.trim() || !name.trim()) {
      toast.error("Code and name are required.");
      return;
    }
    if (entity === "group" && !categoryId) {
      toast.error("Category is required for product groups.");
      return;
    }

    setSaving(true);
    try {
      let newId: string | undefined;

      if (entity === "category") {
        const res = await productsCategoriesStore(
          { code: code.trim(), name: name.trim() },
          withAuth()
        );
        const data = unwrap<{ id: string }>(res);
        newId = data.id;
      } else if (entity === "brand") {
        const res = await productsBrandsStore(
          { name: name.trim(), description: description.trim() || undefined },
          withAuth()
        );
        const data = unwrap<{ id: string }>(res);
        newId = data.id;
      } else if (entity === "group") {
        const res = await productsGroupsStore(
          { name: name.trim(), description: description.trim() || undefined },
          withAuth()
        );
        const data = unwrap<{ id: string }>(res);
        newId = data.id;
      } else if (entity === "uom") {
        const res = await productsUomsStore(
          { name: name.trim(), short_name: name.trim().slice(0, 10) },
          withAuth()
        );
        const data = unwrap<{ id: string }>(res);
        newId = data.id;
      }

      toast.success(`${TITLES[entity].new.replace("Add ", "")} created.`);
      onOpenChange(false);
      setCode("");
      setName("");
      setDescription("");
      setCategoryId("");
      setUomType("unit");
      onSaved(newId);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : "Failed to save.";
      toast.error(message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>{TITLES[entity].new}</DialogTitle>
        </DialogHeader>
        <div className="space-y-4">
          <div className="space-y-2">
            <Label>Code</Label>
            <Input
              value={code}
              onChange={(e) => setCode(e.target.value.toUpperCase())}
              placeholder="Auto-generated if blank"
              className="font-mono"
            />
          </div>
          <div className="space-y-2">
            <Label>Name</Label>
            <Input
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="Enter name"
            />
          </div>
          {entity === "group" && (
            <div className="space-y-2">
              <Label>Category</Label>
              <Select
                value={categoryId}
                onChange={(e) => setCategoryId(e.target.value)}
                placeholder="Select category..."
                options={categories.map((c) => ({
                  value: c.id,
                  label: `${c.code} — ${c.name}`,
                }))}
              />
            </div>
          )}
          {entity === "uom" && (
            <div className="space-y-2">
              <Label>Type</Label>
              <Select
                value={uomType}
                onChange={(e) => setUomType(e.target.value)}
                options={UOM_TYPES.map((t) => ({
                  value: t,
                  label: t.charAt(0).toUpperCase() + t.slice(1),
                }))}
              />
            </div>
          )}
          {(entity === "category" || entity === "brand" || entity === "group") && (
            <div className="space-y-2">
              <Label>Description</Label>
              <Input
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="Optional description"
              />
            </div>
          )}
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)} disabled={saving}>
            Cancel
          </Button>
          <Button onClick={handleSave} disabled={saving}>
            {saving ? "Saving..." : "Save"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
