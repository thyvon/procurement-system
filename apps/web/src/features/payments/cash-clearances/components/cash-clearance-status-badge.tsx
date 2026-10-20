"use client";

import { useTranslations } from "next-intl";
import { Badge } from "@/components/ui/badge";

const STATUS_LABEL_KEYS: Record<string, string> = {
  draft: "draft",
  in_review: "inReview",
  approved: "approved",
  rejected: "rejected",
  returned: "returned",
  cancelled: "cancelled",
};

const STATUS_VARIANTS: Record<
  string,
  "default" | "secondary" | "destructive" | "outline"
> = {
  draft: "outline",
  in_review: "secondary",
  approved: "default",
  rejected: "destructive",
  returned: "outline",
  cancelled: "destructive",
};

export function CashClearanceStatusBadge({ status }: { status: string | null }) {
  const t = useTranslations("payments.table");

  if (!status) {
    return <span className="text-muted-foreground">—</span>;
  }

  const key = STATUS_LABEL_KEYS[status];
  return (
    <Badge variant={STATUS_VARIANTS[status] ?? "secondary"}>
      {key ? t(key) : status}
    </Badge>
  );
}
