/**
 * Frontend twin of the API's ApprovalSubjectRegistry: every subject type the
 * approval engine understands, and where its document lives. The shared
 * approval components (submit panel, action dialog, detail page, tray) read
 * this instead of assuming one subject — adding a module to approvals is one
 * entry here, mirroring the API registry entry.
 */
import { getPaymentsAdvanceLiquidationsPrintUrl } from "@/lib/api/advance-liquidation/advance-liquidation";
import { getPaymentsAdvanceRequestsPrintUrl } from "@/lib/api/advance-request/advance-request";
import { getPaymentsCashClearancesPrintUrl } from "@/lib/api/cash-clearance/cash-clearance";
import { getPaymentsCashRequestsPrintUrl } from "@/lib/api/cash-request/cash-request";
import { getPaymentsPettyCashRequestsPrintUrl } from "@/lib/api/petty-cash-request/petty-cash-request";
import { getPurchaseOrdersEvaluationsPrintUrl } from "@/lib/api/evaluation/evaluation";

type ApprovalSubjectConfig = {
  /**
   * The document's TanStack Query key prefix (plural literal, one key → one
   * queryFn). Approval mutations invalidate this so the subject's own list
   * and detail pages re-read server state after submit/decide.
   */
  documentQueryKey: string[];
  /** The subject's detail page — where a submitted document is viewed. */
  detailPath: (subjectId: string) => string;
  /**
   * Where a returned document is fixed and resubmitted: both modules' edit
   * form — the submit panel lives there, never on the read-only detail page.
   */
  resubmitPath: (subjectId: string) => string;
  /**
   * The server-rendered PDF of the document — every print action in the app
   * opens this backend sheet, the same one the subject's detail page prints.
   */
  printUrl: (subjectId: string) => string;
};

const APPROVAL_SUBJECTS: Record<string, ApprovalSubjectConfig> = {
  evaluation: {
    documentQueryKey: ["evaluations"],
    detailPath: (id) => `/purchase-orders/evaluations/${id}`,
    resubmitPath: (id) => `/purchase-orders/evaluations/${id}/edit`,
    printUrl: getPurchaseOrdersEvaluationsPrintUrl,
  },
  petty_cash_request: {
    documentQueryKey: ["petty-cash-requests"],
    detailPath: (id) => `/payments/petty-cash-requests/${id}`,
    resubmitPath: (id) => `/payments/petty-cash-requests/${id}/edit`,
    printUrl: getPaymentsPettyCashRequestsPrintUrl,
  },
  advance_request: {
    documentQueryKey: ["advance-requests"],
    detailPath: (id) => `/payments/advance-requests/${id}`,
    resubmitPath: (id) => `/payments/advance-requests/${id}/edit`,
    printUrl: getPaymentsAdvanceRequestsPrintUrl,
  },
  cash_request: {
    documentQueryKey: ["cash-requests"],
    detailPath: (id) => `/payments/cash-requests/${id}`,
    resubmitPath: (id) => `/payments/cash-requests/${id}/edit`,
    printUrl: getPaymentsCashRequestsPrintUrl,
  },
  cash_clearance: {
    documentQueryKey: ["cash-clearances"],
    detailPath: (id) => `/payments/cash-clearances/${id}`,
    resubmitPath: (id) => `/payments/cash-clearances/${id}/edit`,
    printUrl: getPaymentsCashClearancesPrintUrl,
  },
  advance_liquidation: {
    documentQueryKey: ["advance-liquidations"],
    detailPath: (id) => `/payments/advance-liquidations/${id}`,
    resubmitPath: (id) => `/payments/advance-liquidations/${id}/edit`,
    printUrl: getPaymentsAdvanceLiquidationsPrintUrl,
  },
};

/**
 * Unknown types resolve to undefined: callers fall back to approvals-only
 * invalidation and generic navigation, same fallback style as subjectLabel.
 */
export function approvalSubject(
  subjectType: string
): ApprovalSubjectConfig | undefined {
  return APPROVAL_SUBJECTS[subjectType];
}
