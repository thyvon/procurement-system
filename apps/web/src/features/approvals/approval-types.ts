import { unwrapWithMeta, withAuth } from "@/lib/api-client";
import { approvalsRequestsIndex } from "@/lib/api/approval-request/approval-request";

export type ApprovalCandidate = {
  id: number;
  name: string;
};

export type ApprovalFlowView = {
  id: unknown;
  code: string;
  name: string;
  minAmount: string;
  maxAmount: string | null;
};

export type ApprovalPreviewStep = {
  position: number;
  key: string;
  label: string;
  actionMode: "record" | "decide";
  allowedActions: string[];
  showOnPrint?: boolean;
  candidates: ApprovalCandidate[] | null;
};

export type ApprovalPreview = {
  subjectType: string;
  subjectId: string | null;
  documentCode: string | null;
  amount: string;
  flow: ApprovalFlowView;
  /** Step position => user id, restored from the saved approval draft. */
  assignees?: Record<string, number> | null;
  steps: ApprovalPreviewStep[];
};

export type ApprovalStepSnapshot = {
  position: number;
  key: string;
  label: string;
  actionMode: "record" | "decide";
  allowedActions: string[];
  /** Hidden steps still decide; only the official print sheet omits them. */
  showOnPrint: boolean;
  assigneeId: number | null;
  assigneeName: string | null;
  assigneePosition?: string | null;
  assigneeSignature?: string | null;
};

export type ApprovalCurrentStep = {
  position: number;
  key: string;
  label: string;
  assigneeId: number | null;
  assigneeName: string | null;
  assigneePosition?: string | null;
  assigneeSignature?: string | null;
};

export type ApprovalActionView = {
  id: string;
  stepPosition: number;
  stepKey: string;
  action: string;
  comment: string | null;
  actor: { id: number | null; name: string | null };
  actedAt: string | null;
};

export type ApprovalStatus =
  | "pending"
  | "approved"
  | "rejected"
  | "returned"
  | "cancelled";

export type ApprovalAction = "approve" | "reject" | "return";

/**
 * What the document looked like the moment this round was submitted —
 * frozen server-side, so a later round can show what changed after a return.
 */
export type ApprovalDocumentSnapshot = {
  awardedTotal: string;
  currency: string;
  recommendationBasis: string;
  items: string[];
  selectedSuppliers: string[];
};

export type ApprovalRequestView = {
  id: string;
  subjectType: string;
  subjectId: string;
  status: ApprovalStatus;
  documentCode: string | null;
  amountSnapshot: string;
  flow: ApprovalFlowView | null;
  currentStep: ApprovalCurrentStep | null;
  steps: ApprovalStepSnapshot[];
  submittedBy: string | null;
  submittedAt: string | null;
  decidedAt: string | null;
  createdById: number | null;
  document: ApprovalDocumentSnapshot | null;
  actions: ApprovalActionView[];
};

export const EVALUATION_SUBJECT = "evaluation";

/**
 * Every approval round ever submitted for a document, newest first —
 * the shared queryFn behind ["approvals","requests","subject",type,id],
 * so return/reject/resubmit cycles stay visible as one audit trail.
 */
export async function fetchApprovalRounds(
  subjectType: string,
  subjectId: string
): Promise<ApprovalRequestView[]> {
  const envelope = unwrapWithMeta<unknown>(
    await approvalsRequestsIndex(
      { subject_type: subjectType, subject_id: subjectId, per_page: 100 },
      withAuth()
    )
  );
  const rows = (envelope.data ?? []) as unknown[];
  return rows.map((row) => parseApprovalRequest(row));
}

type FlowWithSteps = {
  setting?: { subjectType: string };
  steps?: { key: string; label: string; actionMode: string }[];
};

/**
 * Decide-step key → label across flows (first label wins per key),
 * optionally limited to one subject type. Record steps are excluded —
 * users cannot be authority-scoped to a step they only get stamped into.
 */
export function decideStepLabels(
  flows: readonly FlowWithSteps[] | undefined,
  subjectType?: string
): Map<string, string> {
  const labels = new Map<string, string>();
  for (const flow of flows ?? []) {
    if (subjectType !== undefined && flow.setting?.subjectType !== subjectType) {
      continue;
    }
    for (const step of flow.steps ?? []) {
      if (step.actionMode === "decide" && !labels.has(step.key)) {
        labels.set(step.key, step.label);
      }
    }
  }
  return labels;
}

function toNumber(value: unknown): number {
  return Number(value ?? 0);
}

function toNullableNumber(value: unknown): number | null {
  if (value === null || value === undefined || value === "") return null;
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : null;
}

function parseDocument(raw: unknown): ApprovalDocumentSnapshot | null {
  if (raw === null || typeof raw !== "object" || Array.isArray(raw)) {
    return null;
  }
  const snapshot = raw as Record<string, unknown>;
  const list = (value: unknown): string[] =>
    Array.isArray(value) ? value.map((entry) => String(entry)) : [];

  return {
    awardedTotal: String(snapshot.awardedTotal ?? "0"),
    currency: String(snapshot.currency ?? ""),
    recommendationBasis: String(snapshot.recommendationBasis ?? ""),
    items: list(snapshot.items),
    selectedSuppliers: list(snapshot.selectedSuppliers),
  };
}

export function parseApprovalRequest(raw: unknown): ApprovalRequestView {
  const record = (raw ?? {}) as Record<string, unknown>;
  const steps = Array.isArray(record.steps) ? record.steps : [];
  const actions = Array.isArray(record.actions) ? record.actions : [];
  const currentStep = record.currentStep as Record<string, unknown> | null;

  return {
    id: String(record.id ?? ""),
    subjectType: String(record.subjectType ?? ""),
    subjectId: String(record.subjectId ?? ""),
    status: (record.status ?? "pending") as ApprovalStatus,
    documentCode: (record.documentCode as string | null) ?? null,
    amountSnapshot: String(record.amountSnapshot ?? "0"),
    flow: (record.flow as ApprovalFlowView | null) ?? null,
    currentStep: currentStep
      ? {
          position: toNumber(currentStep.position),
          key: String(currentStep.key ?? ""),
          label: String(currentStep.label ?? ""),
          assigneeId: toNullableNumber(currentStep.assigneeId),
          assigneeName: (currentStep.assigneeName as string | null) ?? null,
          assigneePosition:
            (currentStep.assigneePosition as string | null) ?? null,
          assigneeSignature:
            (currentStep.assigneeSignature as string | null) ?? null,
        }
      : null,
    steps: steps.map((step) => {
      const snapshot = step as Record<string, unknown>;
      return {
        position: toNumber(snapshot.position),
        key: String(snapshot.key ?? ""),
        label: String(snapshot.label ?? ""),
        actionMode: (snapshot.actionMode === "record" ? "record" : "decide") as
          | "record"
          | "decide",
        allowedActions: Array.isArray(snapshot.allowedActions)
          ? (snapshot.allowedActions as string[])
          : [],
        // Snapshots predating the flag print every step.
        showOnPrint: snapshot.showOnPrint !== false,
        assigneeId: toNullableNumber(snapshot.assigneeId),
        assigneeName: (snapshot.assigneeName as string | null) ?? null,
        assigneePosition:
          (snapshot.assigneePosition as string | null) ?? null,
        assigneeSignature:
          (snapshot.assigneeSignature as string | null) ?? null,
      };
    }),
    submittedBy: (record.submittedBy as string | null) ?? null,
    submittedAt: (record.submittedAt as string | null) ?? null,
    decidedAt: (record.decidedAt as string | null) ?? null,
    createdById: toNullableNumber(record.createdById),
    document: parseDocument(record.document),
    actions: actions.map((action) => {
      const entry = action as Record<string, unknown>;
      const actor = (entry.actor ?? {}) as Record<string, unknown>;
      return {
        id: String(entry.id ?? ""),
        stepPosition: toNumber(entry.stepPosition),
        stepKey: String(entry.stepKey ?? ""),
        action: String(entry.action ?? ""),
        comment: (entry.comment as string | null) ?? null,
        actor: {
          id: toNullableNumber(actor.id),
          name: (actor.name as string | null) ?? null,
        },
        actedAt: (entry.actedAt as string | null) ?? null,
      };
    }),
  };
}
