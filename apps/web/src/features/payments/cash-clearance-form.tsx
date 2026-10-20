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
} from "@/components/line-items-table";
import { Textarea } from "@/components/ui/textarea";
import {
  Combobox,
  ComboboxContent,
  ComboboxEmpty,
  ComboboxInput,
  ComboboxItem,
  ComboboxList,
} from "@/components/ui/combobox";
import { DatePicker } from "@/components/ui/date-picker";
import { unwrap, unwrapWithMeta, withAuth } from "@/lib/api-client";
import { cn, formatMoney } from "@/lib/utils";
import {
  paymentsCashClearancesShow,
  paymentsCashClearancesStore,
  paymentsCashClearancesUpdate,
} from "@/lib/api/cash-clearance/cash-clearance";
import { paymentsCashRequestsIndex, paymentsCashRequestsShow } from "@/lib/api/cash-request/cash-request";
import { organizationCampusesIndex } from "@/lib/api/campus/campus";
import { organizationDivisionsIndex } from "@/lib/api/division/division";
import { organizationDepartmentsIndex } from "@/lib/api/department/department";
import type { StoreCashClearanceRequest } from "@/lib/api/model/storeCashClearanceRequest";
import type { CashClearanceResource } from "@/lib/api/model/cashClearanceResource";
import type { CashRequestResource } from "@/lib/api/model/cashRequestResource";
import { useMe } from "@/hooks/use-me";
import { CashClearanceFormSkeleton } from "./components/cash-clearance-form-skeleton";

type LineForm = {
  invoiceDate: Date | undefined;
  prRef: string;
  purchaser: string;
  description: string;
  vendor: string;
  vendorPhone: string;
  campus: string;
  division: string;
  department: string;
  qty: string;
  uom: string;
  unitPrice: string;
  discount: string;
  vat: string;
};

type CashClearanceFormValue = {
  cashRequestId: string;
  clearanceDate: Date | undefined;
  exchangeRate: string;
  lines: LineForm[];
};

const EMPTY_LINE: LineForm = {
  invoiceDate: undefined,
  prRef: "",
  purchaser: "",
  description: "",
  vendor: "",
  vendorPhone: "",
  campus: "",
  division: "",
  department: "",
  qty: "",
  uom: "",
  unitPrice: "",
  discount: "",
  vat: "",
};

const EMPTY_FORM: CashClearanceFormValue = {
  cashRequestId: "",
  clearanceDate: undefined,
  exchangeRate: "",
  lines: [{ ...EMPTY_LINE }],
};

const fieldLabelClass =
  "w-36 shrink-0 text-left after:ml-1 after:content-[':']";

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
  const [year, month, day] = value.split("T")[0].split("-").map(Number);
  if (!year || !month || !day) return undefined;
  return new Date(year, month - 1, day);
}

// Server-computed line_total: qty × unit price, minus discount, plus VAT.
function lineAmountOf(line: LineForm): number {
  return (
    toNumber(line.qty) * toNumber(line.unitPrice) -
    toNumber(line.discount) +
    toNumber(line.vat)
  );
}

function hasLineError(line: LineForm): boolean {
  const gross = toNumber(line.qty) * toNumber(line.unitPrice);
  const discount = toNumber(line.discount);
  const vat = toNumber(line.vat);

  // Every cell except pr_ref/purchaser/vendor_phone/discount/vat is required
  // by the API — an empty number input reads as 0, so the raw strings must
  // be checked for money too.
  return (
    !line.invoiceDate ||
    !line.description.trim() ||
    !line.vendor.trim() ||
    !line.campus.trim() ||
    !line.division.trim() ||
    !line.department.trim() ||
    toNumber(line.qty) <= 0 ||
    !line.uom.trim() ||
    !line.unitPrice.trim() ||
    toNumber(line.unitPrice) < 0 ||
    discount < 0 ||
    discount > gross ||
    vat < 0
  );
}

function toForm(resource?: CashClearanceResource | null): CashClearanceFormValue {
  if (!resource) return { ...EMPTY_FORM, lines: [{ ...EMPTY_LINE }] };
  const lines = resource.lines ?? [];
  return {
    cashRequestId: resource.cashRequestId,
    clearanceDate: toDate(resource.clearanceDate),
    exchangeRate: resource.exchangeRate ? String(resource.exchangeRate) : "",
    lines:
      lines.length > 0
        ? lines.map((line) => ({
            invoiceDate: toDate(line.invoiceDate),
            prRef: line.prRef ?? "",
            purchaser: line.purchaser ?? "",
            description: line.description,
            vendor: line.vendor,
            vendorPhone: line.vendorPhone ?? "",
            campus: line.campus ?? "",
            division: line.division ?? "",
            department: line.department ?? "",
            qty: String(line.qty),
            uom: line.uom ?? "",
            unitPrice: String(line.unitPriceUsd),
            discount: String(line.discountUsd ?? ""),
            vat: String(line.vatUsd ?? ""),
          }))
        : [{ ...EMPTY_LINE }],
  };
}

interface CashClearanceFormProps {
  clearanceId?: string;
  onBack: () => void;
}

export function CashClearanceForm({ clearanceId, onBack }: CashClearanceFormProps) {
  const t = useTranslations("payments.form");
  const tc = useTranslations("payments.columns");
  const tn = useTranslations("payments.cashClearances");
  const isEdit = Boolean(clearanceId);
  const qc = useQueryClient();
  const router = useRouter();
  const meQuery = useMe();
  const [form, setForm] = useState<CashClearanceFormValue>(() => toForm(null));
  const [hydratedId, setHydratedId] = useState<string | null>(null);

  const showQuery = useQuery({
    queryKey: ["cash-clearances", "detail", clearanceId],
    queryFn: async (): Promise<CashClearanceResource> =>
      unwrap(
        await paymentsCashClearancesShow(clearanceId!, withAuth())
      ),
    enabled: isEdit,
  });

  // The picker only offers approved cash requests — server-side filtered.
  const cashRequestsQuery = useQuery({
    queryKey: ["cash-requests", "approved"],
    queryFn: async () =>
      unwrapWithMeta<
        CashRequestResource[],
        { page: number; perPage: number; total: number }
      >(await paymentsCashRequestsIndex({ status: "approved", per_page: 100 }, withAuth())),
    staleTime: 5 * 60 * 1000,
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
      unwrap<
        { id: string; name: string; isActive: boolean; divisionIds?: string[] }[]
      >(await organizationDepartmentsIndex(withAuth())),
    staleTime: 5 * 60 * 1000,
  });

  const approvedOptions = (cashRequestsQuery.data?.data ?? []).map(
    (request) => ({
      value: request.id,
      label: request.code,
      totalUsd: Number(request.totalUsd ?? 0),
    })
  );

  // Edit mode: the linked cash request may have left the approved list since
  // the clearance was created — fetch it so the current pick keeps its label.
  const linkedMissing =
    isEdit &&
    !!form.cashRequestId &&
    !approvedOptions.some((option) => option.value === form.cashRequestId);
  const linkedCashRequestQuery = useQuery({
    queryKey: ["cash-requests", "detail", form.cashRequestId || null],
    queryFn: async (): Promise<CashRequestResource> =>
      unwrap(
        await paymentsCashRequestsShow(form.cashRequestId, withAuth())
      ),
    enabled: linkedMissing,
  });

  const pickerOptions = [...approvedOptions];
  const linked = linkedCashRequestQuery.data;
  if (linked && !pickerOptions.some((option) => option.value === linked.id)) {
    pickerOptions.push({
      value: linked.id,
      label: linked.code,
      totalUsd: Number(linked.totalUsd ?? 0),
    });
  }

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
  const canManage = permissions.includes("payments.manage");
  const status = showQuery.data?.status ?? null;
  const isOwner =
    !isEdit ||
    (showQuery.data != null &&
      meQuery.data != null &&
      Number(showQuery.data.createdById) === meQuery.data.id);
  // Mirrors the API: payments.manage AND owner, only while still editable.
  const locked =
    !canManage ||
    (isEdit && (!["draft", "returned"].includes(status ?? "") || !isOwner));

  const patch = (changes: Partial<CashClearanceFormValue>) =>
    setForm((current) => ({ ...current, ...changes }));

  const patchLine = (index: number, changes: Partial<LineForm>) =>
    setForm((current) => ({
      ...current,
      lines: current.lines.map((line, i) =>
        i === index ? { ...line, ...changes } : line
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
      // Clearance lines usually share the same org fields; only the
      // document/money cells start blank.
      return {
        ...current,
        lines: [
          ...current.lines,
          {
            ...EMPTY_LINE,
            campus: last?.campus ?? "",
            division: last?.division ?? "",
            department: last?.department ?? "",
          },
        ],
      };
    });

  const total = form.lines.reduce((sum, line) => sum + lineAmountOf(line), 0);
  const selectedRequestTotal = pickerOptions.find(
    (option) => option.value === form.cashRequestId
  )?.totalUsd;

  // UX-only gate: the API's 422 is the source of truth.
  const missingLines = form.lines.some(hasLineError);
  const canSave =
    !!form.cashRequestId &&
    !!form.clearanceDate &&
    form.lines.length > 0 &&
    !missingLines;

  const saveMutation = useMutation({
    mutationFn: async (): Promise<string> => {
      const payload: StoreCashClearanceRequest = {
        cash_request_id: form.cashRequestId,
        clearance_date: toDateInput(form.clearanceDate) ?? "",
        exchange_rate: form.exchangeRate.trim() ? toNumber(form.exchangeRate) : null,
        lines: form.lines.map((line) => ({
          invoice_date: toDateInput(line.invoiceDate) ?? "",
          pr_ref: line.prRef.trim() || null,
          purchaser: line.purchaser.trim() || null,
          description: line.description.trim(),
          vendor: line.vendor.trim(),
          vendor_phone: line.vendorPhone.trim() || null,
          campus: line.campus.trim(),
          division: line.division.trim(),
          department: line.department.trim(),
          qty: toNumber(line.qty),
          uom: line.uom.trim(),
          unit_price_usd: toNumber(line.unitPrice),
          discount_usd: line.discount.trim() ? toNumber(line.discount) : null,
          vat_usd: line.vat.trim() ? toNumber(line.vat) : null,
        })),
      };

      if (isEdit) {
        unwrap(
          await paymentsCashClearancesUpdate(
            clearanceId!,
            payload,
            withAuth()
          )
        );
        return clearanceId!;
      }
      const created = unwrap<{ id: string }>(
        await paymentsCashClearancesStore(payload, withAuth())
      );
      return created.id;
    },
    onSuccess: (id) => {
      qc.invalidateQueries({ queryKey: ["cash-clearances"] });
      toast.success(isEdit ? tn("updated") : tn("created"));
      if (!isEdit) {
        router.push(`/payments/cash-clearances/${id}/edit`);
      }
    },
  });

  if (isEdit && showQuery.isPending) {
    return <CashClearanceFormSkeleton />;
  }

  if (isEdit && showQuery.isError) {
    return (
      <div className="mt-1 min-w-0 space-y-4">
        <Button variant="outline" onClick={onBack}>
          {t("back")}
        </Button>
        <p className="text-sm text-destructive">{tn("loadFailed")}</p>
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
              {isEdit ? tn("editTitle") : tn("newTitle")}
            </h1>
            <p className="text-sm text-muted-foreground">
              {isEdit ? tn("editDescription") : tn("newDescription")}
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
            <CardTitle>{tc("clearanceDate")}</CardTitle>
          </CardHeader>
          <CardContent className="min-w-0 space-y-3">
            <div className="grid grid-cols-1 gap-x-16 gap-y-3 lg:grid-cols-2">
              <div className="space-y-3">
                <div className="flex items-center gap-2">
                  <Label className={fieldLabelClass}>
                    {t("cashRequest")} <RequiredMark />
                  </Label>
                  <Combobox
                    items={pickerOptions}
                    value={form.cashRequestId || null}
                    onValueChange={(value) =>
                      patch({ cashRequestId: value ?? "" })
                    }
                  >
                    <ComboboxInput
                      placeholder={t("cashRequest")}
                      className="flex-1"
                    />
                    <ComboboxContent>
                      <ComboboxEmpty>{t("cashRequest")}</ComboboxEmpty>
                      <ComboboxList>
                        {(option) => (
                          <ComboboxItem
                            key={option.value}
                            value={option.value}
                          >
                            <span className="font-mono text-xs">
                              {option.label}
                            </span>
                          </ComboboxItem>
                        )}
                      </ComboboxList>
                    </ComboboxContent>
                  </Combobox>
                </div>

                <div className="flex items-center gap-2">
                  <Label className={fieldLabelClass}>
                    {t("clearanceDate")} <RequiredMark />
                  </Label>
                  <DatePicker
                    value={form.clearanceDate}
                    onChange={(date) => patch({ clearanceDate: date })}
                    placeholder={t("clearanceDate")}
                    className="flex-1"
                  />
                </div>
              </div>

              {/* -ml-8/pl-8 centers the border in the grid's 64px gap. */}
              <div className="space-y-3 lg:-ml-8 lg:border-l lg:border-border lg:pl-8">
                <div className="flex items-center gap-2">
                  <Label htmlFor="cc-rate" className={fieldLabelClass}>
                    {t("exchangeRate")}
                  </Label>
                  <Input
                    id="cc-rate"
                    type="number"
                    inputMode="decimal"
                    min={0}
                    step="any"
                    value={form.exchangeRate}
                    onChange={(e) => patch({ exchangeRate: e.target.value })}
                    placeholder="0"
                    className="flex-1 text-right"
                  />
                </div>

                <div className="flex items-center gap-2">
                  <Label className={fieldLabelClass}>
                    {tc("requestAmount")}
                  </Label>
                  <span className="flex-1 text-sm tabular-nums text-muted-foreground">
                    {selectedRequestTotal != null
                      ? formatMoney(selectedRequestTotal, "USD")
                      : "—"}
                  </span>
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
                  key: "invoiceDate",
                  label: t("invoiceDate"),
                  width: "w-32",
                  required: true,
                  cell: (line, index) => (
                    <DatePicker
                      value={line.invoiceDate}
                      onChange={(date) =>
                        patchLine(index, { invoiceDate: date })
                      }
                      placeholder={t("invoiceDate")}
                      className={cn(cellInputClass, "w-full justify-start")}
                    />
                  ),
                },
                {
                  key: "prRef",
                  label: t("prRef"),
                  width: "w-24",
                  cell: (line, index) => (
                    <Input
                      value={line.prRef}
                      onChange={(e) =>
                        patchLine(index, { prRef: e.target.value })
                      }
                      placeholder={t("prRef")}
                      aria-label={t("prRef")}
                      className={cellInputClass}
                    />
                  ),
                },
                {
                  key: "purchaser",
                  label: t("purchaser"),
                  width: "w-28",
                  cell: (line, index) => (
                    <Input
                      value={line.purchaser}
                      onChange={(e) =>
                        patchLine(index, { purchaser: e.target.value })
                      }
                      placeholder={t("purchaser")}
                      aria-label={t("purchaser")}
                      className={cellInputClass}
                    />
                  ),
                },
                {
                  key: "description",
                  label: t("description"),
                  required: true,
                  cell: (line, index) => (
                    <Textarea
                      value={line.description}
                      onChange={(e) =>
                        patchLine(index, { description: e.target.value })
                      }
                      placeholder={t("description")}
                      aria-label={t("description")}
                      rows={1}
                      className="min-h-8! rounded-none border-border/60 bg-background px-1.5 py-[5px] text-sm shadow-none resize-none placeholder:text-sm md:text-sm"
                    />
                  ),
                },
                {
                  key: "vendor",
                  label: t("vendor"),
                  width: "w-32",
                  required: true,
                  cell: (line, index) => (
                    <Input
                      value={line.vendor}
                      onChange={(e) =>
                        patchLine(index, { vendor: e.target.value })
                      }
                      placeholder={t("vendor")}
                      aria-label={t("vendor")}
                      className={cellInputClass}
                    />
                  ),
                },
                {
                  key: "vendorPhone",
                  label: t("vendorPhone"),
                  width: "w-28",
                  cell: (line, index) => (
                    <Input
                      value={line.vendorPhone}
                      onChange={(e) =>
                        patchLine(index, { vendorPhone: e.target.value })
                      }
                      placeholder={t("vendorPhone")}
                      aria-label={t("vendorPhone")}
                      className={cellInputClass}
                    />
                  ),
                },
                {
                  key: "campus",
                  label: t("campus"),
                  width: "w-32",
                  required: true,
                  cell: (line, index) => (
                    <LineCombobox
                      value={line.campus}
                      options={campusOptions}
                      placeholder={t("campus")}
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
                  required: true,
                  cell: (line, index) => (
                    <LineCombobox
                      value={line.division}
                      options={divisionOptions}
                      placeholder={t("division")}
                      ariaLabel={t("division")}
                      onValueChange={(value) => {
                        const division = value ?? "";
                        // Cascade: department is cleared when the division
                        // changes and the old pick is no longer linked.
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
                  required: true,
                  cell: (line, index) => (
                    <LineCombobox
                      value={line.department}
                      options={departmentOptionsFor(line.division)}
                      placeholder={t("department")}
                      ariaLabel={t("department")}
                      disabled={!line.division}
                      onValueChange={(value) =>
                        patchLine(index, { department: value ?? "" })
                      }
                    />
                  ),
                },
                {
                  key: "qty",
                  label: t("qty"),
                  width: "w-20",
                  align: "right",
                  required: true,
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
                  key: "uom",
                  label: t("uom"),
                  width: "w-20",
                  required: true,
                  cell: (line, index) => (
                    <Input
                      value={line.uom}
                      onChange={(e) => patchLine(index, { uom: e.target.value })}
                      placeholder={t("uom")}
                      aria-label={t("uom")}
                      className={cellInputClass}
                    />
                  ),
                },
                {
                  key: "unitPrice",
                  label: t("unitPrice"),
                  width: "w-28",
                  align: "right",
                  required: true,
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
                  label: t("discount"),
                  width: "w-24",
                  align: "right",
                  cell: (line, index) => (
                    <Input
                      type="number"
                      min={0}
                      step="any"
                      inputMode="decimal"
                      value={line.discount}
                      onChange={(e) =>
                        patchLine(index, { discount: e.target.value })
                      }
                      aria-label={t("discount")}
                      className={cellInputRightClass}
                    />
                  ),
                },
                {
                  key: "vat",
                  label: t("vat"),
                  width: "w-24",
                  align: "right",
                  cell: (line, index) => (
                    <Input
                      type="number"
                      min={0}
                      step="any"
                      inputMode="decimal"
                      value={line.vat}
                      onChange={(e) => patchLine(index, { vat: e.target.value })}
                      aria-label={t("vat")}
                      className={cellInputRightClass}
                    />
                  ),
                },
                {
                  key: "amountUsd",
                  label: t("amountUsd"),
                  width: "w-28",
                  align: "right",
                  cell: (line) => (
                    <div className="flex h-8 items-center justify-end text-sm tabular-nums">
                      {formatMoney(lineAmountOf(line), "USD")}
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
                  key: "total",
                  label: t("totalUsd"),
                  value: formatMoney(total, "USD"),
                  strong: true,
                },
                ...(selectedRequestTotal != null
                  ? [
                      {
                        key: "balance",
                        label: tc("balance"),
                        value: formatMoney(selectedRequestTotal - total, "USD"),
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
