"use client";

import { useRef, useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { toast } from "sonner";
import { approvalsDraftsStore } from "@/lib/api/approval-request/approval-request";
import type { StoreApprovalDraftRequest } from "@/lib/api/model/storeApprovalDraftRequest";
import { unwrap, withAuth } from "@/lib/api-client";
import { useDebouncedValue } from "@/hooks/use-debounced-value";

type UseApprovalSubmitOptions = {
  subjectType: string;
  isEdit: boolean;
  /** Existing document id (edit mode) or null/undefined in create mode. */
  documentId?: string | null;
  /** Literal query-key prefix invalidated after save (e.g. ["cash-requests"]). */
  invalidateKey: string[];
  /** Where a create-mode Save lands after the toast (the document's edit page). */
  createEditPath: (id: string) => string;
  /** Already-translated toast messages from the caller's namespace. */
  createdMessage: string;
  updatedMessage: string;
  /** UX gate mirroring the API's 422; false blocks save + prepareDocument. */
  canSave?: boolean;
  /** Form-specific persist; must resolve to the saved document id. */
  save: () => Promise<string>;
  /** Current form snapshot for dirty tracking (JSON string). */
  snapshot: string;
  /** Raw draft amount for the approval preview; debounced inside the hook. */
  amount: number | null;
  /** Extra validation run before save, toasting its own errors (evaluation). */
  beforeSave?: () => boolean;
};

/**
 * Shared submit orchestration for approval-bound document forms: baseline /
 * dirty tracking, draft-assignee persistence, the create-mode save→submit
 * chain the SubmitApprovalPanel drives, and the save toast + navigation.
 * Forms keep only their payload builder and validation gate.
 */
export function useApprovalSubmit({
  subjectType,
  isEdit,
  documentId = null,
  invalidateKey,
  createEditPath,
  createdMessage,
  updatedMessage,
  canSave = true,
  save,
  snapshot,
  amount,
  beforeSave,
}: UseApprovalSubmitOptions) {
  const qc = useQueryClient();
  const router = useRouter();
  const [baseline, setBaseline] = useState<string | null>(null);
  const pendingSaveRef = useRef("");
  const skipNavRef = useRef(false);
  const [approvalAssignees, setApprovalAssignees] = useState<
    Record<number, string[]>
  >({});

  // Persists the approver selection with "Save" — no approval request is
  // created until the panel's Submit.
  const draftMutation = useMutation({
    mutationFn: async (subjectId: string) =>
      unwrap(
        await approvalsDraftsStore(
          {
            subject_type: subjectType,
            subject_id: subjectId,
            assignees: Object.fromEntries(
              Object.entries(approvalAssignees).map(([position, userIds]) => [
                position,
                userIds.map(Number),
              ])
            ),
          } as unknown as StoreApprovalDraftRequest,
          withAuth()
        )
      ),
  });

  const saveMutation = useMutation({
    mutationFn: save,
    onSuccess: (id) => {
      setBaseline(pendingSaveRef.current);
      qc.invalidateQueries({ queryKey: invalidateKey });
      qc.invalidateQueries({ queryKey: ["approvals", "preview"] });
      if (skipNavRef.current) {
        // Save chained into a submit — the panel owns the toast + navigation.
        return;
      }
      if (Object.keys(approvalAssignees).length > 0) {
        draftMutation.mutate(id);
      }
      toast.success(isEdit ? updatedMessage : createdMessage);
      if (!isEdit) {
        router.push(createEditPath(id));
      }
    },
  });

  const isDirty = isEdit && baseline !== null && snapshot !== baseline;
  const draftAmount = useDebouncedValue(amount, 500);

  const handleSave = () => {
    if (beforeSave && !beforeSave()) return;
    pendingSaveRef.current = snapshot;
    saveMutation.mutate();
  };

  /**
   * Create mode: saves the document and returns its id so the approval
   * panel can chain straight into submitting. Returns null when the form
   * fails validation (toasts already explain why) or the save errors.
   */
  const prepareDocument = async (): Promise<string | null> => {
    if (isEdit) return documentId;
    if (!canSave) return null;
    if (beforeSave && !beforeSave()) return null;

    pendingSaveRef.current = snapshot;
    skipNavRef.current = true;
    try {
      return await saveMutation.mutateAsync();
    } finally {
      skipNavRef.current = false;
    }
  };

  return {
    saving: saveMutation.isPending,
    isDirty,
    draftAmount,
    onAssigneesChange: setApprovalAssignees,
    markClean: setBaseline,
    handleSave,
    prepareDocument,
  };
}
