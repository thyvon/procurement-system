"use client";

import { useState } from "react";
import { toast } from "sonner";
import { useMutation } from "@tanstack/react-query";
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
import { unwrap, withAuth } from "@/lib/api-client";
import { productsBrandsStore } from "@/lib/api/brand/brand";
import { productsCategoriesStore } from "@/lib/api/product-category/product-category";
import { productsGroupsStore } from "@/lib/api/product-group/product-group";
import { productsUomsStore } from "@/lib/api/uom/uom";

type EntityType = "category" | "brand" | "group" | "uom";

interface EntityQuickAddModalProps {
  entity: EntityType;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSaved: (newId?: string) => void;
}

const TITLES: Record<EntityType, string> = {
  category: "Add Category",
  brand: "Add Brand",
  group: "Add Product Group",
  uom: "Add Unit of Measure",
};

export function EntityQuickAddModal({
  entity,
  open,
  onOpenChange,
  onSaved,
}: EntityQuickAddModalProps) {
  const [code, setCode] = useState("");
  const [name, setName] = useState("");
  const [description, setDescription] = useState("");

  const saveMutation = useMutation({
    mutationFn: async () => {
      if (entity === "category") {
        const res = await productsCategoriesStore(
          { short_code: code.trim() || null, name: name.trim() },
          withAuth()
        );
        return unwrap<{ id: string }>(res);
      }
      if (entity === "brand") {
        const res = await productsBrandsStore(
          { name: name.trim(), description: description.trim() || null },
          withAuth()
        );
        return unwrap<{ id: string }>(res);
      }
      if (entity === "group") {
        const res = await productsGroupsStore(
          { name: name.trim(), description: description.trim() || null },
          withAuth()
        );
        return unwrap<{ id: string }>(res);
      }
      const res = await productsUomsStore({ name: name.trim() }, withAuth());
      return unwrap<{ id: string }>(res);
    },
    onSuccess: (data) => {
      toast.success(`${TITLES[entity].replace("Add ", "")} created.`);
      onOpenChange(false);
      setCode("");
      setName("");
      setDescription("");
      onSaved(data.id);
    },
  });

  const handleSave = () => {
    if (!name.trim()) {
      toast.error("Name is required.");
      return;
    }
    saveMutation.mutate();
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>{TITLES[entity]}</DialogTitle>
        </DialogHeader>
        <div className="space-y-4">
          {entity === "category" && (
            <div className="space-y-2">
              <Label>Code</Label>
              <Input
                value={code}
                onChange={(e) => setCode(e.target.value.toUpperCase())}
                placeholder="Auto-generated if blank"
                className="font-mono"
              />
            </div>
          )}
          <div className="space-y-2">
            <Label>Name</Label>
            <Input
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="Enter name"
            />
          </div>
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
          <Button
            variant="outline"
            onClick={() => onOpenChange(false)}
            disabled={saveMutation.isPending}
          >
            Cancel
          </Button>
          <Button onClick={handleSave} disabled={saveMutation.isPending}>
            {saveMutation.isPending ? "Saving..." : "Save"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
