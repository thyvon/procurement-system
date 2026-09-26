"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { toast } from "sonner";
import { ArrowLeft, Check, Plus, RotateCcw, Save, X } from "lucide-react";
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
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { RequiredMark } from "@/components/required-mark";
import {
  purchaseOrdersEvaluationsShow,
  purchaseOrdersEvaluationsStore,
  purchaseOrdersEvaluationsUpdate,
} from "@/lib/api/evaluation/evaluation";
import { approvalsRequestsIndex } from "@/lib/api/approval-request/approval-request";
import type { EvaluationResource } from "@/lib/api/model/evaluationResource";
import type { StoreEvaluationRequest } from "@/lib/api/model/storeEvaluationRequest";
import type { UpdateEvaluationRequest } from "@/lib/api/model/updateEvaluationRequest";
import { unwrap, unwrapWithMeta, withAuth } from "@/lib/api-client";
import { useMe } from "@/hooks/use-me";
import {
  EVALUATION_SUBJECT,
  parseApprovalRequest,
  type ApprovalAction,
  type ApprovalRequestView,
} from "@/features/approvals/approval-types";
import { ApprovalTimeline } from "@/features/approvals/components/approval-timeline";
import { ApprovalActionDialog } from "@/features/approvals/components/approval-action-dialog";
import { SubmitApprovalPanel } from "@/features/approvals/components/submit-approval-panel";
import {
  EvaluationMatrix,
  SEED_QUOTATION_COUNT,
  createEmptyQuotation,
  emptyPricing,
  fromEvaluation,
  type EvaluationMatrixValue,
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
  basis: string,
  currency: string,
  exchangeRate: string
): StoreEvaluationRequest {
  return {
    currency: currency as StoreEvaluationRequest["currency"],
    exchange_rate: Number.parseFloat(exchangeRate) || 1,
    recommendation_basis: basis.trim(),
    items: value.items.map((item) => ({
      item_code: item.itemCode.trim(),
      description: item.description.trim(),
      qty: Number(item.qty),
      uom: item.uom.trim(),
    })),
    quotations: value.quotations.map((panel) => ({
      supplier_code: panel.supplierCode,
      supplier_name: panel.supplierName,
      supplier_phone: panel.phone.trim(),
      supplier_address: panel.address.trim(),
      discount: optionalNumber(panel.totals.discount) ?? 0,
      vat: optionalNumber(panel.totals.vat) ?? 0,
      price: optionalText(panel.criteria.price),
      quality: optionalText(panel.criteria.quality),
      lead_time: optionalText(panel.criteria.leadTime),
      warranty: optionalText(panel.criteria.warranty),
      payment_terms: optionalText(panel.criteria.paymentTerms),
      other_remarks: optionalText(panel.criteria.otherRemarks),
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

function round(value: number, decimals: number): number {
  const factor = 10 ** decimals;
  return Math.round(value * factor) / factor;
}

/**
 * Mirrors the server's awarded_total (EvaluationService::syncMatrix): each
 * quotation contributes its winning-line subtotal plus its pro-rated share of
 * that quotation's discount and VAT — a sole winner's contribution equals its
 * grand total.
 */
function computeAwardedTotal(value: EvaluationMatrixValue): number {
  let awarded = 0;

  for (const panel of value.quotations) {
    let subtotal = 0;
    let winning = 0;

    for (const item of value.items) {
      const qty = Number.parseFloat(item.qty);
      const pricing = panel.pricing[item.uid];
      const unitCost = Number.parseFloat(pricing?.unitCost ?? "");
      if (!Number.isFinite(qty) || !Number.isFinite(unitCost)) continue;

      const line = round(qty * unitCost, 4);
      subtotal += line;
      if (pricing?.selected) winning += line;
    }

    const discount = Number.parseFloat(panel.totals.discount) || 0;
    const vat = Number.parseFloat(panel.totals.vat) || 0;
    const share = subtotal > 0 ? winning / subtotal : 0;
    awarded += round(winning - discount * share + vat * share, 2);
  }

  return round(awarded, 2);
}

function serializeForm(
  value: EvaluationMatrixValue,
  basis: string,
  currency: string,
  exchangeRate: string
): string {
  return JSON.stringify({ value, basis, currency, exchangeRate });
}

/**
 * Draft preview mirror of the server-side USD conversion
 * (App\Support\Currency::toUsd) — the approval engine re-reads the
 * authoritative amount on submit.
 */
function toUsdDraft(amount: number, currency: string, exchangeRate: string): number {
  if (currency !== "KHR") return amount;
  const rate = Number.parseFloat(exchangeRate);
  if (!Number.isFinite(rate) || rate <= 0) return amount;
  return round(amount / rate, 2);
}

function useDebouncedValue<T>(value: T, delayMs: number): T {
  const [debounced, setDebounced] = useState(value);

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delayMs);
    return () => clearTimeout(timer);
  }, [value, delayMs]);

  return debounced;
}

interface EvaluationFormProps {
  onBack: () => void;
  evaluationId?: string;
}

export function EvaluationForm({ onBack, evaluationId }: EvaluationFormProps) {
  const tf = useTranslations("purchaseOrders.form");
  const ta = useTranslations("approvals.form");
  const td = useTranslations("approvals.detail");
  const isEdit = Boolean(evaluationId);
  const qc = useQueryClient();
  const router = useRouter();
  const me = useMe();
  const [value, setValue] = useState<EvaluationMatrixValue>(createEmptyValue);
  const [basis, setBasis] = useState("");
  const [currency, setCurrency] = useState("USD");
  const [exchangeRate, setExchangeRate] = useState("1");
  const [hydratedId, setHydratedId] = useState<string | null>(null);
  const [baseline, setBaseline] = useState<string | null>(null);
  const pendingSaveRef = useRef("");
  const skipNavRef = useRef(false);
  const [action, setAction] = useState<ApprovalAction | null>(null);

  const showQuery = useQuery({
    queryKey: ["evaluations", "detail", evaluationId],
    queryFn: async (): Promise<EvaluationResource> =>
      unwrap(await purchaseOrdersEvaluationsShow(evaluationId!, withAuth())),
    enabled: isEdit,
  });

  const approvalQuery = useQuery({
    queryKey: ["approvals", "requests", "subject", EVALUATION_SUBJECT, evaluationId],
    queryFn: async (): Promise<ApprovalRequestView | null> => {
      const envelope = unwrapWithMeta<unknown>(
        await approvalsRequestsIndex(
          { subject_type: EVALUATION_SUBJECT, subject_id: evaluationId! },
          withAuth()
        )
      );
      const rows = (envelope.data ?? []) as unknown[];
      return rows.length > 0 ? parseApprovalRequest(rows[0]) : null;
    },
    enabled: isEdit,
  });

  if (isEdit && showQuery.data && hydratedId !== showQuery.data.id) {
    const nextValue = fromEvaluation(showQuery.data);
    const nextBasis = showQuery.data.recommendationBasis ?? "";
    const nextCurrency = showQuery.data.currency ?? "USD";
    const nextExchangeRate = String(showQuery.data.exchangeRate ?? 1);
    setHydratedId(showQuery.data.id);
    setValue(nextValue);
    setBasis(nextBasis);
    setCurrency(nextCurrency);
    setExchangeRate(nextExchangeRate);
    setBaseline(
      serializeForm(nextValue, nextBasis, nextCurrency, nextExchangeRate)
    );
  }

  const saveMutation = useMutation({
    mutationFn: async (): Promise<EvaluationResource> => {
      const payload = toPayload(value, basis, currency, exchangeRate);
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
    onSuccess: (result) => {
      setBaseline(pendingSaveRef.current);
      qc.invalidateQueries({ queryKey: ["evaluations"] });
      qc.invalidateQueries({ queryKey: ["approvals", "preview"] });
      if (skipNavRef.current) {
        // Save chained into a submit — the panel owns the toast + navigation.
        return;
      }
      toast.success(isEdit ? tf("updated") : tf("created"));
      if (!isEdit) {
        const id = result?.id;
        if (id) {
          router.push(`/purchase-orders/evaluations/${id}/edit`);
        } else {
          onBack();
        }
      }
    },
  });

  const evaluationStatus = showQuery.data?.status ?? null;
  const inReview = evaluationStatus === "in_review";
  const approvalRequest = approvalQuery.data ?? null;
  const currentStep = approvalRequest?.currentStep ?? null;
  const isAssignee =
    approvalRequest?.status === "pending" &&
    currentStep?.assigneeId != null &&
    me.data?.id === currentStep.assigneeId;
  const allowedActions =
    approvalRequest?.steps.find((step) => step.position === currentStep?.position)
      ?.allowedActions ?? [];

  const currentSnapshot = useMemo(
    () => serializeForm(value, basis, currency, exchangeRate),
    [value, basis, currency, exchangeRate]
  );
  const isDirty = isEdit && baseline !== null && currentSnapshot !== baseline;
  const awardedTotal = useMemo(
    () => (isEdit && !hydratedId ? null : computeAwardedTotal(value)),
    [isEdit, hydratedId, value]
  );
  const draftAmount = useDebouncedValue(
    awardedTotal === null
      ? null
      : toUsdDraft(awardedTotal, currency, exchangeRate),
    500
  );

  const addQuotation = () => {
    const pricing = Object.fromEntries(
      value.items.map((item) => [item.uid, emptyPricing()])
    );
    setValue({
      ...value,
      quotations: [...value.quotations, { ...createEmptyQuotation(), pricing }],
    });
  };

  const validateForm = (): boolean => {
    const missingSuppliers = value.quotations.some((q) => !q.supplierCode.trim());
    if (missingSuppliers) {
      toast.error(tf("missingSupplier"));
      return false;
    }
    const missingAddresses = value.quotations.some((q) => !q.address.trim());
    if (missingAddresses) {
      toast.error(tf("missingAddress"));
      return false;
    }
    const missingPhones = value.quotations.some((q) => !q.phone.trim());
    if (missingPhones) {
      toast.error(tf("missingPhone"));
      return false;
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
      return false;
    }
    if (!basis.trim()) {
      toast.error(tf("missingBasis"));
      return false;
    }
    const gap = findWinnerGap(value);
    if (gap) {
      toast.error(tf("noWinner", { item: gap }));
      return false;
    }
    return true;
  };

  const handleSave = () => {
    if (!validateForm()) return;
    pendingSaveRef.current = serializeForm(value, basis, currency, exchangeRate);
    saveMutation.mutate();
  };

  const handleCurrencyChange = (next: string | null) => {
    if (!next) return;
    setCurrency(next);
    if (next === "KHR") {
      if (!(Number.parseFloat(exchangeRate) > 1)) setExchangeRate("4100");
    } else {
      setExchangeRate("1");
    }
  };

  /**
   * Create mode: validates + saves and returns the new id so the approval
   * panel can chain straight into submitting. Returns null when the form
   * fails validation (toasts already explain why) or the save errors.
   */
  const prepareDocument = async (): Promise<string | null> => {
    if (isEdit) return evaluationId ?? null;
    if (!validateForm()) return null;

    pendingSaveRef.current = serializeForm(value, basis, currency, exchangeRate);
    skipNavRef.current = true;
    try {
      const created = await saveMutation.mutateAsync();
      return created?.id ?? null;
    } finally {
      skipNavRef.current = false;
    }
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
              {isEdit ? tf("editTitle") : tf("createTitle")}
            </h1>
            <p className="text-sm text-muted-foreground">
              {isEdit ? tf("editDescription") : tf("createDescription")}
            </p>
          </div>
        </div>
      </div>

      {inReview ? (
        <div className="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm">
          {ta("inReview")}
        </div>
      ) : evaluationStatus === "rejected" ? (
        <div className="rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm">
          {ta("rejectedState")}
        </div>
      ) : evaluationStatus === "returned" ? (
        <div className="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm">
          {ta("returnedState")}
        </div>
      ) : null}

      <fieldset disabled={inReview} className="mx-0 min-w-0 border-0 p-0">
        <Card size="sm" className="min-w-0 text-xs">
          <CardHeader>
            <CardTitle className="text-xs">{tf("title")}</CardTitle>
            <CardAction>
              <Button type="button" onClick={addQuotation}>
                <Plus className="mr-1.5 size-3.5" />
                {tf("addQuotation")}
              </Button>
            </CardAction>
          </CardHeader>
          <CardContent className="min-w-0 space-y-3">
            <div className="flex flex-wrap items-center gap-x-6 gap-y-3 border-b border-border pb-3">
              <div className="flex items-center gap-2">
                <Label
                  htmlFor="evaluation-currency"
                  className="text-left text-xs after:ml-1 after:content-[':']"
                >
                  {tf("currency")}
                </Label>
                <Select value={currency} onValueChange={handleCurrencyChange}>
                  <SelectTrigger
                    id="evaluation-currency"
                    className="h-8 w-44 text-xs"
                  >
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="USD">{tf("currencyUsd")}</SelectItem>
                    <SelectItem value="KHR">{tf("currencyKhr")}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="flex items-center gap-2">
                <Label
                  htmlFor="evaluation-exchange-rate"
                  className="text-left text-xs after:ml-1 after:content-[':']"
                >
                  {tf("exchangeRate")}
                </Label>
                <Input
                  id="evaluation-exchange-rate"
                  value={exchangeRate}
                  onChange={(e) => setExchangeRate(e.target.value)}
                  disabled={currency === "USD"}
                  inputMode="decimal"
                  className="h-8 w-32 text-right text-xs md:text-xs"
                />
                {currency === "KHR" ? (
                  <span className="text-xs text-muted-foreground">
                    {tf("exchangeRateHint")}
                  </span>
                ) : null}
              </div>
            </div>

            <EvaluationMatrix
              value={value}
              currency={currency}
              onChange={setValue}
            />

            <div className="space-y-2 border-t border-border pt-3">
              <Label
                htmlFor="recommendation-basis"
                className="text-left text-xs after:ml-1 after:content-[':']"
              >
                {tf("basis")} <RequiredMark />
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
      </fieldset>

      {approvalRequest ? (
        <div className="space-y-4">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <h2 className="text-sm font-medium">{ta("reviewTitle")}</h2>
            <Button
              variant="outline"
              onClick={() => router.push(`/approvals/${approvalRequest.id}`)}
            >
              {ta("viewRequest")}
            </Button>
          </div>

          <ApprovalTimeline request={approvalRequest} />

          {isAssignee ? (
            <div className="flex flex-wrap items-center justify-end gap-2">
              {allowedActions.includes("approve") ? (
                <Button onClick={() => setAction("approve")}>
                  <Check />
                  {td("approve")}
                </Button>
              ) : null}
              {allowedActions.includes("return") ? (
                <Button variant="outline" onClick={() => setAction("return")}>
                  <RotateCcw />
                  {td("return")}
                </Button>
              ) : null}
              {allowedActions.includes("reject") ? (
                <Button
                  variant="destructive"
                  onClick={() => setAction("reject")}
                >
                  <X />
                  {td("reject")}
                </Button>
              ) : null}
            </div>
          ) : null}
        </div>
      ) : null}

      {inReview ? null : (
        <SubmitApprovalPanel
          subjectType={EVALUATION_SUBJECT}
          subjectId={evaluationId ?? null}
          amount={draftAmount}
          dirty={isDirty}
          disabled={saveMutation.isPending}
          prepareDocument={prepareDocument}
          actions={
            <Button onClick={handleSave} disabled={saveMutation.isPending}>
              <Save />
              <span>{saveMutation.isPending ? tf("saving") : tf("save")}</span>
            </Button>
          }
        />
      )}

      {approvalRequest && action ? (
        <ApprovalActionDialog
          key={action}
          request={approvalRequest}
          action={action}
          open
          onOpenChange={(open) => {
            if (!open) setAction(null);
          }}
        />
      ) : null}
    </div>
  );
}
