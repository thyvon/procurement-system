"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { ArrowLeft, Plus } from "lucide-react";
import { Button } from "@/components/ui/button";
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
  EvaluationMatrix,
  SEED_QUOTATION_COUNT,
  createEmptyQuotation,
  type EvaluationMatrixValue,
} from "./components/evaluation-matrix";

function createEmptyValue(): EvaluationMatrixValue {
  const itemUid = "item-seed-1";
  return {
    items: [{ uid: itemUid, itemCode: "", description: "", qty: "1", uom: "" }],
    quotations: Array.from({ length: SEED_QUOTATION_COUNT }, () => ({
      ...createEmptyQuotation(),
      pricing: { [itemUid]: { brand: "", unitCost: "" } },
    })),
  };
}

interface EvaluationFormProps {
  onBack: () => void;
}

export function EvaluationForm({ onBack }: EvaluationFormProps) {
  const tf = useTranslations("purchaseOrders.form");
  const [value, setValue] = useState<EvaluationMatrixValue>(createEmptyValue);
  const [basis, setBasis] = useState("");

  const addQuotation = () => {
    const pricing = Object.fromEntries(
      value.items.map((item) => [item.uid, { brand: "", unitCost: "" }])
    );
    setValue({
      ...value,
      quotations: [...value.quotations, { ...createEmptyQuotation(), pricing }],
    });
  };

  return (
    <div className="mt-1 min-w-0 space-y-6">
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
              {tf("createTitle")}
            </h1>
            <p className="text-sm text-muted-foreground">
              {tf("createDescription")}
            </p>
          </div>
        </div>
      </div>

      <Card className="min-w-0">
        <CardHeader>
          <CardTitle>{tf("title")}</CardTitle>
          <CardAction>
            <Button type="button" onClick={addQuotation}>
              <Plus className="mr-2 size-4" />
              {tf("addQuotation")}
            </Button>
          </CardAction>
        </CardHeader>
        <CardContent className="min-w-0 space-y-6">
          <EvaluationMatrix value={value} onChange={setValue} />

          <div className="space-y-4 border-t border-border pt-4">
            <div className="space-y-2">
              <Label
                htmlFor="recommendation-basis"
                className="text-left text-sm after:ml-1 after:content-[':']"
              >
                {tf("basis")}
              </Label>
              <Textarea
                id="recommendation-basis"
                value={basis}
                onChange={(e) => setBasis(e.target.value)}
                rows={2}
              />
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
