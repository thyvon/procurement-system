"use client";

import { format, isValid, parseISO } from "date-fns";
import { useTranslations } from "next-intl";
import type { EvaluationResource } from "@/lib/api/model/evaluationResource";
import type {
  ApprovalRequestView,
  ApprovalStepSnapshot,
} from "@/features/approvals/approval-types";
import { useEntity } from "@/hooks/use-entity";
import { EvaluationMatrixReadOnly } from "./evaluation-matrix-readonly";
import type { EvaluationMatrixValue } from "./evaluation-matrix";

interface EvaluationPrintProps {
  evaluation: EvaluationResource;
  value: EvaluationMatrixValue;
  approvalRequest: ApprovalRequestView | null;
}

type SignatureColumn = {
  key: string;
  title: string;
  name: string;
  position: string;
  date: string;
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

export function EvaluationPrint({
  evaluation,
  value,
  approvalRequest,
}: EvaluationPrintProps) {
  const tf = useTranslations("purchaseOrders.form");
  const tp = useTranslations("purchaseOrders.print");
  const entity = useEntity();
  const logo = entity.data?.logo ?? null;

  const columns = signatureColumns(approvalRequest, [
    { key: "prepared", title: tp("prepared"), name: "", position: "", date: "" },
    { key: "reviewed", title: tp("reviewed"), name: "", position: "", date: "" },
    { key: "approved", title: tp("approved"), name: "", position: "", date: "" },
  ]);

  return (
    <div className="print-area hidden print:block">
      <div className="flex min-h-[186mm] flex-col gap-3 text-xs text-foreground">
        <div className="grid grid-cols-[1fr_auto_1fr] items-start gap-4">
          {logo ? (
            <img
              src={logo}
              alt=""
              className="h-14 w-28 shrink-0 object-contain"
            />
          ) : (
            <div aria-hidden className="h-14 w-28 shrink-0" />
          )}
          <h1 className="place-self-center text-center text-lg font-bold tracking-tight">
            {tf("title")}
          </h1>
          <div className="justify-self-end text-left leading-relaxed">
            <div>
              {tp("code")}: {tp("formCode")}
            </div>
            <div>{tp("version")}</div>
          </div>
        </div>

        <EvaluationMatrixReadOnly value={value} currency={evaluation.currency} />

        <div className="border border-border px-2 py-1.5">
          <span className="font-semibold">{tf("basis")}:</span>{" "}
          {evaluation.recommendationBasis || "—"}
        </div>

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
              <SignLine label={tp("name")} value={column.name} />
              <SignLine label={tp("position")} value={column.position} />
              <SignLine label={tp("date")} value={column.date} />
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
