"use client";

import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { toast } from "sonner";
import { ArrowLeft, Plus, Save } from "lucide-react";
import { Button } from "@/components/ui/button";
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
import {
  Combobox,
  ComboboxContent,
  ComboboxEmpty,
  ComboboxInput,
  ComboboxItem,
  ComboboxList,
} from "@/components/ui/combobox";
import { unwrap, withAuth } from "@/lib/api-client";
import { cn, formatMoney } from "@/lib/utils";
import {
  invoicesInvoicesShow,
  invoicesInvoicesStore,
  invoicesInvoicesUpdate,
} from "@/lib/api/invoice/invoice";
import { organizationCampusesIndex } from "@/lib/api/campus/campus";
import { organizationDivisionsIndex } from "@/lib/api/division/division";
import { organizationDepartmentsIndex } from "@/lib/api/department/department";
import type { StoreInvoiceRequest } from "@/lib/api/model/storeInvoiceRequest";
import type { InvoiceResource } from "@/lib/api/model/invoiceResource";
import { useMe } from "@/hooks/use-me";
import { InvoiceFormSkeleton } from "./components/invoice-form-skeleton";
import {
  paymentTermOptions,
  resolveLineDiscounts,
  sumLineDiscounts,
  txTypeOptions,
} from "./invoice-helpers";

type LineForm = {
  description: string;
  uom: string;
  campus: string;
  division: string;
  department: string;
  location: string;
  purpose: string;
  qty: string;
  unitPrice: string;
  discount: string;
};

type TransactionType = "petty_cash" | "advance" | "credit";
type PaymentTerm = "non_credit" | "credit_1w" | "credit_2w" | "credit_1m";

type InvoiceFormValue = {
  invoiceNumber: string;
  supplierName: string;
  supplierCode: string;
  supplierPhone: string;
  supplierAddress: string;
  transactionType: TransactionType | "";
  cashReference: string;
  paymentTerm: PaymentTerm | "";
  invoiceDate: Date | undefined;
  dueDate: Date | undefined;
  currency: "USD" | "KHR";
  exchangeRate: string;
  /** Hydrated legacy amount — only used while vatPercent is empty. */
  vatAmount: string;
  vatPercent: string;
  discountAmount: string;
  notes: string;
  lines: LineForm[];
};

const EMPTY_LINE: LineForm = {
  description: "",
  uom: "",
  campus: "",
  division: "",
  department: "",
  location: "",
  purpose: "",
  qty: "",
  unitPrice: "",
  discount: "",
};

const CASH_REFERENCE_OPTIONS: { value: string; label: string }[] = [];

const EMPTY_FORM: InvoiceFormValue = {
  invoiceNumber: "",
  supplierName: "",
  supplierCode: "",
  supplierPhone: "",
  supplierAddress: "",
  transactionType: "",
  cashReference: "",
  paymentTerm: "",
  invoiceDate: undefined,
  dueDate: undefined,
  currency: "USD",
  exchangeRate: "",
  vatAmount: "",
  vatPercent: "",
  discountAmount: "",
  notes: "",
  lines: [{ ...EMPTY_LINE }],
};

const fieldLabelClass =
  "w-32 shrink-0 text-left after:ml-1 after:content-[':']";

/** Combobox for the campus/division/department line cells. */
function LineCombobox({
  value,
  options,
  placeholder,
  ariaLabel,
  disabled = false,
  onValueChange,
}: {
  value: string;
  options: { value: string; label: string }[];
  placeholder: string;
  ariaLabel: string;
  disabled?: boolean;
  onValueChange: (value: string | null) => void;
}) {
  return (
    <Combobox items={options} value={value || null} onValueChange={onValueChange}>
      <ComboboxInput
        placeholder={placeholder}
        aria-label={ariaLabel}
        disabled={disabled}
        // cellInputClass lands on the InputGroup wrapper; the inner input
        // keeps text-sm unless forced down. The chevron trigger stays
        // visible so users can tell these cells are pickers, not free text.
        className={cn(
          cellInputClass,
          "[&_input]:text-sm [&_input]:placeholder:text-sm"
        )}
      />
      <ComboboxContent>
        <ComboboxEmpty>{placeholder}</ComboboxEmpty>
        <ComboboxList>
          {(option) => (
            <ComboboxItem key={option.value} value={option.value}>
              {option.label}
            </ComboboxItem>
          )}
        </ComboboxList>
      </ComboboxContent>
    </Combobox>
  );
}

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

function round(value: number, decimals: number): number {
  const factor = 10 ** decimals;
  return Math.round(value * factor) / factor;
}

/**
 * Draft preview mirror of the server-side USD conversion
 * (App\Support\Currency::toUsd) — the API recomputes the authoritative
 * grand_total_usd on save.
 */
function toUsdDraft(
  amount: number,
  currency: string,
  exchangeRate: string
): number {
  if (currency !== "KHR") return amount;
  const rate = Number.parseFloat(exchangeRate);
  if (!Number.isFinite(rate) || rate <= 0) return amount;
  return round(amount / rate, 2);
}

function toForm(resource?: InvoiceResource | null): InvoiceFormValue {
  if (!resource) return { ...EMPTY_FORM, lines: [{ ...EMPTY_LINE }] };
  const lines = resource.lines ?? [];
  return {
    invoiceNumber: resource.invoiceNumber,
    supplierName: resource.supplierName,
    supplierCode: resource.supplierCode ?? "",
    supplierPhone: resource.supplierPhone ?? "",
    supplierAddress: resource.supplierAddress ?? "",
    transactionType: (resource.transactionType as TransactionType | null) ?? "",
    cashReference: resource.cashReference ?? "",
    paymentTerm: (resource.paymentTerm as PaymentTerm | null) ?? "",
    invoiceDate: toDate(resource.invoiceDate),
    dueDate: toDate(resource.dueDate),
    currency: resource.currency === "KHR" ? "KHR" : "USD",
    exchangeRate: resource.exchangeRate ? String(resource.exchangeRate) : "",
    vatAmount: resource.vatAmount ? String(resource.vatAmount) : "",
    vatPercent: resource.vatPercent != null ? String(resource.vatPercent) : "",
    discountAmount: resource.discountAmount
      ? String(resource.discountAmount)
      : "",
    notes: resource.notes ?? "",
    lines:
      lines.length > 0
        ? lines.map((line) => ({
            description: line.description,
            uom: line.uom ?? "",
            campus: line.campus ?? "",
            division: line.division ?? "",
            department: line.department ?? "",
            location: line.location ?? "",
            purpose: line.purpose ?? "",
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
  const txTypeSelectOptions = txTypeOptions(t);
  const paymentTermSelectOptions = paymentTermOptions(t);
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

  const campusesQuery = useQuery({
    queryKey: ["campuses"],
    queryFn: async () =>
      unwrap<{ id: string; name: string; isActive: boolean }[]>(
        await organizationCampusesIndex(withAuth())
      ),
    staleTime: 5 * 60 * 1000,
  });
  const divisionsQuery = useQuery({
    queryKey: ["divisions"],
    queryFn: async () =>
      unwrap<{ id: string; name: string; isActive: boolean }[]>(
        await organizationDivisionsIndex(withAuth())
      ),
    staleTime: 5 * 60 * 1000,
  });
  const departmentsQuery = useQuery({
    queryKey: ["departments"],
    queryFn: async () =>
      unwrap<{ id: string; name: string; isActive: boolean; divisionIds?: string[] }[]>(
        await organizationDepartmentsIndex(withAuth())
      ),
    staleTime: 5 * 60 * 1000,
  });

  const campusOptions = (campusesQuery.data ?? [])
    .filter((c) => c.isActive)
    .map((c) => ({ value: c.name, label: c.name }));
  const divisionOptions = (divisionsQuery.data ?? [])
    .filter((d) => d.isActive)
    .map((d) => ({ value: d.name, label: d.name }));
  // Cascade: the department list stays empty until a division is picked,
  // then narrows to that division's linked departments.
  const divisionIdByName = new Map(
    (divisionsQuery.data ?? []).map((d) => [d.name, d.id])
  );
  const departmentOptionsFor = (divisionName: string) => {
    if (!divisionName) return [];
    const divisionId = divisionIdByName.get(divisionName);
    if (divisionId == null) return [];
    return (departmentsQuery.data ?? [])
      .filter(
        (d) => d.isActive && (d.divisionIds ?? []).includes(divisionId)
      )
      .map((d) => ({ value: d.name, label: d.name }));
  };
  const departmentNameSetFor = (divisionName: string) =>
    new Set(departmentOptionsFor(divisionName).map((o) => o.value));

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
    setForm((current) => {
      const last = current.lines[current.lines.length - 1];
      // Accounting lines usually share the same org/location/purpose; only
      // description, uom and money start blank.
      return {
        ...current,
        lines: [
          ...current.lines,
          {
            ...EMPTY_LINE,
            campus: last?.campus ?? "",
            division: last?.division ?? "",
            department: last?.department ?? "",
            location: last?.location ?? "",
            purpose: last?.purpose ?? "",
          },
        ],
      };
    });

  const subtotal = form.lines.reduce((sum, line) => sum + lineTotalOf(line), 0);
  // When lines carry their own discounts, the summary shows their sum as a
  // read-only figure; the header discount input is only for legacy invoices.
  const lineDiscountTotal = sumLineDiscounts(form.lines);
  // Mirror of the API's resolveVat: percent of the discounted subtotal, with
  // the hydrated amount as the legacy fallback while no percent is entered.
  const vatRate =
    form.vatPercent.trim() === "" ? null : toNumber(form.vatPercent);
  const { headerDiscount } = resolveLineDiscounts(
    lineDiscountTotal,
    toNumber(form.discountAmount)
  );
  const discountedBase = subtotal - headerDiscount;
  const vat =
    vatRate === null
      ? toNumber(form.vatAmount)
      : round((discountedBase * vatRate) / 100, 2);
  const total = discountedBase + vat;

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
        supplier_phone: form.supplierPhone.trim() || null,
        supplier_address: form.supplierAddress.trim() || null,
        transaction_type: form.transactionType || null,
        cash_reference: form.cashReference.trim() || null,
        payment_term: form.paymentTerm || null,
        invoice_date: toDateInput(form.invoiceDate) ?? "",
        due_date: toDateInput(form.dueDate),
        currency: form.currency,
        exchange_rate:
          form.currency === "KHR" ? toNumber(form.exchangeRate) : null,
        vat_amount: vat,
        vat_percent: vatRate,
        discount_amount: headerDiscount,
        notes: form.notes.trim() || null,
        lines: form.lines.map((line) => ({
          description: line.description.trim(),
          uom: line.uom.trim() || null,
          campus: line.campus.trim() || null,
          division: line.division.trim() || null,
          department: line.department.trim() || null,
          location: line.location.trim() || null,
          purpose: line.purpose.trim() || null,
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
    return <InvoiceFormSkeleton />;
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
            {/* Two columns only from lg — with the 256px sidebar the md
                columns are ~176px. */}
            <div className="grid grid-cols-1 gap-x-16 gap-y-3 lg:grid-cols-2">
              <div className="space-y-3">
                <div className="flex items-center gap-2">
                  <Label className={fieldLabelClass}>
                    {t("invoiceDate")} <RequiredMark />
                  </Label>
                  <DatePicker
                    value={form.invoiceDate}
                    onChange={(date) => patch({ invoiceDate: date })}
                    placeholder={t("invoiceDate")}
                    className="flex-1"
                  />
                </div>

                <div className="flex items-center gap-2">
                  <Label htmlFor="inv-number" className={fieldLabelClass}>
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

                {/* Two label-aligned rows below xl, one inline row from xl. */}
                <div className="grid grid-cols-[auto_1fr] items-center gap-2 xl:flex">
                  <Label htmlFor="inv-currency" className={fieldLabelClass}>
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
                    <SelectTrigger
                      id="inv-currency"
                      className="min-w-24 w-full flex-1"
                    >
                      <SelectValue placeholder={t("currency")} />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="USD">{t("usd")}</SelectItem>
                      <SelectItem value="KHR">{t("khr")}</SelectItem>
                    </SelectContent>
                  </Select>

                  <Label
                    htmlFor="inv-rate"
                    className={`${fieldLabelClass} xl:w-auto`}
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
                    className="min-w-24 flex-1 text-right"
                  />
                </div>

                <div className="flex items-center gap-2">
                  <Label className={fieldLabelClass}>
                    {t("transactionType")}
                  </Label>
                  <Select
                    value={form.transactionType || null}
                    onValueChange={(value) =>
                      patch({ transactionType: (value as TransactionType) || "" })
                    }
                    items={txTypeSelectOptions}
                  >
                    <SelectTrigger className="w-full flex-1">
                      <SelectValue placeholder={t("transactionTypePlaceholder")} />
                    </SelectTrigger>
                    <SelectContent>
                      {txTypeSelectOptions.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                          {option.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                {/* Options are wired from the DB later — the combobox renders
                    its empty state until then. */}
                <div className="flex items-center gap-2">
                  <Label className={fieldLabelClass}>{t("cashReference")}</Label>
                  <Combobox
                    items={CASH_REFERENCE_OPTIONS}
                    value={form.cashReference || null}
                    onValueChange={(value) =>
                      patch({ cashReference: value ?? "" })
                    }
                  >
                    <ComboboxInput
                      placeholder={t("cashReferencePlaceholder")}
                      className="flex-1"
                    />
                    <ComboboxContent>
                      <ComboboxEmpty>
                        {t("cashReferenceEmpty")}
                      </ComboboxEmpty>
                      <ComboboxList>
                        {(option) => (
                          <ComboboxItem
                            key={option.value}
                            value={option.value}
                          >
                            {option.label}
                          </ComboboxItem>
                        )}
                      </ComboboxList>
                    </ComboboxContent>
                  </Combobox>
                </div>
              </div>

              {/* -ml-8/pl-8 centers the border in the grid's 64px gap. */}
              <div className="space-y-3 lg:-ml-8 lg:border-l lg:border-border lg:pl-8">
                <div className="flex items-center gap-2">
                  <Label htmlFor="inv-supplier" className={fieldLabelClass}>
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
                  <Label htmlFor="inv-phone" className={fieldLabelClass}>
                    {t("supplierPhone")}
                  </Label>
                  <Input
                    id="inv-phone"
                    value={form.supplierPhone}
                    onChange={(e) => patch({ supplierPhone: e.target.value })}
                    placeholder={t("supplierPhonePlaceholder")}
                    className="flex-1"
                  />
                </div>

                <div className="flex items-center gap-2">
                  <Label htmlFor="inv-address" className={fieldLabelClass}>
                    {t("supplierAddress")}
                  </Label>
                  <Textarea
                    id="inv-address"
                    value={form.supplierAddress}
                    onChange={(e) => patch({ supplierAddress: e.target.value })}
                    placeholder={t("supplierAddressPlaceholder")}
                    className="flex-1"
                    rows={2}
                  />
                </div>

                {/* Two label-aligned rows below xl, one inline row from xl —
                    the same pairing shape as Currency + Rate in the left
                    column. */}
                <div className="grid grid-cols-[auto_1fr] items-center gap-2 xl:flex">
                  <Label htmlFor="inv-payment-term" className={fieldLabelClass}>
                    {t("paymentTerm")}
                  </Label>
                  <Select
                    value={form.paymentTerm || null}
                    onValueChange={(value) =>
                      patch({ paymentTerm: (value as PaymentTerm) || "" })
                    }
                    items={paymentTermSelectOptions}
                  >
                    <SelectTrigger
                      id="inv-payment-term"
                      className="min-w-24 w-full flex-1"
                    >
                      <SelectValue placeholder={t("paymentTermPlaceholder")} />
                    </SelectTrigger>
                    <SelectContent>
                      {paymentTermSelectOptions.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                          {option.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>

                  <Label htmlFor="inv-vat-percent" className={`${fieldLabelClass} xl:w-auto`}>
                    {t("vatPercent")}
                  </Label>
                  <Input
                    id="inv-vat-percent"
                    type="number"
                    inputMode="decimal"
                    min={0}
                    max={100}
                    step="any"
                    value={form.vatPercent}
                    onChange={(e) => patch({ vatPercent: e.target.value })}
                    placeholder="0"
                    className="min-w-24 flex-1 text-right"
                  />
                </div>
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
                    <Textarea
                      value={line.description}
                      onChange={(e) =>
                        patchLine(index, { description: e.target.value })
                      }
                      placeholder={t("descriptionPlaceholder")}
                      aria-label={t("description")}
                      rows={1}
                      className="min-h-8! rounded-none border-border/60 bg-background px-1.5 py-[5px] text-sm shadow-none resize-none placeholder:text-sm md:text-sm"
                    />
                  ),
                },
                {
                  key: "campus",
                  label: t("campus"),
                  width: "w-32",
                  cell: (line, index) => (
                    <LineCombobox
                      value={line.campus}
                      options={campusOptions}
                      placeholder={t("campusPlaceholder")}
                      ariaLabel={t("campus")}
                      onValueChange={(value) =>
                        patchLine(index, { campus: value ?? "" })
                      }
                    />
                  ),
                },
                {
                  key: "division",
                  label: t("division"),
                  width: "w-32",
                  cell: (line, index) => (
                    <LineCombobox
                      value={line.division}
                      options={divisionOptions}
                      placeholder={t("divisionPlaceholder")}
                      ariaLabel={t("division")}
                      onValueChange={(value) => {
                        const division = value ?? "";
                        // Cascade: department is disabled without a division,
                        // so clear it on deselect or when not linked to the
                        // newly selected division.
                        const valid =
                          division !== "" &&
                          departmentNameSetFor(division).has(line.department);
                        patchLine(index, {
                          division,
                          ...(valid ? {} : { department: "" }),
                        });
                      }}
                    />
                  ),
                },
                {
                  key: "department",
                  label: t("department"),
                  width: "w-36",
                  cell: (line, index) => (
                    <LineCombobox
                      value={line.department}
                      options={departmentOptionsFor(line.division)}
                      placeholder={t("departmentPlaceholder")}
                      ariaLabel={t("department")}
                      disabled={!line.division}
                      onValueChange={(value) =>
                        patchLine(index, { department: value ?? "" })
                      }
                    />
                  ),
                },
                {
                  key: "location",
                  label: t("location"),
                  width: "w-32",
                  cell: (line, index) => (
                    <Input
                      value={line.location}
                      onChange={(e) =>
                        patchLine(index, { location: e.target.value })
                      }
                      placeholder={t("locationPlaceholder")}
                      aria-label={t("location")}
                      className={cellInputClass}
                    />
                  ),
                },
                {
                  key: "purpose",
                  label: t("purpose"),
                  width: "w-56",
                  cell: (line, index) => (
                    <Textarea
                      value={line.purpose}
                      onChange={(e) =>
                        patchLine(index, { purpose: e.target.value })
                      }
                      placeholder={t("purposePlaceholder")}
                      aria-label={t("purpose")}
                      rows={1}
                      className="min-h-8! rounded-none border-border/60 bg-background px-1.5 py-[5px] text-sm shadow-none resize-none placeholder:text-sm md:text-sm"
                    />
                  ),
                },
                {
                  key: "uom",
                  label: t("uom"),
                  width: "w-24",
                  cell: (line, index) => (
                    <Input
                      value={line.uom}
                      onChange={(e) => patchLine(index, { uom: e.target.value })}
                      placeholder={t("uomPlaceholder")}
                      aria-label={t("uom")}
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
                    <div className="flex h-8 items-center justify-end text-sm tabular-nums">
                      {formatMoney(lineTotalOf(line), form.currency)}
                    </div>
                  ),
                },
                // USD equivalent only for KHR documents — on a USD invoice it
                // would just repeat the line total.
                ...(form.currency === "KHR"
                  ? [
                      {
                        key: "grandTotalUsd",
                        label: t("grandTotalUsd"),
                        width: "w-28",
                        align: "right" as const,
                        cell: (line: LineForm) => (
                        <div className="flex h-8 items-center justify-end text-sm tabular-nums">
                          {formatMoney(
                            toUsdDraft(
                              lineTotalOf(line),
                              form.currency,
                              form.exchangeRate
                            ),
                            "USD"
                          )}
                        </div>
                        ),
                      },
                    ]
                  : []),
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
                  value:
                    lineDiscountTotal > 0 ? (
                      formatMoney(lineDiscountTotal, form.currency)
                    ) : (
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
                  label:
                    vatRate === null
                      ? t("vatAmount")
                      : `${t("vatAmount")} (${vatRate}%)`,
                  value: formatMoney(vat, form.currency),
                },
                {
                  key: "total",
                  label: t("total"),
                  value: formatMoney(total, form.currency),
                  strong: true,
                },
                ...(form.currency === "KHR"
                  ? [
                      {
                        key: "grandTotalUsd",
                        label: t("grandTotalUsd"),
                        value: formatMoney(
                          toUsdDraft(total, form.currency, form.exchangeRate),
                          "USD"
                        ),
                        strong: true,
                      },
                    ]
                  : []),
              ]}
              footerLeading={
                <Button type="button" size="xs" onClick={addLine}>
                  <Plus className="size-3" />
                  {t("addLine")}
                </Button>
              }
            />

            <div className="mt-3 flex items-center gap-2">
              <Label htmlFor="inv-notes" className={fieldLabelClass}>
                {t("notes")}
              </Label>
              <Textarea
                id="inv-notes"
                value={form.notes}
                onChange={(e) => patch({ notes: e.target.value })}
                placeholder={t("notesPlaceholder")}
                className="min-h-0 flex-1 rounded-none border-0 border-b border-border bg-transparent px-0 shadow-none resize-none focus-visible:ring-0"
                rows={1}
              />
            </div>
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
