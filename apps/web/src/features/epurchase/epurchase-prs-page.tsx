"use client"

import { keepPreviousData, useQuery } from "@tanstack/react-query"
import { useEffect, useState } from "react"
import { useRouter } from "next/navigation"
import { useTranslations } from "next-intl"
import { Eye, MoreHorizontal } from "lucide-react"
import { createColumnHelper, type ColumnDef } from "@tanstack/react-table"
import { epurchasePrsIndex } from "@/lib/api/epurchase-pr/epurchase-pr"
import { unwrapWithMeta, withAuth } from "@/lib/api-client"
import { DataTable } from "@/components/ui/data-table"
import { DataTableSkeleton } from "@/components/ui/data-table-skeleton"
import { DataTableColumnHeader } from "@/components/ui/data-table-column-header"
import { type DataTableFeatures } from "@/components/ui/data-table-features"
import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  EPurchaseLoginRequired,
  requiresEPurchaseLogin,
} from "@/features/epurchase/components/login-required"

type EPurchasePr = {
  id: number
  no: number
  refNum: string
  purpose: string
  amount: number | null
  requester: string
  createdAt: string
  status: string | null
  purchaseStatus: string | null
}

type EPurchasePrsPage = {
  data: EPurchasePr[]
  meta?: { page: number; perPage: number; total: number }
}

const columnHelper = createColumnHelper<DataTableFeatures, EPurchasePr>()

function useDebouncedValue<T>(value: T, delayMs: number): T {
  const [debounced, setDebounced] = useState(value)

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delayMs)
    return () => clearTimeout(timer)
  }, [value, delayMs])

  return debounced
}

function useEPurchaseColumns(): ColumnDef<DataTableFeatures, EPurchasePr>[] {
  const tc = useTranslations("epurchasePrs.columns")
  const tt = useTranslations("epurchasePrs.table")
  const router = useRouter()

  return [
    columnHelper.accessor("refNum", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("refNum")} />
      ),
      cell: ({ row }) => (
        <span className="font-mono text-xs">{row.getValue("refNum")}</span>
      ),
    }),
    columnHelper.accessor("purpose", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("purpose")} />
      ),
      cell: ({ row }) => {
        const purpose: string = row.getValue("purpose")
        return (
          <span className="whitespace-normal break-words">{purpose}</span>
        )
      },
    }),
    columnHelper.accessor("amount", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("amount")} />
      ),
      cell: ({ row }) => {
        const amount = row.getValue("amount")
        return amount !== null && amount !== undefined
          ? `$${Number(amount).toFixed(2)}`
          : "—"
      },
    }),
    columnHelper.accessor("requester", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("requester")} />
      ),
      cell: ({ row }) => row.getValue("requester") || "—",
    }),
    columnHelper.accessor("createdAt", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("createdAt")} />
      ),
      cell: ({ row }) => (
        <span className="whitespace-nowrap text-muted-foreground">
          {row.getValue("createdAt")}
        </span>
      ),
    }),
    columnHelper.accessor("status", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("status")} />
      ),
      cell: ({ row }) => row.getValue("status") || "—",
    }),
    columnHelper.accessor("purchaseStatus", {
      header: ({ column }) => (
        <DataTableColumnHeader column={column} title={tc("purchaseStatus")} />
      ),
      cell: ({ row }) => row.getValue("purchaseStatus") || "—",
    }),
    columnHelper.display({
      id: "actions",
      enableSorting: false,
      enableHiding: false,
      cell: ({ row }) => (
        <div className="flex items-center justify-end gap-2">
          <DropdownMenu>
            <DropdownMenuTrigger
              render={<Button variant="ghost" className="size-8 p-0" />}
            >
              <span className="sr-only">{tt("openMenu")}</span>
              <MoreHorizontal className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuGroup>
                <DropdownMenuItem
                  onClick={() =>
                    router.push(
                      `/epurchase/prs/${row.original.id}?ref=${encodeURIComponent(row.original.refNum)}`
                    )
                  }
                >
                  <Eye className="mr-2 size-4" />
                  {tt("view")}
                </DropdownMenuItem>
              </DropdownMenuGroup>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      ),
    }),
  ] as ColumnDef<DataTableFeatures, EPurchasePr>[]
}

export function EPurchasePrsPage() {
  const t = useTranslations("epurchasePrs")
  const [search, setSearch] = useState("")
  const [page, setPage] = useState(0)
  const [perPage, setPerPage] = useState(20)

  const debouncedSearch = useDebouncedValue(search, 300)
  const columns = useEPurchaseColumns()

  const query = useQuery({
    queryKey: ["epurchasePrs", debouncedSearch, page, perPage],
    queryFn: async (): Promise<EPurchasePrsPage> => {
      const response = await epurchasePrsIndex(
        {
          search: debouncedSearch || undefined,
          page: page + 1,
          per_page: perPage,
        },
        withAuth()
      )
      return unwrapWithMeta<EPurchasePr[]>(response) as EPurchasePrsPage
    },
    placeholderData: keepPreviousData,
    retry: false,
  })

  if (query.isPending) {
    return <DataTableSkeleton columns={7} actions={1} />
  }

  if (query.isError) {
    if (requiresEPurchaseLogin(query.error)) {
      return <EPurchaseLoginRequired />
    }

    return (
      <div className="mt-1 rounded-md border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
        {query.error instanceof Error && query.error.message
          ? query.error.message
          : t("loadFailed")}
      </div>
    )
  }

  const meta = query.data?.meta ?? { page: page + 1, perPage, total: 0 }

  return (
    <div className="flex min-h-0 flex-1 flex-col">
      <DataTable
        columns={columns}
        data={query.data?.data ?? []}
        loading={query.isFetching}
        searchPlaceholder={t("searchPlaceholder")}
        serverSearch={{
          value: search,
          onChange: (value) => {
            setSearch(value)
            setPage(0)
          },
        }}
        serverPagination={{
          page,
          perPage,
          total: meta.total,
          onPaginationChange: (next) => {
            setPage(next.page)
            setPerPage(next.perPage)
          },
        }}
      />
    </div>
  )
}
