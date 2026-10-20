"use client";

import { format, isValid, parseISO } from "date-fns";
import { NextIntlClientProvider } from "next-intl";
import enMessages from "../../../../messages/en.json";
import type { AdvanceLiquidationResource } from "@/lib/api/model/advanceLiquidationResource";
import type {
  ApprovalRequestView,
  ApprovalStepSnapshot,
} from "@/features/approvals/approval-types";
import { useEntity } from "@/hooks/use-entity";
import { timeZone } from "@/i18n/config";
import { formatMoney } from "@/lib/utils";

interface AdvanceLiquidationPrintProps {
  liquidation: AdvanceLiquidationResource;
  approvalRequest: ApprovalRequestView | null;
}

type SignatureColumn = {
  key: string;
  title: string;
  name: string;
  position: string;
  date: string;
  signature: string | null;
};

// Literal Khmer + English labels — the official sheet is bilingual by design,
// not by locale.
const L = {
  titleKm: "ការទូទាត់ប្រាក់ជាមុន",
  titleEn: "ADVANCE LIQUIDATION",
  code: ["កូដ", "Code"],
  cashRequest: ["សំណើសាច់ប្រាក់", "Cash request"],
  liquidationDate: ["ថ្ងៃទូទាត់ជាមុន", "Liquidation date"],
  exchangeRate: ["អត្រាប្តូរប្រាក់", "Exchange rate"],
  campus: ["សាខា", "Campus"],
  division: ["ផ្នែក", "Division"],
  department: ["នាយកដ្ឋាន", "Department"],
  totalCashAdvance: ["សរុបប្រាក់ជាមុន", "Total cash advance"],
  totalActualExpense: ["ចំណាយពិតសរុប", "Total actual expense"],
  remainingCash: ["សាច់ប្រាក់នៅសល់", "Remaining cash"],
  description: ["ការពិពណ៌នា", "Description"],
  currency: ["រូបិយប័ណ្ណ", "Currency"],
  amount: ["ចំនួនទឹកប្រាក់", "Amount"],
  amountUsd: ["ចំនួនទឹកប្រាក់ (USD)", "Amount (USD)"],
  prepared: ["អ្នករៀបចំ", "Prepared by"],
  reviewed: ["អ្នកត្រួតពិនិត្យ", "Reviewed by"],
  approved: ["អ្នកយល់ព្រម", "Approved by"],
  name: ["ឈ្មោះ", "Name"],
  position: ["តួនាទី", "Position"],
  date: ["កាលបរិច្ឆេទ", "Date"],
} as const;

function Bi({ pair }: { pair: readonly [string, string] }) {
  return (
    <>
      <span className="block">{pair[0]}</span>
      <span className="block">{pair[1]}</span>
    </>
  );
}

function dateOnly(value: string | null | undefined): string {
  if (!value) return "";
  const datePart = (value.split(" ")[0] ?? "").split("T")[0] ?? "";
  const date = parseISO(datePart);
  return isValid(date) ? format(date, "MMM dd, yyyy") : "";
}

function actionForStep(
  request: ApprovalRequestView,
  step: ApprovalStepSnapshot
) {
  return (
    request.actions
      .filter((action) => action.stepPosition === step.position)
      .at(-1) ?? null
  );
}

function signatureColumns(
  request: ApprovalRequestView | null,
  fallback: SignatureColumn[]
): SignatureColumn[] {
  if (!request) {
    return fallback;
  }

  const visible = request.steps.filter((step) => step.showOnPrint);

  if (visible.length === 0) {
    return fallback;
  }

  return visible.map((step) => {
    const action = actionForStep(request, step);
    const decided = action !== null && action.action !== "cancel";
    const name = step.assigneeName ?? action?.actor.name ?? "";
    const date =
      step.actionMode === "record"
        ? dateOnly(request.submittedAt)
        : dateOnly(action?.actedAt);

    return {
      key: step.key,
      title: step.label,
      name,
      position: step.assigneePosition ?? "",
      date,
      signature: decided ? (step.assigneeSignature ?? null) : null,
    };
  });
}

function SignLine({ label, value }: { label: string; value?: string }) {
  return (
    <div className="flex items-end gap-1.5">
      <span className="shrink-0">{label}:</span>
      {value ? (
        <span className="min-w-0 flex-1 break-words">{value}</span>
      ) : (
        <span
          aria-hidden
          className="h-[1.15em] min-w-0 flex-1 border-b border-border"
        />
      )}
    </div>
  );
}

function MetaCell({
  pair,
  value,
}: {
  pair: readonly [string, string];
  value: string;
}) {
  return (
    <div className="flex items-start gap-1.5">
      <span className="shrink-0 font-semibold">
        <Bi pair={pair} />:
      </span>
      <span className="min-w-0 break-words">{value || "—"}</span>
    </div>
  );
}

function PrintSheet({
  liquidation,
  approvalRequest,
  logo,
}: AdvanceLiquidationPrintProps & { logo: string | null }) {
  const lines = liquidation.lines ?? [];
  const totalExpense = lines.reduce(
    (sum, line) => sum + Number(line.amountUsd ?? 0),
    0
  );

  const columns = signatureColumns(approvalRequest, [
    {
      key: "prepared",
      title: `${L.prepared[0]} / ${L.prepared[1]}`,
      name: "",
      position: "",
      date: "",
      signature: null,
    },
    {
      key: "reviewed",
      title: `${L.reviewed[0]} / ${L.reviewed[1]}`,
      name: "",
      position: "",
      date: "",
      signature: null,
    },
    {
      key: "approved",
      title: `${L.approved[0]} / ${L.approved[1]}`,
      name: "",
      position: "",
      date: "",
      signature: null,
    },
  ]);

  return (
    <div className="flex min-h-[186mm] flex-col gap-3 text-xs text-foreground">
      <div className="grid grid-cols-[1fr_auto_1fr] items-start gap-4">
        {logo ? (
          // eslint-disable-next-line @next/next/no-img-element -- the logo/signature URLs come from the API at runtime; mirrors evaluation-print.tsx
          <img
            src={logo}
            alt=""
            className="h-14 w-28 shrink-0 object-contain"
          />
        ) : (
          <div aria-hidden className="h-14 w-28 shrink-0" />
        )}
        <h1 className="place-self-center text-center text-lg font-bold tracking-tight">
          <span className="block">{L.titleKm}</span>
          <span className="block">{L.titleEn}</span>
        </h1>
        <div className="justify-self-end text-left leading-relaxed">
          <div>
            {L.code[0]} / {L.code[1]}:{" "}
            <span className="font-mono">{liquidation.code}</span>
          </div>
          <div>
            {L.liquidationDate[0]} / {L.liquidationDate[1]}:{" "}
            {dateOnly(liquidation.liquidationDate) || "—"}
          </div>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-x-8 gap-y-1 border border-border px-2 py-1.5 md:grid-cols-3">
        <MetaCell
          pair={L.cashRequest}
          value={liquidation.cashRequestCode ?? liquidation.cashRequestId}
        />
        <MetaCell
          pair={L.exchangeRate}
          value={String(liquidation.exchangeRate ?? "")}
        />
        <MetaCell pair={L.campus} value={liquidation.campus} />
        <MetaCell pair={L.division} value={liquidation.division} />
        <MetaCell pair={L.department} value={liquidation.department} />
        <MetaCell
          pair={L.totalCashAdvance}
          value={formatMoney(Number(liquidation.totalCashAdvanceUsd ?? 0), "USD")}
        />
        <MetaCell
          pair={L.totalActualExpense}
          value={formatMoney(
            Number(liquidation.totalActualExpenseUsd ?? totalExpense),
            "USD"
          )}
        />
        <MetaCell
          pair={L.remainingCash}
          value={formatMoney(Number(liquidation.remainingCashUsd ?? 0), "USD")}
        />
      </div>

      <table className="w-full border-collapse text-[10px]">
        <thead>
          <tr>
            <th className="w-8 border border-border bg-muted/60 px-1 py-1 text-center font-medium">
              #
            </th>
            <th className="border border-border bg-muted/60 px-1 py-1 text-left font-medium">
              <Bi pair={L.description} />
            </th>
            <th className="w-24 border border-border bg-muted/60 px-1 py-1 text-left font-medium">
              <Bi pair={L.currency} />
            </th>
            <th className="w-32 border border-border bg-muted/60 px-1 py-1 text-right font-medium">
              <Bi pair={L.amount} />
            </th>
            <th className="w-32 border border-border bg-muted/60 px-1 py-1 text-right font-medium">
              <Bi pair={L.amountUsd} />
            </th>
          </tr>
        </thead>
        <tbody>
          {lines.map((line, index) => (
            <tr key={line.id}>
              <td className="border border-border px-1 py-1 text-center tabular-nums">
                {index + 1}
              </td>
              <td className="border border-border px-1 py-1">
                {line.description}
              </td>
              <td className="border border-border px-1 py-1">
                {line.currency || "USD"}
              </td>
              <td className="border border-border px-1 py-1 text-right tabular-nums">
                {formatMoney(Number(line.amount), line.currency || "USD")}
              </td>
              <td className="border border-border px-1 py-1 text-right tabular-nums">
                {formatMoney(Number(line.amountUsd), "USD")}
              </td>
            </tr>
          ))}
          <tr>
            <td className="border border-border px-1 py-1" colSpan={4}>
              <span className="float-right font-semibold">
                {L.totalActualExpense[0]} / {L.totalActualExpense[1]}:
              </span>
            </td>
            <td className="border border-border px-1 py-1 text-right font-semibold tabular-nums">
              {formatMoney(
                Number(liquidation.totalActualExpenseUsd ?? totalExpense),
                "USD"
              )}
            </td>
          </tr>
        </tbody>
      </table>

      <div
        className="print-signature mt-auto grid gap-8 pt-4"
        style={{
          gridTemplateColumns: `repeat(${columns.length}, minmax(0, 1fr))`,
        }}
      >
        {columns.map((column) => (
          <div key={column.key} className="flex flex-col gap-1.5">
            <div className="mb-1 text-center font-semibold">
              {column.title}
            </div>
            {column.signature ? (
              <div className="flex h-12 items-end justify-center">
                {/* eslint-disable-next-line @next/next/no-img-element -- signature URLs come from the API at runtime; mirrors evaluation-print.tsx */}
                <img
                  src={column.signature}
                  alt=""
                  className="max-h-12 w-auto max-w-full object-contain"
                />
              </div>
            ) : null}
            <SignLine
              label={`${L.name[0]} / ${L.name[1]}`}
              value={column.name}
            />
            <SignLine
              label={`${L.position[0]} / ${L.position[1]}`}
              value={column.position}
            />
            <SignLine label={`${L.date[0]} / ${L.date[1]}`} value={column.date} />
          </div>
        ))}
      </div>
    </div>
  );
}

export function AdvanceLiquidationPrint(props: AdvanceLiquidationPrintProps) {
  const entity = useEntity();
  const logo = entity.data?.logo ?? null;

  return (
    <div className="print-area hidden print:block">
      <NextIntlClientProvider locale="en" messages={enMessages} timeZone={timeZone}>
        <PrintSheet {...props} logo={logo} />
      </NextIntlClientProvider>
    </div>
  );
}
