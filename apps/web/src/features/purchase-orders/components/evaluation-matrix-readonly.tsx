"use client";

import { Fragment } from "react";
import { useTranslations } from "next-intl";
import { cn } from "@/lib/utils";
import {
  CRITERIA_KEYS,
  bodyCell,
  criteriaLabelCell,
  formatMoney,
  grandTotal,
  headCell,
  lineTotal,
  quoteLabel,
  subTotal,
  type EvaluationMatrixValue,
} from "./evaluation-matrix";

/** Same cells as the form, but content vertically centered in the row. */
const cell = cn(bodyCell, "align-middle");
const criteriaCell = cn(criteriaLabelCell, "align-middle");
const head = cn(headCell, "text-center align-middle");
const headRight = cn(headCell, "text-right align-middle");

function parse(value: string): number {
  const n = Number.parseFloat(value);
  return Number.isFinite(n) ? n : 0;
}

function formatOrDash(value: string, currency: string): string {
  const n = Number.parseFloat(value);
  return Number.isFinite(n) ? formatMoney(n, currency) : "—";
}

interface EvaluationMatrixReadOnlyProps {
  value: EvaluationMatrixValue;
  currency?: string;
}

/**
 * Read-only twin of EvaluationMatrix: identical table markup and cell
 * styles (shared constants), with text instead of inputs.
 */
export function EvaluationMatrixReadOnly({
  value,
  currency = "USD",
}: EvaluationMatrixReadOnlyProps) {
  const t = useTranslations("purchaseOrders.form");
  const { items, quotations } = value;

  const totalRows = ["subTotal", "discount", "vat", "grandTotal"] as const;

  return (
    <div className="min-w-0 space-y-3">
      <div className="w-full max-w-full overflow-x-auto border border-border">
        <table className="w-full min-w-[1400px] border-collapse text-xs">
          <thead>
            <tr>
              <th className={`${head} w-5`} rowSpan={3}>
                {t("no")}
              </th>
              <th className={`${head} w-[50px]`} rowSpan={3}>
                {t("itemCode")}
              </th>
              <th className={`${head} w-[250px]`} rowSpan={3}>
                {t("description")}
              </th>
              <th className={`${head} w-15`} rowSpan={3}>
                {t("qty")}
              </th>
              <th className={`${head} w-15`} rowSpan={3}>
                {t("uom")}
              </th>
              {quotations.map((_, index) => (
                <th
                  key={`q-head-${index}`}
                  className={`${head} min-w-[15px]`}
                  colSpan={3}
                >
                  {t("quotation", { n: quoteLabel(index) })}
                </th>
              ))}
            </tr>
            <tr>
              {quotations.map((panel, index) => (
                <th
                  key={`q-supplier-${index}`}
                  className={`${cell} bg-muted/40`}
                  colSpan={3}
                >
                  <div className="grid grid-cols-[max-content_1fr] items-center gap-x-1.5 gap-y-1 text-left">
                    <span className="text-xs text-muted-foreground">
                      {t("supplierName")}
                    </span>
                    <span className="flex min-h-7 min-w-0 items-center truncate text-xs font-medium">
                      {panel.supplierName || "—"}
                    </span>

                    <span className="self-start pt-1.5 text-xs text-muted-foreground">
                      {t("address")}
                    </span>
                    <span className="block min-h-10 min-w-0 whitespace-pre-wrap text-xs font-medium">
                      {panel.address || "—"}
                    </span>

                    <span className="text-xs text-muted-foreground">
                      {t("phone")}
                    </span>
                    <span className="flex min-h-7 min-w-0 items-center text-xs font-medium">
                      {panel.phone || "—"}
                    </span>
                  </div>
                </th>
              ))}
            </tr>
            <tr>
              {quotations.map((_, index) => (
                <Fragment key={`q-cols-${index}`}>
                  <th className={`${headCell} w-[140px]`}>{t("brand")}</th>
                  <th className={`${headRight} w-[80px]`}>
                    {t("unitCost")}
                  </th>
                  <th className={`${headRight} w-[90px]`}>
                    {t("totalCost")}
                  </th>
                </Fragment>
              ))}
            </tr>
          </thead>
          <tbody>
            {items.map((item, itemIndex) => (
              <tr
                key={item.uid}
                className="border-b border-border hover:bg-muted/30"
              >
                <td className={`${cell} text-center text-xs text-muted-foreground`}>
                  <div className="flex h-7 items-center justify-center">
                    {itemIndex + 1}
                  </div>
                </td>
                <td className={cell}>
                  <div className="flex h-7 items-center justify-center">
                    {item.itemCode || "—"}
                  </div>
                </td>
                <td className={cell}>
                  <div className="flex min-h-[56px] items-center whitespace-pre-wrap">
                    {item.description || "—"}
                  </div>
                </td>
                <td className={cell}>
                  <div className="flex h-7 items-center justify-center tabular-nums">
                    {item.qty}
                  </div>
                </td>
                <td className={cell}>
                  <div className="flex h-7 items-center justify-center">
                    {item.uom || "—"}
                  </div>
                </td>
                {quotations.map((panel, qIndex) => {
                  const pricing = panel.pricing[item.uid];
                  return (
                    <Fragment key={`item-${item.uid}-q-${qIndex}`}>
                      <td
                        className={cn(
                          cell,
                          pricing?.selected && "bg-green-600/10"
                        )}
                      >
                        <div className="flex h-7 items-center">
                          {pricing?.brand || "—"}
                        </div>
                      </td>
                      <td
                        className={cn(
                          cell,
                          pricing?.selected && "bg-green-600/10"
                        )}
                      >
                        <div className="flex h-7 items-center justify-end tabular-nums">
                          {pricing ? formatOrDash(pricing.unitCost, currency) : "—"}
                        </div>
                      </td>
                      <td
                        className={cn(
                          cell,
                          pricing?.selected && "bg-green-600/10"
                        )}
                      >
                        <div className="flex h-7 items-center justify-end tabular-nums">
                          {pricing &&
                          Number.isFinite(Number.parseFloat(pricing.unitCost))
                            ? formatMoney(lineTotal(item, pricing), currency)
                            : "—"}
                        </div>
                      </td>
                    </Fragment>
                  );
                })}
              </tr>
            ))}

            {totalRows.map((key) => (
              <tr
                key={key}
                className={key === "grandTotal" ? "border-t-2 border-border" : undefined}
              >
                <td className={`${cell} bg-muted/20`} colSpan={5}>
                  <div className="flex items-center justify-end gap-2 pr-2 text-xs font-medium">
                    <span>{t(key)}</span>
                  </div>
                </td>
                {quotations.map((panel, qIndex) => {
                  const readonly =
                    key === "subTotal" || key === "grandTotal";
                  const amount =
                    key === "subTotal"
                      ? subTotal(items, panel)
                      : key === "grandTotal"
                        ? grandTotal(items, panel)
                        : key === "discount"
                          ? parse(panel.totals.discount)
                          : parse(panel.totals.vat);
                  const hasValue =
                    readonly ||
                    (key === "discount"
                      ? panel.totals.discount.trim() !== ""
                      : panel.totals.vat.trim() !== "");
                  const strong = key === "grandTotal";
                  return (
                    <td key={`${key}-${qIndex}`} className={cell} colSpan={3}>
                      <div
                        className={`flex h-7 items-center justify-end text-xs tabular-nums${strong ? " font-semibold" : ""}`}
                      >
                        {hasValue ? formatMoney(amount, currency) : "—"}
                      </div>
                    </td>
                  );
                })}
              </tr>
            ))}

            {CRITERIA_KEYS.map((key, criteriaIndex) => (
              <tr key={key}>
                <td className={`${criteriaCell} border-l w-10`} />
                <td className={`${criteriaCell} min-w-[120px]`} />
                <td className={criteriaCell} colSpan={3}>
                  <div className="text-left">
                    <span>
                      {criteriaIndex + 1}. {t(key)}
                    </span>
                  </div>
                </td>
                {quotations.map((panel, qIndex) => {
                  const raw = panel.criteria[key];
                  return (
                    <td key={`${key}-${qIndex}`} className={cell} colSpan={3}>
                      <div className="flex h-full min-h-7 items-center justify-center whitespace-pre-wrap text-center">
                        {raw || <span className="text-muted-foreground">—</span>}
                      </div>
                    </td>
                  );
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
