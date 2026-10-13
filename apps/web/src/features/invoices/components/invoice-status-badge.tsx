"use client";

import { useTranslations } from "next-intl";
import { Badge } from "@/components/ui/badge";

const STATUS_LABEL_KEYS: Record<string, string> = {
  draft: "draft",
  recorded: "recorded",
  cancelled: "cancelled",
};

const STATUS_VARIANTS: Record<
  string,
  "default" | "secondary" | "destructive" | "outline"
> = {
  draft: "outline",
  recorded: "default",
  cancelled: "destructive",
};

export function InvoiceStatusBadge({ status }: { status: string | null }) {
  const t = useTranslations("invoices.table");

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
