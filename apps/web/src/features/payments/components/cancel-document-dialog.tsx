"use client";

import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";

/**
 * Shared confirm step before `PATCH .../status { status: "cancelled" }` on a
 * payment document. The caller supplies its own namespace's
 * `cancelDocumentTitle`; the shared `payments.table` block owns the body and
 * the buttons.
 */
export function CancelDocumentDialog({
  open,
  code,
  title,
  pending,
  onOpenChange,
  onConfirm,
}: {
  open: boolean;
  code: string | null;
  title: string;
  pending: boolean;
  onOpenChange: (open: boolean) => void;
  onConfirm: () => void;
}) {
  const tt = useTranslations("payments.table");

  return (
    <Dialog open={open} onOpenChange={(next) => !next && onOpenChange(false)}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription>
            {tt.rich("cancelDocumentDescription", {
              name: code ?? "",
              strong: (chunks) => <strong>{chunks}</strong>,
            })}
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button
            variant="outline"
            onClick={() => onOpenChange(false)}
            disabled={pending}
          >
            {tt("cancel")}
          </Button>
          <Button onClick={onConfirm} disabled={pending}>
            {pending ? tt("cancelling") : tt("confirmCancelDocument")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
