"use client";

import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
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
import { unwrap, unwrapWithMeta, withAuth } from "@/lib/api-client";
import { cn, formatMoney } from "@/lib/utils";
import {
  paymentsAdvanceLiquidationsShow,
  paymentsAdvanceLiquidationsStore,
  paymentsAdvanceLiquidationsUpdate,
} from "@/lib/api/advance-liquidation/advance-liquidation";
import {
  paymentsAdvanceRequestsIndex,
  paymentsAdvanceRequestsShow,
} from "@/lib/api/advance-request/advance-request";
import { organizationCampusesIndex } from "@/lib/api/campus/campus";
import { organizationDivisionsIndex } from "@/lib/api/division/division";
import { organizationDepartmentsIndex } from "@/lib/api/department/department";
import type { StoreAdvanceLiquidationRequest } from "@/lib/api/model/storeAdvanceLiquidationRequest";
import type { AdvanceLiquidationResource } from "@/lib/api/model/advanceLiquidationResource";
import type { CashRequestResource } from "@/lib/api/model/cashRequestResource";
import { useMe } from "@/hooks/use-me";
import { useApprovalSubmit } from "@/features/approvals/use-approval-submit";
import { SubmitApprovalPanel } from "@/features/approvals/components/submit-approval-panel";
import { AdvanceLiquidationFormSkeleton } from "./components/advance-liquidation-form-skeleton";
import { lineAmountUsd } from "../cash-request-helpers";

const ADVANCE_LIQUIDATION_SUBJECT = "advance_liquidation";

type LineForm = {
  description: string;
  currency: "USD" | "KHR";
  amount: string;
};

type LiquidationFormValue = {
  cashRequestId: string;
  liquidationDate: Date | undefined;
  exchangeRate: string;
  campus: string;
  division: string;
  department: string;
  lines: LineForm[];
};

const EMPTY_LINE: LineForm = {
  description: "",
  currency: "USD",
  amount: "",
};

const EMPTY_FORM: LiquidationFormValue = {
  cashRequestId: "",
  liquidationDate: undefined,
  exchangeRate: "",
  campus: "",
  division: "",
  department: "",
  lines: [{ ...EMPTY_LINE }],
};

const fieldLabelClass =
  "w-36 shrink-0 text-left after:ml-1 after:content-[':']";

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

function hasLineError(line: LineForm): boolean {
  // Every cell is required by the API — an empty number input reads as 0, so
  // the raw strings must be checked for money too.
  return (
    !line.description.trim() ||
    line.amount.trim() === "" ||
    toNumber(line.amount) < 0
  );
}

function toForm(
  resource?: AdvanceLiquidationResource | null
): LiquidationFormValue {
  if (!resource) return { ...EMPTY_FORM, lines: [{ ...EMPTY_LINE }] };
  const lines = resource.lines ?? [];
  return {
    cashRequestId: resource.cashRequestId,
    liquidationDate: toDate(resource.liquidationDate),
    exchangeRate: resource.exchangeRate ? String(resource.exchangeRate) : "",
    campus: resource.campus ?? "",
    division: resource.division ?? "",
    department: resource.department ?? "",
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

interface AdvanceLiquidationFormProps {
  liquidationId?: string;
  onBack: () => void;
}

export function AdvanceLiquidationForm({
  liquidationId,
  onBack,
}: AdvanceLiquidationFormProps) {
  const t = useTranslations("payments.form");
  const tn = useTranslations("payments.advanceLiquidations");
  const isEdit = Boolean(liquidationId);
  const meQuery = useMe();
  const [form, setForm] = useState<LiquidationFormValue>(() => toForm(null));
  const [hydratedId, setHydratedId] = useState<string | null>(null);

  const showQuery = useQuery({
    queryKey: ["advance-liquidations", "detail", liquidationId],
    queryFn: async (): Promise<AdvanceLiquidationResource> =>
      unwrap(
        await paymentsAdvanceLiquidationsShow(liquidationId!, withAuth())
      ),
    enabled: isEdit,
  });

  // The picker only offers approved advance requests — server-side filtered.
  const cashRequestsQuery = useQuery({
    queryKey: ["advance-requests", "approved"],
    queryFn: async () =>
      unwrapWithMeta<
        CashRequestResource[],
        { page: number; perPage: number; total: number }
      >(
        await paymentsAdvanceRequestsIndex(
          { status: "approved", per_page: 100 },
          withAuth()
        )
      ),
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
  // the liquidation was created — fetch it so the current pick keeps its label.
  const linkedMissing =
    isEdit &&
    !!form.cashRequestId &&
    !approvedOptions.some((option) => option.value === form.cashRequestId);
  const linkedCashRequestQuery = useQuery({
    queryKey: ["advance-requests", "detail", form.cashRequestId || null],
    queryFn: async (): Promise<CashRequestResource> =>
      unwrap(
        await paymentsAdvanceRequestsShow(form.cashRequestId, withAuth())
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

  const patch = (changes: Partial<LiquidationFormValue>) =>
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
      // Nothing else on a liquidation line to inherit — description and
      // amount start blank, currency follows the previous row.
      return {
        ...current,
        lines: [
          ...current.lines,
          { ...EMPTY_LINE, currency: last?.currency ?? "USD" },
        ],
      };
    });

  const headerRate =
    form.exchangeRate.trim() === "" ? 1 : toNumber(form.exchangeRate);
  const totalActualExpense = form.lines.reduce(
    (sum, line) =>
      sum + lineAmountUsd(toNumber(line.amount), line.currency, headerRate),
    0
  );
  const hasKhrLine = form.lines.some((line) => line.currency === "KHR");
  const selectedRequestTotal = pickerOptions.find(
    (option) => option.value === form.cashRequestId
  )?.totalUsd;

  // UX-only gate: the API's 422 is the source of truth.
  const missingLines = form.lines.some(hasLineError);
  const canSave =
    !!form.cashRequestId &&
    !!form.liquidationDate &&
    !!form.campus.trim() &&
    !!form.division.trim() &&
    !!form.department.trim() &&
    (form.exchangeRate.trim() === "" || toNumber(form.exchangeRate) > 0) &&
    (!hasKhrLine || toNumber(form.exchangeRate) > 0) &&
    form.lines.length > 0 &&
    !missingLines;

  const save = async (): Promise<string> => {
    const payload: StoreAdvanceLiquidationRequest = {
      cash_request_id: form.cashRequestId,
      liquidation_date: toDateInput(form.liquidationDate) ?? "",
      exchange_rate:
        form.exchangeRate.trim() === "" ? null : toNumber(form.exchangeRate),
      campus: form.campus.trim(),
      division: form.division.trim(),
      department: form.department.trim(),
      lines: form.lines.map((line) => ({
        description: line.description.trim(),
        currency: line.currency,
        amount: toNumber(line.amount),
      })),
    };

    if (isEdit) {
      unwrap(
        await paymentsAdvanceLiquidationsUpdate(
          liquidationId!,
          payload,
          withAuth()
        )
      );
      return liquidationId!;
    }
    const created = unwrap<{ id: string }>(
      await paymentsAdvanceLiquidationsStore(payload, withAuth())
    );
    return created.id;
  };

  const approval = useApprovalSubmit({
    subjectType: ADVANCE_LIQUIDATION_SUBJECT,
    isEdit,
    documentId: liquidationId,
    invalidateKey: ["advance-liquidations"],
    createEditPath: (id) => `/payments/advance-liquidations/${id}/edit`,
    createdMessage: tn("created"),
    updatedMessage: tn("updated"),
    canSave,
    save,
    snapshot: JSON.stringify(form),
    amount: isEdit && !hydratedId ? null : totalActualExpense,
  });

  if (isEdit && showQuery.data && hydratedId !== showQuery.data.id) {
    const next = toForm(showQuery.data);
    setHydratedId(showQuery.data.id);
    setForm(next);
    approval.markClean(JSON.stringify(next));
  }

  if (isEdit && showQuery.isPending) {
    return <AdvanceLiquidationFormSkeleton />;
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
            <CardTitle>{t("liquidationDate")}</CardTitle>
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
                    {t("liquidationDate")} <RequiredMark />
                  </Label>
                  <DatePicker
                    value={form.liquidationDate}
                    onChange={(date) => patch({ liquidationDate: date })}
                    placeholder={t("liquidationDate")}
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
                  <Label htmlFor="al-rate" className={fieldLabelClass}>
                    {t("exchangeRate")} {hasKhrLine ? <RequiredMark /> : null}
                  </Label>
                  <Input
                    id="al-rate"
                    type="number"
                    inputMode="decimal"
                    min={0}
                    step="any"
                    value={form.exchangeRate}
                    onChange={(e) => patch({ exchangeRate: e.target.value })}
                    placeholder="1"
                    className="flex-1 text-right"
                  />
                </div>

                <div className="flex items-center gap-2">
                  <Label className={fieldLabelClass}>
                    {t("totalCashAdvance")}
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
                        className={cn(
                          cellInputClass,
                          "justify-start font-normal"
                        )}
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
                // liquidation it would just repeat the amount.
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
                  key: "totalActualExpense",
                  label: t("totalActualExpense"),
                  value: formatMoney(totalActualExpense, "USD"),
                },
                {
                  key: "totalCashAdvance",
                  label: t("totalCashAdvance"),
                  value:
                    selectedRequestTotal != null
                      ? formatMoney(selectedRequestTotal, "USD")
                      : "—",
                },
                {
                  key: "remainingCash",
                  label: t("remainingCash"),
                  value:
                    selectedRequestTotal != null
                      ? formatMoney(
                          selectedRequestTotal - totalActualExpense,
                          "USD"
                        )
                      : "—",
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

      {locked ? (
        <div className="flex items-center justify-end gap-2">
          <Button variant="outline" onClick={onBack}>
            {t("cancel")}
          </Button>
        </div>
      ) : (
        <SubmitApprovalPanel
          subjectType={ADVANCE_LIQUIDATION_SUBJECT}
          subjectId={liquidationId ?? null}
          amount={approval.draftAmount}
          dirty={approval.isDirty}
          disabled={approval.saving || !canSave}
          prepareDocument={approval.prepareDocument}
          onAssigneesChange={approval.onAssigneesChange}
          actions={
            <>
              <Button
                variant="outline"
                onClick={onBack}
                disabled={approval.saving}
              >
                {t("cancel")}
              </Button>
              <Button
                onClick={approval.handleSave}
                disabled={approval.saving || !canSave}
              >
                <Save />
                <span>{approval.saving ? t("saving") : t("save")}</span>
              </Button>
            </>
          }
        />
      )}
    </div>
  );
}
