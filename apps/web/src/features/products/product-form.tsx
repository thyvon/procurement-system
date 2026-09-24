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
import { RequiredMark } from "@/components/required-mark";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
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
import { MultiSelectCombobox } from "./components/multi-select-combobox";
import { CategoryDialog } from "./components/category-dialog";
import { GroupDialog } from "./components/group-dialog";
import { BrandDialog } from "./components/brand-dialog";
import { UomDialog } from "./components/uom-dialog";
import { SubUnitDialog } from "./components/sub-unit-dialog";
import { VariationTemplateDialog } from "./components/variation-template-dialog";

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
        <p className="text-sm text-muted-foreground">{t("loadError")}</p>
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

  const [quickAddEntity, setQuickAddEntity] = useState<
    "category" | "subCategory" | "brand" | "group" | "uom" | "subUnit" | null
  >(null);
  const [templateDialogOpen, setTemplateDialogOpen] = useState(false);

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
    queryFn: async () =>
      unwrap<
        Array<{
          id: string;
          name: string;
          isActive: boolean;
          options?: Array<{ id: string; value: string; sortOrder: number }>;
        }>
      >(await productsVariationTemplatesIndex(withAuth())),
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

  const addTemplate = (templateId: string) => {
    if (!templateIds.includes(templateId)) {
      setTemplateIds((prev) => [...prev, templateId]);
    }
  };

  const handleTemplatesChange = (next: string[]) => {
    const removed = templateIds.filter((tid) => !next.includes(tid));
    setTemplateIds(next);
    if (removed.length > 0) {
      setVariantRows((prev) =>
        prev.map((r) => {
          const values = { ...r.values };
          for (const tid of removed) delete values[tid];
          return { ...r, values };
        })
      );
    }
  };

  const buildPayload = () => ({
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
  });

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
    if (productType === "variable") {
      if (templateIds.length === 0) {
        toast.error(t("matrix.needTemplate"));
        return;
      }
      if (variantRows.length === 0) {
        toast.error(t("matrix.needRow"));
        return;
      }
      const seen = new Set<string>();
      for (const r of variantRows) {
        const ids = templateIds.map((tid) => r.values[tid]).filter(Boolean);
        if (ids.length !== templateIds.length) {
          toast.error(t("matrix.rowMissingValue"));
          return;
        }
        const key = ids.slice().sort().join("|");
        if (seen.has(key)) {
          toast.error(t("matrix.duplicateCombo"));
          return;
        }
        seen.add(key);
      }
    }
    saveMutation.mutate();
  };

  // Dialogs invalidate their own query keys; here we only select the new record.
  const selectSaved = (
    entity: "category" | "subCategory" | "group" | "brand" | "uom" | "subUnit",
    id: string
  ) => {
    if (entity === "category") {
      setCategoryId(id);
      setSubCategoryId("");
      setGroupId("");
    }
    if (entity === "subCategory") setSubCategoryId(id);
    if (entity === "group") setGroupId(id);
    if (entity === "brand") setBrandId(id);
    if (entity === "uom") {
      setUomId(id);
      setSubUnitId("");
    }
    if (entity === "subUnit") setSubUnitId(id);
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
                <div className="flex items-center gap-3">
                  <Label className="w-28 shrink-0 text-left after:ml-1 after:content-[':']">{t("code")}</Label>
                  <Input
                    value={code}
                    onChange={(e) => setCode(e.target.value.toUpperCase())}
                    placeholder={t("codePlaceholder")}
                    className="flex-1 font-mono"
                  />
                </div>
                <div className="flex items-start gap-3">
                  <Label className="w-28 shrink-0 pt-1.5 text-left after:ml-1 after:content-[':']">{t("nameKm")}</Label>
                  <Textarea
                    value={nameKm}
                    onChange={(e) => setNameKm(e.target.value)}
                    placeholder={t("nameKmPlaceholder")}
                    rows={3}
                    className="flex-1"
                  />
                </div>
                <div className="flex items-start gap-3">
                  <Label className="w-28 shrink-0 pt-1.5 text-left after:ml-1 after:content-[':']">{t("nameEn")} <RequiredMark /></Label>
                  <Textarea
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    placeholder={t("nameEnPlaceholder")}
                    rows={3}
                    className="flex-1"
                  />
                </div>
                <div className="flex items-start gap-3">
                  <Label className="w-28 shrink-0 pt-1.5 text-left after:ml-1 after:content-[':']">{t("description")}</Label>
                  <Textarea
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                    rows={3}
                    placeholder={t("descriptionPlaceholder")}
                    className="flex-1"
                  />
                </div>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <div className="flex items-center gap-3">
                    <Label className="w-28 shrink-0 text-left after:ml-1 after:content-[':']">{t("purchasePrice")}</Label>
                    <Input
                      type="number"
                      min="0"
                      step="0.01"
                      value={purchasePrice}
                      onChange={(e) => setPurchasePrice(e.target.value)}
                      placeholder="0.00"
                      className="flex-1 font-mono"
                    />
                  </div>
                  <div className="flex items-center gap-3">
                    <Label className="w-28 shrink-0 text-left after:ml-1 after:content-[':']">{t("subUnitPurchasePrice")}</Label>
                    <Input
                      type="number"
                      min="0"
                      step="0.01"
                      value={subUnitPurchasePrice}
                      onChange={(e) => setSubUnitPurchasePrice(e.target.value)}
                      placeholder="0.00"
                      className="flex-1 font-mono"
                      disabled={!subUnitId}
                    />
                  </div>
                </div>
              </div>
            </div>

            <div>
              <Label className="text-sm font-medium">{t("catalogSection")}</Label>
              <div className="mt-2 space-y-4">
                <div className="flex items-center gap-3">
                  <Label className="w-28 shrink-0 text-left after:ml-1 after:content-[':']">{t("productType")}</Label>
                  <div className="flex-1">
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
                        if (val !== "variable") setTemplateIds([]);
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
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  <Label className="w-28 shrink-0 text-left after:ml-1 after:content-[':']">{t("category")}</Label>
                  <div className="flex flex-1 gap-1.5">
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
                </div>
                {categoryId && (
                  <div className="flex items-center gap-3">
                    <Label className="w-28 shrink-0 text-left after:ml-1 after:content-[':']">{t("subCategory")}</Label>
                    <div className="flex flex-1 gap-1.5">
                      <div className="flex-1">
                        <Combobox
                          items={ComboboxNS.createItems(subCategories, {
                            getValue: (c) => c.id,
                            getLabel: (c) => c.name,
                          })}
                          value={subCategoryId}
                          onValueChange={(val) => setSubCategoryId(val as string)}
                        >
                          <ComboboxInput placeholder={t("selectSubCategory")} />
                          <ComboboxContent>
                            <ComboboxEmpty>{t("selectSubCategory")}</ComboboxEmpty>
                            <ComboboxList>
                              {(c) => (
                                <ComboboxItem key={c.id} value={c.id}>
                                  {c.name}
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
                        onClick={() => setQuickAddEntity("subCategory")}
                      >
                        <Plus className="size-4" />
                      </Button>
                    </div>
                  </div>
                )}
                <div className="flex items-center gap-3">
                  <Label className="w-28 shrink-0 text-left after:ml-1 after:content-[':']">{t("group")}</Label>
                  <div className="flex flex-1 gap-1.5">
                    <div className="flex-1">
                      <Combobox
                        items={ComboboxNS.createItems(groups, {
                          getValue: (g) => g.id,
                          getLabel: (g) => g.name,
                        })}
                        value={groupId}
                        onValueChange={(val) => setGroupId(val as string)}
                      >
                        <ComboboxInput
                          placeholder={groups.length ? t("selectGroup") : t("noGroups")}
                        />
                        <ComboboxContent>
                          <ComboboxEmpty>{t("noGroups")}</ComboboxEmpty>
                          <ComboboxList>
                            {(g) => (
                              <ComboboxItem key={g.id} value={g.id}>
                                {g.name}
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
                      onClick={() => setQuickAddEntity("group")}
                    >
                      <Plus className="size-4" />
                    </Button>
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  <Label className="w-28 shrink-0 text-left after:ml-1 after:content-[':']">{t("brand")}</Label>
                  <div className="flex flex-1 gap-1.5">
                    <div className="flex-1">
                      <Combobox
                        items={ComboboxNS.createItems(brands, {
                          getValue: (b) => b.id,
                          getLabel: (b) => b.name,
                        })}
                        value={brandId}
                        onValueChange={(val) => setBrandId(val as string)}
                      >
                        <ComboboxInput placeholder={t("none")} />
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
                </div>
                <div className="flex items-center gap-3">
                  <Label className="w-28 shrink-0 text-left after:ml-1 after:content-[':']">{t("uom")}</Label>
                  <div className="flex flex-1 gap-1.5">
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
                        <ComboboxInput placeholder={t("none")} />
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
                </div>
                {selectedUom && (
                  <div className="flex items-center gap-3">
                    <Label className="w-28 shrink-0 text-left after:ml-1 after:content-[':']">{t("subUnit")}</Label>
                    <div className="flex flex-1 gap-1.5">
                      <div className="flex-1">
                        <Combobox
                          items={ComboboxNS.createItems(selectedUom.subUnits ?? [], {
                            getValue: (s) => s.id,
                            getLabel: (s) =>
                              `${s.shortName || s.name}${s.conversionFactor ? ` (×${s.conversionFactor})` : ""}`,
                          })}
                          value={subUnitId}
                          onValueChange={(val) => setSubUnitId(val as string)}
                        >
                          <ComboboxInput placeholder={t("none")} />
                          <ComboboxContent>
                            <ComboboxEmpty>{t("subUnit")}</ComboboxEmpty>
                            <ComboboxList>
                              {(s) => (
                                <ComboboxItem key={s.id} value={s.id}>
                                  {s.shortName || s.name}
                                  {s.conversionFactor ? ` (×${s.conversionFactor})` : ""}
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
                        onClick={() => setQuickAddEntity("subUnit")}
                      >
                        <Plus className="size-4" />
                      </Button>
                    </div>
                  </div>
                )}
                <div className="flex items-center gap-3">
                  <Label className="w-28 shrink-0 text-left after:ml-1 after:content-[':']">{t("status")}</Label>
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
              <MultiSelectCombobox
                options={templates.map((x) => ({
                  id: x.id,
                  label: x.name,
                  meta: String(x.options?.length ?? 0),
                }))}
                value={templateIds}
                onValueChange={handleTemplatesChange}
                placeholder={t("selectTemplates")}
                emptyMessage={t("noTemplates")}
                footer={
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="w-full justify-start"
                    onClick={() => setTemplateDialogOpen(true)}
                  >
                    <Plus className="size-4" />
                    <span>{t("createTemplate")}</span>
                  </Button>
                }
              />
              <p className="text-xs text-muted-foreground">
                {t("variationTemplatesHint")}
              </p>
            </div>
          </CardContent>
        </Card>
      )}

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
            templates={templates.map((x) => ({
              ...x,
              options: x.options ?? [],
            }))}
            uoms={uoms}
            productCode={code}
            productName={name}
            productUomId={uomId}
            isVariation={productType === "variable"}
          />
        </CardContent>
      </Card>

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

      {quickAddEntity === "category" && (
        <CategoryDialog
          open
          onOpenChange={(open) => {
            if (!open) setQuickAddEntity(null);
          }}
          onSaved={(id) => selectSaved("category", id)}
        />
      )}

      {quickAddEntity === "subCategory" && categoryId && (
        <CategoryDialog
          key={`sub-${categoryId}`}
          open
          onOpenChange={(open) => {
            if (!open) setQuickAddEntity(null);
          }}
          parentId={categoryId}
          onSaved={(id) => selectSaved("subCategory", id)}
        />
      )}

      {quickAddEntity === "group" && (
        <GroupDialog
          open
          onOpenChange={(open) => {
            if (!open) setQuickAddEntity(null);
          }}
          onSaved={(id) => selectSaved("group", id)}
        />
      )}

      {quickAddEntity === "brand" && (
        <BrandDialog
          open
          onOpenChange={(open) => {
            if (!open) setQuickAddEntity(null);
          }}
          onSaved={(id) => selectSaved("brand", id)}
        />
      )}

      {quickAddEntity === "uom" && (
        <UomDialog
          open
          onOpenChange={(open) => {
            if (!open) setQuickAddEntity(null);
          }}
          onSaved={(id) => selectSaved("uom", id)}
        />
      )}

      {quickAddEntity === "subUnit" && selectedUom && (
        <SubUnitDialog
          key={selectedUom.id}
          open
          onOpenChange={(open) => {
            if (!open) setQuickAddEntity(null);
          }}
          uom={selectedUom}
          onSaved={(id) => selectSaved("subUnit", id)}
        />
      )}

      {templateDialogOpen && (
        <VariationTemplateDialog
          open
          onOpenChange={(open) => {
            if (!open) setTemplateDialogOpen(false);
          }}
          onSaved={(id) => {
            if (id) addTemplate(id);
          }}
        />
      )}
    </div>
  );
}
