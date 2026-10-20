"use client";

import { format, isValid, parseISO } from "date-fns";
import { NextIntlClientProvider } from "next-intl";
import enMessages from "../../../../messages/en.json";
import type { CashClearanceResource } from "@/lib/api/model/cashClearanceResource";
import type {
  ApprovalRequestView,
  ApprovalStepSnapshot,
} from "@/features/approvals/approval-types";
import { useEntity } from "@/hooks/use-entity";
import { timeZone } from "@/i18n/config";
import { formatMoney } from "@/lib/utils";

interface CashClearancePrintProps {
  clearance: CashClearanceResource;
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
  titleKm: "ការទូទាត់សាច់ប្រាក់",
  titleEn: "CASH CLEARANCE",
  code: ["កូដ", "Code"],
  cashRequest: ["សំណើសាច់ប្រាក់", "Cash request"],
  clearanceDate: ["ថ្ងៃទូទាត់", "Clearance date"],
  exchangeRate: ["អត្រាប្តូរប្រាក់", "Exchange rate"],
  requestAmount: ["ចំនួនស្នើសុំ", "Request amount"],
  total: ["សរុប (USD)", "Total (USD)"],
  balance: ["សមតុល្យ", "Balance"],
  invoiceDate: ["ថ្ងៃវិក្កយបត្រ", "Invoice date"],
  prRef: ["លេខយោង PR", "PR ref"],
  purchaser: ["អ្នកទិញ", "Purchaser"],
  description: ["ការពិពណ៌នា", "Description"],
  vendor: ["អ្នកផ្គត់ផ្គង់", "Vendor"],
  campus: ["សាខា", "Campus"],
  division: ["ផ្នែក", "Division"],
  department: ["នាយកដ្ឋាន", "Department"],
  qty: ["ចំនួន", "Qty"],
  uom: ["ឯកតា", "UoM"],
  unitPrice: ["តម្លៃឯកតា", "Unit price"],
  discount: ["បញ្ចុះតម្លៃ", "Discount"],
  vat: ["អាករ", "VAT"],
  amount: ["ចំនួនទឹកប្រាក់", "Amount"],
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

function MetaCell({ pair, value }: { pair: readonly [string, string]; value: string }) {
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
  clearance,
  approvalRequest,
  logo,
}: CashClearancePrintProps & { logo: string | null }) {
  const lines = clearance.lines ?? [];
  const total = lines.reduce((sum, line) => sum + Number(line.amountUsd ?? 0), 0);

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
            {L.code[0]} / {L.code[1]}: <span className="font-mono">{clearance.code}</span>
          </div>
          <div>
            {L.clearanceDate[0]} / {L.clearanceDate[1]}:{" "}
            {dateOnly(clearance.clearanceDate) || "—"}
          </div>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-x-8 gap-y-1 border border-border px-2 py-1.5 md:grid-cols-3">
        <MetaCell pair={L.cashRequest} value={clearance.cashRequestId} />
        <MetaCell pair={L.exchangeRate} value={String(clearance.exchangeRate ?? "")} />
        <MetaCell
          pair={L.requestAmount}
          value={formatMoney(Number(clearance.requestAmountUsd ?? 0), "USD")}
        />
        <MetaCell
          pair={L.total}
          value={formatMoney(Number(clearance.totalUsd ?? total), "USD")}
        />
        <MetaCell
          pair={L.balance}
          value={formatMoney(Number(clearance.balanceUsd ?? 0), "USD")}
        />
      </div>

      <table className="w-full border-collapse text-[10px]">
        <thead>
          <tr>
            <th className="border border-border bg-muted/60 px-1 py-1 text-left font-medium">
              #
            </th>
            <th className="border border-border bg-muted/60 px-1 py-1 text-left font-medium">
              <Bi pair={L.invoiceDate} />
            </th>
            <th className="border border-border bg-muted/60 px-1 py-1 text-left font-medium">
              <Bi pair={L.prRef} />
            </th>
            <th className="border border-border bg-muted/60 px-1 py-1 text-left font-medium">
              <Bi pair={L.purchaser} />
            </th>
            <th className="border border-border bg-muted/60 px-1 py-1 text-left font-medium">
              <Bi pair={L.description} />
            </th>
            <th className="border border-border bg-muted/60 px-1 py-1 text-left font-medium">
              <Bi pair={L.vendor} />
            </th>
            <th className="border border-border bg-muted/60 px-1 py-1 text-left font-medium">
              <Bi pair={L.campus} />
            </th>
            <th className="border border-border bg-muted/60 px-1 py-1 text-left font-medium">
              <Bi pair={L.division} />
            </th>
            <th className="border border-border bg-muted/60 px-1 py-1 text-left font-medium">
              <Bi pair={L.department} />
            </th>
            <th className="border border-border bg-muted/60 px-1 py-1 text-right font-medium">
              <Bi pair={L.qty} />
            </th>
            <th className="border border-border bg-muted/60 px-1 py-1 text-left font-medium">
              <Bi pair={L.uom} />
            </th>
            <th className="border border-border bg-muted/60 px-1 py-1 text-right font-medium">
              <Bi pair={L.unitPrice} />
            </th>
            <th className="border border-border bg-muted/60 px-1 py-1 text-right font-medium">
              <Bi pair={L.discount} />
            </th>
            <th className="border border-border bg-muted/60 px-1 py-1 text-right font-medium">
              <Bi pair={L.vat} />
            </th>
            <th className="border border-border bg-muted/60 px-1 py-1 text-right font-medium">
              <Bi pair={L.amount} />
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
                {dateOnly(line.invoiceDate) || "—"}
              </td>
              <td className="border border-border px-1 py-1">{line.prRef || "—"}</td>
              <td className="border border-border px-1 py-1">{line.purchaser || "—"}</td>
              <td className="border border-border px-1 py-1">{line.description}</td>
              <td className="border border-border px-1 py-1">{line.vendor}</td>
              <td className="border border-border px-1 py-1">{line.campus}</td>
              <td className="border border-border px-1 py-1">{line.division}</td>
              <td className="border border-border px-1 py-1">{line.department}</td>
              <td className="border border-border px-1 py-1 text-right tabular-nums">
                {line.qty}
              </td>
              <td className="border border-border px-1 py-1">{line.uom}</td>
              <td className="border border-border px-1 py-1 text-right tabular-nums">
                {formatMoney(Number(line.unitPriceUsd), "USD")}
              </td>
              <td className="border border-border px-1 py-1 text-right tabular-nums">
                {formatMoney(Number(line.discountUsd), "USD")}
              </td>
              <td className="border border-border px-1 py-1 text-right tabular-nums">
                {formatMoney(Number(line.vatUsd), "USD")}
              </td>
              <td className="border border-border px-1 py-1 text-right font-semibold tabular-nums">
                {formatMoney(Number(line.amountUsd), "USD")}
              </td>
            </tr>
          ))}
          <tr>
            <td className="border border-border px-1 py-1" colSpan={14}>
              <span className="float-right font-semibold">
                {L.total[0]} / {L.total[1]}:
              </span>
            </td>
            <td className="border border-border px-1 py-1 text-right font-semibold tabular-nums">
              {formatMoney(Number(clearance.totalUsd ?? total), "USD")}
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
            <SignLine label={`${L.name[0]} / ${L.name[1]}`} value={column.name} />
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

export function CashClearancePrint(props: CashClearancePrintProps) {
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
