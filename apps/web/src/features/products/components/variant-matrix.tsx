"use client";

import { useState } from "react";
import { toast } from "sonner";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Trash2, Plus } from "lucide-react";

export interface VariantRow {
  id?: string;
  uid: string;
  values: Record<string, string>;
  sku: string;
  description: string;
  uomId: string;
  subUnitId: string;
  purchasePrice: string;
  subUnitPurchasePrice: string;
  imageUrl: string;
}

interface VariantMatrixProps {
  rows: VariantRow[];
  onRowsChange: (rows: VariantRow[]) => void;
  templateIds: string[];
  templates: Array<{
    id: string;
    name: string;
    options: Array<{ id: string; value: string }>;
  }>;
  uoms: Array<{
    id: string;
    name: string;
    subUnits?: Array<{ id: string; name: string; shortName: string; conversionFactor: number }>;
  }>;
  productCode: string;
  productName: string;
  isVariation: boolean;
}

export function VariantMatrix({
  rows,
  onRowsChange,
  templateIds,
  templates,
  uoms,
  productCode,
  productName,
  isVariation,
}: VariantMatrixProps) {
  const t = useTranslations("products.form.matrix");
  const [addingRow, setAddingRow] = useState(false);
  const [rowDraft, setRowDraft] = useState<Record<string, string>>({});

  const genSku = (prevCount: number): string =>
    `${(productCode.trim() || "PRD").toUpperCase()}-${String(prevCount + 1).padStart(3, "0")}`;

  const updateRow = (uid: string, patch: Partial<VariantRow>) => {
    onRowsChange(rows.map((r) => (r.uid === uid ? { ...r, ...patch } : r)));
  };

  const removeRow = (uid: string) => {
    onRowsChange(rows.filter((r) => r.uid !== uid));
  };

  const addRow = () => {
    if (isVariation && templateIds.length === 0) {
      toast.error(t("selectTemplateFirst"));
      return;
    }

    const newRow: VariantRow = {
      uid: `row-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
      values: isVariation ? rowDraft : {},
      sku: genSku(rows.length),
      description: "",
      uomId: "",
      subUnitId: "",
      purchasePrice: "",
      subUnitPurchasePrice: "",
      imageUrl: "",
    };

    onRowsChange([...rows, newRow]);
    setAddingRow(false);
    setRowDraft({});
  };

  const getRowLabel = (row: VariantRow): string => {
    return templateIds
      .map((tid) => {
        const template = templates.find((x) => x.id === tid);
        const option = template?.options.find((o) => o.id === row.values[tid]);
        return option?.value;
      })
      .filter(Boolean)
      .join(", ");
  };

  const getSubUnits = (uomId: string) => {
    const uom = uoms.find((u) => u.id === uomId);
    return uom?.subUnits ?? [];
  };

  return (
    <div className="space-y-3">
      <div className="overflow-x-auto rounded-lg border border-border">
        <table className="w-full text-sm">
          <thead className="bg-muted/60">
            <tr>
              <th className="w-12 px-3 py-2 text-left text-xs font-medium text-muted-foreground">{t("no")}</th>
              {isVariation &&
                templateIds.map((tid) => {
                  const template = templates.find((x) => x.id === tid);
                  return (
                    <th key={tid} className="min-w-[130px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">
                      {template?.name ?? t("description")}
                    </th>
                  );
                })}
              <th className="min-w-[130px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">{t("sku")}</th>
              <th className="min-w-[200px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">{t("description")}</th>
              <th className="min-w-[110px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">{t("uom")}</th>
              <th className="min-w-[110px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">{t("subUnit")}</th>
              <th className="min-w-[110px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">{t("purchasePrice")}</th>
              <th className="min-w-[110px] px-3 py-2 text-left text-xs font-medium text-muted-foreground">{t("subUnitPurchasePrice")}</th>
              <th className="min-w-[70px] px-3 py-2 text-right text-xs font-medium text-muted-foreground">{t("actions")}</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row, index) => {
              const label = getRowLabel(row);
              return (
                <tr key={row.uid} className="border-t border-border hover:bg-muted/40">
                  <td className="px-3 py-2 text-xs text-muted-foreground">{index + 1}</td>
                  {isVariation &&
                    templateIds.map((tid) => {
                      const template = templates.find((x) => x.id === tid);
                      if (!template) return null;
                      return (
                        <td key={tid} className="px-3 py-2">
                          <Select
                            value={row.values[tid] ?? ""}
                            onValueChange={(v) =>
                              updateRow(row.uid, {
                                values: { ...row.values, [tid]: v ?? "" },
                              })
                            }
                            items={template.options.map((o) => ({ value: o.id, label: o.value }))}
                          >
                            <SelectTrigger className="h-8 text-xs">
                              <SelectValue placeholder={t("pickTemplate", { name: template.name })} />
                            </SelectTrigger>
                            <SelectContent>
                              {template.options.map((o) => (
                                <SelectItem key={o.id} value={o.id}>
                                  {o.value}
                                </SelectItem>
                              ))}
                            </SelectContent>
                          </Select>
                        </td>
                      );
                    })}
                  <td className="px-3 py-2">
                    <Input
                      value={row.sku}
                      onChange={(e) => updateRow(row.uid, { sku: e.target.value })}
                      className="h-8 font-mono text-xs"
                    />
                  </td>
                  <td className="px-3 py-2 text-xs">
                    {`${productName || "Product"}${label ? ` — ${label}` : ""}`}
                  </td>
                  <td className="px-3 py-2">
                    <Select
                      value={row.uomId}
                      onValueChange={(v) =>
                        updateRow(row.uid, { uomId: v ?? "", subUnitId: "" })
                      }
                      items={uoms.map((u) => ({ value: u.id, label: u.name }))}
                    >
                      <SelectTrigger className="h-8 text-xs">
                        <SelectValue placeholder={t("none")} />
                      </SelectTrigger>
                      <SelectContent>
                        {uoms.map((u) => (
                          <SelectItem key={u.id} value={u.id}>
                            {u.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </td>
                  <td className="px-3 py-2">
                    <Select
                      value={row.subUnitId}
                      onValueChange={(v) => updateRow(row.uid, { subUnitId: v ?? "" })}
                      disabled={!row.uomId}
                      items={getSubUnits(row.uomId).map((s) => ({
                        value: s.id,
                        label: `${s.shortName || s.name}${s.conversionFactor ? ` (×${s.conversionFactor})` : ""}`,
                      }))}
                    >
                      <SelectTrigger className="h-8 text-xs">
                        <SelectValue placeholder={t("none")} />
                      </SelectTrigger>
                      <SelectContent>
                        {getSubUnits(row.uomId).map((s) => (
                          <SelectItem key={s.id} value={s.id}>
                            {s.shortName || s.name}{s.conversionFactor ? ` (×${s.conversionFactor})` : ""}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </td>
                  <td className="px-3 py-2">
                    <Input
                      type="number"
                      value={row.purchasePrice}
                      onChange={(e) => updateRow(row.uid, { purchasePrice: e.target.value })}
                      placeholder="0.00"
                      className="h-8 w-28 font-mono text-xs"
                    />
                  </td>
                  <td className="px-3 py-2">
                    <Input
                      type="number"
                      value={row.subUnitPurchasePrice}
                      onChange={(e) => updateRow(row.uid, { subUnitPurchasePrice: e.target.value })}
                      placeholder="0.00"
                      className="h-8 w-28 font-mono text-xs"
                      disabled={!row.subUnitId}
                    />
                  </td>
                  <td className="px-3 py-2 text-right">
                    {isVariation ? (
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-7 text-destructive hover:text-destructive"
                        onClick={() => removeRow(row.uid)}
                      >
                        <Trash2 className="size-3.5" />
                      </Button>
                    ) : (
                      <span className="text-xs text-muted-foreground">{t("none")}</span>
                    )}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {isVariation && (
        <>
          {addingRow ? (
            <div className="space-y-3 rounded-xl border border-primary/30 bg-primary/5 p-4">
              <p className="text-sm font-semibold">{t("addRowTitle")}</p>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                {templateIds.map((tid) => {
                  const template = templates.find((x) => x.id === tid);
                  if (!template) return null;
                  return (
                    <div key={tid} className="space-y-1">
                      <label className="text-xs font-medium text-foreground">
                        {template.name}
                      </label>
                      <Select
                        value={rowDraft[tid] ?? ""}
                        onValueChange={(v) =>
                          setRowDraft((prev) => ({ ...prev, [tid]: v ?? "" }))
                        }
                        items={template.options.map((o) => ({ value: o.id, label: o.value }))}
                      >
                        <SelectTrigger className="w-full">
                          <SelectValue placeholder={t("pickTemplate", { name: template.name })} />
                        </SelectTrigger>
                        <SelectContent>
                          {template.options.map((o) => (
                            <SelectItem key={o.id} value={o.id}>
                              {o.value}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                  );
                })}
              </div>
              <div className="flex items-center justify-between gap-2">
                <p className="text-xs text-muted-foreground">
                  {t("selectValuesHint")}
                </p>
                <div className="flex shrink-0 gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => {
                      setAddingRow(false);
                      setRowDraft({});
                    }}
                  >
                    {t("cancel")}
                  </Button>
                  <Button size="sm" onClick={addRow}>
                    <Plus />
                    {t("add")}
                  </Button>
                </div>
              </div>
            </div>
          ) : (
            <Button variant="outline" size="sm" onClick={() => setAddingRow(true)}>
              <Plus />
              {t("addRow")}
            </Button>
          )}
        </>
      )}
    </div>
  );
}
