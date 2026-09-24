"use client";

import { useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { toast } from "sonner";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { ArrowLeft, Save, Plus } from "lucide-react";
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
import { unwrap, withAuth } from "@/lib/api-client";
import {
  productsItemsShow,
  productsItemsStore,
  productsItemsUpdate,
} from "@/lib/api/product/product";
import type { ProductsItemsShow200Data } from "@/lib/api/model/productsItemsShow200Data";
import type { StoreProductRequest } from "@/lib/api/model/storeProductRequest";
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

type ProductType = "single" | "variable";

type CategoryOption = { id: string; name: string; code: string; parentId: string | null };

interface ProductFormProps {
  mode: "create" | "edit";
  productId?: string;
}

function splitCategory(
  categoryId: string | null,
  categories: CategoryOption[]
): { parent: string; sub: string } {
  if (!categoryId) return { parent: "", sub: "" };
  const leaf = categories.find((c) => c.id === categoryId);
  if (leaf?.parentId) return { parent: leaf.parentId, sub: leaf.id };
  return { parent: categoryId, sub: "" };
}

function toVariantRows(
  product: ProductsItemsShow200Data
): VariantRow[] {
  return (product.variants ?? []).map((v) => ({
    id: v.id,
    uid: v.id,
    values: (v.optionValues ?? {}) as unknown as Record<string, string>,
    sku: v.code ?? "",
    description: v.description ?? "",
    uomId: product.uomId ?? "",
    subUnitId: v.subUnitId ?? "",
    purchasePrice:
      v.purchasePrice !== null && v.purchasePrice !== undefined
        ? String(v.purchasePrice)
        : "",
    subUnitPurchasePrice:
      v.subUnitPurchasePrice !== null && v.subUnitPurchasePrice !== undefined
        ? String(v.subUnitPurchasePrice)
        : "",
    imageUrl: v.imageUrl ?? "",
  }));
}

export function ProductForm({ mode, productId }: ProductFormProps) {
  const t = useTranslations("products.form");
  const isEdit = mode === "edit" && !!productId;

  const productQuery = useQuery({
    queryKey: ["products", productId],
    queryFn: async () =>
      unwrap<ProductsItemsShow200Data>(
        await productsItemsShow(productId!, withAuth())
      ),
    enabled: isEdit,
  });

  const categoriesQuery = useQuery({
    queryKey: ["categories"],
    queryFn: async () => unwrap<CategoryOption[]>(
      await productsCategoriesIndex(withAuth())
    ),
  });

  const isHydrating = isEdit && (productQuery.isPending || categoriesQuery.isPending);

  if (isHydrating) {
    return (
      <div className="space-y-6">
        <div className="flex items-center gap-3">
          <div className="size-9 animate-pulse rounded-md bg-muted" />
          <div className="space-y-1.5">
            <div className="h-5 w-32 animate-pulse rounded bg-muted" />
            <div className="h-4 w-48 animate-pulse rounded bg-muted" />
          </div>
        </div>
        <div className="h-96 w-full animate-pulse rounded-lg bg-muted" />
      </div>
    );
  }

  if (isEdit && (!productQuery.data || productQuery.isError)) {
    return (
      <div className="space-y-4">
        <p className="text-sm text-muted-foreground">{t("nameRequired")}</p>
        <BackButton label={t("back")} />
      </div>
    );
  }

  const categories = categoriesQuery.data ?? [];
  const initial = productQuery.data;
  const catSplit = initial
    ? splitCategory(initial.categoryId, categories)
    : { parent: "", sub: "" };

  return (
    <ProductFormInner
      key={productId ?? "create"}
      mode={mode}
      productId={productId}
      categories={categories}
      initial={
        initial
          ? {
              code: initial.code ?? "",
              name: initial.name ?? "",
              nameKm: initial.nameKm ?? "",
              description: initial.description ?? "",
              productType:
                initial.productType === "variable" ? "variable" : "single",
              categoryId: catSplit.parent,
              subCategoryId: catSplit.sub,
              groupId: initial.groupId ?? "",
              brandId: initial.brandId ?? "",
              uomId: initial.uomId ?? "",
              subUnitId: initial.subUnitId ?? "",
              purchasePrice:
                initial.purchasePrice !== null && initial.purchasePrice !== undefined
                  ? String(initial.purchasePrice)
                  : "",
              subUnitPurchasePrice:
                initial.subUnitPurchasePrice !== null &&
                initial.subUnitPurchasePrice !== undefined
                  ? String(initial.subUnitPurchasePrice)
                  : "",
              isActive: initial.isActive ?? true,
              templateIds: Array.isArray(initial.templateIds)
                ? initial.templateIds.map((id) => String(id))
                : [],
              variantRows: toVariantRows(initial),
            }
          : undefined
      }
    />
  );
}

function BackButton({ label }: { label: string }) {
  const router = useRouter();
  return (
    <Button variant="outline" onClick={() => router.push("/products")}>
      <ArrowLeft />
      {label}
    </Button>
  );
}

interface ProductFormInnerProps {
  mode: "create" | "edit";
  productId?: string;
  categories: CategoryOption[];
  initial?: {
    code: string;
    name: string;
    nameKm: string;
    description: string;
    productType: ProductType;
    categoryId: string;
    subCategoryId: string;
    groupId: string;
    brandId: string;
    uomId: string;
    subUnitId: string;
    purchasePrice: string;
    subUnitPurchasePrice: string;
    isActive: boolean;
    templateIds: string[];
    variantRows: VariantRow[];
  };
}

function ProductFormInner({ mode, productId, categories, initial }: ProductFormInnerProps) {
  const router = useRouter();
  const qc = useQueryClient();
  const t = useTranslations("products.form");
  const isEdit = mode === "edit" && !!productId;

  const [code, setCode] = useState(initial?.code ?? "");
  const [name, setName] = useState(initial?.name ?? "");
  const [nameKm, setNameKm] = useState(initial?.nameKm ?? "");
  const [description, setDescription] = useState(initial?.description ?? "");
  const [productType, setProductType] = useState<ProductType>(
    initial?.productType ?? "single"
  );
  const [categoryId, setCategoryId] = useState(initial?.categoryId ?? "");
  const [subCategoryId, setSubCategoryId] = useState(initial?.subCategoryId ?? "");
  const [groupId, setGroupId] = useState(initial?.groupId ?? "");
  const [brandId, setBrandId] = useState(initial?.brandId ?? "");
  const [uomId, setUomId] = useState(initial?.uomId ?? "");
  const [subUnitId, setSubUnitId] = useState(initial?.subUnitId ?? "");
  const [purchasePrice, setPurchasePrice] = useState(initial?.purchasePrice ?? "");
  const [subUnitPurchasePrice, setSubUnitPurchasePrice] = useState(
    initial?.subUnitPurchasePrice ?? ""
  );
  const [isActive, setIsActive] = useState(initial?.isActive ?? true);
  const [templateIds, setTemplateIds] = useState<string[]>(initial?.templateIds ?? []);
  const [variantRows, setVariantRows] = useState<VariantRow[]>(initial?.variantRows ?? []);

  const [quickAddEntity, setQuickAddEntity] = useState<"category" | "brand" | "group" | "uom" | null>(null);

  const { data: groupsData } = useQuery({
    queryKey: ["groups"],
    queryFn: async () => unwrap<Array<{ id: string; name: string }>>(
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
    enabled: productType === "variable",
  });

  const groups = useMemo(() => groupsData ?? [], [groupsData]);
  const brands = useMemo(() => brandsData ?? [], [brandsData]);
  const uoms = useMemo(() => uomsData ?? [], [uomsData]);
  const templates = useMemo(() => templatesData ?? [], [templatesData]);

  const parentCategories = useMemo(
    () => categories.filter((c) => !c.parentId),
    [categories]
  );

  const subCategories = useMemo(
    () => categories.filter((c) => c.parentId === categoryId),
    [categories, categoryId]
  );

  const selectedUom = useMemo(() => uoms.find((u) => u.id === uomId), [uoms, uomId]);

  const buildPayload = () => {
    const payload = {
      code: code.trim() || null,
      name: name.trim(),
      name_km: nameKm.trim() || null,
      description: description.trim() || null,
      product_type: productType,
      category_id: subCategoryId || categoryId || null,
      group_id: groupId || null,
      brand_id: brandId || null,
      uom_id: uomId || null,
      sub_unit_id: subUnitId || null,
      purchase_price: purchasePrice === "" ? null : Number(purchasePrice),
      sub_unit_purchase_price:
        subUnitPurchasePrice === "" ? null : Number(subUnitPurchasePrice),
      is_active: isActive,
      ...(productType === "variable"
        ? {
            template_ids: templateIds,
            variants: variantRows.map((row) => ({
              id: row.id ?? null,
              code: row.sku.trim() || null,
              description: row.description.trim() || null,
              option_values: row.values as unknown as string[],
              sub_unit_id: row.subUnitId || null,
              purchase_price:
                row.purchasePrice === "" ? null : Number(row.purchasePrice),
              sub_unit_purchase_price:
                row.subUnitPurchasePrice === ""
                  ? null
                  : Number(row.subUnitPurchasePrice),
            })),
          }
        : {}),
    };
    return payload;
  };

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = buildPayload();
      if (isEdit && productId) {
        const updatePayload = {
          ...payload,
          code: payload.code ?? undefined,
        };
        return unwrap(await productsItemsUpdate(productId, updatePayload, withAuth()));
      }
      return unwrap(await productsItemsStore(payload as StoreProductRequest, withAuth()));
    },
    onSuccess: () => {
      toast.success(isEdit ? t("updated") : t("created"));
      qc.invalidateQueries({ queryKey: ["products"] });
      router.push("/products");
    },
  });

  const handleSave = () => {
    if (!name.trim()) {
      toast.error(t("nameRequired"));
      return;
    }
    saveMutation.mutate();
  };

  const handleQuickAddSaved = (entity: string, newId?: string) => {
    if (entity === "category" && newId) {
      setCategoryId(newId);
      setSubCategoryId("");
    }
    if (entity === "group" && newId) setGroupId(newId);
    if (entity === "brand" && newId) setBrandId(newId);
    if (entity === "uom" && newId) setUomId(newId);
    const key =
      entity === "category"
        ? "categories"
        : entity === "group"
          ? "groups"
          : entity === "brand"
            ? "brands"
            : "uoms";
    qc.invalidateQueries({ queryKey: [key] });
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Button
            variant="outline"
            size="icon"
            onClick={() => router.push("/products")}
            aria-label={t("back")}
          >
            <ArrowLeft />
          </Button>
          <div>
            <h1 className="text-xl font-semibold tracking-tight">
              {isEdit ? t("editTitle") : t("createTitle")}
            </h1>
            <p className="text-sm text-muted-foreground">
              {isEdit ? t("editDescription") : t("createDescription")}
            </p>
          </div>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>{t("productForm")}</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 gap-8 lg:grid-cols-[1.3fr_1fr]">
            <div>
              <Label className="text-sm font-medium">{t("descriptionSection")}</Label>
              <div className="mt-2 space-y-4">
                <Field orientation="vertical">
                  <FieldLabel>{t("code")}</FieldLabel>
                  <Input
                    value={code}
                    onChange={(e) => setCode(e.target.value.toUpperCase())}
                    placeholder={t("codePlaceholder")}
                    className="font-mono"
                  />
                </Field>
                <Field orientation="vertical">
                  <FieldLabel>{t("nameKm")}</FieldLabel>
                  <Textarea
                    value={nameKm}
                    onChange={(e) => setNameKm(e.target.value)}
                    placeholder={t("nameKmPlaceholder")}
                    rows={3}
                  />
                </Field>
                <Field orientation="vertical">
                  <FieldLabel>{t("nameEn")}</FieldLabel>
                  <Textarea
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    placeholder={t("nameEnPlaceholder")}
                    rows={3}
                  />
                </Field>
                <Field orientation="vertical">
                  <FieldLabel>{t("description")}</FieldLabel>
                  <Textarea
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                    rows={3}
                    placeholder={t("descriptionPlaceholder")}
                  />
                </Field>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <Field orientation="vertical">
                    <FieldLabel>{t("purchasePrice")}</FieldLabel>
                    <Input
                      type="number"
                      min="0"
                      step="0.01"
                      value={purchasePrice}
                      onChange={(e) => setPurchasePrice(e.target.value)}
                      placeholder="0.00"
                      className="font-mono"
                    />
                  </Field>
                  <Field orientation="vertical">
                    <FieldLabel>{t("subUnitPurchasePrice")}</FieldLabel>
                    <Input
                      type="number"
                      min="0"
                      step="0.01"
                      value={subUnitPurchasePrice}
                      onChange={(e) => setSubUnitPurchasePrice(e.target.value)}
                      placeholder="0.00"
                      className="font-mono"
                      disabled={!subUnitId}
                    />
                  </Field>
                </div>
              </div>
            </div>

            <div>
              <Label className="text-sm font-medium">{t("catalogSection")}</Label>
              <div className="mt-2 space-y-4">
                <Field orientation="vertical">
                  <FieldLabel>{t("productType")}</FieldLabel>
                  <Combobox
                    items={ComboboxNS.createItems(
                      [
                        { value: "single" as const, label: t("typeSingle") },
                        { value: "variable" as const, label: t("typeVariable") },
                      ],
                      {
                        getValue: (x) => x.value,
                        getLabel: (x) => x.label,
                      }
                    )}
                    value={productType}
                    onValueChange={(val) => {
                      setProductType(val as ProductType);
                      if (val !== "variable") {
                        setTemplateIds([]);
                        setVariantRows([]);
                      }
                    }}
                  >
                    <ComboboxInput placeholder={t("selectType")} />
                    <ComboboxContent>
                      <ComboboxEmpty>{t("selectType")}</ComboboxEmpty>
                      <ComboboxList>
                        {(x) => (
                          <ComboboxItem key={x.value} value={x.value}>
                            {x.label}
                          </ComboboxItem>
                        )}
                      </ComboboxList>
                    </ComboboxContent>
                  </Combobox>
                </Field>
                <Field orientation="vertical">
                  <FieldLabel>{t("category")}</FieldLabel>
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
                          setSubCategoryId("");
                          setGroupId("");
                        }}
                      >
                        <ComboboxInput placeholder={t("selectCategory")} />
                        <ComboboxContent>
                          <ComboboxEmpty>{t("selectCategory")}</ComboboxEmpty>
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
                    <FieldLabel>{t("subCategory")}</FieldLabel>
                    <Select
                      value={subCategoryId}
                      onValueChange={(v) => setSubCategoryId(v ?? "")}
                      items={subCategories.map((c) => ({ value: c.id, label: c.name }))}
                    >
                      <SelectTrigger className="w-full">
                        <SelectValue placeholder={t("selectSubCategory")} />
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
                  <FieldLabel>{t("group")}</FieldLabel>
                  <div className="flex gap-1.5">
                    <div className="flex-1">
                      <Select
                        value={groupId}
                        onValueChange={(v) => setGroupId(v ?? "")}
                        items={groups.map((g) => ({ value: g.id, label: g.name }))}
                      >
                        <SelectTrigger className="w-full">
                          <SelectValue placeholder={
                            groups.length ? t("selectGroup") : t("noGroups")
                          } />
                        </SelectTrigger>
                        <SelectContent>
                          {groups.map((g) => (
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
                  <FieldLabel>{t("brand")}</FieldLabel>
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
                          <ComboboxEmpty>{t("brand")}</ComboboxEmpty>
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
                  <FieldLabel>{t("uom")}</FieldLabel>
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
                          <ComboboxEmpty>{t("uom")}</ComboboxEmpty>
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
                    <FieldLabel>{t("subUnit")}</FieldLabel>
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
                  <FieldLabel>{t("status")}</FieldLabel>
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

      {productType === "variable" && (
        <Card>
          <CardHeader>
            <CardTitle>{t("variationTemplates")}</CardTitle>
          </CardHeader>
          <CardContent>
            <Label className="text-sm font-medium">{t("variationTemplates")}</Label>
            <div className="mt-2 space-y-2">
              <p className="text-xs text-muted-foreground">
                {t("variationTemplatesHint")}
              </p>
              <div className="flex flex-wrap gap-2">
                {templates.map((x) => (
                  <Button
                    key={x.id}
                    type="button"
                    variant={templateIds.includes(x.id) ? "default" : "outline"}
                    size="sm"
                    onClick={() => {
                      setTemplateIds((prev) =>
                        prev.includes(x.id)
                          ? prev.filter((id) => id !== x.id)
                          : [...prev, x.id]
                      );
                    }}
                  >
                    {x.name}
                    {x.options && (
                      <span className="ml-1 text-xs opacity-60">
                        ({x.options.length})
                      </span>
                    )}
                  </Button>
                ))}
                {templates.length === 0 && (
                  <p className="text-sm text-muted-foreground">{t("noTemplates")}</p>
                )}
              </div>
            </div>
          </CardContent>
        </Card>
      )}

      {productType === "variable" && (
        <Card>
          <CardHeader className="flex-row items-center justify-between gap-3">
            <CardTitle>{t("variationMatrix")}</CardTitle>
            <div className="text-sm text-muted-foreground">
              {t("matrix.count", { count: variantRows.length })}
            </div>
          </CardHeader>
          <CardContent>
            <VariantMatrix
              rows={variantRows}
              onRowsChange={setVariantRows}
              templateIds={templateIds}
              templates={templates.map((x) => ({ ...x, options: x.options ?? [] }))}
              uoms={uoms}
              productCode={code}
              productName={name}
              isVariation={productType === "variable"}
            />
          </CardContent>
        </Card>
      )}

      <div className="flex items-center justify-end gap-2">
        <Button
          variant="outline"
          onClick={() => router.push("/products")}
          disabled={saveMutation.isPending}
        >
          {t("cancel")}
        </Button>
        <Button onClick={handleSave} disabled={saveMutation.isPending}>
          <Save />
          <span>{saveMutation.isPending ? t("saving") : t("save")}</span>
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
        />
      )}
    </div>
  );
}
