"use client";

import { Fragment, useMemo } from "react";
import { useMutation, useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { Plus, X } from "lucide-react";
import { Combobox as ComboboxNS } from "@base-ui/react/combobox";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import {
  Combobox,
  ComboboxContent,
  ComboboxEmpty,
  ComboboxInput,
  ComboboxItem,
  ComboboxList,
} from "@/components/ui/combobox";
import { epurchaseItemsIndex } from "@/lib/api/epurchase-item/epurchase-item";
import { epurchaseVendorsInfo } from "@/lib/api/epurchase-vendor/epurchase-vendor";
import { unwrap, unwrapWithMeta, withAuth } from "@/lib/api-client";
import { SupplierPicker, type VendorSearchRow } from "./supplier-picker";

type ItemRow = {
  uid: string;
  itemCode: string;
  description: string;
  qty: string;
  uom: string;
};

type QuotationPricing = {
  brand: string;
  unitCost: string;
  selected: boolean;
};

type QuotationTotals = {
  discount: string;
  vat: string;
};

type QuotationCriteria = {
  price: string;
  quality: string;
  leadTime: string;
  warranty: string;
  paymentTerms: string;
};

type QuotationPanel = {
  supplierCode: string;
  supplierName: string;
  address: string;
  phone: string;
  vatPercentage: string;
  pricing: Record<string, QuotationPricing>;
  totals: QuotationTotals;
  criteria: QuotationCriteria;
};

export type EvaluationMatrixValue = {
  items: ItemRow[];
  quotations: QuotationPanel[];
};

export type { ItemRow, QuotationPricing, QuotationPanel };

type EPurchaseVendorInfo = {
  id: number;
  code: string;
  nameEn: string;
  nameKhmer: string;
  phone: string;
  address: string;
  email: string;
  paymentTerm: string;
  vatPercentage: string;
};

type EPurchaseItem = {
  code: string;
  description: string;
  category: string;
  subCategory: string;
  uom: string;
  estimatePrice: number | null;
  avgPrice: number | null;
  status: string;
};

export const SEED_QUOTATION_COUNT = 2;

const CRITERIA_KEYS = [
  "price",
  "quality",
  "leadTime",
  "warranty",
  "paymentTerms",
] as const;

const money = new Intl.NumberFormat("en-US", {
  style: "currency",
  currency: "USD",
  minimumFractionDigits: 2,
});

function parseMoney(value: string): number {
  const n = Number.parseFloat(value);
  return Number.isFinite(n) ? n : 0;
}

function lineTotal(item: ItemRow, pricing: QuotationPricing | undefined): number {
  if (!pricing) return 0;
  return parseMoney(item.qty) * parseMoney(pricing.unitCost);
}

function subTotal(items: ItemRow[], panel: QuotationPanel): number {
  return items.reduce((sum, item) => sum + lineTotal(item, panel.pricing[item.uid]), 0);
}

function grandTotal(items: ItemRow[], panel: QuotationPanel): number {
  return (
    subTotal(items, panel) -
    parseMoney(panel.totals.discount) +
    parseMoney(panel.totals.vat)
  );
}

function recalcVat(
  panel: QuotationPanel,
  items: ItemRow[]
): Pick<QuotationPanel, "totals"> {
  const rate = Number.parseFloat(panel.vatPercentage);
  if (!Number.isFinite(rate)) {
    return { totals: panel.totals };
  }

  return {
    totals: {
      ...panel.totals,
      vat: ((subTotal(items, panel) * rate) / 100).toFixed(2),
    },
  };
}

export function createEmptyQuotation(): QuotationPanel {
  return {
    supplierCode: "",
    supplierName: "",
    address: "",
    phone: "",
    vatPercentage: "",
    pricing: {},
    totals: { discount: "", vat: "" },
    criteria: {
      price: "",
      quality: "",
      leadTime: "",
      warranty: "",
      paymentTerms: "",
    },
  };
}

export function emptyPricing(): QuotationPricing {
  return { brand: "", unitCost: "", selected: false };
}

function quoteLabel(index: number): string {
  return ["I", "II", "III"][index] ?? String(index + 1);
}

function useCatalogItems() {
  return useQuery({
    queryKey: ["epurchaseItems", "picker"],
    queryFn: async (): Promise<EPurchaseItem[]> => {
      const response = await epurchaseItemsIndex({ per_page: 100 }, withAuth());
      const page = unwrapWithMeta<EPurchaseItem[]>(response) as {
        data: EPurchaseItem[];
      };
      return page.data;
    },
    retry: false,
  });
}

const headCell =
  "border border-border bg-muted/60 px-2 py-1 text-left text-xs font-medium";
const bodyCell = "border border-border p-1 align-top text-xs";
const criteriaLabelCell =
  "border-y border-border bg-muted/40 px-2 py-1 text-left text-xs font-medium";

interface EvaluationMatrixProps {
  value: EvaluationMatrixValue;
  onChange: (
    next:
      | EvaluationMatrixValue
      | ((prev: EvaluationMatrixValue) => EvaluationMatrixValue)
  ) => void;
}

export function EvaluationMatrix({ value, onChange }: EvaluationMatrixProps) {
  const t = useTranslations("purchaseOrders.form");
  const { items, quotations } = value;
  const itemsQuery = useCatalogItems();

  const catalogItems = useMemo(() => itemsQuery.data ?? [], [itemsQuery.data]);

  const catalogComboItems = useMemo(
    () =>
      ComboboxNS.createItems(catalogItems, {
        getValue: (c) => c.code,
        getLabel: (c) => `${c.code} — ${c.description}`,
      }),
    [catalogItems]
  );

  const patchItem = (uid: string, patch: Partial<ItemRow>) => {
    const nextItems = items.map((item) =>
      item.uid === uid ? { ...item, ...patch } : item
    );
    onChange({
      items: nextItems,
      quotations: quotations.map((panel) => ({
        ...panel,
        ...recalcVat(panel, nextItems),
      })),
    });
  };

  const patchQuotation = (index: number, patch: Partial<QuotationPanel>) => {
    const editingVat =
      "totals" in patch && patch.totals !== undefined && "vat" in patch.totals;

    onChange({
      ...value,
      quotations: quotations.map((panel, i) => {
        if (i !== index) return panel;
        const next = { ...panel, ...patch };
        return editingVat ? next : { ...next, ...recalcVat(next, items) };
      }),
    });
  };

  const vendorInfoMutation = useMutation({
    mutationFn: async (variables: {
      vendorId: number;
      panelIndex: number;
      supplierCode: string;
    }): Promise<EPurchaseVendorInfo> => {
      const response = await epurchaseVendorsInfo(
        { supplier_code: String(variables.vendorId) },
        withAuth()
      );
      return unwrap<EPurchaseVendorInfo>(response);
    },
    onSuccess: (info, variables) => {
      onChange((current) => {
        const panel = current.quotations[variables.panelIndex];
        if (panel?.supplierCode !== variables.supplierCode) return current;

        return {
          ...current,
          quotations: current.quotations.map((existing, i) => {
            if (i !== variables.panelIndex) return existing;
            const next: QuotationPanel = {
              ...existing,
              address: info.address,
              phone: info.phone,
              vatPercentage: info.vatPercentage ?? "",
            };
            return { ...next, ...recalcVat(next, current.items) };
          }),
        };
      });
    },
  });

  const patchPricing = (
    index: number,
    itemUid: string,
    patch: Partial<QuotationPricing>
  ) => {
    const panel = quotations[index];
    if (!panel) return;

    const nextPricing = {
      ...panel.pricing,
      [itemUid]: { ...emptyPricing(), ...panel.pricing[itemUid], ...patch },
    };
    const selecting = nextPricing[itemUid].selected;

    onChange((current) => ({
      ...current,
      quotations: current.quotations.map((existing, i) => {
        if (i === index) {
          return { ...existing, pricing: nextPricing };
        }
        if (!selecting) return existing;
        const existingLine = existing.pricing[itemUid];
        if (!existingLine?.selected) return existing;
        return {
          ...existing,
          pricing: {
            ...existing.pricing,
            [itemUid]: { ...existingLine, selected: false },
          },
        };
      }),
    }));
  };

  const selectSupplier = (index: number, row: VendorSearchRow | null) => {
    if (!row) {
      patchQuotation(index, {
        supplierCode: "",
        supplierName: "",
        address: "",
        phone: "",
        vatPercentage: "",
        totals: { ...quotations[index].totals, vat: "" },
      });
      return;
    }

    const alreadyUsed = quotations.some(
      (panel, i) =>
        i !== index &&
        panel.supplierCode !== "" &&
        panel.supplierCode === row.code
    );
    if (alreadyUsed) {
      toast.error(t("duplicateSupplier"));
      return;
    }

    patchQuotation(index, {
      supplierCode: row.code,
      supplierName: row.nameEn,
      address: "",
      phone: "",
      vatPercentage: "",
    });
    vendorInfoMutation.mutate({
      vendorId: row.id,
      panelIndex: index,
      supplierCode: row.code,
    });
  };

  const selectItem = (uid: string, code: string) => {
    const item = catalogItems.find((c) => c.code === code);
    if (!item) {
      patchItem(uid, { itemCode: "" });
      return;
    }
    patchItem(uid, {
      itemCode: item.code,
      description: item.description,
      uom: item.uom,
    });
  };

  const patchTotals = (index: number, patch: Partial<QuotationTotals>) => {
    const panel = quotations[index];
    if (!panel) return;
    patchQuotation(index, { totals: { ...panel.totals, ...patch } });
  };

  const patchCriteria = (index: number, patch: Partial<QuotationCriteria>) => {
    const panel = quotations[index];
    if (!panel) return;
    patchQuotation(index, { criteria: { ...panel.criteria, ...patch } });
  };

  const addItem = () => {
    const uid = `item-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`;
    onChange({
      items: [...items, { uid, itemCode: "", description: "", qty: "1", uom: "" }],
      quotations: quotations.map((panel, panelIndex) => ({
        ...panel,
        pricing: {
          ...panel.pricing,
          [uid]: { ...emptyPricing(), selected: panelIndex === 0 },
        },
      })),
    });
  };

  const removeQuotation = (index: number) => {
    if (index < 2 || quotations.length <= 2) return;
    onChange({
      ...value,
      quotations: quotations.filter((_, i) => i !== index),
    });
  };

  const totalRows = [
    { key: "subTotal", readonly: true },
    { key: "discount", readonly: false },
    { key: "vat", readonly: false },
    { key: "grandTotal", readonly: true },
  ] as const;

  return (
    <div className="min-w-0 space-y-3">
      <div className="w-full max-w-full overflow-x-auto rounded-lg border border-border">
        <table className="w-full min-w-[1400px] border-collapse text-xs">
          <thead>
            <tr>
              <th className={`${headCell} w-10`} rowSpan={3}>
                {t("no")}
              </th>
              <th className={`${headCell} w-[100px]`} rowSpan={3}>
                {t("itemCode")}
              </th>
              <th className={`${headCell} w-[200px]`} rowSpan={3}>
                {t("description")}
              </th>
              <th className={`${headCell} w-15`} rowSpan={3}>
                {t("qty")}
              </th>
              <th className={`${headCell} w-15`} rowSpan={3}>
                {t("uom")}
              </th>
              {quotations.map((_, index) => (
                <th
                  key={`q-head-${index}`}
                  className={`${headCell} min-w-[15px] text-center`}
                  colSpan={3}
                >
                  <div className="flex items-center justify-center gap-1">
                    <span>{t("quotation", { n: quoteLabel(index) })}</span>
                    {index >= 2 && quotations.length > 2 && (
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon-xs"
                        onClick={() => removeQuotation(index)}
                        aria-label={`${t("removeQuotation")} ${t("quotation", { n: quoteLabel(index) })}`}
                        className="text-muted-foreground hover:text-foreground"
                      >
                        <X className="size-3.5" />
                      </Button>
                    )}
                  </div>
                </th>
              ))}
            </tr>
            <tr>
              {quotations.map((panel, index) => (
                <th
                  key={`q-supplier-${index}`}
                  className={`${bodyCell} bg-muted/40`}
                  colSpan={3}
                >
                  <div className="space-y-1">
                    <SupplierPicker
                      value={panel.supplierCode}
                      supplierName={panel.supplierName}
                      onSelect={(row) => selectSupplier(index, row)}
                      placeholder={t("supplierPlaceholder")}
                      emptyMessage={t("selectSupplier")}
                      ariaLabel={`${t("quotation", { n: quoteLabel(index) })} ${t("supplierName")}`}
                    />
                    <Textarea
                      value={panel.address}
                      readOnly
                      placeholder={t("addressPlaceholder")}
                      rows={2}
                      className="min-h-10 resize-none border-0 bg-background px-1.5 font-normal text-xs shadow-none placeholder:text-xs md:text-xs"
                      aria-label={`${t("quotation", { n: quoteLabel(index) })} ${t("address")}`}
                    />
                    <Input
                      value={panel.phone}
                      readOnly
                      placeholder={t("phonePlaceholder")}
                      className="h-7 border-0 bg-background px-1.5 font-normal text-xs shadow-none placeholder:text-xs md:text-xs"
                      aria-label={`${t("quotation", { n: quoteLabel(index) })} ${t("phone")}`}
                    />
                  </div>
                </th>
              ))}
            </tr>
            <tr>
              {quotations.map((_, index) => (
                <Fragment key={`q-cols-${index}`}>
                  <th className={`${headCell} w-12 text-center`}>{t("winner")}</th>
                  <th className={`${headCell} w-[140px]`}>{t("brand")}</th>
                  <th className={`${headCell} w-[80px] text-right`}>{t("unitCost")}</th>
                </Fragment>
              ))}
            </tr>
          </thead>
          <tbody>
            {items.map((item, itemIndex) => (
              <tr key={item.uid} className="border-b border-border hover:bg-muted/30">
                <td className={`${bodyCell} text-center text-xs text-muted-foreground`}>
                  {itemIndex + 1}
                </td>
                <td className={bodyCell}>
                  <Combobox
                    items={catalogComboItems}
                    value={item.itemCode || null}
                    onValueChange={(val) =>
                      selectItem(item.uid, typeof val === "string" ? val : "")
                    }
                  >
                    <ComboboxInput
                      placeholder={t("itemCodePlaceholder")}
                      className="h-7 min-w-0 border-0 bg-background px-1.5 text-xs shadow-none [&_input]:text-xs [&_input]:md:text-xs"
                      aria-label={t("itemCode")}
                    />
                    <ComboboxContent>
                      <ComboboxEmpty className="text-xs">{t("selectItem")}</ComboboxEmpty>
                      <ComboboxList>
                        {(c) => (
                          <ComboboxItem key={c.code} value={c.code} className="text-xs">
                            {`${c.code} — ${c.description}`}
                          </ComboboxItem>
                        )}
                      </ComboboxList>
                    </ComboboxContent>
                  </Combobox>
                </td>
                <td className={bodyCell}>
                  <Textarea
                    value={item.description}
                    onChange={(e) => patchItem(item.uid, { description: e.target.value })}
                    placeholder={t("descriptionPlaceholder")}
                    rows={3}
                    className="min-h-[56px] resize-none border-0 bg-background px-1.5 text-xs shadow-none placeholder:text-xs md:text-xs"
                  />
                </td>
                <td className={bodyCell}>
                    <Input
                      value={item.qty}
                      onChange={(e) => patchItem(item.uid, { qty: e.target.value })}
                      placeholder={t("qtyPlaceholder")}
                      inputMode="decimal"
                      className="h-7 border-0 bg-background px-1.5 text-right text-xs shadow-none placeholder:text-xs md:text-xs"
                    />
                </td>
                <td className={bodyCell}>
                    <Input
                      value={item.uom}
                      onChange={(e) => patchItem(item.uid, { uom: e.target.value })}
                      placeholder={t("uomPlaceholder")}
                      className="h-7 border-0 bg-background px-1.5 text-xs shadow-none placeholder:text-xs md:text-xs"
                    />
                </td>
                {quotations.map((panel, qIndex) => {
                  const pricing = panel.pricing[item.uid] ?? emptyPricing();
                  return (
                    <Fragment key={`item-${item.uid}-q-${qIndex}`}>
                      <td className={bodyCell}>
                        <div className="flex h-7 items-center justify-center">
                          <Checkbox
                            checked={pricing.selected}
                            onCheckedChange={(checked) =>
                              patchPricing(qIndex, item.uid, {
                                selected: checked === true,
                              })
                            }
                            aria-label={`${t("quotation", { n: quoteLabel(qIndex) })} ${t("winner")}`}
                          />
                        </div>
                      </td>
                      <td className={bodyCell}>
                        <Input
                          value={pricing.brand}
                          onChange={(e) =>
                            patchPricing(qIndex, item.uid, { brand: e.target.value })
                          }
                          placeholder={t("brandPlaceholder")}
                          className="h-7 border-0 bg-background px-1.5 text-xs shadow-none placeholder:text-xs md:text-xs"
                        />
                      </td>
                      <td className={bodyCell}>
                        <Input
                          value={pricing.unitCost}
                          onChange={(e) =>
                            patchPricing(qIndex, item.uid, { unitCost: e.target.value })
                          }
                          placeholder={t("unitCostPlaceholder")}
                          inputMode="decimal"
                          className="h-7 border-0 bg-background px-1.5 text-right text-xs shadow-none placeholder:text-xs md:text-xs"
                        />
                      </td>
                    </Fragment>
                  );
                })}
              </tr>
            ))}

            {totalRows.map(({ key, readonly }) => (
              <tr
                key={key}
                className={key === "grandTotal" ? "border-t-2 border-border" : undefined}
              >
                <td className={`${bodyCell} bg-muted/20`} colSpan={5}>
                  <div className="flex justify-end pr-2 text-xs font-medium">{t(key)}</div>
                </td>
                {quotations.map((panel, qIndex) => {
                  if (readonly) {
                    const amount =
                      key === "subTotal" ? subTotal(items, panel) : grandTotal(items, panel);
                    const strong = key === "grandTotal";
                    return (
                      <td key={`${key}-${qIndex}`} className={bodyCell} colSpan={3}>
                        <div
                          className={`flex h-7 items-center justify-end text-xs tabular-nums${strong ? " font-semibold" : ""}`}
                        >
                          {money.format(amount)}
                        </div>
                      </td>
                    );
                  }
                  const field = key === "discount" ? "discount" : "vat";
                  return (
                    <td key={`${key}-${qIndex}`} className={bodyCell} colSpan={3}>
                      <Input
                        value={panel.totals[field]}
                        onChange={(e) => patchTotals(qIndex, { [field]: e.target.value })}
                        placeholder={t(`${field}Placeholder`)}
                        inputMode="decimal"
                        className="h-7 border-0 bg-background px-1.5 text-right text-xs shadow-none placeholder:text-xs md:text-xs"
                      />
                    </td>
                  );
                })}
              </tr>
            ))}

            {CRITERIA_KEYS.map((key, criteriaIndex) => (
              <tr key={key}>
                <td className={`${criteriaLabelCell} border-l w-10`} />
                <td className={`${criteriaLabelCell} min-w-[120px]`} />
                <td className={criteriaLabelCell} colSpan={3}>
                  <div className="text-left">
                    <span>
                      {criteriaIndex + 1}. {t(key)}
                    </span>
                  </div>
                </td>
                {quotations.map((panel, qIndex) => (
                  <td key={`${key}-${qIndex}`} className={bodyCell} colSpan={3}>
                    {key === "warranty" || key === "leadTime" ? (
                      <Textarea
                        value={panel.criteria[key]}
                        onChange={(e) => patchCriteria(qIndex, { [key]: e.target.value })}
                        placeholder={t("criteriaPlaceholder")}
                        rows={key === "warranty" ? 3 : 1}
                        className="min-h-[28px] resize-none border-0 bg-background px-1.5 text-center text-xs shadow-none placeholder:text-xs md:text-xs"
                      />
                    ) : (
                      <Input
                        value={panel.criteria[key]}
                        onChange={(e) => patchCriteria(qIndex, { [key]: e.target.value })}
                        placeholder={t("criteriaPlaceholder")}
                        className="h-7 border-0 bg-background px-1.5 text-center text-xs shadow-none placeholder:text-xs md:text-xs"
                      />
                    )}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="flex flex-wrap gap-2">
        <Button type="button" variant="outline" size="xs" onClick={addItem}>
          <Plus className="size-3" />
          {t("addItem")}
        </Button>
      </div>
    </div>
  );
}
