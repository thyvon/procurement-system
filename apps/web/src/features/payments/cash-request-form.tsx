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
import { organizationCampusesIndex } from "@/lib/api/campus/campus";
import { organizationDivisionsIndex } from "@/lib/api/division/division";
import { organizationDepartmentsIndex } from "@/lib/api/department/department";
import {
  paymentsCashRequestsShow,
  paymentsCashRequestsStore,
  paymentsCashRequestsUpdate,
} from "@/lib/api/cash-request/cash-request";
import type { StoreCashRequestRequest } from "@/lib/api/model/storeCashRequestRequest";
import type { CashRequestResource } from "@/lib/api/model/cashRequestResource";
import { useMe } from "@/hooks/use-me";
import { CashRequestFormSkeleton } from "./components/cash-request-form-skeleton";
import {
  EMPTY_LINE,
  fieldLabelClass,
  hasLineError,
  lineAmountUsd,
  toDate,
  toDateInput,
  toNumber,
  type LineForm,
} from "./cash-request-helpers";

type CashRequestFormValue = {
  campus: string;
  division: string;
  department: string;
  requestDate: Date | undefined;
  exchangeRate: string;
  modeOfPayment: string;
  paymentTo: string;
  bankAccountNo: string;
  bankName: string;
  remark: string;
  lines: LineForm[];
};

const EMPTY_FORM: CashRequestFormValue = {
  campus: "",
  division: "",
  department: "",
  requestDate: undefined,
  exchangeRate: "",
  modeOfPayment: "",
  paymentTo: "",
  bankAccountNo: "",
  bankName: "",
  remark: "",
  lines: [{ ...EMPTY_LINE }],
};

/** Combobox for the campus/division/department header cells. */
function FieldCombobox({
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
        className="flex-1"
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

function toForm(resource?: CashRequestResource | null): CashRequestFormValue {
  if (!resource) return { ...EMPTY_FORM, lines: [{ ...EMPTY_LINE }] };
  const lines = resource.lines ?? [];
  return {
    campus: resource.campus ?? "",
    division: resource.division ?? "",
    department: resource.department ?? "",
    requestDate: toDate(resource.requestDate),
    exchangeRate: resource.exchangeRate ? String(resource.exchangeRate) : "",
    modeOfPayment: resource.modeOfPayment ?? "",
    paymentTo: resource.paymentTo ?? "",
    bankAccountNo: resource.bankAccountNo ?? "",
    bankName: resource.bankName ?? "",
    remark: resource.remark ?? "",
    lines:
      lines.length > 0
        ? lines.map((line) => ({
            description: line.description,
            currency: line.currency === "KHR" ? "KHR" : "USD",
            amount: String(line.amount),
          }))
        : [{ ...EMPTY_LINE }],
  };
}

interface CashRequestFormProps {
  cashRequestId?: string;
  onBack: () => void;
}

export function CashRequestForm({
  cashRequestId,
  onBack,
}: CashRequestFormProps) {
  const t = useTranslations("payments.form");
  const tc = useTranslations("payments.cashRequests");
  // No payments.form.detailsCard — the shared breadcrumb word is the closest
  // existing key for the header card title.
  const td = useTranslations("breadcrumb");
  const isEdit = Boolean(cashRequestId);
  const qc = useQueryClient();
  const router = useRouter();
  const meQuery = useMe();
  const [form, setForm] = useState<CashRequestFormValue>(() => toForm(null));
  const [hydratedId, setHydratedId] = useState<string | null>(null);

  const showQuery = useQuery({
    queryKey: ["cash-requests", "detail", cashRequestId],
    queryFn: async (): Promise<CashRequestResource> =>
      unwrap(await paymentsCashRequestsShow(cashRequestId!, withAuth())),
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

  const patch = (changes: Partial<CashRequestFormValue>) =>
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
      // Nothing else on a cash request line to inherit — description and
      // amount start blank, currency follows the previous row.
      return {
        ...current,
        lines: [
          ...current.lines,
          { ...EMPTY_LINE, currency: last?.currency ?? "USD" },
        ],
      };
    });

  const headerRate = form.exchangeRate.trim() === "" ? 1 : toNumber(form.exchangeRate);
  const totalUsd = form.lines.reduce(
    (sum, line) =>
      sum + lineAmountUsd(toNumber(line.amount), line.currency, headerRate),
    0
  );
  const hasKhrLine = form.lines.some((line) => line.currency === "KHR");

  // UX-only gate: the API's 422 is the source of truth.
  const missingLines = form.lines.some(hasLineError);
  const canSave =
    !!form.campus.trim() &&
    !!form.division.trim() &&
    !!form.department.trim() &&
    !!form.requestDate &&
    (form.exchangeRate.trim() === "" || toNumber(form.exchangeRate) > 0) &&
    (!hasKhrLine || toNumber(form.exchangeRate) > 0) &&
    form.lines.length > 0 &&
    !missingLines;

  const saveMutation = useMutation({
    mutationFn: async (): Promise<string> => {
      const payload: StoreCashRequestRequest = {
        type: "cash",
        campus: form.campus.trim(),
        division: form.division.trim(),
        department: form.department.trim(),
        request_date: toDateInput(form.requestDate) ?? "",
        exchange_rate:
          form.exchangeRate.trim() === "" ? null : toNumber(form.exchangeRate),
        mode_of_payment: form.modeOfPayment.trim() || null,
        payment_to: form.paymentTo.trim() || null,
        bank_account_no: form.bankAccountNo.trim() || null,
        bank_name: form.bankName.trim() || null,
        remark: form.remark.trim() || null,
        lines: form.lines.map((line) => ({
          description: line.description.trim(),
          currency: line.currency,
          amount: toNumber(line.amount),
        })),
      };

      if (isEdit) {
        unwrap(
          await paymentsCashRequestsUpdate(cashRequestId!, payload, withAuth())
        );
        return cashRequestId!;
      }
      const created = unwrap<{ id: string }>(
        await paymentsCashRequestsStore(payload, withAuth())
      );
      return created.id;
    },
    onSuccess: (id) => {
      qc.invalidateQueries({ queryKey: ["cash-requests"] });
      toast.success(isEdit ? tc("updated") : tc("created"));
      if (!isEdit) {
        router.push(`/payments/cash-requests/${id}/edit`);
      }
    },
  });

  if (isEdit && showQuery.isPending) {
    return <CashRequestFormSkeleton />;
  }

  if (isEdit && showQuery.isError) {
    return (
      <div className="mt-1 min-w-0 space-y-4">
        <Button variant="outline" onClick={onBack}>
          {t("back")}
        </Button>
        <p className="text-sm text-destructive">{tc("loadFailed")}</p>
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
              {isEdit ? tc("editTitle") : tc("newTitle")}
            </h1>
            <p className="text-sm text-muted-foreground">
              {isEdit ? tc("editDescription") : tc("newDescription")}
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
            <CardTitle>{td("details")}</CardTitle>
          </CardHeader>
          <CardContent className="min-w-0 space-y-3">
            {/* Two columns only from lg — with the 256px sidebar the md
                columns are ~176px. */}
            <div className="grid grid-cols-1 gap-x-16 gap-y-3 lg:grid-cols-2">
              <div className="space-y-3">
                <div className="flex items-center gap-2">
                  <Label className={fieldLabelClass}>
                    {t("requestDate")} <RequiredMark />
                  </Label>
                  <DatePicker
                    value={form.requestDate}
                    onChange={(date) => patch({ requestDate: date })}
                    placeholder={t("requestDate")}
                    className="flex-1"
                  />
                </div>

                <div className="flex items-center gap-2">
                  <Label className={fieldLabelClass}>
                    {t("campus")} <RequiredMark />
                  </Label>
                  <FieldCombobox
                    value={form.campus}
                    options={campusOptions}
                    placeholder={t("campus")}
                    ariaLabel={t("campus")}
                    onValueChange={(value) => patch({ campus: value ?? "" })}
                  />
                </div>

                <div className="flex items-center gap-2">
                  <Label className={fieldLabelClass}>
                    {t("division")} <RequiredMark />
                  </Label>
                  <FieldCombobox
                    value={form.division}
                    options={divisionOptions}
                    placeholder={t("division")}
                    ariaLabel={t("division")}
                    onValueChange={(value) => {
                      const division = value ?? "";
                      // Cascade: department is disabled without a division,
                      // so clear it on deselect or when not linked to the
                      // newly selected division.
                      const valid =
                        division !== "" &&
                        departmentNameSetFor(division).has(form.department);
                      patch({
                        division,
                        ...(valid ? {} : { department: "" }),
                      });
                    }}
                  />
                </div>

                <div className="flex items-center gap-2">
                  <Label className={fieldLabelClass}>
                    {t("department")} <RequiredMark />
                  </Label>
                  <FieldCombobox
                    value={form.department}
                    options={departmentOptionsFor(form.division)}
                    placeholder={t("department")}
                    ariaLabel={t("department")}
                    disabled={!form.division}
                    onValueChange={(value) =>
                      patch({ department: value ?? "" })
                    }
                  />
                </div>
              </div>

              {/* -ml-8/pl-8 centers the border in the grid's 64px gap. */}
              <div className="space-y-3 lg:-ml-8 lg:border-l lg:border-border lg:pl-8">
                <div className="flex items-center gap-2">
                  <Label htmlFor="cr-rate" className={fieldLabelClass}>
                    {t("exchangeRate")}{" "}
                    {hasKhrLine ? <RequiredMark /> : null}
                  </Label>
                  <Input
                    id="cr-rate"
                    type="number"
                    inputMode="decimal"
                    min={0}
                    step="any"
                    value={form.exchangeRate}
                    onChange={(e) => patch({ exchangeRate: e.target.value })}
                    placeholder="1"
                    className="flex-1"
                  />
                </div>

                <div className="flex items-center gap-2">
                  <Label htmlFor="cr-mode" className={fieldLabelClass}>
                    {t("modeOfPayment")}
                  </Label>
                  <Input
                    id="cr-mode"
                    value={form.modeOfPayment}
                    onChange={(e) => patch({ modeOfPayment: e.target.value })}
                    placeholder={t("modeOfPayment")}
                    className="flex-1"
                  />
                </div>

                <div className="flex items-center gap-2">
                  <Label htmlFor="cr-payment-to" className={fieldLabelClass}>
                    {t("paymentTo")}
                  </Label>
                  <Input
                    id="cr-payment-to"
                    value={form.paymentTo}
                    onChange={(e) => patch({ paymentTo: e.target.value })}
                    placeholder={t("paymentTo")}
                    className="flex-1"
                  />
                </div>

                <div className="flex items-center gap-2">
                  <Label htmlFor="cr-bank-account" className={fieldLabelClass}>
                    {t("bankAccountNo")}
                  </Label>
                  <Input
                    id="cr-bank-account"
                    value={form.bankAccountNo}
                    onChange={(e) =>
                      patch({ bankAccountNo: e.target.value })
                    }
                    placeholder={t("bankAccountNo")}
                    className="flex-1"
                  />
                </div>

                <div className="flex items-center gap-2">
                  <Label htmlFor="cr-bank-name" className={fieldLabelClass}>
                    {t("bankName")}
                  </Label>
                  <Input
                    id="cr-bank-name"
                    value={form.bankName}
                    onChange={(e) => patch({ bankName: e.target.value })}
                    placeholder={t("bankName")}
                    className="flex-1"
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
                  key: "currency",
                  label: t("currency"),
                  width: "w-24",
                  required: true,
                  cell: (line, index) => (
                    <Select
                      value={line.currency}
                      onValueChange={(value) =>
                        patchLine(index, {
                          currency: value === "KHR" ? "KHR" : "USD",
                        })
                      }
                      items={[
                        { value: "USD", label: "USD" },
                        { value: "KHR", label: "KHR" },
                      ]}
                    >
                      <SelectTrigger
                        aria-label={t("currency")}
                        className={cn(cellInputClass, "justify-start font-normal")}
                      >
                        <SelectValue placeholder={t("currency")} />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="USD">USD</SelectItem>
                        <SelectItem value="KHR">KHR</SelectItem>
                      </SelectContent>
                    </Select>
                  ),
                },
                {
                  key: "amount",
                  label: t("amount"),
                  width: "w-32",
                  align: "right",
                  required: true,
                  cell: (line, index) => (
                    <Input
                      type="number"
                      min={0}
                      step="any"
                      inputMode="decimal"
                      value={line.amount}
                      onChange={(e) =>
                        patchLine(index, { amount: e.target.value })
                      }
                      aria-label={t("amount")}
                      className={cellInputRightClass}
                    />
                  ),
                },
                // USD equivalent only when a row is in KHR — on an all-USD
                // request it would just repeat the amount.
                ...(hasKhrLine
                  ? [
                      {
                        key: "amountUsd",
                        label: t("amountUsd"),
                        width: "w-32",
                        align: "right" as const,
                        cell: (line: LineForm) => (
                          <div className="flex h-8 items-center justify-end text-sm tabular-nums">
                            {formatMoney(
                              lineAmountUsd(
                                toNumber(line.amount),
                                line.currency,
                                headerRate
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
                  key: "total",
                  label: t("totalUsd"),
                  value: formatMoney(totalUsd, "USD"),
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

            <div className="mt-3 flex items-center gap-2">
              <Label htmlFor="cr-remark" className={fieldLabelClass}>
                {t("remark")}
              </Label>
              <Textarea
                id="cr-remark"
                value={form.remark}
                onChange={(e) => patch({ remark: e.target.value })}
                placeholder={t("remark")}
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
