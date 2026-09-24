"use client";

import { useState } from "react";
import { toast } from "sonner";
import { useTranslations } from "next-intl";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Trash2, Plus, X, Loader2 } from "lucide-react";
import { unwrap, withAuth } from "@/lib/api-client";
import { productsVariationTemplatesUpdate } from "@/lib/api/variation/variation";
import { CreatableCombobox } from "./creatable-combobox";

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
}

interface MatrixTemplate {
  id: string;
  name: string;
  isActive: boolean;
  options: Array<{ id: string; value: string; sortOrder: number }>;
}

interface VariantMatrixProps {
  rows: VariantRow[];
  onRowsChange: (rows: VariantRow[]) => void;
  templateIds: string[];
  templates: MatrixTemplate[];
  uoms: Array<{
    id: string;
    name: string;
    subUnits?: Array<{ id: string; name: string; shortName: string; conversionFactor: number }>;
  }>;
  productCode: string;
  productName: string;
  productUomId: string;
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
  productUomId,
  isVariation,
}: VariantMatrixProps) {
  const t = useTranslations("products.form.matrix");
  const qc = useQueryClient();
  const [addingRow, setAddingRow] = useState(false);
  const [addingBusy, setAddingBusy] = useState(false);
  const [rowDraft, setRowDraft] = useState<Record<string, string>>({});
  const [newRowFields, setNewRowFields] = useState<Record<string, string>>({});

  const genSku = (prevCount: number): string =>
    `${(productCode.trim() || "PRD").toUpperCase()}-${String(prevCount + 1).padStart(3, "0")}`;

  const updateRow = (uid: string, patch: Partial<VariantRow>) => {
    onRowsChange(rows.map((r) => (r.uid === uid ? { ...r, ...patch } : r)));
  };

  const removeRow = (uid: string) => {
    onRowsChange(rows.filter((r) => r.uid !== uid));
  };

  const createValueMutation = useMutation({
    mutationFn: async ({ template, name }: { template: MatrixTemplate; name: string }) => {
      const raw = name.trim();
      const existing = template.options.find(
        (o) => o.value.trim().toLowerCase() === raw.toLowerCase()
      );
      if (existing) return existing;
      const options = [
        ...template.options.map((o) => ({
          id: o.id,
          value: o.value,
          sort_order: o.sortOrder,
        })),
        { value: raw, sort_order: (template.options.length + 1) * 10 },
      ];
      const saved = unwrap<{ options?: Array<{ id: string; value: string }> }>(
        await productsVariationTemplatesUpdate(
          template.id,
          { name: template.name, is_active: template.isActive, options },
          withAuth()
        )
      );
      return (
        saved.options?.find((o) => o.value.trim().toLowerCase() === raw.toLowerCase()) ?? null
      );
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["variation-templates"] });
    },
  });

  const startAddRow = () => {
    if (isVariation && templateIds.length === 0) {
      toast.error(t("selectTemplateFirst"));
      return;
    }
    setAddingRow(true);
    setRowDraft({});
    setNewRowFields({});
  };

  const commitAddRow = async () => {
    if (isVariation) {
      const missing = templateIds.filter((tid) => !rowDraft[tid]);
      if (missing.length > 0) {
        toast.error(t("selectValuesForAll"));
        return;
      }
    }
    setAddingBusy(true);
    try {
      const values: Record<string, string> = {};
      for (const tid of templateIds) {
        if (rowDraft[tid]) values[tid] = rowDraft[tid];
      }
      onRowsChange([
        ...rows,
        {
          uid: `row-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
          values: isVariation ? values : {},
          sku: newRowFields.sku?.trim() || genSku(rows.length),
          description: "",
          uomId: newRowFields.uomId || productUomId || "",
          subUnitId: newRowFields.subUnitId || "",
          purchasePrice: newRowFields.purchasePrice ?? "",
          subUnitPurchasePrice: newRowFields.subUnitPurchasePrice ?? "",
        },
      ]);
      setAddingRow(false);
      setRowDraft({});
      setNewRowFields({});
    } finally {
      setAddingBusy(false);
    }
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

  const draftUomId = newRowFields.uomId || productUomId || "";

  return (
    <div className="space-y-3">
      <div className="overflow-x-auto rounded-lg border border-border">
        <table className="w-full min-w-[900px] border-collapse text-sm">
          <thead className="bg-muted/60">
            <tr className="border-b">
              <th className="h-10 px-2 text-left font-medium">{t("no")}</th>
              {isVariation &&
                templateIds.map((tid) => {
                  const template = templates.find((x) => x.id === tid);
                  return (
                    <th
                      key={tid}
                      className="min-w-[130px] px-2 text-left font-medium"
                    >
                      {template?.name ?? t("description")}
                    </th>
                  );
                })}
              <th className="min-w-[140px] px-2 text-left font-medium">{t("sku")}</th>
              <th className="min-w-[200px] px-2 text-left font-medium">{t("description")}</th>
              <th className="min-w-[120px] px-2 text-left font-medium">{t("uom")}</th>
              <th className="min-w-[120px] px-2 text-left font-medium">{t("subUnit")}</th>
              <th className="min-w-[150px] px-2 text-left font-medium">{t("purchasePrice")}</th>
              <th className="min-w-[150px] px-2 text-left font-medium">{t("subUnitPurchasePrice")}</th>
              <th className="min-w-[80px] px-2 text-right font-medium">{t("actions")}</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row, index) => {
              const label = getRowLabel(row);
              return (
                <tr key={row.uid} className="border-b hover:bg-muted/40">
                  <td className="p-2 align-middle text-xs text-muted-foreground">{index + 1}</td>
                  {isVariation &&
                    templateIds.map((tid) => {
                      const template = templates.find((x) => x.id === tid);
                      if (!template) return null;
                      return (
                        <td key={tid} className="p-2 align-middle">
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
                  <td className="p-2 align-middle">
                    <Input
                      value={row.sku}
                      onChange={(e) => updateRow(row.uid, { sku: e.target.value })}
                      className="h-8 font-mono text-xs"
                    />
                  </td>
                  <td className="p-2 align-middle text-xs">
                    {`${productName || "Product"}${label ? ` — ${label}` : ""}`}
                  </td>
                  <td className="p-2 align-middle">
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
                  <td className="p-2 align-middle">
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
                  <td className="p-2 align-middle">
                    <Input
                      type="number"
                      value={row.purchasePrice}
                      onChange={(e) => updateRow(row.uid, { purchasePrice: e.target.value })}
                      placeholder="0.00"
                      className="h-8 w-full font-mono text-xs"
                    />
                  </td>
                  <td className="p-2 align-middle">
                    <Input
                      type="number"
                      value={row.subUnitPurchasePrice}
                      onChange={(e) => updateRow(row.uid, { subUnitPurchasePrice: e.target.value })}
                      placeholder="0.00"
                      className="h-8 w-full font-mono text-xs"
                      disabled={!row.subUnitId}
                    />
                  </td>
                  <td className="p-2 align-middle text-right">
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

            {isVariation && addingRow && (
              <tr className="border-b bg-primary/5">
                <td className="p-2 align-middle text-xs text-muted-foreground">New</td>
                {templateIds.map((tid) => {
                  const template = templates.find((x) => x.id === tid);
                  if (!template) return null;
                  return (
                    <td key={tid} className="p-2 align-middle">
                      <CreatableCombobox
                        value={
                          template.options.find((o) => o.id === rowDraft[tid])?.value ?? ""
                        }
                        onChange={async (name) => {
                          const existing = template.options.find(
                            (o) => o.value.toLowerCase() === name.toLowerCase()
                          );
                          if (existing) {
                            setRowDraft((prev) => ({ ...prev, [tid]: existing.id }));
                            return;
                          }
                          try {
                            const created = await createValueMutation.mutateAsync({
                              template,
                              name,
                            });
                            if (created) {
                              setRowDraft((prev) => ({ ...prev, [tid]: created.id }));
                            }
                          } catch {
                            // global MutationCache shows the API message
                          }
                        }}
                        options={template.options.map((o) => o.value)}
                        placeholder={`${template.name}...`}
                      />
                    </td>
                  );
                })}
                <td className="p-2 align-middle">
                  <Input
                    value={newRowFields.sku ?? ""}
                    onChange={(e) =>
                      setNewRowFields((prev) => ({ ...prev, sku: e.target.value }))
                    }
                    placeholder={genSku(rows.length)}
                    className="h-8 font-mono text-xs"
                  />
                </td>
                <td className="p-2 align-middle text-xs text-muted-foreground">—</td>
                <td className="p-2 align-middle">
                  <Select
                    value={draftUomId}
                    onValueChange={(v) =>
                      setNewRowFields((prev) => ({ ...prev, uomId: v ?? "", subUnitId: "" }))
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
                <td className="p-2 align-middle">
                  <Select
                    value={newRowFields.subUnitId ?? ""}
                    onValueChange={(v) =>
                      setNewRowFields((prev) => ({ ...prev, subUnitId: v ?? "" }))
                    }
                    disabled={!draftUomId}
                    items={getSubUnits(draftUomId).map((s) => ({
                      value: s.id,
                      label: `${s.shortName || s.name}${s.conversionFactor ? ` (×${s.conversionFactor})` : ""}`,
                    }))}
                  >
                    <SelectTrigger className="h-8 text-xs">
                      <SelectValue placeholder={t("none")} />
                    </SelectTrigger>
                    <SelectContent>
                      {getSubUnits(draftUomId).map((s) => (
                        <SelectItem key={s.id} value={s.id}>
                          {s.shortName || s.name}{s.conversionFactor ? ` (×${s.conversionFactor})` : ""}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </td>
                <td className="p-2 align-middle">
                  <Input
                    type="number"
                    value={newRowFields.purchasePrice ?? ""}
                    onChange={(e) =>
                      setNewRowFields((prev) => ({ ...prev, purchasePrice: e.target.value }))
                    }
                    placeholder="0.00"
                    className="h-8 w-full font-mono text-xs"
                  />
                </td>
                <td className="p-2 align-middle">
                  <Input
                    type="number"
                    value={newRowFields.subUnitPurchasePrice ?? ""}
                    onChange={(e) =>
                      setNewRowFields((prev) => ({
                        ...prev,
                        subUnitPurchasePrice: e.target.value,
                      }))
                    }
                    placeholder="0.00"
                    className="h-8 w-full font-mono text-xs"
                    disabled={!(newRowFields.subUnitId || draftUomId)}
                  />
                </td>
                <td className="p-2 align-middle text-right">
                  <div className="flex justify-end gap-1">
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      className="size-7"
                      onClick={() => {
                        setAddingRow(false);
                        setRowDraft({});
                        setNewRowFields({});
                      }}
                      disabled={addingBusy}
                      aria-label={t("cancel")}
                    >
                      <X className="size-3.5" />
                    </Button>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      className="size-7 text-primary"
                      onClick={commitAddRow}
                      disabled={addingBusy}
                      aria-label={t("add")}
                    >
                      {addingBusy ? (
                        <Loader2 className="size-3.5 animate-spin" />
                      ) : (
                        <Plus className="size-3.5" />
                      )}
                    </Button>
                  </div>
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {isVariation && !addingRow && (
        <Button type="button" variant="outline" size="sm" onClick={startAddRow}>
          <Plus />
          {t("addRow")}
        </Button>
      )}
    </div>
  );
}
