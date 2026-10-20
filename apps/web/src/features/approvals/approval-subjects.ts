/**
 * Frontend twin of the API's ApprovalSubjectRegistry: every subject type the
 * approval engine understands, and where its document lives. The shared
 * approval components (submit panel, action dialog, detail page, tray) read
 * this instead of assuming one subject — adding a module to approvals is one
 * entry here, mirroring the API registry entry.
 */
export type ApprovalSubjectConfig = {
  /**
   * The document's TanStack Query key prefix (plural literal, one key → one
   * queryFn). Approval mutations invalidate this so the subject's own list
   * and detail pages re-read server state after submit/decide.
   */
  documentQueryKey: string[];
  /** The subject's detail page — where a submitted document is viewed. */
  detailPath: (subjectId: string) => string;
  /**
   * Where a returned document is fixed and resubmitted: evaluation's form
   * (its submit panel lives there), payments' detail page (same reason).
   */
  resubmitPath: (subjectId: string) => string;
};

export const APPROVAL_SUBJECTS: Record<string, ApprovalSubjectConfig> = {
  evaluation: {
    documentQueryKey: ["evaluations"],
    detailPath: (id) => `/purchase-orders/evaluations/${id}`,
    resubmitPath: (id) => `/purchase-orders/evaluations/${id}/edit`,
  },
  petty_cash_request: {
    documentQueryKey: ["petty-cash-requests"],
    detailPath: (id) => `/payments/petty-cash-requests/${id}`,
    resubmitPath: (id) => `/payments/petty-cash-requests/${id}`,
  },
  advance_request: {
    documentQueryKey: ["advance-requests"],
    detailPath: (id) => `/payments/advance-requests/${id}`,
    resubmitPath: (id) => `/payments/advance-requests/${id}`,
  },
  cash_request: {
    documentQueryKey: ["cash-requests"],
    detailPath: (id) => `/payments/cash-requests/${id}`,
    resubmitPath: (id) => `/payments/cash-requests/${id}`,
  },
  cash_clearance: {
    documentQueryKey: ["cash-clearances"],
    detailPath: (id) => `/payments/cash-clearances/${id}`,
    resubmitPath: (id) => `/payments/cash-clearances/${id}`,
  },
  advance_liquidation: {
    documentQueryKey: ["advance-liquidations"],
    detailPath: (id) => `/payments/advance-liquidations/${id}`,
    resubmitPath: (id) => `/payments/advance-liquidations/${id}`,
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
