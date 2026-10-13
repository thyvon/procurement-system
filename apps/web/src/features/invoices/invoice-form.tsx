"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { toast } from "sonner";
import { ArrowLeft, Plus, Save } from "lucide-react";
import { Button } from "@/components/ui/button";
import { DataTableSkeleton } from "@/components/ui/data-table-skeleton";
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { RequiredMark } from "@/components/required-mark";
import {
  LineItemsTable,
  cellInputClass,
  cellInputRightClass,
  summaryInputClass,
} from "@/components/line-items-table";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { DatePicker } from "@/components/ui/date-picker";
import { unwrap, withAuth } from "@/lib/api-client";
import { formatMoney } from "@/lib/utils";
import {
  invoicesInvoicesShow,
  invoicesInvoicesStore,
  invoicesInvoicesUpdate,
} from "@/lib/api/invoice/invoice";
import type { StoreInvoiceRequest } from "@/lib/api/model/storeInvoiceRequest";
import type { InvoiceResource } from "@/lib/api/model/invoiceResource";
import { useMe } from "@/hooks/use-me";

type LineForm = {
  description: string;
  qty: string;
  unitPrice: string;
  discount: string;
};

type InvoiceFormValue = {
  invoiceNumber: string;
  supplierName: string;
  supplierCode: string;
  invoiceDate: Date | undefined;
  dueDate: Date | undefined;
  currency: "USD" | "KHR";
  exchangeRate: string;
  vatAmount: string;
  discountAmount: string;
  notes: string;
  lines: LineForm[];
};

const EMPTY_LINE: LineForm = {
  description: "",
  qty: "",
  unitPrice: "",
  discount: "",
};

const EMPTY_FORM: InvoiceFormValue = {
  invoiceNumber: "",
  supplierName: "",
  supplierCode: "",
  invoiceDate: undefined,
  dueDate: undefined,
  currency: "USD",
  exchangeRate: "",
  vatAmount: "",
  discountAmount: "",
  notes: "",
  lines: [{ ...EMPTY_LINE }],
};

function toNumber(value: string): number {
  const parsed = Number.parseFloat(value);
  return Number.isFinite(parsed) ? parsed : 0;
}

function toDateInput(date: Date | undefined): string | null {
  if (!date) return null;
  const year = date.getFullYear();
  const month = `${date.getMonth() + 1}`.padStart(2, "0");
  const day = `${date.getDate()}`.padStart(2, "0");
  return `${year}-${month}-${day}`;
}

function toDate(value: string | null | undefined): Date | undefined {
  if (!value) return undefined;
  const [year, month, day] = value.split("-").map(Number);
  if (!year || !month || !day) return undefined;
  return new Date(year, month - 1, day);
}

// Net of the per-line discount — mirrors the API's line_total.
function lineTotalOf(line: LineForm): number {
  return (
    toNumber(line.qty) * toNumber(line.unitPrice) - toNumber(line.discount)
  );
}

function hasLineError(line: LineForm): boolean {
  const gross = toNumber(line.qty) * toNumber(line.unitPrice);
  const discount = toNumber(line.discount);

  return (
    !line.description.trim() ||
    toNumber(line.qty) <= 0 ||
    toNumber(line.unitPrice) < 0 ||
    discount < 0 ||
    discount > gross
  );
}

function toForm(resource?: InvoiceResource | null): InvoiceFormValue {
  if (!resource) return { ...EMPTY_FORM, lines: [{ ...EMPTY_LINE }] };
  return {
    invoiceNumber: resource.invoiceNumber,
    supplierName: resource.supplierName,
    supplierCode: resource.supplierCode ?? "",
    invoiceDate: toDate(resource.invoiceDate),
    dueDate: toDate(resource.dueDate),
    currency: resource.currency === "KHR" ? "KHR" : "USD",
    exchangeRate: resource.exchangeRate ? String(resource.exchangeRate) : "",
    vatAmount: resource.vatAmount ? String(resource.vatAmount) : "",
    discountAmount: resource.discountAmount
      ? String(resource.discountAmount)
      : "",
    notes: resource.notes ?? "",
    lines:
      (resource.lines ?? []).length > 0
        ? (resource.lines ?? []).map((line) => ({
            description: line.description,
            qty: String(line.qty),
            unitPrice: String(line.unitPrice),
            discount: String(line.discount ?? ""),
          }))
        : [{ ...EMPTY_LINE }],
  };
}

interface InvoiceFormProps {
  invoiceId?: string;
  onBack: () => void;
}

export function InvoiceForm({ invoiceId, onBack }: InvoiceFormProps) {
  const t = useTranslations("invoices.form");
  const isEdit = Boolean(invoiceId);
  const qc = useQueryClient();
  const router = useRouter();
  const meQuery = useMe();
  const [form, setForm] = useState<InvoiceFormValue>(() => toForm(null));
  const [hydratedId, setHydratedId] = useState<string | null>(null);

  const showQuery = useQuery({
    queryKey: ["invoices", "detail", invoiceId],
    queryFn: async (): Promise<InvoiceResource> =>
      unwrap(await invoicesInvoicesShow(invoiceId!, withAuth())),
    enabled: isEdit,
  });

  if (isEdit && showQuery.data && hydratedId !== showQuery.data.id) {
    setHydratedId(showQuery.data.id);
    setForm(toForm(showQuery.data));
  }

  const permissions = (meQuery.data?.permissions ?? []) as string[];
  const canManage = permissions.includes("invoices.manage");
  const status = showQuery.data?.status ?? null;
  const isOwner =
    !isEdit ||
    (showQuery.data != null &&
      meQuery.data != null &&
      showQuery.data.createdById === meQuery.data.id);
  // Mirrors the API: invoices.manage AND owner, only while still a draft.
  const locked = !canManage || (isEdit && (status !== "draft" || !isOwner));

  const patch = (changes: Partial<InvoiceFormValue>) =>
    setForm((current) => ({ ...current, ...changes }));

  const patchLine = (index: number, changes: Partial<LineForm>) =>
    setForm((current) => ({
      ...current,
      lines: current.lines.map((line, i) =>
        i === index ? { ...line, ...changes } : line,
      ),
    }));

  const removeLine = (index: number) =>
    setForm((current) => ({
      ...current,
      lines: current.lines.filter((_, i) => i !== index),
    }));

  const addLine = () =>
    setForm((current) => ({
      ...current,
      lines: [...current.lines, { ...EMPTY_LINE }],
    }));

  const subtotal = form.lines.reduce((sum, line) => sum + lineTotalOf(line), 0);
  const total =
    subtotal - toNumber(form.discountAmount) + toNumber(form.vatAmount);

  // UX-only gate: the API's 422 is the source of truth.
  const missingLines = form.lines.some(hasLineError);
  const canSave =
    !!form.invoiceNumber.trim() &&
    !!form.supplierName.trim() &&
    !!form.invoiceDate &&
    form.lines.length > 0 &&
    !missingLines;

  const saveMutation = useMutation({
    mutationFn: async (): Promise<string> => {
      const payload: StoreInvoiceRequest = {
        invoice_number: form.invoiceNumber.trim(),
        supplier_name: form.supplierName.trim(),
        supplier_code: form.supplierCode.trim() || null,
        invoice_date: toDateInput(form.invoiceDate) ?? "",
        due_date: toDateInput(form.dueDate),
        currency: form.currency,
        exchange_rate:
          form.currency === "KHR" ? toNumber(form.exchangeRate) : null,
        vat_amount: toNumber(form.vatAmount),
        discount_amount: toNumber(form.discountAmount),
        notes: form.notes.trim() || null,
        lines: form.lines.map((line) => ({
          description: line.description.trim(),
          qty: toNumber(line.qty),
          unit_price: toNumber(line.unitPrice),
          discount: toNumber(line.discount),
        })),
      };

      if (isEdit) {
        unwrap(await invoicesInvoicesUpdate(invoiceId!, payload, withAuth()));
        return invoiceId!;
      }
      const created = unwrap<{ id: string }>(
        await invoicesInvoicesStore(payload, withAuth())
      );
      return created.id;
    },
    onSuccess: (id) => {
      qc.invalidateQueries({ queryKey: ["invoices"] });
      toast.success(isEdit ? t("updated") : t("created"));
      if (!isEdit) {
        router.push(`/invoices/${id}/edit`);
      }
    },
  });

  if (isEdit && showQuery.isPending) {
    return <DataTableSkeleton columns={6} actions={2} />;
  }

  if (isEdit && showQuery.isError) {
    return (
      <div className="mt-1 min-w-0 space-y-4">
        <Button variant="outline" onClick={onBack}>
          {t("back")}
        </Button>
        <p className="text-sm text-destructive">{t("loadFailed")}</p>
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
            aria-label={t("back")}
          >
            <ArrowLeft />
          </Button>
          <div>
            <h1 className="text-xl font-semibold tracking-tight">
              {isEdit ? t("editTitle") : t("newTitle")}
            </h1>
            <p className="text-sm text-muted-foreground">
              {isEdit ? t("editDescription") : t("newDescription")}
            </p>
          </div>
        </div>
        {isEdit ? (
          <span className="font-mono text-sm text-muted-foreground">
            {showQuery.data?.code}
          </span>
        ) : null}
      </div>

      <fieldset disabled={locked} className="min-w-0 border-0 p-0">
        <Card size="sm" className="min-w-0">
          <CardHeader>
            <CardTitle>{t("detailsCard")}</CardTitle>
          </CardHeader>
          <CardContent className="min-w-0 space-y-3">
            <div className="grid grid-cols-1 gap-x-6 gap-y-3 md:grid-cols-2">
              <div className="flex items-center gap-2">
                <Label
                  htmlFor="inv-number"
                  className="w-32 shrink-0 text-left after:ml-1 after:content-[':']"
                >
                  {t("invoiceNumber")} <RequiredMark />
                </Label>
                <Input
                  id="inv-number"
                  value={form.invoiceNumber}
                  onChange={(e) => patch({ invoiceNumber: e.target.value })}
                  placeholder={t("invoiceNumberPlaceholder")}
                  className="flex-1"
                />
              </div>

              <div className="flex items-center gap-2">
                <Label
                  htmlFor="inv-supplier"
                  className="w-32 shrink-0 text-left after:ml-1 after:content-[':']"
                >
                  {t("supplierName")} <RequiredMark />
                </Label>
                <Input
                  id="inv-supplier"
                  value={form.supplierName}
                  onChange={(e) => patch({ supplierName: e.target.value })}
                  placeholder={t("supplierNamePlaceholder")}
                  className="flex-1"
                />
              </div>

              <div className="flex items-center gap-2">
                <Label
                  htmlFor="inv-supplier-code"
                  className="w-32 shrink-0 text-left after:ml-1 after:content-[':']"
                >
                  {t("supplierCode")}
                </Label>
                <Input
                  id="inv-supplier-code"
                  value={form.supplierCode}
                  onChange={(e) => patch({ supplierCode: e.target.value })}
                  placeholder={t("supplierCodePlaceholder")}
                  className="flex-1 font-mono"
                />
              </div>

              <div className="flex items-center gap-2">
                <Label className="w-32 shrink-0 text-left after:ml-1 after:content-[':']">
                  {t("invoiceDate")} <RequiredMark />
                </Label>
                <DatePicker
                  value={form.invoiceDate}
                  onChange={(date) => patch({ invoiceDate: date })}
                  placeholder={t("invoiceDate")}
                  className="flex-1"
                />
              </div>

              {/* Currency sits in the left column, its Rate right beside it —
                  same label/control pattern as every other cell. */}
              <div className="flex items-center gap-2">
                <Label
                  htmlFor="inv-currency"
                  className="w-32 shrink-0 text-left after:ml-1 after:content-[':']"
                >
                  {t("currency")}
                </Label>
                <Select
                  value={form.currency}
                  onValueChange={(value) =>
                    patch({ currency: value === "KHR" ? "KHR" : "USD" })
                  }
                  items={[
                    { value: "USD", label: t("usd") },
                    { value: "KHR", label: t("khr") },
                  ]}
                >
                  <SelectTrigger id="inv-currency" className="min-w-0 flex-1">
                    <SelectValue placeholder={t("currency")} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="USD">{t("usd")}</SelectItem>
                    <SelectItem value="KHR">{t("khr")}</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div className="flex items-center gap-2">
                <Label
                  htmlFor="inv-rate"
                  className="w-32 shrink-0 text-left after:ml-1 after:content-[':']"
                >
                  {t("exchangeRate")}{" "}
                  {form.currency === "KHR" ? <RequiredMark /> : null}
                </Label>
                <Input
                  id="inv-rate"
                  type="number"
                  inputMode="decimal"
                  min={0}
                  step="any"
                  value={form.exchangeRate}
                  onChange={(e) => patch({ exchangeRate: e.target.value })}
                  placeholder={t("exchangeRatePlaceholder")}
                  disabled={form.currency !== "KHR"}
                  className="flex-1 text-right"
                />
              </div>

              <div className="flex items-center gap-2">
                <Label className="w-32 shrink-0 text-left after:ml-1 after:content-[':']">
                  {t("dueDate")}
                </Label>
                <DatePicker
                  value={form.dueDate}
                  onChange={(date) => patch({ dueDate: date })}
                  placeholder={t("dueDate")}
                  className="flex-1"
                  disabled={(date) =>
                    form.invoiceDate ? date < form.invoiceDate : false
                  }
                />
              </div>

              <div className="flex items-center gap-2 md:col-span-2">
                <Label
                  htmlFor="inv-notes"
                  className="w-32 shrink-0 text-left after:ml-1 after:content-[':']"
                >
                  {t("notes")}
                </Label>
                <Textarea
                  id="inv-notes"
                  value={form.notes}
                  onChange={(e) => patch({ notes: e.target.value })}
                  placeholder={t("notesPlaceholder")}
                  className="flex-1"
                  rows={2}
                />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card size="sm" className="mt-4 min-w-0">
          <CardHeader>
            <CardTitle>
              {t("lines")} <RequiredMark />
            </CardTitle>
          </CardHeader>
          <CardContent className="min-w-0">
            <LineItemsTable
              columns={[
                {
                  key: "description",
                  label: t("description"),
                  cell: (line, index) => (
                    <Input
                      value={line.description}
                      onChange={(e) =>
                        patchLine(index, { description: e.target.value })
                      }
                      placeholder={t("descriptionPlaceholder")}
                      aria-label={t("description")}
                      className={cellInputClass}
                    />
                  ),
                },
                {
                  key: "qty",
                  label: t("qty"),
                  width: "w-24",
                  align: "right",
                  cell: (line, index) => (
                    <Input
                      type="number"
                      min={0}
                      step="any"
                      inputMode="decimal"
                      value={line.qty}
                      onChange={(e) => patchLine(index, { qty: e.target.value })}
                      aria-label={t("qty")}
                      className={cellInputRightClass}
                    />
                  ),
                },
                {
                  key: "unitPrice",
                  label: t("unitPrice"),
                  width: "w-32",
                  align: "right",
                  cell: (line, index) => (
                    <Input
                      type="number"
                      min={0}
                      step="any"
                      inputMode="decimal"
                      value={line.unitPrice}
                      onChange={(e) =>
                        patchLine(index, { unitPrice: e.target.value })
                      }
                      aria-label={t("unitPrice")}
                      className={cellInputRightClass}
                    />
                  ),
                },
                {
                  key: "discount",
                  label: t("discountAmount"),
                  width: "w-28",
                  align: "right",
                  cell: (line, index) => (
                    <Input
                      type="number"
                      min={0}
                      step="any"
                      inputMode="decimal"
                      value={line.discount ?? ""}
                      onChange={(e) =>
                        patchLine(index, { discount: e.target.value })
                      }
                      aria-label={t("discountAmount")}
                      className={cellInputRightClass}
                    />
                  ),
                },
                {
                  key: "lineTotal",
                  label: t("lineTotal"),
                  width: "w-28",
                  align: "right",
                  cell: (line) => (
                    <div className="flex h-7 items-center justify-end text-xs tabular-nums">
                      {formatMoney(lineTotalOf(line), form.currency)}
                    </div>
                  ),
                },
              ]}
              rows={form.lines}
              rowKey={(_, index) => String(index)}
              showIndex
              disabled={locked}
              onRemove={removeLine}
              removeLabel={t("removeLine")}
              summary={[
                {
                  key: "subtotal",
                  label: t("subtotal"),
                  value: formatMoney(subtotal, form.currency),
                },
                {
                  key: "discount",
                  label: t("discountAmount"),
                  value: (
                    <Input
                      type="number"
                      inputMode="decimal"
                      min={0}
                      step="any"
                      value={form.discountAmount}
                      onChange={(e) =>
                        patch({ discountAmount: e.target.value })
                      }
                      aria-label={t("discountAmount")}
                      className={summaryInputClass}
                    />
                  ),
                },
                {
                  key: "vat",
                  label: t("vatAmount"),
                  value: (
                    <Input
                      type="number"
                      inputMode="decimal"
                      min={0}
                      step="any"
                      value={form.vatAmount}
                      onChange={(e) => patch({ vatAmount: e.target.value })}
                      aria-label={t("vatAmount")}
                      className={summaryInputClass}
                    />
                  ),
                },
                {
                  key: "total",
                  label: t("total"),
                  value: formatMoney(total, form.currency),
                  strong: true,
                },
              ]}
              footerLeading={
                <Button type="button" size="xs" onClick={addLine}>
                  <Plus className="size-3" />
                  {t("addLine")}
                </Button>
              }
            />
          </CardContent>
        </Card>
      </fieldset>

      <div className="flex items-center justify-end gap-2">
        <Button
          variant="outline"
          onClick={onBack}
          disabled={saveMutation.isPending}
        >
          {t("cancel")}
        </Button>
        {locked ? null : (
          <Button
            onClick={() => saveMutation.mutate()}
            disabled={saveMutation.isPending || !canSave}
          >
            <Save />
            <span>{saveMutation.isPending ? t("saving") : t("save")}</span>
          </Button>
        )}
      </div>
    </div>
  );
}
