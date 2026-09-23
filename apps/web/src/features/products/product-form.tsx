"use client";

import { useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { toast } from "sonner";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowLeft, Save, Sparkles, Plus } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { Field, FieldLabel } from "@/components/ui/field";
import {
  Combobox,
  ComboboxContent,
  ComboboxEmpty,
  ComboboxInput,
  ComboboxItem,
  ComboboxList,
} from "@/components/ui/combobox";
import { Combobox as ComboboxNS } from "@base-ui/react/combobox";
import { unwrap, withAuth } from "./api";
import {
  productsItemsStore,
  productsItemsUpdate,
} from "@/lib/api/product/product";
import {
  productsCategoriesIndex,
} from "@/lib/api/product-category/product-category";
import {
  productsGroupsIndex,
} from "@/lib/api/product-group/product-group";
import { productsBrandsIndex } from "@/lib/api/brand/brand";
import { productsUomsIndex } from "@/lib/api/uom/uom";
import {
  productsVariationTemplatesIndex,
} from "@/lib/api/variation/variation";
import { VariantMatrix, type VariantRow } from "./components/variant-matrix";
import { EntityQuickAddModal } from "./components/entity-quick-add-modal";

type ProductType = "single" | "variation";

const PRODUCT_TYPES: { value: ProductType; label: string }[] = [
  { value: "single", label: "Single Product" },
  { value: "variation", label: "Variation" },
];

const autoCode = (name: string): string => {
  const letters = (name.match(/[a-z0-9]/gi) || []).join("").slice(0, 6).toUpperCase() || "PRD";
  const chars = "ABCDEFGHJKLMNPQRSTUVWXYZ123456789";
  let suffix = "";
  for (let i = 0; i < 6; i++) suffix += chars[Math.floor(Math.random() * chars.length)];
  return `${letters}-${suffix}`;
};

interface ProductFormProps {
  mode: "create" | "edit";
  initialData?: {
    id: string;
    code: string;
    name: string;
    name_km: string | null;
    description: string | null;
    product_type: string;
    product_category_id: string | null;
    product_group_id: string | null;
    brand_id: string | null;
    uom_id: string | null;
    sub_unit_id: string | null;
    purchase_price: string | null;
    is_active: boolean;
  };
}

export function ProductForm({ mode, initialData }: ProductFormProps) {
  const router = useRouter();
  const qc = useQueryClient();
  const isEdit = mode === "edit";

  const [code, setCode] = useState(initialData?.code ?? "");
  const [name, setName] = useState(initialData?.name ?? "");
  const [nameKm, setNameKm] = useState(initialData?.name_km ?? "");
  const [description, setDescription] = useState(initialData?.description ?? "");
  const [productType, setProductType] = useState<ProductType>(
    (initialData?.product_type as ProductType) ?? "single"
  );
  const [categoryId, setCategoryId] = useState(initialData?.product_category_id ?? "");
  const [groupId, setGroupId] = useState(initialData?.product_group_id ?? "");
  const [brandId, setBrandId] = useState(initialData?.brand_id ?? "");
  const [uomId, setUomId] = useState(initialData?.uom_id ?? "");
  const [subUnitId, setSubUnitId] = useState(initialData?.sub_unit_id ?? "");
  const [isActive, setIsActive] = useState(initialData?.is_active ?? true);
  const [templateIds, setTemplateIds] = useState<string[]>([]);
  const [variantRows, setVariantRows] = useState<VariantRow[]>([]);

  const [quickAddEntity, setQuickAddEntity] = useState<"category" | "brand" | "group" | "uom" | null>(null);

  const { data: categoriesData } = useQuery({
    queryKey: ["categories"],
    queryFn: async () => unwrap<Array<{ id: string; name: string; code: string; parent_id: string | null }>>(
      await productsCategoriesIndex(withAuth())
    ),
  });

  const { data: groupsData } = useQuery({
    queryKey: ["groups"],
    queryFn: async () => unwrap<Array<{ id: string; name: string; category_id: string | null }>>(
      await productsGroupsIndex(withAuth())
    ),
  });

  const { data: brandsData } = useQuery({
    queryKey: ["brands"],
    queryFn: async () => unwrap<Array<{ id: string; name: string }>>(
      await productsBrandsIndex(withAuth())
    ),
  });

  const { data: uomsData } = useQuery({
    queryKey: ["uoms"],
    queryFn: async () => unwrap<Array<{ id: string; name: string; shortName: string; subUnits?: Array<{ id: string; name: string; shortName: string; conversionFactor: number }> }>>(
      await productsUomsIndex(withAuth())
    ),
  });

  const { data: templatesData } = useQuery({
    queryKey: ["variation-templates"],
    queryFn: async () => unwrap<Array<{ id: string; name: string; options?: Array<{ id: string; value: string }> }>>(
      await productsVariationTemplatesIndex(withAuth())
    ),
    enabled: productType === "variation",
  });

  const categories = useMemo(() => categoriesData ?? [], [categoriesData]);
  const groups = useMemo(() => groupsData ?? [], [groupsData]);
  const brands = useMemo(() => brandsData ?? [], [brandsData]);
  const uoms = useMemo(() => uomsData ?? [], [uomsData]);
  const templates = useMemo(() => templatesData ?? [], [templatesData]);

  const parentCategories = useMemo(
    () => categories.filter((c) => !c.parent_id),
    [categories]
  );

  const subCategories = useMemo(
    () => categories.filter((c) => c.parent_id === categoryId),
    [categories, categoryId]
  );

  const filteredGroups = useMemo(
    () => groups.filter((g) => g.category_id === (subCategories.length > 0 ? subCategories[0]?.id : categoryId)),
    [groups, categoryId, subCategories]
  );

  const selectedUom = useMemo(() => uoms.find((u) => u.id === uomId), [uoms, uomId]);

  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ["products"] });
  };

  const saveMutation = useMutation({
    mutationFn: async () => {
      const finalCode = code.trim() || autoCode(name.trim());

      const payload = {
        code: finalCode,
        name: name.trim(),
        name_km: nameKm.trim() || undefined,
        description: description.trim() || undefined,
        product_type: productType,
        category_id: categoryId || undefined,
        group_id: groupId || undefined,
        brand_id: brandId || undefined,
        uom_id: uomId || undefined,
        sub_unit_id: subUnitId || undefined,
        is_active: isActive,
      };

      if (isEdit && initialData?.id) {
        return productsItemsUpdate(initialData.id, payload as never, withAuth());
      }
      return productsItemsStore(payload as never, withAuth());
    },
    onSuccess: () => {
      toast.success(isEdit ? "Product updated." : "Product created.");
      invalidate();
      router.push("/products");
    },
    onError: (err: Error) => {
      toast.error(err.message || "Failed to save product.");
    },
  });

  const handleSave = () => {
    if (!name.trim()) {
      toast.error("Product name is required.");
      return;
    }
    saveMutation.mutate();
  };

  const handleQuickAddSaved = (entity: string, newId?: string) => {
    if (entity === "category" && newId) setCategoryId(newId);
    if (entity === "group" && newId) setGroupId(newId);
    if (entity === "brand" && newId) setBrandId(newId);
    if (entity === "uom" && newId) setUomId(newId);
    qc.invalidateQueries({ queryKey: [entity === "group" ? "groups" : `${entity}s`] });
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Button
            variant="outline"
            size="icon"
            onClick={() => router.push("/products")}
            aria-label="Back to products"
          >
            <ArrowLeft />
          </Button>
          <div>
            <h1 className="text-xl font-semibold tracking-tight">
              {isEdit ? "Edit Product" : "Add Product"}
            </h1>
            <p className="text-sm text-muted-foreground">
              {isEdit ? "Update the product details." : "Register a new product."}
            </p>
          </div>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Product Form</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 gap-8 lg:grid-cols-[1.3fr_1fr]">
            <div>
              <Label className="text-sm font-medium">Product Description</Label>
              <div className="mt-2 space-y-4">
                <Field orientation="vertical">
                  <FieldLabel>Product Code</FieldLabel>
                  <div className="flex gap-1.5">
                    <div className="flex-1">
                      <Input
                        value={code}
                        onChange={(e) => setCode(e.target.value.toUpperCase())}
                        placeholder="Auto-generated if blank"
                        className="font-mono"
                      />
                    </div>
                    <Button
                      type="button"
                      variant="outline"
                      size="icon"
                      className="shrink-0"
                      onClick={() => name.trim() && setCode(autoCode(name.trim()))}
                      disabled={!name.trim()}
                      aria-label="Generate code from name"
                    >
                      <Sparkles className="size-4" />
                    </Button>
                  </div>
                </Field>
                <Field orientation="vertical">
                  <FieldLabel>Name (KH)</FieldLabel>
                  <Textarea
                    value={nameKm}
                    onChange={(e) => setNameKm(e.target.value)}
                    placeholder="Khmer name..."
                    rows={3}
                  />
                </Field>
                <Field orientation="vertical">
                  <FieldLabel>Name (EN)</FieldLabel>
                  <Textarea
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    placeholder="English name..."
                    rows={3}
                  />
                </Field>
                <Field orientation="vertical">
                  <FieldLabel>Description</FieldLabel>
                  <Textarea
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                    rows={3}
                    placeholder="Additional description..."
                  />
                </Field>
              </div>
            </div>

            <div>
              <Label className="text-sm font-medium">Catalog Setup</Label>
              <div className="mt-2 space-y-4">
                <Field orientation="vertical">
                  <FieldLabel>Product Type</FieldLabel>
                  <Combobox
                    items={ComboboxNS.createItems(PRODUCT_TYPES, {
                      getValue: (t) => t.value,
                      getLabel: (t) => t.label,
                    })}
                    value={productType}
                    onValueChange={(val) => {
                      setProductType(val as ProductType);
                      if (val !== "variation") setTemplateIds([]);
                    }}
                  >
                    <ComboboxInput placeholder="Select type..." />
                    <ComboboxContent>
                      <ComboboxEmpty>No types found.</ComboboxEmpty>
                      <ComboboxList>
                        {(t) => (
                          <ComboboxItem key={t.value} value={t.value}>
                            {t.label}
                          </ComboboxItem>
                        )}
                      </ComboboxList>
                    </ComboboxContent>
                  </Combobox>
                </Field>
                <Field orientation="vertical">
                  <FieldLabel>Category</FieldLabel>
                  <div className="flex gap-1.5">
                    <div className="flex-1">
                      <Combobox
                        items={ComboboxNS.createItems(parentCategories, {
                          getValue: (c) => c.id,
                          getLabel: (c) => `${c.code} — ${c.name}`,
                        })}
                        value={categoryId}
                        onValueChange={(val) => {
                          setCategoryId(val as string);
                          setGroupId("");
                        }}
                      >
                        <ComboboxInput placeholder="Select category..." />
                        <ComboboxContent>
                          <ComboboxEmpty>No categories found.</ComboboxEmpty>
                          <ComboboxList>
                            {(c) => (
                              <ComboboxItem key={c.id} value={c.id}>
                                {c.code} — {c.name}
                              </ComboboxItem>
                            )}
                          </ComboboxList>
                        </ComboboxContent>
                      </Combobox>
                    </div>
                    <Button
                      variant="outline"
                      size="icon"
                      className="shrink-0"
                      onClick={() => setQuickAddEntity("category")}
                    >
                      <Plus className="size-4" />
                    </Button>
                  </div>
                </Field>
                {categoryId && subCategories.length > 0 && (
                  <Field orientation="vertical">
                    <FieldLabel>Sub-Category</FieldLabel>
                    <Select
                      value={groupId}
                      onValueChange={(v) => setGroupId(v ?? "")}
                      items={subCategories.map((c) => ({ value: c.id, label: c.name }))}
                    >
                      <SelectTrigger className="w-full">
                        <SelectValue placeholder="Select sub-category..." />
                      </SelectTrigger>
                      <SelectContent>
                        {subCategories.map((c) => (
                          <SelectItem key={c.id} value={c.id}>
                            {c.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </Field>
                )}
                <Field orientation="vertical">
                  <FieldLabel>Product Group</FieldLabel>
                  <div className="flex gap-1.5">
                    <div className="flex-1">
                      <Select
                        value={groupId}
                        onValueChange={(v) => setGroupId(v ?? "")}
                        disabled={!categoryId}
                        items={filteredGroups.map((g) => ({ value: g.id, label: g.name }))}
                      >
                        <SelectTrigger className="w-full">
                          <SelectValue placeholder={
                            categoryId
                              ? filteredGroups.length
                                ? "Select group..."
                                : "No groups in this category"
                              : "Select category first"
                          } />
                        </SelectTrigger>
                        <SelectContent>
                          {filteredGroups.map((g) => (
                            <SelectItem key={g.id} value={g.id}>
                              {g.name}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                    <Button
                      variant="outline"
                      size="icon"
                      className="shrink-0"
                      onClick={() => setQuickAddEntity("group")}
                    >
                      <Plus className="size-4" />
                    </Button>
                  </div>
                </Field>
                <Field orientation="vertical">
                  <FieldLabel>Brand</FieldLabel>
                  <div className="flex gap-1.5">
                    <div className="flex-1">
                      <Combobox
                        items={ComboboxNS.createItems(brands, {
                          getValue: (b) => b.id,
                          getLabel: (b) => b.name,
                        })}
                        value={brandId}
                        onValueChange={(val) => setBrandId(val as string)}
                      >
                        <ComboboxInput placeholder="None" />
                        <ComboboxContent>
                          <ComboboxEmpty>No brands found.</ComboboxEmpty>
                          <ComboboxList>
                            {(b) => (
                              <ComboboxItem key={b.id} value={b.id}>
                                {b.name}
                              </ComboboxItem>
                            )}
                          </ComboboxList>
                        </ComboboxContent>
                      </Combobox>
                    </div>
                    <Button
                      variant="outline"
                      size="icon"
                      className="shrink-0"
                      onClick={() => setQuickAddEntity("brand")}
                    >
                      <Plus className="size-4" />
                    </Button>
                  </div>
                </Field>
                <Field orientation="vertical">
                  <FieldLabel>Unit of Measure</FieldLabel>
                  <div className="flex gap-1.5">
                    <div className="flex-1">
                      <Combobox
                        items={ComboboxNS.createItems(uoms, {
                          getValue: (u) => u.id,
                          getLabel: (u) => u.shortName ? `${u.name} (${u.shortName})` : u.name,
                        })}
                        value={uomId}
                        onValueChange={(val) => {
                          setUomId(val as string);
                          setSubUnitId("");
                        }}
                      >
                        <ComboboxInput placeholder="None" />
                        <ComboboxContent>
                          <ComboboxEmpty>No UOMs found.</ComboboxEmpty>
                          <ComboboxList>
                            {(u) => (
                              <ComboboxItem key={u.id} value={u.id}>
                                {u.name}
                                {u.shortName && (
                                  <span className="ml-1 text-muted-foreground">
                                    ({u.shortName})
                                  </span>
                                )}
                              </ComboboxItem>
                            )}
                          </ComboboxList>
                        </ComboboxContent>
                      </Combobox>
                    </div>
                    <Button
                      variant="outline"
                      size="icon"
                      className="shrink-0"
                      onClick={() => setQuickAddEntity("uom")}
                    >
                      <Plus className="size-4" />
                    </Button>
                  </div>
                </Field>
                {selectedUom?.subUnits && selectedUom.subUnits.length > 0 && (
                  <Field orientation="vertical">
                    <FieldLabel>Sub-Unit</FieldLabel>
                    <Select
                      value={subUnitId}
                      onValueChange={(v) => setSubUnitId(v ?? "")}
                      items={selectedUom.subUnits.map((s) => ({
                        value: s.id,
                        label: `${s.shortName || s.name}${s.conversionFactor ? ` (×${s.conversionFactor})` : ""}`,
                      }))}
                    >
                      <SelectTrigger className="w-full">
                        <SelectValue placeholder="None" />
                      </SelectTrigger>
                      <SelectContent>
                        {selectedUom.subUnits.map((s) => (
                          <SelectItem key={s.id} value={s.id}>
                            {s.shortName || s.name}{s.conversionFactor ? ` (×${s.conversionFactor})` : ""}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </Field>
                )}
                <div className="flex items-center justify-between">
                  <FieldLabel>Status</FieldLabel>
                  <Switch
                    checked={isActive}
                    onCheckedChange={setIsActive}
                  />
                </div>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      {productType === "variation" && (
        <Card>
          <CardHeader>
            <CardTitle>Variation Product</CardTitle>
          </CardHeader>
          <CardContent>
            <Label className="text-sm font-medium">Variation Templates</Label>
            <div className="mt-2 space-y-2">
              <p className="text-xs text-muted-foreground">
                Select templates to define variation dimensions (e.g., Size, Color).
              </p>
              <div className="flex flex-wrap gap-2">
                {templates.map((t) => (
                  <Button
                    key={t.id}
                    type="button"
                    variant={templateIds.includes(t.id) ? "default" : "outline"}
                    size="sm"
                    onClick={() => {
                      setTemplateIds((prev) =>
                        prev.includes(t.id)
                          ? prev.filter((id) => id !== t.id)
                          : [...prev, t.id]
                      );
                    }}
                  >
                    {t.name}
                    {t.options && (
                      <span className="ml-1 text-xs opacity-60">
                        ({t.options.length})
                      </span>
                    )}
                  </Button>
                ))}
                {templates.length === 0 && (
                  <p className="text-sm text-muted-foreground">No templates available.</p>
                )}
              </div>
            </div>
          </CardContent>
        </Card>
      )}

      <Card>
        <CardHeader className="flex-row items-center justify-between gap-3">
          <CardTitle>Variation Matrix</CardTitle>
          <div className="text-sm text-muted-foreground">
            <strong className="font-mono">{variantRows.length}</strong> variants
          </div>
        </CardHeader>
        <CardContent>
          <VariantMatrix
            rows={variantRows}
            onRowsChange={setVariantRows}
            templateIds={templateIds}
            templates={templates.map((t) => ({ ...t, options: t.options ?? [] }))}
            uoms={uoms}
            productCode={code}
            productName={name}
            isVariation={productType === "variation"}
          />
        </CardContent>
      </Card>

      <div className="flex items-center justify-end gap-2">
        <Button
          variant="outline"
          onClick={() => router.push("/products")}
          disabled={saveMutation.isPending}
        >
          Cancel
        </Button>
        <Button onClick={handleSave} disabled={saveMutation.isPending}>
          <Save />
          <span>{saveMutation.isPending ? "Saving..." : "Save Product"}</span>
        </Button>
      </div>

      {quickAddEntity && (
        <EntityQuickAddModal
          entity={quickAddEntity}
          open={!!quickAddEntity}
          onOpenChange={(open) => {
            if (!open) setQuickAddEntity(null);
          }}
          onSaved={(newId) => handleQuickAddSaved(quickAddEntity, newId)}
          categories={categories}
        />
      )}
    </div>
  );
}
