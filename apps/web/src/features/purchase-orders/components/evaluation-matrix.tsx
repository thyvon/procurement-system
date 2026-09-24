"use client";

import { Fragment } from "react";
import { useTranslations } from "next-intl";
import { Plus, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";

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
  supplierName: string;
  address: string;
  phone: string;
  pricing: Record<string, QuotationPricing>;
  totals: QuotationTotals;
  criteria: QuotationCriteria;
};

export type EvaluationMatrixValue = {
  items: ItemRow[];
  quotations: QuotationPanel[];
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

export function createEmptyQuotation(): QuotationPanel {
  return {
    supplierName: "",
    address: "",
    phone: "",
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

function quoteLabel(index: number): string {
  return ["I", "II", "III"][index] ?? String(index + 1);
}

const headCell =
  "border border-border bg-muted/60 px-2 py-1.5 text-left text-xs font-medium";
const bodyCell = "border border-border p-1.5 align-top";
const criteriaLabelCell =
  "border-y border-border bg-muted/40 px-2 py-2 text-left text-sm font-medium";

interface EvaluationMatrixProps {
  value: EvaluationMatrixValue;
  onChange: (next: EvaluationMatrixValue) => void;
}

export function EvaluationMatrix({ value, onChange }: EvaluationMatrixProps) {
  const t = useTranslations("purchaseOrders.form");
  const { items, quotations } = value;

  const patchItem = (uid: string, patch: Partial<ItemRow>) => {
    onChange({
      ...value,
      items: items.map((item) => (item.uid === uid ? { ...item, ...patch } : item)),
    });
  };

  const patchQuotation = (index: number, patch: Partial<QuotationPanel>) => {
    onChange({
      ...value,
      quotations: quotations.map((panel, i) =>
        i === index ? { ...panel, ...patch } : panel
      ),
    });
  };

  const patchPricing = (
    index: number,
    itemUid: string,
    patch: Partial<QuotationPricing>
  ) => {
    const panel = quotations[index];
    if (!panel) return;
    patchQuotation(index, {
      pricing: {
        ...panel.pricing,
        [itemUid]: { ...panel.pricing[itemUid], ...patch },
      },
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
      quotations: quotations.map((panel) => ({
        ...panel,
        pricing: { ...panel.pricing, [uid]: { brand: "", unitCost: "" } },
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
        <table className="w-full min-w-[1400px] border-collapse text-sm">
          <thead>
            <tr>
              <th className={`${headCell} w-10`} rowSpan={3}>
                {t("no")}
              </th>
              <th className={`${headCell} min-w-[120px]`} rowSpan={3}>
                {t("itemCode")}
              </th>
              <th className={`${headCell} min-w-[260px]`} rowSpan={3}>
                {t("description")}
              </th>
              <th className={`${headCell} w-24`} rowSpan={3}>
                {t("qty")}
              </th>
              <th className={`${headCell} w-28`} rowSpan={3}>
                {t("uom")}
              </th>
              {quotations.map((_, index) => (
                <th
                  key={`q-head-${index}`}
                  className={`${headCell} min-w-[200px] text-center`}
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
                    <Input
                      value={panel.supplierName}
                      onChange={(e) => patchQuotation(index, { supplierName: e.target.value })}
                      placeholder={t("supplierPlaceholder")}
                      className="h-7 border-0 bg-background px-1.5 text-xs font-semibold shadow-none"
                      aria-label={`${t("quotation", { n: quoteLabel(index) })} ${t("supplierName")}`}
                    />
                    <Input
                      value={panel.address}
                      onChange={(e) => patchQuotation(index, { address: e.target.value })}
                      placeholder={t("addressPlaceholder")}
                      className="h-7 border-0 bg-background px-1.5 text-xs shadow-none"
                      aria-label={`${t("quotation", { n: quoteLabel(index) })} ${t("address")}`}
                    />
                    <Input
                      value={panel.phone}
                      onChange={(e) => patchQuotation(index, { phone: e.target.value })}
                      placeholder={t("phonePlaceholder")}
                      className="h-7 border-0 bg-background px-1.5 text-xs shadow-none"
                      aria-label={`${t("quotation", { n: quoteLabel(index) })} ${t("phone")}`}
                    />
                  </div>
                </th>
              ))}
            </tr>
            <tr>
              {quotations.map((_, index) => (
                <Fragment key={`q-cols-${index}`}>
                  <th className={`${headCell} min-w-[100px]`}>{t("brand")}</th>
                  <th className={`${headCell} min-w-[140px] text-right`}>{t("unitCost")}</th>
                  <th className={`${headCell} min-w-[120px] text-right`}>{t("totalCost")}</th>
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
                  <Input
                    value={item.itemCode}
                    onChange={(e) => patchItem(item.uid, { itemCode: e.target.value })}
                    placeholder={t("itemCodePlaceholder")}
                    className="h-8 border-0 bg-background px-1.5 text-xs shadow-none"
                  />
                </td>
                <td className={bodyCell}>
                  <Textarea
                    value={item.description}
                    onChange={(e) => patchItem(item.uid, { description: e.target.value })}
                    placeholder={t("descriptionPlaceholder")}
                    rows={3}
                    className="min-h-[72px] resize-none border-0 bg-background px-1.5 text-xs shadow-none"
                  />
                </td>
                <td className={bodyCell}>
                  <Input
                    value={item.qty}
                    onChange={(e) => patchItem(item.uid, { qty: e.target.value })}
                    placeholder={t("qtyPlaceholder")}
                    inputMode="decimal"
                          className="h-8 border-0 bg-background px-1.5 text-right text-sm shadow-none"
                  />
                </td>
                <td className={bodyCell}>
                  <Input
                    value={item.uom}
                    onChange={(e) => patchItem(item.uid, { uom: e.target.value })}
                    placeholder={t("uomPlaceholder")}
                    className="h-8 border-0 bg-background px-1.5 text-xs shadow-none"
                  />
                </td>
                {quotations.map((panel, qIndex) => {
                  const pricing = panel.pricing[item.uid] ?? { brand: "", unitCost: "" };
                  return (
                    <Fragment key={`item-${item.uid}-q-${qIndex}`}>
                      <td className={bodyCell}>
                        <Input
                          value={pricing.brand}
                          onChange={(e) =>
                            patchPricing(qIndex, item.uid, { brand: e.target.value })
                          }
                          placeholder={t("brandPlaceholder")}
                          className="h-8 border-0 bg-background px-1.5 text-xs shadow-none"
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
                          className="h-8 border-0 bg-background px-1.5 text-right text-sm shadow-none"
                        />
                      </td>
                      <td className={bodyCell}>
                        <div className="flex h-8 items-center justify-end text-sm tabular-nums">
                          {money.format(lineTotal(item, pricing))}
                        </div>
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
                  <div className="flex justify-end pr-2 text-sm font-medium">{t(key)}</div>
                </td>
                {quotations.map((panel, qIndex) => {
                  if (readonly) {
                    const amount =
                      key === "subTotal" ? subTotal(items, panel) : grandTotal(items, panel);
                    const strong = key === "grandTotal";
                    return (
                      <td key={`${key}-${qIndex}`} className={bodyCell} colSpan={3}>
                        <div
                          className={`flex h-8 items-center justify-end text-sm tabular-nums${strong ? " font-semibold" : ""}`}
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
                        className="h-8 border-0 bg-background px-1.5 text-right text-sm shadow-none"
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
                        className="min-h-[36px] resize-none border-0 bg-background px-1.5 text-center text-xs shadow-none"
                      />
                    ) : (
                      <Input
                        value={panel.criteria[key]}
                        onChange={(e) => patchCriteria(qIndex, { [key]: e.target.value })}
                        placeholder={t("criteriaPlaceholder")}
                        className="h-8 border-0 bg-background px-1.5 text-center text-xs shadow-none"
                      />
                    )}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="flex flex-wrap gap-4">
        <Button type="button" variant="outline" size="sm" onClick={addItem}>
          <Plus className="size-3.5" />
          {t("addItem")}
        </Button>
      </div>
    </div>
  );
}
