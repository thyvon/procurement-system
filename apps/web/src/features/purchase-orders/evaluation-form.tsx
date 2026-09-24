"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { ArrowLeft, Plus, Save } from "lucide-react";
import { Button } from "@/components/ui/button";
import { DataTableSkeleton } from "@/components/ui/data-table-skeleton";
import {
  Card,
  CardAction,
  CardContent,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  purchaseOrdersEvaluationsShow,
  purchaseOrdersEvaluationsStore,
  purchaseOrdersEvaluationsUpdate,
} from "@/lib/api/evaluation/evaluation";
import type { EvaluationResource } from "@/lib/api/model/evaluationResource";
import type { StoreEvaluationRequest } from "@/lib/api/model/storeEvaluationRequest";
import type { UpdateEvaluationRequest } from "@/lib/api/model/updateEvaluationRequest";
import { unwrap, withAuth } from "@/lib/api-client";
import {
  EvaluationMatrix,
  SEED_QUOTATION_COUNT,
  createEmptyQuotation,
  emptyPricing,
  type EvaluationMatrixValue,
  type QuotationPanel,
} from "./components/evaluation-matrix";

function createEmptyValue(): EvaluationMatrixValue {
  const itemUid = "item-seed-1";
  return {
    items: [{ uid: itemUid, itemCode: "", description: "", qty: "1", uom: "" }],
    quotations: Array.from({ length: SEED_QUOTATION_COUNT }, (_, index) => ({
      ...createEmptyQuotation(),
      pricing: {
        [itemUid]: { ...emptyPricing(), selected: index === 0 },
      },
    })),
  };
}

function fromEvaluation(evaluation: EvaluationResource): EvaluationMatrixValue {
  const items = (evaluation.items ?? []).map((item) => ({
    uid: item.id,
    itemCode: item.itemCode,
    description: item.description,
    qty: String(item.qty),
    uom: item.uom,
  }));

  const quotations: QuotationPanel[] = (evaluation.quotations ?? []).map((quote) => {
    const pricing: QuotationPanel["pricing"] = {};
    for (const item of items) {
      pricing[item.uid] = emptyPricing();
    }
    for (const line of quote.lines ?? []) {
      const item = items.find((i) => i.uid === line.itemId);
      if (!item) continue;
      pricing[item.uid] = {
        brand: line.brand ?? "",
        unitCost: String(line.unitCost),
        selected: line.isSelected,
      };
    }
    return {
      supplierCode: quote.supplierCode,
      supplierName: quote.supplierName,
      address: quote.supplierAddress ?? "",
      phone: quote.supplierPhone ?? "",
      vatPercentage: "",
      pricing,
      totals: {
        discount: quote.discount ? String(quote.discount) : "",
        vat: quote.vat ? String(quote.vat) : "",
      },
      criteria: {
        price: quote.price ?? "",
        quality: quote.quality ?? "",
        leadTime: quote.leadTime ?? "",
        warranty: quote.warranty ?? "",
        paymentTerms: quote.paymentTerms ?? "",
      },
    };
  });

  // Keep only the first winner per item (legacy rows may have multiple).
  const seenWinners = new Set<string>();
  for (const panel of quotations) {
    for (const item of items) {
      const line = panel.pricing[item.uid];
      if (!line?.selected) continue;
      if (seenWinners.has(item.uid)) {
        panel.pricing[item.uid] = { ...line, selected: false };
      } else {
        seenWinners.add(item.uid);
      }
    }
  }

  return { items, quotations };
}

function optionalNumber(value: string): number | null {
  if (value.trim() === "") return null;
  const n = Number.parseFloat(value);
  return Number.isFinite(n) ? n : null;
}

function optionalText(value: string): string | null {
  const trimmed = value.trim();
  return trimmed === "" ? null : trimmed;
}

function toPayload(
  value: EvaluationMatrixValue,
  basis: string
): StoreEvaluationRequest {
  return {
    recommendation_basis: optionalText(basis),
    items: value.items.map((item) => ({
      item_code: item.itemCode.trim(),
      description: item.description.trim(),
      qty: Number(item.qty),
      uom: item.uom.trim(),
    })),
    quotations: value.quotations.map((panel) => ({
      supplier_code: panel.supplierCode,
      supplier_name: panel.supplierName,
      supplier_phone: optionalText(panel.phone),
      supplier_address: optionalText(panel.address),
      discount: optionalNumber(panel.totals.discount) ?? 0,
      vat: optionalNumber(panel.totals.vat) ?? 0,
      price: optionalText(panel.criteria.price),
      quality: optionalText(panel.criteria.quality),
      lead_time: optionalText(panel.criteria.leadTime),
      warranty: optionalText(panel.criteria.warranty),
      payment_terms: optionalText(panel.criteria.paymentTerms),
      lines: value.items.map((item, index) => {
        const pricing = panel.pricing[item.uid] ?? emptyPricing();
        return {
          item_index: index,
          brand: optionalText(pricing.brand),
          unit_cost: Number.parseFloat(pricing.unitCost) || 0,
          is_selected: pricing.selected,
        };
      }),
    })),
  };
}

function findWinnerGap(value: EvaluationMatrixValue): string | null {
  for (const item of value.items) {
    const hasWinner = value.quotations.some(
      (panel) => panel.pricing[item.uid]?.selected
    );
    if (!hasWinner) return item.itemCode || item.description || item.uid;
  }
  return null;
}

interface EvaluationFormProps {
  onBack: () => void;
  evaluationId?: string;
}

export function EvaluationForm({ onBack, evaluationId }: EvaluationFormProps) {
  const tf = useTranslations("purchaseOrders.form");
  const isEdit = Boolean(evaluationId);
  const qc = useQueryClient();
  const [value, setValue] = useState<EvaluationMatrixValue>(createEmptyValue);
  const [basis, setBasis] = useState("");
  const [hydratedId, setHydratedId] = useState<string | null>(null);

  const showQuery = useQuery({
    queryKey: ["evaluations", "detail", evaluationId],
    queryFn: async (): Promise<EvaluationResource> =>
      unwrap(await purchaseOrdersEvaluationsShow(evaluationId!, withAuth())),
    enabled: isEdit,
  });

  if (isEdit && showQuery.data && hydratedId !== showQuery.data.id) {
    setHydratedId(showQuery.data.id);
    setValue(fromEvaluation(showQuery.data));
    setBasis(showQuery.data.recommendationBasis ?? "");
  }

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = toPayload(value, basis);
      if (isEdit) {
        return unwrap(
          await purchaseOrdersEvaluationsUpdate(
            evaluationId!,
            payload as UpdateEvaluationRequest,
            withAuth()
          )
        );
      }
      return unwrap(await purchaseOrdersEvaluationsStore(payload, withAuth()));
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["evaluations"] });
      toast.success(isEdit ? tf("updated") : tf("created"));
      onBack();
    },
  });

  const addQuotation = () => {
    const pricing = Object.fromEntries(
      value.items.map((item) => [item.uid, emptyPricing()])
    );
    setValue({
      ...value,
      quotations: [...value.quotations, { ...createEmptyQuotation(), pricing }],
    });
  };

  const handleSave = () => {
    const missingSuppliers = value.quotations.some((q) => !q.supplierCode.trim());
    if (missingSuppliers) {
      toast.error(tf("missingSupplier"));
      return;
    }
    const missingItems = value.items.some(
      (item) =>
        !item.itemCode.trim() ||
        !item.description.trim() ||
        !item.uom.trim() ||
        !Number.isFinite(Number(item.qty)) ||
        Number(item.qty) <= 0
    );
    if (missingItems) {
      toast.error(tf("missingItem"));
      return;
    }
    const gap = findWinnerGap(value);
    if (gap) {
      toast.error(tf("noWinner", { item: gap }));
      return;
    }
    saveMutation.mutate();
  };

  if (isEdit && showQuery.isPending) {
    return <DataTableSkeleton columns={6} actions={2} />;
  }

  if (isEdit && showQuery.isError) {
    return (
      <div className="mt-1 min-w-0 space-y-4">
        <Button variant="outline" onClick={onBack}>
          {tf("back")}
        </Button>
        <p className="text-sm text-destructive">{tf("loadFailed")}</p>
      </div>
    );
  }

  return (
    <div className="mt-1 min-w-0 space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Button
            variant="outline"
            size="icon"
            onClick={onBack}
            aria-label={tf("back")}
          >
            <ArrowLeft />
          </Button>
          <div>
            <h1 className="text-xl font-semibold tracking-tight">
              {isEdit ? tf("editTitle") : tf("createTitle")}
            </h1>
            <p className="text-sm text-muted-foreground">
              {isEdit ? tf("editDescription") : tf("createDescription")}
            </p>
          </div>
        </div>
      </div>

      <Card size="sm" className="min-w-0 text-xs">
        <CardHeader>
          <CardTitle className="text-xs">{tf("title")}</CardTitle>
          <CardAction>
            <Button type="button" size="sm" onClick={addQuotation}>
              <Plus className="mr-1.5 size-3.5" />
              {tf("addQuotation")}
            </Button>
          </CardAction>
        </CardHeader>
        <CardContent className="min-w-0 space-y-3">
          <EvaluationMatrix value={value} onChange={setValue} />

          <div className="space-y-2 border-t border-border pt-3">
            <Label
              htmlFor="recommendation-basis"
              className="text-left text-xs after:ml-1 after:content-[':']"
            >
              {tf("basis")}
            </Label>
            <Textarea
              id="recommendation-basis"
              value={basis}
              onChange={(e) => setBasis(e.target.value)}
              rows={2}
              className="min-h-12 text-xs placeholder:text-xs md:text-xs"
            />
          </div>
        </CardContent>
      </Card>

      <div className="flex items-center justify-end gap-2">
        <Button onClick={handleSave} disabled={saveMutation.isPending}>
          <Save />
          <span>{saveMutation.isPending ? tf("saving") : tf("save")}</span>
        </Button>
      </div>
    </div>
  );
}
