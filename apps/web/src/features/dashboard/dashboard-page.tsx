"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import {
  ArrowUpRight,
  ChevronRight,
  Clock,
  DollarSign,
  FileCheck2,
  FileText,
  TrendingUp,
  Wallet,
} from "lucide-react";
import { cn, formatDate, formatMoney } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardAction,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  KPI_BY_PERIOD,
  MONTHLY_SPEND_TREND,
  PENDING_APPROVALS,
  RECENT_DOCUMENTS,
  SPEND_BREAKDOWN,
  type PeriodKey,
  type RecentDocumentRow,
} from "./mock-data";

type TableView = "approvals" | "recent";

const STATUS_PILL_STYLE: Record<RecentDocumentRow["status"], string> = {
  approved: "bg-primary/10 text-primary",
  pending: "bg-muted text-muted-foreground",
  returned: "bg-secondary text-secondary-foreground",
  rejected: "bg-destructive/10 text-destructive",
};

function initials(name: string): string {
  return name
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? "")
    .join("");
}

/**
 * Builds a smooth cubic-bezier SVG path (`M ... C ...`) through `(x, y)` points.
 */
function buildSmoothPath(points: Array<{ x: number; y: number }>): string {
  if (points.length === 0) return "";
  if (points.length === 1) return `M ${points[0].x} ${points[0].y}`;

  return points.reduce((path, point, idx, arr) => {
    if (idx === 0) return `M ${point.x} ${point.y}`;
    const prev = arr[idx - 1];
    const cpx1 = prev.x + (point.x - prev.x) * 0.45;
    const cpy1 = prev.y;
    const cpx2 = prev.x + (point.x - prev.x) * 0.55;
    const cpy2 = point.y;
    return `${path} C ${cpx1} ${cpy1}, ${cpx2} ${cpy2}, ${point.x} ${point.y}`;
  }, "");
}

export function DashboardPage() {
  const t = useTranslations("dashboard");
  const router = useRouter();

  const [period, setPeriod] = useState<PeriodKey>("month");
  const [tableView, setTableView] = useState<TableView>("approvals");
  const [hoveredMonthIdx, setHoveredMonthIdx] = useState<number | null>(null);
  const [hoveredChannel, setHoveredChannel] = useState<string | null>(null);

  const kpi = KPI_BY_PERIOD[period];

  const activeIdx =
    hoveredMonthIdx !== null
      ? hoveredMonthIdx
      : MONTHLY_SPEND_TREND.length - 1;
  const activeMonth = MONTHLY_SPEND_TREND[activeIdx];
  const activeMonthTotal =
    activeMonth.purchaseOrdersUsd +
    activeMonth.directInvoicesUsd +
    activeMonth.cashRequestsUsd;

  // Smooth SVG Area Chart coordinates (viewBox 600 x 190)
  const chartWidth = 600;
  const chartHeight = 190;
  const padX = 24;
  const padTop = 18;
  const padBottom = 16;
  const usableWidth = chartWidth - padX * 2;
  const usableHeight = chartHeight - padTop - padBottom;
  const maxVal = 160_000;
  const baselineY = padTop + usableHeight;

  const totalPoints = MONTHLY_SPEND_TREND.map((m, idx) => {
    const total =
      m.purchaseOrdersUsd + m.directInvoicesUsd + m.cashRequestsUsd;
    const x =
      padX + (idx / Math.max(1, MONTHLY_SPEND_TREND.length - 1)) * usableWidth;
    const y = padTop + (1 - Math.min(total, maxVal) / maxVal) * usableHeight;
    return { x, y, total };
  });

  const poPoints = MONTHLY_SPEND_TREND.map((m, idx) => {
    const x =
      padX + (idx / Math.max(1, MONTHLY_SPEND_TREND.length - 1)) * usableWidth;
    const y =
      padTop +
      (1 - Math.min(m.purchaseOrdersUsd, maxVal) / maxVal) * usableHeight;
    return { x, y };
  });

  const totalLinePath = buildSmoothPath(totalPoints);
  const totalAreaPath = `${totalLinePath} L ${totalPoints[totalPoints.length - 1].x} ${baselineY} L ${totalPoints[0].x} ${baselineY} Z`;
  const poLinePath = buildSmoothPath(poPoints);

  const totalDocumentsCount = SPEND_BREAKDOWN.reduce(
    (sum, item) => sum + item.documentsCount,
    0,
  );

  return (
    <div className="mt-1 min-w-0 space-y-6 pb-8">
      {/* Page Header */}
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="space-y-0.5">
          <h1 className="text-xl font-semibold tracking-tight">
            {t("greeting")}
          </h1>
          <p className="text-sm text-muted-foreground">{t("subtitle")}</p>
        </div>

        <Tabs
          value={period}
          onValueChange={(value) => setPeriod(value as PeriodKey)}
        >
          <TabsList className="rounded-full p-1">
            <TabsTrigger value="month" className="rounded-full px-3">
              {t("periods.month")}
            </TabsTrigger>
            <TabsTrigger value="quarter" className="rounded-full px-3">
              {t("periods.quarter")}
            </TabsTrigger>
            <TabsTrigger value="year" className="rounded-full px-3">
              {t("periods.year")}
            </TabsTrigger>
          </TabsList>
        </Tabs>
      </div>

      {/* Row 1: Soft Executive KPI Cards */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {/* KPI 1: Total Spend */}
        <Card className="rounded-2xl shadow-xs ring-foreground/6 transition-all duration-300 hover:shadow-md">
          <CardContent className="space-y-3.5 pt-1">
            <div className="flex items-center justify-between">
              <span className="text-xs font-medium text-muted-foreground">
                {t("kpis.totalSpend.title")}
              </span>
              <div className="flex size-9 items-center justify-center rounded-full bg-primary/8 text-primary">
                <DollarSign className="size-4" />
              </div>
            </div>
            <div className="text-2xl font-semibold tracking-tight tabular-nums">
              {formatMoney(kpi.totalSpendUsd)}
            </div>
            <div className="flex items-center gap-2 text-xs">
              <span className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2.5 py-0.5 font-medium text-primary tabular-nums">
                <TrendingUp className="size-3" />
                {kpi.spendDelta}
              </span>
              <span className="truncate text-muted-foreground">
                {t("kpis.totalSpend.vsPrevious")}
              </span>
            </div>
          </CardContent>
        </Card>

        {/* KPI 2: Pending Approvals */}
        <Card className="rounded-2xl shadow-xs ring-foreground/6 transition-all duration-300 hover:shadow-md">
          <CardContent className="space-y-3.5 pt-1">
            <div className="flex items-center justify-between">
              <span className="text-xs font-medium text-muted-foreground">
                {t("kpis.pendingApprovals.title")}
              </span>
              <div className="flex size-9 items-center justify-center rounded-full bg-primary/8 text-primary">
                <FileCheck2 className="size-4" />
              </div>
            </div>
            <div className="flex items-baseline gap-2">
              <span className="text-2xl font-semibold tracking-tight tabular-nums">
                {kpi.pendingApprovalsCount}
              </span>
              <span className="text-xs text-muted-foreground tabular-nums">
                ({formatMoney(kpi.pendingApprovalsUsd)})
              </span>
            </div>
            <div className="flex items-center gap-2 text-xs">
              <span className="inline-flex items-center gap-1 rounded-full bg-destructive/10 px-2.5 py-0.5 font-medium text-destructive">
                <Clock className="size-3" />
                {t("kpis.pendingApprovals.urgentCount", {
                  count: kpi.urgentApprovalsCount,
                })}
              </span>
              <span className="truncate text-muted-foreground">
                {t("kpis.pendingApprovals.requiresAttention")}
              </span>
            </div>
          </CardContent>
        </Card>

        {/* KPI 3: Active POs & Evaluations */}
        <Card className="rounded-2xl shadow-xs ring-foreground/6 transition-all duration-300 hover:shadow-md">
          <CardContent className="space-y-3.5 pt-1">
            <div className="flex items-center justify-between">
              <span className="text-xs font-medium text-muted-foreground">
                {t("kpis.activeProcurements.title")}
              </span>
              <div className="flex size-9 items-center justify-center rounded-full bg-primary/8 text-primary">
                <FileText className="size-4" />
              </div>
            </div>
            <div className="text-2xl font-semibold tracking-tight tabular-nums">
              {kpi.activeProcurementsCount}
            </div>
            <div className="flex items-center gap-2 text-xs text-muted-foreground">
              <span className="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 font-medium text-foreground tabular-nums">
                {t("kpis.activeProcurements.poCount", {
                  count: kpi.openPosCount,
                })}
              </span>
              <span className="truncate">
                {t("kpis.activeProcurements.evalCount", {
                  count: kpi.openEvaluationsCount,
                })}
              </span>
            </div>
          </CardContent>
        </Card>

        {/* KPI 4: Open Cash & Advances */}
        <Card className="rounded-2xl shadow-xs ring-foreground/6 transition-all duration-300 hover:shadow-md">
          <CardContent className="space-y-3.5 pt-1">
            <div className="flex items-center justify-between">
              <span className="text-xs font-medium text-muted-foreground">
                {t("kpis.openPayments.title")}
              </span>
              <div className="flex size-9 items-center justify-center rounded-full bg-primary/8 text-primary">
                <Wallet className="size-4" />
              </div>
            </div>
            <div className="text-2xl font-semibold tracking-tight tabular-nums">
              {formatMoney(kpi.openPaymentsUsd)}
            </div>
            <div className="flex items-center gap-2 text-xs text-muted-foreground">
              <span className="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 font-medium text-foreground tabular-nums">
                {t("kpis.openPayments.activeRequests", {
                  count: kpi.openPaymentsCount,
                })}
              </span>
              <span className="truncate">
                {t("kpis.openPayments.clearanceCount", {
                  count: kpi.clearancePendingCount,
                })}{" "}
                {t("pipeline.pending")}
              </span>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Row 2: Smooth Spline Area Chart (2/3) + Segmented Channel Breakdown (1/3) */}
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* Left: Smooth Cubic-Bezier Spend Curve */}
        <Card className="rounded-2xl shadow-xs ring-foreground/6 lg:col-span-2">
          <CardHeader className="border-b-0 pb-0">
            <CardTitle>{t("spendTrend.title")}</CardTitle>
            <CardDescription className="tabular-nums">
              {t(`months.${activeMonth.monthKey}`)}:{" "}
              <span className="font-semibold text-foreground">
                {formatMoney(activeMonthTotal)}
              </span>{" "}
              <span className="text-muted-foreground">
                · {t("spendTrend.series.purchaseOrders")}{" "}
                {formatMoney(activeMonth.purchaseOrdersUsd)} ·{" "}
                {t("spendTrend.series.directInvoices")}{" "}
                {formatMoney(activeMonth.directInvoicesUsd)}
              </span>
            </CardDescription>
            <CardAction>
              <div className="flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
                <span className="inline-flex items-center gap-1.5">
                  <span className="size-2.5 rounded-full bg-chart-1" />
                  {t("kpis.totalSpend.title")}
                </span>
                <span className="inline-flex items-center gap-1.5">
                  <span className="size-2.5 rounded-full bg-chart-2" />
                  {t("spendTrend.series.purchaseOrders")}
                </span>
              </div>
            </CardAction>
          </CardHeader>

          <CardContent className="pt-2">
            <div className="relative h-60 w-full">
              {/* Soft Horizontal Guide Lines */}
              <div className="pointer-events-none absolute inset-0 bottom-7 flex flex-col justify-between">
                {[160, 120, 80, 40, 0].map((step) => (
                  <div key={step} className="flex w-full items-center gap-3">
                    <span className="w-9 shrink-0 text-right text-[11px] text-muted-foreground tabular-nums">
                      ${step}k
                    </span>
                    <div className="h-px flex-1 bg-border/50" />
                  </div>
                ))}
              </div>

              {/* Smooth SVG Spline Area */}
              <div className="relative ml-12 h-[calc(100%-28px)]">
                <svg
                  viewBox={`0 0 ${chartWidth} ${chartHeight}`}
                  preserveAspectRatio="none"
                  className="size-full overflow-visible"
                >
                  {/* Soft Shaded Area Under Total Curve */}
                  <path d={totalAreaPath} className="fill-chart-1/12" />

                  {/* Purchase Orders Secondary Smooth Curve */}
                  <path
                    d={poLinePath}
                    fill="none"
                    strokeWidth="2"
                    strokeDasharray="4 4"
                    strokeLinecap="round"
                    className="stroke-chart-2/70"
                  />

                  {/* Total Spend Primary Smooth Curve */}
                  <path
                    d={totalLinePath}
                    fill="none"
                    strokeWidth="3"
                    strokeLinecap="round"
                    className="stroke-chart-1"
                  />

                  {/* Active Hover Vertical Guide & Point */}
                  {totalPoints[activeIdx] ? (
                    <g>
                      <line
                        x1={totalPoints[activeIdx].x}
                        y1={padTop}
                        x2={totalPoints[activeIdx].x}
                        y2={baselineY}
                        strokeWidth="1"
                        strokeDasharray="3 3"
                        className="stroke-primary/30"
                      />
                      <circle
                        cx={totalPoints[activeIdx].x}
                        cy={totalPoints[activeIdx].y}
                        r="5"
                        strokeWidth="2.5"
                        className="fill-background stroke-chart-1 transition-all duration-200"
                      />
                    </g>
                  ) : null}
                </svg>

                {/* Interactive Month Columns & X-Axis Labels */}
                <div className="absolute inset-x-0 top-0 -bottom-7 grid grid-cols-6">
                  {MONTHLY_SPEND_TREND.map((point, idx) => {
                    const isSelected = idx === activeIdx;
                    return (
                      <button
                        key={point.monthKey}
                        type="button"
                        onMouseEnter={() => setHoveredMonthIdx(idx)}
                        onMouseLeave={() => setHoveredMonthIdx(null)}
                        onClick={() => setHoveredMonthIdx(idx)}
                        className="group flex h-full flex-col items-center justify-end pb-0.5 focus-visible:outline-none"
                      >
                        <span
                          className={cn(
                            "rounded-full px-2.5 py-0.5 text-xs font-medium transition-all duration-200",
                            isSelected
                              ? "bg-primary/10 text-primary"
                              : "text-muted-foreground group-hover:text-foreground",
                          )}
                        >
                          {t(`months.${point.monthKey}`)}
                        </span>
                      </button>
                    );
                  })}
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Right: Proportional Segmented Bar + Channel Breakdown */}
        <Card className="rounded-2xl shadow-xs ring-foreground/6">
          <CardHeader className="border-b-0 pb-0">
            <CardTitle>{t("spendBreakdown.title")}</CardTitle>
            <CardDescription>{t("spendBreakdown.description")}</CardDescription>
          </CardHeader>

          <CardContent className="flex flex-1 flex-col justify-between gap-5">
            {/* Top Summary & Proportional Multi-Segment Bar */}
            <div className="space-y-3 rounded-xl bg-muted/35 p-3.5">
              <div className="flex items-baseline justify-between">
                <span className="text-lg font-semibold tracking-tight tabular-nums">
                  {formatMoney(kpi.totalSpendUsd)}
                </span>
                <span className="text-xs text-muted-foreground tabular-nums">
                  {totalDocumentsCount} {t("recentDocs.columns.document")}
                </span>
              </div>

              <div className="flex h-3 w-full gap-1 overflow-hidden rounded-full">
                {SPEND_BREAKDOWN.map((item) => {
                  const isDimmed =
                    hoveredChannel !== null && hoveredChannel !== item.key;
                  return (
                    <div
                      key={item.key}
                      onMouseEnter={() => setHoveredChannel(item.key)}
                      onMouseLeave={() => setHoveredChannel(null)}
                      className={cn(
                        "h-full first:rounded-l-full last:rounded-r-full transition-opacity duration-200",
                        item.barClass,
                        isDimmed ? "opacity-35" : "opacity-100",
                      )}
                      style={{ width: `${item.sharePercent}%` }}
                    />
                  );
                })}
              </div>
            </div>

            {/* Clean Channel Rows */}
            <div className="space-y-2">
              {SPEND_BREAKDOWN.map((item) => {
                const isHighlighted = hoveredChannel === item.key;
                return (
                  <div
                    key={item.key}
                    onMouseEnter={() => setHoveredChannel(item.key)}
                    onMouseLeave={() => setHoveredChannel(null)}
                    className={cn(
                      "flex items-center justify-between gap-3 rounded-xl px-2.5 py-2 transition-colors duration-200",
                      isHighlighted ? "bg-muted/60" : "hover:bg-muted/40",
                    )}
                  >
                    <div className="flex min-w-0 items-center gap-2.5">
                      <span
                        className={cn(
                          "size-3 shrink-0 rounded-full",
                          item.dotClass,
                        )}
                      />
                      <div className="min-w-0">
                        <p className="truncate text-xs font-medium">
                          {t(`spendBreakdown.channels.${item.key}`)}
                        </p>
                        <p className="text-[11px] text-muted-foreground tabular-nums">
                          {item.documentsCount}{" "}
                          {t("kpis.openPayments.clearanceCount", {
                            count: item.documentsCount,
                          }).replace(/^\d+\s*/, "")}
                        </p>
                      </div>
                    </div>

                    <div className="flex shrink-0 items-center gap-2.5 tabular-nums">
                      <span className="text-xs font-semibold">
                        {formatMoney(item.amountUsd)}
                      </span>
                      <span className="inline-flex w-11 justify-center rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
                        {item.sharePercent}%
                      </span>
                    </div>
                  </div>
                );
              })}
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Row 3: Soft Airy Approvals & Recent Documents Table */}
      <Card className="rounded-2xl shadow-xs ring-foreground/6">
        <CardHeader className="border-b-0 pb-2">
          <CardTitle>
            {tableView === "approvals"
              ? t("pendingTable.title")
              : t("recentDocs.title")}
          </CardTitle>
          <CardDescription>
            {tableView === "approvals"
              ? t("pendingTable.description")
              : t("recentDocs.description")}
          </CardDescription>
          <CardAction>
            <div className="flex flex-wrap items-center gap-2">
              <Tabs
                value={tableView}
                onValueChange={(val) => setTableView(val as TableView)}
              >
                <TabsList className="rounded-full p-1">
                  <TabsTrigger value="approvals" className="rounded-full px-3">
                    {t("kpis.pendingApprovals.title")} (
                    {PENDING_APPROVALS.length})
                  </TabsTrigger>
                  <TabsTrigger value="recent" className="rounded-full px-3">
                    {t("recentDocs.title")}
                  </TabsTrigger>
                </TabsList>
              </Tabs>
              <Button
                variant="outline"
                size="sm"
                className="rounded-full px-3"
                onClick={() =>
                  router.push(
                    tableView === "approvals"
                      ? "/approvals"
                      : "/purchase-orders/list",
                  )
                }
              >
                {tableView === "approvals"
                  ? t("quickActions.reviewApprovals")
                  : t("recentDocs.viewAllPos")}
                <ArrowUpRight className="size-3.5" />
              </Button>
            </div>
          </CardAction>
        </CardHeader>

        <CardContent className="px-2 pb-2">
          {tableView === "approvals" ? (
            <Table>
              <TableHeader>
                <TableRow className="border-b-border/60 hover:bg-transparent">
                  <TableHead className="px-4 text-xs text-muted-foreground">
                    {t("pendingTable.columns.code")}
                  </TableHead>
                  <TableHead className="px-4 text-xs text-muted-foreground">
                    {t("pendingTable.columns.subject")}
                  </TableHead>
                  <TableHead className="px-4 text-xs text-muted-foreground">
                    {t("pendingTable.columns.step")}
                  </TableHead>
                  <TableHead className="px-4 text-xs text-muted-foreground">
                    {t("pendingTable.columns.submitted")}
                  </TableHead>
                  <TableHead className="px-4 text-right text-xs text-muted-foreground">
                    {t("pendingTable.columns.amount")}
                  </TableHead>
                  <TableHead className="w-10 px-4" />
                </TableRow>
              </TableHeader>
              <TableBody>
                {PENDING_APPROVALS.map((row) => (
                  <TableRow
                    key={row.id}
                    className="group cursor-pointer border-b-border/40 transition-colors duration-200 hover:bg-muted/35"
                    onClick={() => router.push(row.href)}
                  >
                    <TableCell className="px-4 py-3.5">
                      <span className="inline-flex rounded-full bg-muted/70 px-2.5 py-0.5 font-mono text-xs font-medium">
                        {row.code}
                      </span>
                      <p className="mt-1 text-xs text-muted-foreground">
                        {t(`docTypes.${row.docTypeKey}`)}
                      </p>
                    </TableCell>
                    <TableCell className="max-w-72 px-4 py-3.5">
                      <p className="truncate font-medium">{row.title}</p>
                      <p className="truncate text-xs text-muted-foreground">
                        {row.campus} · {row.department}
                      </p>
                    </TableCell>
                    <TableCell className="px-4 py-3.5">
                      <span className="inline-flex items-center rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-secondary-foreground">
                        {row.currentStep}
                      </span>
                    </TableCell>
                    <TableCell className="px-4 py-3.5">
                      <div className="flex items-center gap-2.5">
                        <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-[11px] font-semibold text-primary">
                          {initials(row.submittedBy)}
                        </span>
                        <div>
                          <p className="text-xs font-medium">
                            {row.submittedBy}
                          </p>
                          <p className="text-xs text-muted-foreground">
                            {formatDate(row.submittedDate)}
                          </p>
                        </div>
                      </div>
                    </TableCell>
                    <TableCell className="px-4 py-3.5 text-right font-semibold tabular-nums">
                      {formatMoney(row.amountUsd)}
                    </TableCell>
                    <TableCell className="px-4 py-3.5 text-right text-muted-foreground">
                      <ChevronRight className="ml-auto size-4 transition-transform duration-200 group-hover:translate-x-0.5" />
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          ) : (
            <Table>
              <TableHeader>
                <TableRow className="border-b-border/60 hover:bg-transparent">
                  <TableHead className="px-4 text-xs text-muted-foreground">
                    {t("pendingTable.columns.code")}
                  </TableHead>
                  <TableHead className="px-4 text-xs text-muted-foreground">
                    {t("recentDocs.columns.party")}
                  </TableHead>
                  <TableHead className="px-4 text-xs text-muted-foreground">
                    {t("recentDocs.columns.date")}
                  </TableHead>
                  <TableHead className="px-4 text-xs text-muted-foreground">
                    {t("recentDocs.columns.status")}
                  </TableHead>
                  <TableHead className="px-4 text-right text-xs text-muted-foreground">
                    {t("recentDocs.columns.amount")}
                  </TableHead>
                  <TableHead className="w-10 px-4" />
                </TableRow>
              </TableHeader>
              <TableBody>
                {RECENT_DOCUMENTS.map((doc) => (
                  <TableRow
                    key={doc.id}
                    className="group cursor-pointer border-b-border/40 transition-colors duration-200 hover:bg-muted/35"
                    onClick={() => router.push(doc.href)}
                  >
                    <TableCell className="px-4 py-3.5">
                      <span className="inline-flex rounded-full bg-muted/70 px-2.5 py-0.5 font-mono text-xs font-medium">
                        {doc.code}
                      </span>
                      <p className="mt-1 text-xs text-muted-foreground">
                        {t(`docTypes.${doc.docTypeKey}`)}
                      </p>
                    </TableCell>
                    <TableCell className="px-4 py-3.5">
                      <p className="font-medium">
                        {doc.CounterpartyOrRequester}
                      </p>
                      <p className="text-xs text-muted-foreground">
                        {doc.department}
                      </p>
                    </TableCell>
                    <TableCell className="px-4 py-3.5 text-xs text-muted-foreground">
                      {formatDate(doc.date)}
                    </TableCell>
                    <TableCell className="px-4 py-3.5">
                      <span
                        className={cn(
                          "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium",
                          STATUS_PILL_STYLE[doc.status],
                        )}
                      >
                        {t(`statuses.${doc.status}`)}
                      </span>
                    </TableCell>
                    <TableCell className="px-4 py-3.5 text-right font-semibold tabular-nums">
                      {formatMoney(doc.amountUsd)}
                    </TableCell>
                    <TableCell className="px-4 py-3.5 text-right text-muted-foreground">
                      <ChevronRight className="ml-auto size-4 transition-transform duration-200 group-hover:translate-x-0.5" />
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
