import { Fragment, useState } from "react";
import { useTranslations } from "next-intl";
import { CheckCircle2, ChevronDown, Circle, CircleDot } from "lucide-react";
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

export function ApprovalTimeline({
  request,
  rounds,
}: {
  request: ApprovalRequestView;
  rounds?: ApprovalRequestView[];
}) {
  const t = useTranslations("approvals.detail");
  const actions = request.actions ?? [];
  const current = request.currentStep;
  // Steps only tell a live story while a decision is pending; once decided,
  // the status badge + history already say it all.
  const showSteps = request.status === "pending";
  // All rounds of this document, oldest first → Round 1, Round 2, …
  const historyRounds = [...(rounds?.length ? rounds : [request])].sort(
    (a, b) => (a.submittedAt ?? "").localeCompare(b.submittedAt ?? "")
  );
  const historyCount = historyRounds.reduce(
    (total, round) => total + (round.actions?.length ?? 0),
    0
  );
  const [openRounds, setOpenRounds] = useState<Record<string, boolean>>({});
  const isRoundOpen = (roundId: string, isLast: boolean) =>
    openRounds[roundId] ?? isLast;
  const toggleRound = (roundId: string, isLast: boolean) =>
    setOpenRounds((current) => ({
      ...current,
      [roundId]: !(current[roundId] ?? isLast),
    }));

  return (
    <Card>
      <CardHeader>
        <CardTitle>{showSteps ? t("steps") : t("history")}</CardTitle>
      </CardHeader>
      <CardContent className="space-y-5">
        {showSteps ? (
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
                    {step.assigneeName ??
                      (step.actionMode === "record"
                        ? t("autoRecorded")
                        : t("assignedTo"))}
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
        ) : null}

        <div
          className={
            showSteps ? "space-y-3 border-t border-border pt-4" : "space-y-3"
          }
        >
          {showSteps ? (
            <h3 className="text-sm font-medium">{t("history")}</h3>
          ) : null}
          {historyCount === 0 ? (
            <p className="text-sm text-muted-foreground">{t("noHistory")}</p>
          ) : (
            <table className="w-full border-collapse text-left align-top">
              {historyRounds.map((round, index) => {
                const roundActions = round.actions ?? [];
                const isLast = index === historyRounds.length - 1;
                const open = isRoundOpen(round.id, isLast);

                return (
                  <tbody key={round.id}>
                    <tr>
                      <td
                        colSpan={5}
                        className={`pb-1.5 ${index > 0 ? "pt-5" : "pt-0"}`}
                      >
                        <button
                          type="button"
                          onClick={() => toggleRound(round.id, isLast)}
                          aria-expanded={open}
                          className="flex w-full flex-wrap items-center gap-x-2 gap-y-1.5 rounded-md px-1 py-0.5 text-left outline-none transition-colors hover:bg-muted/50 focus-visible:ring-3 focus-visible:ring-ring/50"
                        >
                          <span
                            aria-hidden="true"
                            className="size-3.5 shrink-0 rounded-full bg-primary ring-4 ring-background"
                          />
                          {historyRounds.length > 1 ? (
                            <span className="text-sm font-semibold">
                              {t("round", { n: index + 1 })}
                            </span>
                          ) : null}
                          <ApprovalStatusBadge status={round.status} />
                          <span className="text-xs text-muted-foreground">
                            {t("submittedBy")} {round.submittedBy ?? "—"} ·{" "}
                            {round.submittedAt}
                            {round.decidedAt
                              ? ` · ${t("decidedAt")} ${round.decidedAt}`
                              : ""}
                          </span>
                          <ChevronDown
                            aria-hidden="true"
                            className={`ml-auto size-4 shrink-0 text-muted-foreground transition-transform ${open ? "rotate-0" : "-rotate-90"}`}
                          />
                        </button>
                      </td>
                    </tr>

                    {open && roundActions.length === 0 ? (
                      <tr>
                        <td
                          colSpan={5}
                          className="pb-1 pl-6 text-sm text-muted-foreground"
                        >
                          {t("noHistory")}
                        </td>
                      </tr>
                    ) : open ? (
                      roundActions.map((action) => {
                        const stepLabel = round.steps.find(
                          (step) => step.position === action.stepPosition
                        )?.label;

                        return (
                          <Fragment key={action.id}>
                            <tr>
                              <td className="w-6 py-1">
                                <span
                                  aria-hidden="true"
                                  className="mt-1.5 block size-2 rounded-full bg-primary/60"
                                />
                              </td>
                              <td className="py-1 pr-4 text-sm font-medium whitespace-nowrap">
                                {t(actionLabelKey(action.action))}
                              </td>
                              <td className="py-1 pr-4">
                                {stepLabel ? (
                                  <Badge
                                    variant="outline"
                                    className="text-[10px] font-normal"
                                  >
                                    {stepLabel}
                                  </Badge>
                                ) : null}
                              </td>
                              <td className="py-1 pr-4 text-xs text-muted-foreground">
                                {t("by", { name: action.actor.name ?? "—" })}
                              </td>
                              <td className="py-1 text-xs text-muted-foreground whitespace-nowrap">
                                {action.actedAt}
                              </td>
                            </tr>
                            {action.comment ? (
                              <tr>
                                <td />
                                <td colSpan={4} className="pb-2.5">
                                  <p className="whitespace-pre-wrap rounded-md border border-border bg-muted/40 px-2.5 py-1.5 text-xs leading-relaxed text-muted-foreground">
                                    {action.comment}
                                  </p>
                                </td>
                              </tr>
                            ) : null}
                          </Fragment>
                        );
                      })
                    ) : null}
                  </tbody>
                );
              })}
            </table>
          )}
        </div>
      </CardContent>
    </Card>
  );
}
