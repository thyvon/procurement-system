"use client";

import type { ReactNode } from "react";
import { format, isValid, parseISO } from "date-fns";
import type { CashRequestResource } from "@/lib/api/model/cashRequestResource";
import type {
  ApprovalRequestView,
  ApprovalStepSnapshot,
} from "@/features/approvals/approval-types";
import { useEntity } from "@/hooks/use-entity";
import { bodyCell, headCell } from "@/components/line-items-table";
import { formatDate, formatMoney } from "@/lib/utils";
import {
  showsAdvanceRequestFor,
  showsPaymentFields,
} from "../cash-request-helpers";

interface CashRequestPrintProps {
  cashRequest: CashRequestResource;
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

/** Literal bilingual labels — the print sheet always ships in Khmer + English. */
const TYPE_LABELS: Record<string, string> = {
  petty_cash: "Petty Cash · ប្រាក់តូច",
  advance: "Advance · លុយទុកមុន",
  cash: "Cash · សាច់ប្រាក់",
};

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

function InfoRow({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="flex gap-1.5">
      <span className="shrink-0 text-muted-foreground">{label}:</span>
      <span className="min-w-0 flex-1 break-words">{value || "—"}</span>
    </div>
  );
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

function PrintSheet({
  cashRequest,
  approvalRequest,
  logo,
}: CashRequestPrintProps & { logo: string | null }) {
  const columns = signatureColumns(approvalRequest, [
    {
      key: "prepared",
      title: "Prepared by · រៀបចំដោយ",
      name: "",
      position: "",
      date: "",
      signature: null,
    },
    {
      key: "reviewed",
      title: "Reviewed by · ពិនិត្យដោយ",
      name: "",
      position: "",
      date: "",
      signature: null,
    },
    {
      key: "approved",
      title: "Approved by · យល់ព្រមដោយ",
      name: "",
      position: "",
      date: "",
      signature: null,
    },
  ]);

  const payFields = showsPaymentFields(cashRequest.type);
  const advanceFields = showsAdvanceRequestFor(cashRequest.type);

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
          <span className="block">សំណើសុំប្រាក់</span>
          <span className="block text-sm font-semibold">Cash Request</span>
        </h1>
        <div className="justify-self-end text-left leading-relaxed">
          <div>
            Code · លេខកូដ:{" "}
            <span className="font-mono">{cashRequest.code}</span>
          </div>
          <div>Status · ស្ថានភាព: {cashRequest.status}</div>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-x-8 gap-y-1 border border-border px-2 py-1.5 md:grid-cols-3">
        <InfoRow label="Type · ប្រភេទ" value={TYPE_LABELS[cashRequest.type] ?? cashRequest.type} />
        <InfoRow label="Campus · សាខា" value={cashRequest.campus} />
        <InfoRow label="Division · ផ្នែក" value={cashRequest.division} />
        <InfoRow label="Department · នាយកដ្ឋាន" value={cashRequest.department} />
        <InfoRow
          label="Request date · ថ្ងៃសុំ"
          value={formatDate(cashRequest.requestDate)}
        />
        <InfoRow
          label="Exchange rate · អត្រាប្តូរប្រាក់"
          value={String(cashRequest.exchangeRate ?? "")}
        />
        <InfoRow
          label="Total (USD) · សរុប (ដុល្លារ)"
          value={
            <span className="tabular-nums">
              {formatMoney(Number(cashRequest.totalUsd ?? 0), "USD")}
            </span>
          }
        />
        {payFields ? (
          <>
            <InfoRow
              label="Mode of payment · របៀបទូទាត់"
              value={cashRequest.modeOfPayment}
            />
            <InfoRow label="Payment to · ទូទាត់ឲ្យ" value={cashRequest.paymentTo} />
            <InfoRow
              label="Bank account no · លេខគណនីធនាគារ"
              value={cashRequest.bankAccountNo}
            />
            <InfoRow label="Bank name · ធនាគារ" value={cashRequest.bankName} />
          </>
        ) : null}
        {advanceFields ? (
          <InfoRow
            label="Adv. request for · សុំលុយជូន"
            value={cashRequest.advanceRequestFor}
          />
        ) : null}
        <InfoRow label="Remark · ចំណាំ" value={cashRequest.remark} />
      </div>

      <table className="w-full border-collapse text-xs table-fixed">
        <colgroup>
          <col />
          <col className="w-24" />
          <col className="w-32" />
          <col className="w-32" />
        </colgroup>
        <thead>
          <tr>
            <th className={headCell}>Description · ពិពណ៌នា</th>
            <th className={headCell}>Currency · រូបិយប័ណ្ណ</th>
            <th className={`${headCell} text-right`}>Amount · ចំនួនទឹកប្រាក់</th>
            <th className={`${headCell} text-right`}>
              Amount (USD) · ចំនួនទឹកប្រាក់ (USD)
            </th>
          </tr>
        </thead>
        <tbody>
          {(cashRequest.lines ?? []).map((line) => (
            <tr key={line.id}>
              <td className={`${bodyCell} break-words`}>{line.description}</td>
              <td className={bodyCell}>{line.currency}</td>
              <td className={`${bodyCell} text-right tabular-nums`}>
                {formatMoney(Number(line.amount ?? 0), line.currency)}
              </td>
              <td className={`${bodyCell} text-right tabular-nums`}>
                {formatMoney(Number(line.amountUsd ?? 0), "USD")}
              </td>
            </tr>
          ))}
        </tbody>
        <tfoot>
          <tr>
            <td className={`${headCell} text-right`} colSpan={3}>
              Total (USD) · សរុប (ដុល្លារ)
            </td>
            <td className={`${headCell} text-right tabular-nums`}>
              {formatMoney(Number(cashRequest.totalUsd ?? 0), "USD")}
            </td>
          </tr>
        </tfoot>
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
            <SignLine label="Name · ឈ្មោះ" value={column.name} />
            <SignLine label="Position · តួនាទី" value={column.position} />
            <SignLine label="Date · កាលបរិច្ឆេទ" value={column.date} />
          </div>
        ))}
      </div>
    </div>
  );
}

export function CashRequestPrint(props: CashRequestPrintProps) {
  const entity = useEntity();
  const logo = entity.data?.logo ?? null;

  return (
    <div className="print-area hidden print:block">
      <PrintSheet {...props} logo={logo} />
    </div>
  );
}
