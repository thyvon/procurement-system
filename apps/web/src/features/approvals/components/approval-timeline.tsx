import { useTranslations } from "next-intl";
import { CheckCircle2, Circle, CircleDot } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import type { ApprovalRequestView, ApprovalStatus } from "../approval-types";

const STATUS_VARIANT: Record<
  ApprovalStatus,
  "default" | "secondary" | "destructive" | "outline"
> = {
  pending: "secondary",
  approved: "default",
  rejected: "destructive",
  returned: "outline",
};

export function ApprovalStatusBadge({ status }: { status: ApprovalStatus }) {
  const t = useTranslations("approvals.statuses");

  return <Badge variant={STATUS_VARIANT[status]}>{t(status)}</Badge>;
}

function actionLabelKey(action: string): string {
  switch (action) {
    case "submit":
      return "actionSubmit";
    case "record":
      return "actionRecord";
    case "approve":
      return "actionApprove";
    case "reject":
      return "actionReject";
    case "return":
      return "actionReturn";
    default:
      return "actionRecord";
  }
}

export function ApprovalTimeline({ request }: { request: ApprovalRequestView }) {
  const t = useTranslations("approvals.detail");
  const actions = request.actions ?? [];
  const current = request.currentStep;

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t("steps")}</CardTitle>
      </CardHeader>
      <CardContent className="space-y-5">
        <ol className="space-y-3">
          {request.steps.map((step) => {
            const done = actions.some(
              (action) => action.stepPosition === step.position
            );
            const isCurrent =
              request.status === "pending" && current?.position === step.position;

            return (
              <li key={step.position} className="flex items-start gap-3">
                <span className="mt-0.5 flex size-6 shrink-0 items-center justify-center">
                  {done ? (
                    <CheckCircle2 className="size-5 text-primary" />
                  ) : isCurrent ? (
                    <CircleDot className="size-5 text-muted-foreground" />
                  ) : (
                    <Circle className="size-4 text-muted-foreground/60" />
                  )}
                </span>
                <div className="flex min-w-0 flex-1 flex-wrap items-center gap-x-2 gap-y-1">
                  <span
                    className={
                      isCurrent ? "text-sm font-medium" : "text-sm"
                    }
                  >
                    {step.label}
                  </span>
                  <Badge variant="outline" className="text-[10px] font-normal">
                    {step.actionMode === "record"
                      ? t("autoRecorded")
                      : step.assigneeName ?? t("assignedTo")}
                  </Badge>
                  {isCurrent ? (
                    <span className="text-xs text-muted-foreground">
                      {t("waiting")}
                    </span>
                  ) : null}
                </div>
              </li>
            );
          })}
        </ol>

        <div className="space-y-3 border-t border-border pt-4">
          <h3 className="text-sm font-medium">{t("history")}</h3>
          {actions.length === 0 ? (
            <p className="text-sm text-muted-foreground">{t("noHistory")}</p>
          ) : (
            <ol className="space-y-3">
              {actions.map((action) => (
                <li key={action.id} className="flex items-start gap-3 text-sm">
                  <span className="mt-1 size-1.5 shrink-0 rounded-full bg-primary" />
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-x-2">
                      <span className="font-medium">
                        {t(actionLabelKey(action.action))}
                      </span>
                      <span className="text-muted-foreground">
                        {t("by", { name: action.actor.name ?? "—" })}
                      </span>
                      <span className="text-xs text-muted-foreground">
                        {action.actedAt}
                      </span>
                    </div>
                    {action.comment ? (
                      <p className="mt-1 whitespace-pre-wrap text-muted-foreground">
                        {action.comment}
                      </p>
                    ) : null}
                  </div>
                </li>
              ))}
            </ol>
          )}
        </div>
      </CardContent>
    </Card>
  );
}
