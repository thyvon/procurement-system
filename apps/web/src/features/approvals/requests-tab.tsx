"use client";

import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { createColumnHelper, type ColumnDef } from "@tanstack/react-table";
import { format } from "date-fns";
import { MoreHorizontal, Eye, XIcon, Settings2 } from "lucide-react";
import {
  approvalsInbox,
  approvalsOutbox,
  approvalsRequestsIndex,
} from "@/lib/api/approval-request/approval-request";
import type { ApprovalsInboxStatus } from "@/lib/api/model/approvalsInboxStatus";
import { unwrapWithMeta, withAuth } from "@/lib/api-client";
import { Button } from "@/components/ui/button";
import { DataTable } from "@/components/ui/data-table";
import { DataTableSkeleton } from "@/components/ui/data-table-skeleton";
import { DataTableColumnHeader } from "@/components/ui/data-table-column-header";
import { DatePicker } from "@/components/ui/date-picker";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { type DataTableFeatures } from "@/components/ui/data-table-features";
import {
  parseApprovalRequest,
  type ApprovalRequestView,
  type ApprovalStatus,
} from "./approval-types";
import { ApprovalStatusBadge } from "./components/approval-timeline";

type TrayScope = "inbox" | "outbox" | "all";

type RequestRow = {
  id: string;
  code: string;
  subject: string;
  step: string;
  assignee: string;
  amount: string;
  status: ApprovalStatus;
  submittedBy: string | null;
  submittedAt: string | null;
};

type TrayPage = {
  data: RequestRow[];
  meta?: { page: number; perPage: number; total: number };
};

const money = new Intl.NumberFormat("en-US", {
  style: "currency",
  currency: "USD",
  minimumFractionDigits: 2,
});

function toRow(request: ApprovalRequestView, subjectLabel: string): RequestRow {
  return {
    id: request.id,
    code: request.documentCode ?? "—",
    subject: subjectLabel,
    step: request.currentStep?.label ?? "—",
    assignee: request.currentStep?.assigneeName ?? "—",
    amount: money.format(Number(request.amountSnapshot)),
    status: request.status,
    submittedBy: request.submittedBy,
    submittedAt: request.submittedAt,
  };
}

function useDebouncedValue<T>(value: T, delayMs: number): T {
  const [debounced, setDebounced] = useState(value);

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delayMs);
    return () => clearTimeout(timer);
  }, [value, delayMs]);

  return debounced;
}

const columnHelper = createColumnHelper<DataTableFeatures, RequestRow>();

function useRequestColumns({
  onViewRequest,
}: {
  onViewRequest: (request: RequestRow) => void;
}): ColumnDef<DataTableFeatures, RequestRow>[] {
  const tc = useTranslations("approvals.columns");
  const tt = useTranslations("approvals.table");

  return [
    columnHelper.accessor("code", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("code")} />
      ),
      cell: ({ row }) => (
        <span className="font-mono text-xs">{row.getValue("code")}</span>
      ),
    }),
    columnHelper.accessor("subject", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("subject")} />
      ),
    }),
    columnHelper.accessor("step", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("step")} />
      ),
      cell: ({ row }) => (
        <span className="text-muted-foreground">{row.getValue("step")}</span>
      ),
    }),
    columnHelper.accessor("assignee", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("assignee")} />
      ),
      cell: ({ row }) => (
        <span className="text-muted-foreground">{row.getValue("assignee")}</span>
      ),
    }),
    columnHelper.accessor("amount", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("amount")} />
      ),
      cell: ({ row }) => (
        <span className="tabular-nums">{row.getValue("amount")}</span>
      ),
    }),
    columnHelper.accessor("status", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("status")} />
      ),
      cell: ({ row }) => <ApprovalStatusBadge status={row.getValue("status")} />,
    }),
    columnHelper.accessor("submittedBy", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("submittedBy")} />
      ),
      cell: ({ row }) => (
        <span className="text-muted-foreground">
          {row.getValue("submittedBy") ?? "—"}
        </span>
      ),
    }),
    columnHelper.accessor("submittedAt", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("date")} />
      ),
      cell: ({ row }) => (
        <span className="text-muted-foreground">
          {row.getValue("submittedAt") ?? "—"}
        </span>
      ),
    }),
    columnHelper.display({
      id: "actions",
      enableSorting: false,
      enableHiding: false,
      cell: ({ row }) => {
        const request = row.original;
        return (
          <DropdownMenu>
            <DropdownMenuTrigger
              render={
                <Button variant="ghost" className="size-8 p-0" />
              }
            >
              <span className="sr-only">{tt("openMenu")}</span>
              <MoreHorizontal className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuGroup>
                <DropdownMenuItem onClick={() => onViewRequest(request)}>
                  <Eye className="mr-2 size-4" />
                  {tt("view")}
                </DropdownMenuItem>
              </DropdownMenuGroup>
            </DropdownMenuContent>
          </DropdownMenu>
        );
      },
    }),
  ] as ColumnDef<DataTableFeatures, RequestRow>[];
}

function defaultStatusFor(scope: TrayScope): string {
  return scope === "inbox" ? "pending" : "all";
}

export function RequestsTab() {
  const t = useTranslations("approvals");
  const router = useRouter();
  const [scope, setScope] = useState<TrayScope>("inbox");
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("pending");
  const [subjectFilter, setSubjectFilter] = useState("all");
  const [dateFrom, setDateFrom] = useState<Date>();
  const [dateTo, setDateTo] = useState<Date>();
  const [page, setPage] = useState(0);
  const [perPage, setPerPage] = useState(20);

  const debouncedSearch = useDebouncedValue(search, 300);
  const defaultStatus = defaultStatusFor(scope);

  const columns = useRequestColumns({
    onViewRequest: (request) => router.push(`/approvals/${request.id}`),
  });

  const params = {
    search: debouncedSearch || undefined,
    status:
      statusFilter === "all"
        ? undefined
        : (statusFilter as ApprovalsInboxStatus),
    subject_type: subjectFilter === "all" ? undefined : subjectFilter,
    date_from: dateFrom ? format(dateFrom, "yyyy-MM-dd") : undefined,
    date_to: dateTo ? format(dateTo, "yyyy-MM-dd") : undefined,
    page: page + 1,
    per_page: perPage,
  };

  const query = useQuery({
    queryKey: [
      "approvals",
      "tray",
      scope,
      debouncedSearch,
      statusFilter,
      subjectFilter,
      dateFrom,
      dateTo,
      page,
      perPage,
    ],
    queryFn: async (): Promise<TrayPage> => {
      const response =
        scope === "inbox"
          ? await approvalsInbox(params, withAuth())
          : scope === "outbox"
            ? await approvalsOutbox(params, withAuth())
            : await approvalsRequestsIndex(params, withAuth());
      const envelope = unwrapWithMeta<
        unknown,
        { page: number; perPage: number; total: number }
      >(response);
      const resources = (envelope.data ?? []) as unknown[];
      const subjectLabel = t("subjects.evaluation");

      return {
        data: resources.map((resource) => {
          const request = parseApprovalRequest(resource);
          return toRow(
            request,
            request.subjectType === "evaluation"
              ? subjectLabel
              : request.subjectType
          );
        }),
        meta: envelope.meta,
      };
    },
    placeholderData: keepPreviousData,
  });

  const handleScopeChange = (value: string) => {
    setScope(value as TrayScope);
    setStatusFilter(defaultStatusFor(value as TrayScope));
    setPage(0);
  };

  const filtersTouched =
    statusFilter !== defaultStatus ||
    subjectFilter !== "all" ||
    Boolean(dateFrom) ||
    Boolean(dateTo);

  const clearFilters = () => {
    setStatusFilter(defaultStatus);
    setSubjectFilter("all");
    setDateFrom(undefined);
    setDateTo(undefined);
    setPage(0);
  };

  if (query.isPending) {
    return <DataTableSkeleton columns={8} actions={2} />;
  }

  const meta = query.data?.meta ?? { page: page + 1, perPage, total: 0 };
  const rows = query.data?.data ?? [];

  return (
    <div className="mt-1 min-w-0 space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold tracking-tight">{t("title")}</h1>
          <p className="text-sm text-muted-foreground">{t("description")}</p>
        </div>
        <Button
          variant="outline"
          onClick={() => router.push("/approvals/settings")}
        >
          <Settings2 className="mr-2 size-4" />
          {t("configure")}
        </Button>
      </div>

      <Tabs value={scope} onValueChange={handleScopeChange}>
        <TabsList>
          <TabsTrigger value="inbox">{t("tabs.inbox")}</TabsTrigger>
          <TabsTrigger value="outbox">{t("tabs.outbox")}</TabsTrigger>
          <TabsTrigger value="all">{t("tabs.all")}</TabsTrigger>
        </TabsList>
      </Tabs>

      <DataTable
        columns={columns}
        data={rows}
        loading={query.isFetching}
        serverSearch={{
          value: search,
          onChange: (value) => {
            setSearch(value);
            setPage(0);
          },
        }}
        serverPagination={{
          page,
          perPage,
          total: meta.total,
          onPaginationChange: (next) => {
            setPage(next.page);
            setPerPage(next.perPage);
          },
        }}
        filterColumn="status"
        filterValue={statusFilter}
        onFilterChange={(value) => {
          setStatusFilter(value);
          setPage(0);
        }}
        filterOptions={[
          { value: "pending", label: t("statuses.pending") },
          { value: "approved", label: t("statuses.approved") },
          { value: "rejected", label: t("statuses.rejected") },
          { value: "returned", label: t("statuses.returned") },
        ]}
        filterPlaceholder={t("table.allStatuses")}
        searchPlaceholder={t("table.searchPlaceholder")}
        toolbar={
          <div className="flex flex-wrap items-center gap-2">
            <Select
              value={subjectFilter}
              onValueChange={(value) => {
                setSubjectFilter(value ?? "all");
                setPage(0);
              }}
              items={[
                { value: "all", label: t("table.allSubjects") },
                { value: "evaluation", label: t("subjects.evaluation") },
              ]}
            >
              <SelectTrigger className="h-8">
                <SelectValue placeholder={t("table.allSubjects")} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{t("table.allSubjects")}</SelectItem>
                <SelectItem value="evaluation">
                  {t("subjects.evaluation")}
                </SelectItem>
              </SelectContent>
            </Select>

            <DatePicker
              value={dateFrom}
              onChange={(date) => {
                setDateFrom(date);
                setPage(0);
              }}
              placeholder={t("table.dateFrom")}
              disabled={(date) => (dateTo ? date > dateTo : false)}
            />
            <DatePicker
              value={dateTo}
              onChange={(date) => {
                setDateTo(date);
                setPage(0);
              }}
              placeholder={t("table.dateTo")}
              disabled={(date) => (dateFrom ? date < dateFrom : false)}
            />

            {filtersTouched && (
              <Button
                variant="ghost"
                className="h-8 px-2 text-muted-foreground"
                onClick={clearFilters}
              >
                <XIcon className="size-3.5" />
                {t("table.clearFilters")}
              </Button>
            )}
          </div>
        }
      />
    </div>
  );
}
